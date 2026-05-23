from flask import Blueprint, flash, redirect, render_template, request, url_for
from flask_login import current_user, login_required
from sqlalchemy import or_

from models import Mensaje, Usuario, db


bp = Blueprint("mensajes", __name__, url_prefix="/mensajes")


@bp.route("/", methods=["GET", "POST"])
@login_required
def inbox():
    if request.method == "POST":
        scope = request.form.get("scope", "directo")
        asunto = request.form.get("asunto", "").strip()
        cuerpo = request.form.get("cuerpo", "").strip()
        para_id = request.form.get("para_id")

        if not asunto or not cuerpo:
            flash("Asunto y mensaje son obligatorios.", "danger")
            return redirect(url_for("mensajes.inbox"))

        destinatarios = []
        if scope == "directo" and para_id:
            destino = Usuario.query.get(int(para_id))
            if destino:
                destinatarios = [destino]
        elif current_user.es_admin():
            query = Usuario.query.filter(Usuario.id != current_user.id, Usuario.activo.is_(True))
            if scope == "estudiantes":
                query = query.filter_by(rol="estudiante")
            elif scope == "docentes":
                query = query.filter_by(rol="docente")
            elif scope == "biffi":
                query = query.filter_by(institucion_id=1)
            destinatarios = query.all()

        for destino in destinatarios:
            db.session.add(Mensaje(de_id=current_user.id, para_id=destino.id, asunto=asunto, cuerpo=cuerpo))
        db.session.commit()
        flash("Mensaje enviado correctamente.", "success")
        return redirect(url_for("mensajes.inbox"))

    inbox_msgs = Mensaje.query.filter_by(para_id=current_user.id, eliminado_para=False).order_by(Mensaje.enviado_en.desc()).all()
    sent_msgs = Mensaje.query.filter_by(de_id=current_user.id, eliminado_de=False).order_by(Mensaje.enviado_en.desc()).all()

    contactos_query = Usuario.query.filter(Usuario.id != current_user.id, Usuario.activo.is_(True))
    if current_user.es_estudiante():
        contactos_query = contactos_query.filter(Usuario.rol.in_(["docente", "admin"]))
    elif current_user.es_docente() and current_user.institucion_id != 1:
        contactos_query = contactos_query.filter(or_(Usuario.institucion_id == current_user.institucion_id, Usuario.rol == "admin"))

    contactos = contactos_query.order_by(Usuario.nombre.asc()).all()
    return render_template("mensajes.html", inbox_msgs=inbox_msgs, sent_msgs=sent_msgs, contactos=contactos)


@bp.route("/<int:mensaje_id>/leer")
@login_required
def leer(mensaje_id):
    mensaje = Mensaje.query.get_or_404(mensaje_id)
    if mensaje.para_id != current_user.id and mensaje.de_id != current_user.id:
        return redirect(url_for("mensajes.inbox"))
    if mensaje.para_id == current_user.id:
        mensaje.leido = True
        db.session.commit()
    return redirect(url_for("mensajes.inbox", open=mensaje_id))


@bp.route("/<int:mensaje_id>/eliminar")
@login_required
def eliminar(mensaje_id):
    mensaje = Mensaje.query.get_or_404(mensaje_id)
    if mensaje.para_id == current_user.id:
        mensaje.eliminado_para = True
    if mensaje.de_id == current_user.id:
        mensaje.eliminado_de = True
    db.session.commit()
    flash("Mensaje eliminado.", "info")
    return redirect(url_for("mensajes.inbox"))
