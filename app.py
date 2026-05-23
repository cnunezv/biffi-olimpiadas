import os
from datetime import datetime

from dotenv import load_dotenv
from flask import Flask, jsonify
from flask_login import LoginManager, current_user
from werkzeug.middleware.proxy_fix import ProxyFix

from config import Config
from models import Institucion, Mensaje, Pregunta, PruebaConfig, Recurso, Resultado, Seccion, Usuario, db
from routes.admin import bp as admin_bp
from routes.auth import bp as auth_bp
from routes.biblioteca import bp as biblioteca_bp
from routes.dashboard import bp as dashboard_bp
from routes.docente import bp as docente_bp
from routes.forms_google import bp as forms_bp
from routes.mensajes import bp as mensajes_bp
from routes.simulacro_legacy import bp as simulacro_bp
from utils import GRUPOS, hash_password, unread_count


load_dotenv()
login_manager = LoginManager()
login_manager.login_view = "auth.login"


def create_app():
    app = Flask(__name__)
    app.config.from_object(Config)
    app.wsgi_app = ProxyFix(app.wsgi_app, x_proto=1, x_host=1, x_port=1, x_prefix=1)

    for folder in (app.config["UPLOAD_FOLDER"], app.config["PDF_UPLOAD_FOLDER"], app.config["IMG_UPLOAD_FOLDER"]):
        os.makedirs(folder, exist_ok=True)

    db.init_app(app)
    login_manager.init_app(app)

    app.register_blueprint(auth_bp)
    app.register_blueprint(dashboard_bp)
    app.register_blueprint(simulacro_bp)
    app.register_blueprint(mensajes_bp)
    app.register_blueprint(biblioteca_bp)
    app.register_blueprint(admin_bp)
    app.register_blueprint(docente_bp)
    app.register_blueprint(forms_bp)

    @app.context_processor
    def inject_globals():
        return {
            "unread_count": unread_count(current_user.id) if current_user.is_authenticated else 0,
            "current_year": datetime.utcnow().year,
        }

    with app.app_context():
        db.create_all()
        seed_data()

    @app.get("/healthz")
    def healthz():
        return jsonify({"status": "ok"})

    return app


@login_manager.user_loader
def load_user(user_id):
    return Usuario.query.get(int(user_id))


def seed_data():
    if Institucion.query.count() == 0:
        instituciones = [
            ("Colegio Biffi", "Cartagena", "Bolivar", "BIFFI", "#7C1F30"),
            ("Colegio La Salle", "Cartagena", "Bolivar", "LASALLE", "#1a3a6b"),
            ("Colegio San Pedro Claver", "Cartagena", "Bolivar", "CLAVER", "#0d5c1a"),
            ("IED Manuel Elkin Patarroyo", "Cartagena", "Bolivar", "PATARROYO", "#7a4000"),
            ("Colegio Jorge Washington", "Cartagena", "Bolivar", "WASHINGTON", "#8b1a1a"),
            ("Colegio Nuestra Señora del Carmen", "Cartagena", "Bolivar", "CARMEN", "#005b8e"),
            ("Instituto Técnico Industrial", "Cartagena", "Bolivar", "ITI", "#4a0080"),
        ]
        for nombre, ciudad, dep, codigo, color in instituciones:
            db.session.add(Institucion(nombre=nombre, ciudad=ciudad, departamento=dep, codigo=codigo, color=color, activa=True))
        db.session.commit()

    if Seccion.query.count() == 0:
        for nombre, etiqueta, habilitada in [
            ("general", "Información general", True),
            ("simulacros", "Simulacros", True),
            ("clasificatoria", "Clasificatoria", False),
            ("selectiva", "Selectiva", False),
            ("final", "Final", False),
            ("biblioteca", "Biblioteca", False),
        ]:
            db.session.add(Seccion(nombre=nombre, etiqueta=etiqueta, habilitada=habilitada))
        db.session.commit()

    if PruebaConfig.query.count() == 0:
        defaults = {
            "simulacro": (None, 10, 5, True),
            "clasificatoria": (60, 10, 1, False),
            "selectiva": (90, 15, 1, False),
            "final": (120, 20, 1, False),
        }
        for tipo, (tiempo, preguntas, intentos, habilitada) in defaults.items():
            for grupo in GRUPOS:
                db.session.add(
                    PruebaConfig(
                        tipo_prueba=tipo,
                        grupo_grado=grupo,
                        tiempo_limite_min=tiempo,
                        num_preguntas=preguntas,
                        max_intentos=intentos,
                        habilitada=habilitada,
                        instrucciones=f"Lee con atención las instrucciones de la fase {tipo}.",
                    )
                )
        db.session.commit()

    if Usuario.query.count() == 0:
        usuarios_seed = [
            ("Carlos", "Nuñez", "carlos.nunez", "carlos@biffi.edu.co", "admin1234", "admin", "avanzado", None, None, 1),
            ("Fabiana", "Ariza", "fabiana.ariza", "fabiana@biffi.edu.co", "docente123", "docente", "avanzado", None, None, 1),
            ("Andres", "Martinez", "andres.martinez", "andres@biffi.edu.co", "docente123", "docente", "avanzado", None, None, 1),
            ("Maria", "Perez", "maria.perez", "maria@biffi.edu.co", "biffi2026", "estudiante", "medio", 10, "10A", 1),
            ("Juan", "Garcia", "juan.garcia", "juan@biffi.edu.co", "biffi2026", "estudiante", "medio", 10, "10B", 1),
            ("Luisa", "Diaz", "luisa.diaz", "luisa@lasalle.edu.co", "biffi2026", "estudiante", "basico", 5, "5A", 2),
            ("Samuel", "Rojas", "samuel.rojas", "samuel@claver.edu.co", "biffi2026", "estudiante", "medio", 7, "7B", 3),
            ("Valentina", "Ruiz", "valentina.ruiz", "valentina@patarroyo.edu.co", "biffi2026", "estudiante", "medio", 8, "8A", 4),
            ("Mateo", "Luna", "mateo.luna", "mateo@washington.edu.co", "biffi2026", "estudiante", "avanzado", 11, "11A", 5),
            ("Isabella", "Castro", "isabella.castro", "isabella@carmen.edu.co", "biffi2026", "estudiante", "basico", 4, "4A", 6),
            ("Tomas", "Vega", "tomas.vega", "tomas@iti.edu.co", "biffi2026", "estudiante", "medio", 9, "9C", 7),
            ("Sara", "Mendoza", "sara.mendoza", "sara@biffi.edu.co", "biffi2026", "estudiante", "avanzado", 11, "11B", 1),
            ("Daniel", "Correa", "daniel.correa", "daniel@lasalle.edu.co", "biffi2026", "estudiante", "medio", 6, "6A", 2),
        ]
        for nombre, apellido, usuario, correo, password, rol, nivel, grado, curso, institucion_id in usuarios_seed:
            db.session.add(
                Usuario(
                    nombre=nombre,
                    apellido=apellido,
                    usuario=usuario,
                    correo=correo,
                    contrasena=hash_password(password),
                    rol=rol,
                    nivel=nivel,
                    grado=grado,
                    curso=curso,
                    institucion_id=institucion_id,
                    activo=True,
                )
            )
        db.session.commit()

    if Pregunta.query.count() == 0:
        ejemplos = []
        for idx in range(1, 11):
            ejemplos.append({"pregunta": f"Si $2x + {idx} = {idx + 8}$, entonces $x =$", "op1": "2", "op2": "3", "op3": "4", "op4": "5", "correcta": "op3", "nivel": "basico", "grupo_grado": "10-11", "tipo_prueba": "simulacro", "tema": "Algebra", "explicacion": "Al despejar, se obtiene $2x = 8$ y por tanto $x = 4$."})
        for idx in range(1, 9):
            ejemplos.append({"pregunta": f"Calcula $\\sqrt{{{idx * idx * 4}}}$.", "op1": str(idx), "op2": str(idx * 2), "op3": str(idx * 3), "op4": str(idx * 4), "correcta": "op2", "nivel": "medio", "grupo_grado": "10-11", "tipo_prueba": "simulacro", "tema": "Radicacion", "explicacion": "La raiz cuadrada principal de $4n^2$ es $2n$."})
        for idx in range(1, 7):
            ejemplos.append({"pregunta": f"Resuelve la suma $\\sum_{{k=1}}^{{{idx + 2}}} k$.", "op1": str((idx + 2) * (idx + 3) // 2), "op2": str((idx + 1) * (idx + 2) // 2), "op3": str((idx + 2) * (idx + 4) // 2), "op4": str((idx + 3) * (idx + 4) // 2), "correcta": "op1", "nivel": "avanzado", "grupo_grado": "10-11", "tipo_prueba": "simulacro", "tema": "Series", "explicacion": "Usamos la formula $n(n+1)/2$."})
        for grupo, total in [("6-7", 6), ("8-9", 6), ("4-5", 4)]:
            for idx in range(1, total + 1):
                ejemplos.append({"pregunta": f"Cuanto es {idx + 3} + {idx + 4}?", "op1": str(idx + 5), "op2": str(idx + 6), "op3": str((idx + 3) + (idx + 4)), "op4": str(idx + 10), "correcta": "op3", "nivel": "basico", "grupo_grado": grupo, "tipo_prueba": "simulacro", "tema": "Aritmetica", "explicacion": "Se suman directamente los dos enteros."})
        for data in ejemplos:
            db.session.add(Pregunta(**data))
        db.session.commit()

    ensure_competition_questions()

    if Recurso.query.count() == 0:
        docente_biffi = Usuario.query.filter_by(usuario="fabiana.ariza").first()
        if docente_biffi:
            db.session.add(Recurso(titulo="Guia de entrenamiento algebraico", descripcion="Material base para nivel medio y avanzado.", tipo="pdf", archivo="demo-guia.pdf", subido_por=docente_biffi.id, visible=True))
            db.session.commit()

    if Mensaje.query.count() == 0:
        admin = Usuario.query.filter_by(usuario="carlos.nunez").first()
        estudiante = Usuario.query.filter_by(usuario="maria.perez").first()
        docente = Usuario.query.filter_by(usuario="fabiana.ariza").first()
        if admin and estudiante and docente:
            db.session.add(Mensaje(de_id=admin.id, para_id=estudiante.id, asunto="Bienvenida a las Olimpiadas", cuerpo="Recuerda revisar tus simulacros y la biblioteca antes de la fase clasificatoria."))
            db.session.add(Mensaje(de_id=estudiante.id, para_id=docente.id, asunto="Duda sobre progresiones", cuerpo="Profesora, podria compartir mas ejercicios de progresiones aritmeticas?"))
            db.session.commit()


def ensure_competition_questions():
    for tipo in ["clasificatoria", "selectiva", "final"]:
        existentes = Pregunta.query.filter_by(tipo_prueba=tipo).count()
        if existentes > 0:
            continue

        base_questions = (
            Pregunta.query.filter_by(tipo_prueba="simulacro")
            .order_by(Pregunta.id.asc())
            .all()
        )
        for original in base_questions:
            db.session.add(
                Pregunta(
                    pregunta=original.pregunta,
                    imagen_url=original.imagen_url,
                    op1=original.op1,
                    op2=original.op2,
                    op3=original.op3,
                    op4=original.op4,
                    correcta=original.correcta,
                    nivel=original.nivel,
                    grupo_grado=original.grupo_grado,
                    tipo_prueba=tipo,
                    tema=original.tema,
                    explicacion=original.explicacion,
                )
            )
        db.session.commit()


app = create_app()


if __name__ == "__main__":
    app.run(debug=True)
