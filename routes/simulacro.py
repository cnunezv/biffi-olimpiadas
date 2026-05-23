import random
from datetime import datetime

from flask import Blueprint, abort, flash, redirect, render_template, request, url_for
from flask_login import current_user, login_required

from models import Pregunta, PruebaConfig, Resultado, db
from utils import NIVELES, TIPOS_PRUEBA, grupo_de_grado, intentos_restantes, legacy_bridge_url, nivel_key, preguntas_disponibles, prueba_habilitada


bp = Blueprint("simulacro", __name__, url_prefix="/pruebas")


@bp.route("/")
@login_required
def pruebas():
    if not current_user.es_estudiante():
        return redirect(url_for("dashboard.home"))

    return redirect(legacy_bridge_url(current_user, "pruebas.php"))

    grupo = grupo_de_grado(current_user.grado)
    tarjetas = []
    for tipo in TIPOS_PRUEBA:
        config = PruebaConfig.query.filter_by(tipo_prueba=tipo, grupo_grado=grupo).first()
        seccion_activa = prueba_habilitada(tipo, grupo)
        for nivel in NIVELES:
            disponibles = len(preguntas_disponibles(tipo, grupo, nivel))
            restantes = intentos_restantes(current_user.id, tipo, grupo, nivel)
            bloqueado = (restantes == 0) or not seccion_activa or not config or disponibles == 0
            tarjetas.append(
                {
                    "tipo": tipo,
                    "nivel": nivel,
                    "grupo": grupo,
                    "config": config,
                    "disponibles": disponibles,
                    "restantes": restantes,
                    "bloqueado": bloqueado,
                    "estado": "Bloqueado" if bloqueado else "Disponible",
                }
            )
    return render_template("pruebas.html", tarjetas=tarjetas, grupo=grupo)


@bp.route("/<tipo>/<nivel>", methods=["GET", "POST"])
@login_required
def presentar(tipo, nivel):
    if not current_user.es_estudiante():
        abort(403)
    if tipo not in TIPOS_PRUEBA or nivel not in NIVELES:
        abort(404)

    return redirect(legacy_bridge_url(current_user, f"simulacro.php?tipo={tipo}&nivel={nivel}"))

    grupo = grupo_de_grado(current_user.grado)
    config = PruebaConfig.query.filter_by(tipo_prueba=tipo, grupo_grado=grupo).first_or_404()
    seccion_activa = prueba_habilitada(tipo, grupo)
    restantes = intentos_restantes(current_user.id, tipo, grupo, nivel)
    preguntas = preguntas_disponibles(tipo, grupo, nivel)

    if not seccion_activa:
        flash("Esta prueba aún no ha sido habilitada por la coordinación.", "warning")
        return render_template("simulacro.html", preguntas=[], bloqueado=True, bloqueo_mensaje="La sección todavía no está habilitada.", tipo=tipo, nivel=nivel, grupo=grupo, config=config, intento_restante=restantes, resultado=None)

    if restantes == 0:
        return render_template("simulacro.html", preguntas=[], bloqueado=True, bloqueo_mensaje="Ya agotaste tus intentos para esta prueba. Usa el botón para contactar a tu docente.", tipo=tipo, nivel=nivel, grupo=grupo, config=config, intento_restante=restantes, resultado=None)

    if not preguntas:
        flash("No hay preguntas cargadas para esta combinación todavía.", "warning")
        return redirect(url_for("simulacro.pruebas"))

    seleccion = random.sample(preguntas, min(config.num_preguntas, len(preguntas)))
    if request.method == "POST":
        detalle = []
        puntaje = 0
        ids = [int(pid) for pid in request.form.getlist("pregunta_ids")]
        preguntas_mapa = {p.id: p for p in Pregunta.query.filter(Pregunta.id.in_(ids)).all()}
        for pid in ids:
            pregunta = preguntas_mapa.get(pid)
            respuesta = request.form.get(f"respuesta_{pid}", "")
            correcta = pregunta.correcta if pregunta else ""
            es_correcta = respuesta == correcta
            puntaje += 1 if es_correcta else 0
            detalle.append(
                {
                    "id": pid,
                    "pregunta": pregunta.pregunta if pregunta else "",
                    "respuesta_usuario": respuesta,
                    "respuesta_correcta": correcta,
                    "explicacion": pregunta.explicacion if pregunta else "",
                    "es_correcta": es_correcta,
                }
            )

        resultado = Resultado(
            usuario_id=current_user.id,
            nivel=nivel_key(nivel, grupo, tipo),
            puntaje=puntaje,
            total=len(ids),
            tiempo_seg=int(request.form.get("tiempo_seg", "0") or 0),
            detalle=detalle,
            fecha=datetime.utcnow(),
        )
        db.session.add(resultado)
        db.session.commit()

        restantes_post = intentos_restantes(current_user.id, tipo, grupo, nivel)
        return render_template("simulacro.html", preguntas=[], resultado=resultado, bloqueado=False, tipo=tipo, nivel=nivel, grupo=grupo, config=config, intento_restante=restantes_post, ultimo_intento=restantes_post == 0)

    return render_template("simulacro.html", preguntas=seleccion, resultado=None, bloqueado=False, tipo=tipo, nivel=nivel, grupo=grupo, config=config, intento_restante=restantes, ultimo_intento=False)
