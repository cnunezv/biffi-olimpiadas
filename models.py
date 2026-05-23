from datetime import datetime

from flask_login import UserMixin
from flask_sqlalchemy import SQLAlchemy
from sqlalchemy import UniqueConstraint
from sqlalchemy.orm import validates


db = SQLAlchemy()


class Institucion(db.Model):
    __tablename__ = "instituciones"

    id = db.Column(db.Integer, primary_key=True)
    nombre = db.Column(db.String(150), nullable=False)
    ciudad = db.Column(db.String(120), nullable=False)
    departamento = db.Column(db.String(120), nullable=False, default="Bolivar")
    codigo = db.Column(db.String(30), unique=True, nullable=False)
    color = db.Column(db.String(7), nullable=False, default="#7C1F30")
    activa = db.Column(db.Boolean, default=True, nullable=False)
    creada_en = db.Column(db.DateTime, default=datetime.utcnow, nullable=False)

    usuarios = db.relationship("Usuario", back_populates="institucion", lazy=True)


class Usuario(UserMixin, db.Model):
    __tablename__ = "usuarios"

    id = db.Column(db.Integer, primary_key=True)
    nombre = db.Column(db.String(120), nullable=False)
    apellido = db.Column(db.String(120), nullable=False)
    usuario = db.Column(db.String(80), unique=True, nullable=False)
    correo = db.Column(db.String(120), unique=True, nullable=False)
    contrasena = db.Column(db.String(255), nullable=False)
    rol = db.Column(db.String(20), nullable=False)
    nivel = db.Column(db.String(20), default="basico", nullable=False)
    grado = db.Column(db.Integer, nullable=True)
    curso = db.Column(db.String(20), nullable=True)
    institucion_id = db.Column(db.Integer, db.ForeignKey("instituciones.id"), nullable=False)
    activo = db.Column(db.Boolean, default=True, nullable=False)
    creado_en = db.Column(db.DateTime, default=datetime.utcnow, nullable=False)

    institucion = db.relationship("Institucion", back_populates="usuarios")
    resultados = db.relationship("Resultado", back_populates="usuario", lazy=True)
    mensajes_enviados = db.relationship("Mensaje", foreign_keys="Mensaje.de_id", back_populates="remitente", lazy=True)
    mensajes_recibidos = db.relationship("Mensaje", foreign_keys="Mensaje.para_id", back_populates="destinatario", lazy=True)

    def get_id(self):
        return str(self.id)

    @property
    def nombre_completo(self):
        return f"{self.nombre} {self.apellido}"

    @property
    def grupo_grado(self):
        if self.grado is None:
            return None
        if self.grado <= 5:
            return "4-5"
        if self.grado <= 7:
            return "6-7"
        if self.grado <= 9:
            return "8-9"
        return "10-11"

    def es_admin(self):
        return self.rol == "admin"

    def es_docente(self):
        return self.rol == "docente"

    def es_estudiante(self):
        return self.rol == "estudiante"


class Pregunta(db.Model):
    __tablename__ = "preguntas"

    id = db.Column(db.Integer, primary_key=True)
    pregunta = db.Column(db.Text, nullable=False)
    imagen_url = db.Column(db.String(255))
    op1 = db.Column(db.String(255), nullable=False)
    op2 = db.Column(db.String(255), nullable=False)
    op3 = db.Column(db.String(255), nullable=False)
    op4 = db.Column(db.String(255), nullable=False)
    correcta = db.Column(db.String(3), nullable=False)
    nivel = db.Column(db.String(20), nullable=False)
    grupo_grado = db.Column(db.String(10), nullable=False)
    tipo_prueba = db.Column(db.String(30), nullable=False)
    tema = db.Column(db.String(120), nullable=False)
    explicacion = db.Column(db.Text)
    creado_en = db.Column(db.DateTime, default=datetime.utcnow, nullable=False)


class Resultado(db.Model):
    __tablename__ = "resultados"

    id = db.Column(db.Integer, primary_key=True)
    usuario_id = db.Column(db.Integer, db.ForeignKey("usuarios.id"), nullable=False)
    nivel = db.Column(db.String(100), nullable=False, index=True)
    puntaje = db.Column(db.Integer, nullable=False)
    total = db.Column(db.Integer, nullable=False)
    tiempo_seg = db.Column(db.Integer, default=0, nullable=False)
    detalle = db.Column(db.JSON, nullable=False, default=list)
    fecha = db.Column(db.DateTime, default=datetime.utcnow, nullable=False)

    usuario = db.relationship("Usuario", back_populates="resultados")


class Mensaje(db.Model):
    __tablename__ = "mensajes"

    id = db.Column(db.Integer, primary_key=True)
    de_id = db.Column(db.Integer, db.ForeignKey("usuarios.id"), nullable=False)
    para_id = db.Column(db.Integer, db.ForeignKey("usuarios.id"), nullable=False)
    asunto = db.Column(db.String(200), nullable=False)
    cuerpo = db.Column(db.Text, nullable=False)
    leido = db.Column(db.Boolean, default=False, nullable=False)
    eliminado_de = db.Column(db.Boolean, default=False, nullable=False)
    eliminado_para = db.Column(db.Boolean, default=False, nullable=False)
    enviado_en = db.Column(db.DateTime, default=datetime.utcnow, nullable=False)

    remitente = db.relationship("Usuario", foreign_keys=[de_id], back_populates="mensajes_enviados")
    destinatario = db.relationship("Usuario", foreign_keys=[para_id], back_populates="mensajes_recibidos")


class Recurso(db.Model):
    __tablename__ = "recursos"

    id = db.Column(db.Integer, primary_key=True)
    titulo = db.Column(db.String(200), nullable=False)
    descripcion = db.Column(db.Text)
    tipo = db.Column(db.String(30), nullable=False)
    archivo = db.Column(db.String(255), nullable=False)
    subido_por = db.Column(db.Integer, db.ForeignKey("usuarios.id"), nullable=False)
    visible = db.Column(db.Boolean, default=True, nullable=False)
    descargas = db.Column(db.Integer, default=0, nullable=False)
    creado_en = db.Column(db.DateTime, default=datetime.utcnow, nullable=False)

    autor = db.relationship("Usuario")


class Seccion(db.Model):
    __tablename__ = "secciones"

    id = db.Column(db.Integer, primary_key=True)
    nombre = db.Column(db.String(80), unique=True, nullable=False)
    etiqueta = db.Column(db.String(120), nullable=False)
    habilitada = db.Column(db.Boolean, default=False, nullable=False)


class PruebaConfig(db.Model):
    __tablename__ = "pruebas_config"
    __table_args__ = (UniqueConstraint("tipo_prueba", "grupo_grado", name="uq_tipo_grupo"),)

    id = db.Column(db.Integer, primary_key=True)
    tipo_prueba = db.Column(db.String(30), nullable=False)
    grupo_grado = db.Column(db.String(10), nullable=False)
    tiempo_limite_min = db.Column(db.Integer, nullable=True)
    num_preguntas = db.Column(db.Integer, nullable=False, default=10)
    max_intentos = db.Column(db.Integer, nullable=False, default=1)
    habilitada = db.Column(db.Boolean, default=False, nullable=False)
    instrucciones = db.Column(db.Text, default="")


class FormGoogle(db.Model):
    __tablename__ = "forms_google"

    id = db.Column(db.Integer, primary_key=True)
    titulo = db.Column(db.String(200), nullable=False)
    descripcion = db.Column(db.Text)
    form_url = db.Column(db.String(500), nullable=False)
    sheet_csv_url = db.Column(db.String(500))
    tipo_prueba = db.Column(db.String(30), nullable=False)
    grupo_grado = db.Column(db.String(10), nullable=False)
    habilitada = db.Column(db.Boolean, default=False, nullable=False)
    tiempo_limite_min = db.Column(db.Integer, nullable=True)
    fecha_inicio = db.Column(db.DateTime, nullable=True)
    fecha_cierre = db.Column(db.DateTime, nullable=True)
    creado_por = db.Column(db.Integer, db.ForeignKey("usuarios.id"), nullable=False)

    autor = db.relationship("Usuario")

    @validates("tipo_prueba")
    def validate_tipo(self, _key, value):
        return value.lower()
