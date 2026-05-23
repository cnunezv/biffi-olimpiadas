from flask import Blueprint, flash, redirect, render_template, request, url_for
from flask_login import current_user, login_required, login_user, logout_user

from models import Usuario
from utils import update_session_user, verify_password


bp = Blueprint("auth", __name__)


@bp.route("/", methods=["GET", "POST"])
@bp.route("/login", methods=["GET", "POST"])
def login():
    if current_user.is_authenticated:
        return redirect(url_for("dashboard.home"))

    if request.method == "POST":
        usuario = request.form.get("usuario", "").strip()
        password = request.form.get("contrasena", "")
        user = Usuario.query.filter_by(usuario=usuario, activo=True).first()

        if not user or not verify_password(password, user.contrasena):
            flash("Credenciales inválidas. Verifica usuario y contraseña.", "danger")
            return render_template("login.html")

        login_user(user)
        update_session_user(user)
        flash(f"Bienvenido, {user.nombre}.", "success")
        return redirect(url_for("dashboard.home"))

    return render_template("login.html")


@bp.route("/logout")
@login_required
def logout():
    logout_user()
    flash("Tu sesión fue cerrada correctamente.", "info")
    return redirect(url_for("auth.login"))
