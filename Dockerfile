FROM python:3.14-slim

ENV PYTHONDONTWRITEBYTECODE=1 \
    PYTHONUNBUFFERED=1 \
    DEBUG=false

WORKDIR /app

COPY requirements.txt ./
RUN pip install --no-cache-dir -r requirements.txt

COPY . .

RUN SECRET_KEY=build-only-not-used-at-runtime python manage.py collectstatic --no-input

EXPOSE 8000

CMD ["sh", "-c", "python manage.py migrate --noinput && gunicorn invasion_site.wsgi:application --bind 0.0.0.0:${PORT:-8000} --workers 2"]
