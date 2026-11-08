import os


def main() -> None:
    if os.environ.get("VERCEL_ENV") != "production":
        return
    if not os.environ.get("DATABASE_URL"):
        raise RuntimeError("Set DATABASE_URL to the production PostgreSQL database before deploying.")
    if not os.environ.get("SECRET_KEY"):
        raise RuntimeError("Set SECRET_KEY in the Vercel production environment before deploying.")

    os.environ.setdefault("DEBUG", "false")
    os.environ.setdefault("DJANGO_SETTINGS_MODULE", "invasion_site.settings")

    from django.core.management import call_command

    call_command("migrate", interactive=False)


if __name__ == "__main__":
    main()
