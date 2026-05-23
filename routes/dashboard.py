from sqlalchemy import func
from flask import Blueprint, render_template
from flask_login import current_user, login_required

from models import Resultado
from utils import NIVELES, TIPOS_PRUEBA, grupo_de_grado, intentos_restantes


bp = Blueprint("dashboard", __name__, url_prefix="/dashboard")


@bp.route("/")
@login_required
def home():
    grupo = current_user.grupo_grado if current_user.grado else None
    historial = Resultado.query.filter_by(usuario_id=current_user.id).order_by(Resultado.fecha.desc()).limit(5).all()
    resumen = []
    if grupo:
        for tipo in TIPOS_PRUEBA:
            for nivel in NIVELES:
                resumen.append({"tipo": tipo, "nivel": nivel, "restantes": intentos_restantes(current_user.id, tipo, grupo, nivel)})
    stats = {
        "presentados": Resultado.query.filter_by(usuario_id=current_user.id).count(),
        "mejor_puntaje": Resultado.query.with_entities(func.max(Resultado.puntaje)).filter_by(usuario_id=current_user.id).scalar() or 0,
        "grupo": grupo_de_grado(current_user.grado) if current_user.grado else "N/A",
    }
    return render_template("dashboard.html", historial=historial, resumen=resumen, stats=stats)
