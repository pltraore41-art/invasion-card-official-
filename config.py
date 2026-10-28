import os

from dotenv import load_dotenv

BASE_DIR = os.path.abspath(os.path.dirname(__file__))
load_dotenv(os.path.join(BASE_DIR, ".env"))


class Config:
    if os.environ.get("VERCEL"):
        missing_vercel_settings = [
            name
            for name in ("DATABASE_URL", "SECRET_KEY", "ADMIN_PASSWORD")
            if not os.environ.get(name)
        ]
        if missing_vercel_settings:
            missing_settings = ", ".join(missing_vercel_settings)
            raise RuntimeError(
                f"Configurez les variables Vercel suivantes avant le déploiement : {missing_settings}."
            )

    SECRET_KEY = os.environ.get("SECRET_KEY", "dev-change-me-in-production")
    DATABASE_URL = os.environ.get("DATABASE_URL")
    if DATABASE_URL and DATABASE_URL.startswith(("postgres://", "postgresql://")):
        DATABASE_URL = DATABASE_URL.replace("postgres://", "postgresql+psycopg://", 1)
        DATABASE_URL = DATABASE_URL.replace("postgresql://", "postgresql+psycopg://", 1)
    SQLALCHEMY_DATABASE_URI = DATABASE_URL or f"sqlite:///{os.path.join(BASE_DIR, 'invasion.db')}"
    SQLALCHEMY_TRACK_MODIFICATIONS = False
    ADMIN_PASSWORD = os.environ.get("ADMIN_PASSWORD", "")

    BRAND_NAME = "Invasion de Cardigan"
    BRAND_TAGLINE = "Lancement officiel de la marque"
    LAUNCH_EVENT = "Événement de lancement Invasion de Cardigan"
    EVENT_DATE = "14 novembre 2026"
    EVENT_START = "2026-11-14T00:00:00"
