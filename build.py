import os


def main() -> None:
    is_production = os.environ.get("VERCEL_ENV") == "production"
    if is_production and not os.environ.get("DATABASE_URL"):
        raise RuntimeError("Set DATABASE_URL to the production PostgreSQL database before deploying.")
    if is_production and not os.environ.get("DATABASE_URL_UNPOOLED"):
        raise RuntimeError("Set DATABASE_URL_UNPOOLED for production database migrations.")
    if is_production and not os.environ.get("SECRET_KEY"):
        raise RuntimeError("Set SECRET_KEY in the Vercel production environment before deploying.")

    if is_production:
        os.environ["DATABASE_URL"] = os.environ["DATABASE_URL_UNPOOLED"]
        os.environ.setdefault("DEBUG", "false")
    os.environ.setdefault("DJANGO_SETTINGS_MODULE", "invasion_site.settings")

    import django
    from django.core.management import call_command

    django.setup()
    call_command("check")
    if is_production:
        call_command("migrate", interactive=False)


if __name__ == "__main__":
    main()
