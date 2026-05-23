import csv
import hashlib
import hmac
import io
import json
import os
from base64 import urlsafe_b64encode
from functools import wraps
from pathlib import Path
from urllib.parse import urlencode
from uuid import uuid4

import bcrypt
import requests
from flask import abort, current_app, flash, session
from flask_login import current_user

from models import Mensaje, Pregunta, PruebaConfig, Resultado, Seccion


GRUPOS = ["4-5", "6-7", "8-9", "10-11"]
TIPOS_PRUEBA = ["simulacro", "clasificatoria", "selectiva", "final"]
NIVELES = ["basico", "medio", "avanzado"]


def grupo_de_grado(grado: int) -> str:
    if grado <= 5:
        return "4-5"
    if grado <= 7:
        return "6-7"
    if grado <= 9:
        return "8-9"
    return "10-11"


def nivel_key(nivel: str, grupo: str, tipo: str) -> str:
    return f"{nivel}_{grupo}_{tipo}"


def intentos_restantes(uid: int, tipo: str, grupo: str, nivel: str) -> int:
    config = PruebaConfig.query.filter_by(tipo_prueba=tipo, grupo_grado=grupo).first()
    if not config:
        return 0
    if config.max_intentos == 0:
        return -1
    patron = nivel_key(nivel, grupo, tipo)
    usados = Resultado.query.filter(
        Resultado.usuario_id == uid,
        Resultado.nivel == patron,
    ).count()
    return max(0, config.max_intentos - usados)


def preguntas_disponibles(tipo: str, grupo: str, nivel: str):
    return Pregunta.query.filter_by(tipo_prueba=tipo, grupo_grado=grupo, nivel=nivel).order_by(Pregunta.creado_en.desc()).all()


def seccion_habilitada(nombre: str) -> bool:
    seccion = Seccion.query.filter_by(nombre=nombre).first()
    return bool(seccion and seccion.habilitada)


def prueba_habilitada(tipo: str, grupo: str) -> bool:
    if tipo == "simulacro":
        return True
    config = PruebaConfig.query.filter_by(tipo_prueba=tipo, grupo_grado=grupo).first()
    if not config:
        return False
    return bool(config.habilitada and seccion_habilitada(tipo))


def update_session_user(user):
    session["user_data"] = {
        "id": user.id,
        "nombre": user.nombre,
        "apellido": user.apellido,
        "rol": user.rol,
        "grado": user.grado,
        "institucion_id": user.institucion_id,
        "grupo_grado": user.grupo_grado,
    }


def hash_password(raw_password: str) -> str:
    return bcrypt.hashpw(raw_password.encode("utf-8"), bcrypt.gensalt()).decode("utf-8")


def verify_password(raw_password: str, hashed_password: str) -> bool:
    return bcrypt.checkpw(raw_password.encode("utf-8"), hashed_password.encode("utf-8"))


def admin_required(view):
    @wraps(view)
    def wrapped(*args, **kwargs):
        if not current_user.is_authenticated or not current_user.es_admin():
            abort(403)
        return view(*args, **kwargs)

    return wrapped


def docente_required(view):
    @wraps(view)
    def wrapped(*args, **kwargs):
        if not current_user.is_authenticated or current_user.rol not in {"admin", "docente"}:
            abort(403)
        return view(*args, **kwargs)

    return wrapped


def puede_editar_pruebas(view):
    @wraps(view)
    def wrapped(*args, **kwargs):
        if not current_user.is_authenticated:
            abort(403)
        if current_user.es_admin():
            return view(*args, **kwargs)
        if current_user.es_docente() and current_user.institucion_id == 1:
            return view(*args, **kwargs)
        abort(403)

    return wrapped


def unread_count(user_id: int) -> int:
    return Mensaje.query.filter_by(para_id=user_id, leido=False, eliminado_para=False).count()


def secure_upload(file_storage, folder: str, allowed_extensions: set[str]) -> str | None:
    if not file_storage or not file_storage.filename:
        return None
    extension = Path(file_storage.filename).suffix.lower()
    if extension not in allowed_extensions:
        flash("El archivo no tiene una extensión permitida.", "danger")
        return None
    filename = f"{uuid4().hex}{extension}"
    target = Path(folder) / filename
    os.makedirs(folder, exist_ok=True)
    file_storage.save(target)
    return filename


def tipo_recurso_por_extension(filename: str) -> str:
    ext = Path(filename).suffix.lower()
    if ext == ".pdf":
        return "pdf"
    if ext == ".zip":
        return "zip"
    if ext in {".jpg", ".jpeg", ".png", ".svg", ".gif", ".webp"}:
        return "imagen"
    if ext in {".doc", ".docx"}:
        return "docx"
    if ext in {".ppt", ".pptx"}:
        return "pptx"
    return "enlace"


def sync_section_and_configs(tipo_prueba: str, habilitada: bool):
    seccion = Seccion.query.filter_by(nombre=tipo_prueba).first()
    if seccion:
        seccion.habilitada = habilitada
    configs = PruebaConfig.query.filter_by(tipo_prueba=tipo_prueba).all()
    for config in configs:
        if tipo_prueba != "simulacro":
            config.habilitada = habilitada


def cargar_csv_publico(url: str) -> list[dict]:
    if not url:
        return []
    response = requests.get(url, timeout=10)
    response.raise_for_status()
    contenido = io.StringIO(response.text)
    return list(csv.DictReader(contenido))


def legacy_bridge_url(user, next_path: str) -> str:
    payload = {
        "user_id": user.id,
        "nombre": user.nombre,
        "apellido": user.apellido,
        "usuario": user.usuario,
        "rol": user.rol,
        "grado": user.grado,
        "institucion_id": user.institucion_id,
    }
    payload_raw = json.dumps(payload, ensure_ascii=True, separators=(",", ":")).encode("utf-8")
    payload_b64 = urlsafe_b64encode(payload_raw).decode("ascii")
    signature = hmac.new(
        current_app.config["LEGACY_BRIDGE_SECRET"].encode("utf-8"),
        payload_b64.encode("utf-8"),
        hashlib.sha256,
    ).hexdigest()
    mount = current_app.config.get("APPLICATION_ROOT", "")
    query = urlencode({"payload": payload_b64, "sig": signature, "next": next_path})
    return f"{mount}/legacy_bridge.php?{query}"
