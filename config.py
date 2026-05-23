import os
from pathlib import Path


BASE_DIR = Path(__file__).resolve().parent


class Config:
    SECRET_KEY = os.getenv("SECRET_KEY", "biffi-olimpiadas-secret-2026")
    LEGACY_BRIDGE_SECRET = os.getenv("LEGACY_BRIDGE_SECRET", "biffi-legacy-bridge-2026")
    APPLICATION_ROOT = os.getenv("APPLICATION_ROOT", "")
    SQLALCHEMY_DATABASE_URI = os.getenv(
        "DATABASE_URL",
        f"sqlite:///{BASE_DIR / 'instance' / 'biffi_olimpiadas.db'}",
    )
    SQLALCHEMY_TRACK_MODIFICATIONS = False
    UPLOAD_FOLDER = str(BASE_DIR / "static" / "uploads")
    PDF_UPLOAD_FOLDER = str(BASE_DIR / "static" / "uploads" / "pdfs")
    IMG_UPLOAD_FOLDER = str(BASE_DIR / "static" / "uploads" / "imgs")
    MAX_CONTENT_LENGTH = 16 * 1024 * 1024
    SESSION_COOKIE_PATH = APPLICATION_ROOT or "/"
