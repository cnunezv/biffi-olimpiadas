from collections import defaultdict

from flask import Blueprint, flash, redirect, render_template, request, url_for
from flask_login import login_required

from models import Institucion, Resultado, Seccion, Usuario, db
from utils import TIPOS_PRUEBA, admin_required, hash_password, sync_section_and_configs


bp = Blueprint("admin", __name__, url_prefix="/admin")


@bp.route("/")
@login_required
@admin_required
def index():
    stats = {
        "instituciones": Institucion.query.count(),
        "usuarios": Usuario.query.count(),
        "estudiantes": Usuario.query.filter_by(rol="estudiante").count(),
        "resultados": Resultado.query.count(),
    }
    ultimos = Resultado.query.order_by(Resultado.fecha.desc()).limit(10).all()
    return render_template("admin/index.html", stats=stats, ultimos=ultimos)


@bp.route("/instituciones", methods=["GET", "POST"])
@login_required
@admin_required
def instituciones():
    if request.method == "POST":
        db.session.add(
            Institucion(
                nombre=request.form.get("nombre", ""),
                ciudad=request.form.get("ciudad", ""),
                departamento=request.form.get("departamento", ""),
                codigo=request.form.get("codigo", ""),
                color=request.form.get("color", "#7C1F30"),
                activa=bool(request.form.get("activa")),
            )
        )
        db.session.commit()
        flash("Institución registrada.", "success")
        return redirect(url_for("admin.instituciones"))
    return render_template("admin/instituciones.html", instituciones=Institucion.query.order_by(Institucion.nombre).all())


@bp.route("/usuarios", methods=["GET", "POST"])
@login_required
@admin_required
def usuarios():
    if request.method == "POST":
        db.session.add(
            Usuario(
                nombre=request.form.get("nombre", ""),
                apellido=request.form.get("apellido", ""),
                usuario=request.form.get("usuario", ""),
                correo=request.form.get("correo", ""),
                contrasena=hash_password(request.form.get("contrasena", "temporal123")),
                rol=request.form.get("rol", "estudiante"),
                nivel=request.form.get("nivel", "basico"),
                grado=int(request.form["grado"]) if request.form.get("grado") else None,
                curso=request.form.get("curso"),
                institucion_id=int(request.form.get("institucion_id")),
                activo=bool(request.form.get("activo")),
            )
        )
        db.session.commit()
        flash("Usuario creado.", "success")
        return redirect(url_for("admin.usuarios"))

    agrupados = defaultdict(list)
    for usuario in Usuario.query.join(Institucion).order_by(Institucion.nombre, Usuario.rol, Usuario.nombre).all():
        agrupados[usuario.institucion.nombre].append(usuario)
    return render_template("admin/usuarios.html", agrupados=agrupados, instituciones=Institucion.query.all())


@bp.route("/secciones", methods=["GET", "POST"])
@login_required
@admin_required
def secciones():
    if request.method == "POST":
        seccion = Seccion.query.get_or_404(int(request.form.get("seccion_id")))
        seccion.habilitada = bool(request.form.get("habilitada"))
        if seccion.nombre in TIPOS_PRUEBA:
            sync_section_and_configs(seccion.nombre, seccion.habilitada)
        db.session.commit()
        flash("Sección actualizada y sincronizada.", "success")
        return redirect(url_for("admin.secciones"))
    return render_template("admin/secciones.html", secciones=Seccion.query.order_by(Seccion.nombre).all())


@bp.route("/resultados")
@login_required
@admin_required
def resultados():
    registros = Resultado.query.join(Usuario).order_by(Resultado.fecha.desc()).limit(200).all()
    return render_template("admin/resultados.html", registros=registros)


@bp.route("/reset-intentos/<int:usuario_id>/<tipo>/<grupo>/<nivel>")
@login_required
@admin_required
def reset_intentos(usuario_id, tipo, grupo, nivel):
    patron = f"{nivel}_{grupo}_{tipo}"
    Resultado.query.filter(Resultado.usuario_id == usuario_id, Resultado.nivel == patron).delete(synchronize_session=False)
    db.session.commit()
    flash("Intentos reseteados correctamente.", "warning")
    return redirect(url_for("admin.resultados"))
