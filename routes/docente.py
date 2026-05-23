from flask import Blueprint, current_app, flash, redirect, render_template, request, url_for
from flask_login import current_user, login_required
from sqlalchemy import desc, func

from models import Institucion, Pregunta, PruebaConfig, Resultado, Usuario, db
from utils import GRUPOS, NIVELES, TIPOS_PRUEBA, docente_required, puede_editar_pruebas, secure_upload


bp = Blueprint("docente", __name__, url_prefix="/docente")


@bp.route("/")
@login_required
@docente_required
def panel():
    resultados = Resultado.query.join(Usuario)
    if not current_user.es_admin() and current_user.institucion_id != 1:
        resultados = resultados.filter(Usuario.institucion_id == current_user.institucion_id)

    filtros = {
        "nivel": request.args.get("nivel", ""),
        "grupo": request.args.get("grupo", ""),
        "institucion": request.args.get("institucion", ""),
        "q": request.args.get("q", "").strip(),
    }
    if filtros["nivel"]:
        resultados = resultados.filter(Resultado.nivel.like(f"{filtros['nivel']}%"))
    if filtros["grupo"]:
        resultados = resultados.filter(Resultado.nivel.like(f"%\\_{filtros['grupo']}\\_%", escape="\\"))
    if filtros["institucion"]:
        resultados = resultados.filter(Usuario.institucion_id == int(filtros["institucion"]))
    if filtros["q"]:
        patron = f"%{filtros['q']}%"
        resultados = resultados.filter((Usuario.nombre + " " + Usuario.apellido).ilike(patron))

    tabla_resultados = resultados.order_by(Resultado.fecha.desc()).limit(100).all()
    ranking_instituciones = (
        db.session.query(
            Institucion.nombre,
            Institucion.color,
            func.count(Resultado.id).label("participaciones"),
            func.coalesce(func.avg(Resultado.puntaje * 1.0 / func.nullif(Resultado.total, 0)), 0).label("promedio"),
        )
        .join(Usuario, Usuario.institucion_id == Institucion.id)
        .join(Resultado, Resultado.usuario_id == Usuario.id)
        .group_by(Institucion.id)
        .order_by(desc("promedio"), desc("participaciones"))
        .all()
    )
    if not current_user.es_admin() and current_user.institucion_id != 1:
        ranking_instituciones = [r for r in ranking_instituciones if r.nombre == current_user.institucion.nombre]

    ranking_individual = (
        db.session.query(Usuario, func.sum(Resultado.puntaje).label("aciertos"), func.sum(Resultado.total).label("preguntas"))
        .join(Resultado, Resultado.usuario_id == Usuario.id)
        .group_by(Usuario.id)
        .order_by(desc("aciertos"))
        .limit(12)
        .all()
    )
    if not current_user.es_admin() and current_user.institucion_id != 1:
        ranking_individual = [r for r in ranking_individual if r[0].institucion_id == current_user.institucion_id]

    return render_template("docente/panel.html", ranking_instituciones=ranking_instituciones, ranking_individual=ranking_individual, tabla_resultados=tabla_resultados, instituciones=Institucion.query.order_by(Institucion.nombre).all(), filtros=filtros)


@bp.route("/editor-pruebas", methods=["GET", "POST"])
@login_required
@puede_editar_pruebas
def editor_pruebas():
    if request.method == "POST":
        if request.form.get("form_type") == "config":
            config = PruebaConfig.query.get_or_404(int(request.form.get("config_id")))
            config.tiempo_limite_min = int(request.form["tiempo_limite_min"]) if request.form.get("tiempo_limite_min") else None
            config.num_preguntas = int(request.form.get("num_preguntas", config.num_preguntas))
            config.max_intentos = int(request.form.get("max_intentos", config.max_intentos))
            config.habilitada = bool(request.form.get("habilitada"))
            config.instrucciones = request.form.get("instrucciones", "")
            db.session.commit()
            flash("Configuración actualizada.", "success")
        else:
            imagen = request.files.get("imagen")
            imagen_name = secure_upload(imagen, current_app.config["IMG_UPLOAD_FOLDER"], {".jpg", ".jpeg", ".png", ".svg", ".gif", ".webp"}) if imagen else None
            pregunta = Pregunta(
                pregunta=request.form.get("pregunta", ""),
                imagen_url=f"uploads/imgs/{imagen_name}" if imagen_name else None,
                op1=request.form.get("op1", ""),
                op2=request.form.get("op2", ""),
                op3=request.form.get("op3", ""),
                op4=request.form.get("op4", ""),
                correcta=request.form.get("correcta", "op1"),
                nivel=request.form.get("nivel", "basico"),
                grupo_grado=request.form.get("grupo_grado", "10-11"),
                tipo_prueba=request.form.get("tipo_prueba", "simulacro"),
                tema=request.form.get("tema", "General"),
                explicacion=request.form.get("explicacion", ""),
            )
            db.session.add(pregunta)
            db.session.commit()
            flash("Pregunta creada correctamente.", "success")
        return redirect(url_for("docente.editor_pruebas"))

    filtros = {"grupo": request.args.get("grupo", ""), "tipo": request.args.get("tipo", ""), "nivel": request.args.get("nivel", "")}
    preguntas = Pregunta.query
    if filtros["grupo"]:
        preguntas = preguntas.filter_by(grupo_grado=filtros["grupo"])
    if filtros["tipo"]:
        preguntas = preguntas.filter_by(tipo_prueba=filtros["tipo"])
    if filtros["nivel"]:
        preguntas = preguntas.filter_by(nivel=filtros["nivel"])
    preguntas = preguntas.order_by(Pregunta.creado_en.desc()).all()
    configs = PruebaConfig.query.order_by(PruebaConfig.tipo_prueba, PruebaConfig.grupo_grado).all()
    return render_template("docente/editor_pruebas.html", preguntas=preguntas, configs=configs, grupos=GRUPOS, niveles=NIVELES, tipos=TIPOS_PRUEBA, filtros=filtros)
