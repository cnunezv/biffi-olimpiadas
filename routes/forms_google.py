from flask import Blueprint, flash, redirect, render_template, request, url_for
from flask_login import current_user, login_required

from models import FormGoogle, db
from utils import GRUPOS, TIPOS_PRUEBA, cargar_csv_publico, docente_required


bp = Blueprint("forms_google", __name__, url_prefix="/forms-google")


@bp.route("/", methods=["GET", "POST"])
@login_required
@docente_required
def index():
    if request.method == "POST":
        db.session.add(
            FormGoogle(
                titulo=request.form.get("titulo", ""),
                descripcion=request.form.get("descripcion", ""),
                form_url=request.form.get("form_url", ""),
                sheet_csv_url=request.form.get("sheet_csv_url", ""),
                tipo_prueba=request.form.get("tipo_prueba", "clasificatoria"),
                grupo_grado=request.form.get("grupo_grado", "10-11"),
                habilitada=bool(request.form.get("habilitada")),
                tiempo_limite_min=int(request.form["tiempo_limite_min"]) if request.form.get("tiempo_limite_min") else None,
                creado_por=current_user.id,
            )
        )
        db.session.commit()
        flash("Formulario registrado.", "success")
        return redirect(url_for("forms_google.index"))

    forms = FormGoogle.query.order_by(FormGoogle.id.desc()).all()
    form_id = request.args.get("ver")
    respuestas = []
    active_form = None
    if form_id:
        active_form = FormGoogle.query.get(int(form_id))
        if active_form and active_form.sheet_csv_url:
            try:
                respuestas = cargar_csv_publico(active_form.sheet_csv_url)
            except Exception:
                flash("No fue posible leer el CSV público en este momento.", "warning")
    return render_template("forms_google.html", forms=forms, respuestas=respuestas, active_form=active_form, grupos=GRUPOS, tipos=TIPOS_PRUEBA)
