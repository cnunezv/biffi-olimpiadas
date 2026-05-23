from flask import Blueprint, abort, redirect, url_for
from flask_login import current_user, login_required

from utils import NIVELES, TIPOS_PRUEBA, legacy_bridge_url


bp = Blueprint("simulacro", __name__, url_prefix="/pruebas")


@bp.route("/")
@login_required
def pruebas():
    if not current_user.es_estudiante():
        return redirect(url_for("dashboard.home"))
    return redirect(legacy_bridge_url(current_user, "pruebas.php"))


@bp.route("/<tipo>/<nivel>", methods=["GET", "POST"])
@login_required
def presentar(tipo, nivel):
    if not current_user.es_estudiante():
        abort(403)
    if tipo not in TIPOS_PRUEBA or nivel not in NIVELES:
        abort(404)
    return redirect(legacy_bridge_url(current_user, f"simulacro.php?tipo={tipo}&nivel={nivel}"))
