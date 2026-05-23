from flask import Blueprint, current_app, flash, redirect, render_template, request, send_from_directory, url_for
from flask_login import current_user, login_required

from models import Recurso, db
from utils import secure_upload, tipo_recurso_por_extension


bp = Blueprint("biblioteca", __name__, url_prefix="/biblioteca")


@bp.route("/", methods=["GET", "POST"])
@login_required
def index():
    if request.method == "POST":
        if current_user.rol not in {"admin", "docente"}:
            flash("No tienes permisos para subir archivos.", "danger")
            return redirect(url_for("biblioteca.index"))
        archivo = request.files.get("archivo")
        filename = secure_upload(
            archivo,
            current_app.config["PDF_UPLOAD_FOLDER"],
            {".pdf", ".zip", ".jpg", ".jpeg", ".png", ".svg", ".gif", ".docx", ".pptx"},
        )
        if filename:
            recurso = Recurso(
                titulo=request.form.get("titulo", "").strip(),
                descripcion=request.form.get("descripcion", "").strip(),
                tipo=tipo_recurso_por_extension(filename),
                archivo=filename,
                subido_por=current_user.id,
                visible=True,
            )
            db.session.add(recurso)
            db.session.commit()
            flash("Recurso cargado exitosamente.", "success")
        return redirect(url_for("biblioteca.index"))

    q = request.args.get("q", "").strip()
    recursos = Recurso.query.filter_by(visible=True)
    if q:
        recursos = recursos.filter(Recurso.titulo.ilike(f"%{q}%") | Recurso.descripcion.ilike(f"%{q}%"))
    recursos = recursos.order_by(Recurso.creado_en.desc()).all()
    return render_template("biblioteca.html", recursos=recursos, q=q)


@bp.route("/download/<int:recurso_id>")
@login_required
def download(recurso_id):
    recurso = Recurso.query.get_or_404(recurso_id)
    recurso.descargas += 1
    db.session.commit()
    return send_from_directory(current_app.config["PDF_UPLOAD_FOLDER"], recurso.archivo, as_attachment=True)
