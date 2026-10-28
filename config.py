import os

from dotenv import load_dotenv

BASE_DIR = os.path.abspath(os.path.dirname(__file__))
load_dotenv(os.path.join(BASE_DIR, ".env"))


class Config:
    SECRET_KEY = os.environ.get("SECRET_KEY", "dev-change-me-in-production")
    SQLALCHEMY_DATABASE_URI = os.environ.get(
        "DATABASE_URL", f"sqlite:///{os.path.join(BASE_DIR, 'invasion.db')}"
    )
    SQLALCHEMY_TRACK_MODIFICATIONS = False
    ADMIN_PASSWORD = os.environ.get("ADMIN_PASSWORD", "")

    BRAND_NAME = "Invasion de Cardigan"
    BRAND_TAGLINE = "Lancement officiel de la marque"
    LAUNCH_EVENT = "Événement de lancement Invasion de Cardigan"
    EVENT_DATE = "14 novembre 2026"
    EVENT_START = "2026-11-14T00:00:00"
