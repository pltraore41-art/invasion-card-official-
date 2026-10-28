from flask import Flask
from flask_sqlalchemy import SQLAlchemy

from config import Config

db = SQLAlchemy()


def create_app(config_class=Config):
    app = Flask(__name__)
    app.config.from_object(config_class)

    db.init_app(app)

    from app import routes  # noqa: F401

    app.register_blueprint(routes.bp)

    @app.context_processor
    def inject_brand():
        return {
            "brand_name": app.config["BRAND_NAME"],
            "brand_tagline": app.config["BRAND_TAGLINE"],
            "launch_event": app.config["LAUNCH_EVENT"],
            "event_start": app.config["EVENT_START"],
        }

    with app.app_context():
        db.create_all()

    return app
