# Invasion de Cardigan

Application Django d'inscription et de gestion des participants à l'événement. Les inscriptions sont stockées côté serveur dans PostgreSQL ; elles sont accessibles depuis les différents appareils et ne dépendent pas du navigateur qui a servi à remplir le formulaire.

## Fonctionnalités

- Page d'accueil de l'événement, collection et compte à rebours.
- Inscription avec présence, accompagnants, boissons, connaissance du cardigan, objectif, taille, quantité, couleur et commentaire.
- Référence privée, récapitulatif et billet téléchargeable avec QR code.
- Mise à jour de la présence avec référence et nom.
- Espace organisateur : tableau de bord, statistiques, recherche, filtres, fiches, modification, suppression, export CSV, impression et pointage QR.
- Administration protégée par les comptes Django. La liste des participants et les exports ne sont visibles qu'après connexion d'un compte organisateur.

## Lancer en local sous Windows

Python 3.14 est requis.

```powershell
py -3.14 -m venv .venv
.\.venv\Scripts\Activate.ps1
python -m pip install -r requirements.txt
Copy-Item .env.example .env
python manage.py migrate
python manage.py createsuperuser
python manage.py runserver
```

Le site sera disponible sur [http://127.0.0.1:8000](http://127.0.0.1:8000). En local, Django utilise SQLite. `createsuperuser` demande un identifiant, une adresse e-mail et un mot de passe ; le mot de passe est haché par Django et n'est pas ajouté au dépôt. Il est également possible de lancer `start.bat` avec l'environnement virtuel existant dans `env`.

## Hébergement sur Vercel avec Neon PostgreSQL

Vercel détecte Django à partir de `manage.py` et collecte automatiquement les fichiers statiques. Neon héberge la base PostgreSQL persistante. Les migrations sont exécutées automatiquement lors d'un déploiement Vercel en production.

1. Créez un projet PostgreSQL sur [Neon](https://neon.tech/) et copiez sa chaîne de connexion PostgreSQL (`DATABASE_URL`). Gardez cette valeur privée.
2. Sur [Vercel](https://vercel.com/new), importez le dépôt GitHub `pltraore41-art/invasion-card-official-`. Gardez le répertoire racine par défaut ; Vercel détectera Django.
3. Dans **Project → Settings → Environment Variables**, ajoutez pour l'environnement **Production** :
   - `DATABASE_URL` : chaîne de connexion Neon.
   - `DATABASE_URL_UNPOOLED` : chaîne de connexion directe Neon, utilisée pour appliquer les migrations.
   - `SECRET_KEY` : une longue clé aléatoire privée.
   - `DEBUG` : `false`.
   - `APP_TIMEZONE` : `Europe/Paris`.
   Si vous utilisez un domaine personnalisé, ajoutez aussi son nom à `ALLOWED_HOSTS` et son origine HTTPS à `CSRF_TRUSTED_ORIGINS`.
4. Enregistrez puis lancez **Deploy**. Si les variables sont ajoutées après un déploiement, relancez le déploiement pour appliquer les migrations à Neon.
5. Après le premier déploiement, installez le CLI Vercel et connectez le dossier du projet (`vercel link`). Récupérez les variables de production puis créez le compte organisateur :

   ```sh
   vercel env pull .env.local --environment production
   python manage.py createsuperuser
   ```

   Entrez un mot de passe fort dans l'invite. Le fichier `.env.local` est ignoré par Git et ne doit pas être partagé.
6. Ouvrez le domaine `.vercel.app` fourni par Vercel et testez l'inscription, la connexion administrateur et le billet QR.

Vercel exécute l'application Django comme une fonction et sert les fichiers statiques par son CDN. Le site utilise l'URL poolée Neon (`DATABASE_URL`) et les migrations l'URL directe (`DATABASE_URL_UNPOOLED`). Neon fournit la base de données : **ne supprimez pas le projet Neon**, car il contient les inscriptions. Vérifiez les offres et limites des deux services avant la mise en ligne.

GitHub Pages ne peut pas exécuter Django. L'ancien fichier `render.yaml` reste disponible pour un déploiement Render, mais n'est pas utilisé par Vercel.

## Tests

```powershell
python manage.py check
python manage.py makemigrations --check --dry-run
python manage.py test
```

Le workflow GitHub Actions `.github/workflows/django-ci.yml` exécute les vérifications Django et les tests à chaque modification. Vercel déploie les modifications de la branche `main` une fois le dépôt GitHub importé et les variables de production configurées.

## Notes sur l'ancien site

Les dossiers `docs/`, `public/` et les fichiers PHP/Supabase sont les anciennes versions du site. L'application Django utilise le design et les images de `app/static/` et n'appelle pas Supabase. Les anciennes inscriptions SQLite ou Supabase ne sont pas copiées automatiquement dans la nouvelle base PostgreSQL ; exportez les données nécessaires avant de changer de service.
