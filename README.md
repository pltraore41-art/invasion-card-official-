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

## Hébergement depuis GitHub avec PostgreSQL

GitHub stocke le code source, mais **GitHub Pages ne peut pas exécuter Django ni héberger une base de données**. Le dépôt est préparé pour Render, qui récupère le code depuis GitHub et exécute Django avec une base PostgreSQL persistante.

1. Vérifiez le coût du service web Render (**Starter**) et de PostgreSQL (**Basic**) avant de créer les ressources ; les tarifs et les limites peuvent changer. Le plan gratuit de base de données n'est pas retenu pour les inscriptions, car il ne garantit pas une conservation permanente des données.
2. Connectez le dépôt GitHub à Render et créez un **Blueprint** à partir de `render.yaml`. Le Blueprint prépare un service Django distinct et une base PostgreSQL dédiée. La configuration lance les migrations, collecte les fichiers statiques et utilise une clé Django secrète générée par Render.
3. Dans le Shell du service Render, créez le compte organisateur :

   ```sh
   python manage.py createsuperuser --username Invasion@gmail.com --email Invasion@gmail.com
   ```

   Saisissez un nouveau mot de passe fort dans l'invite. Ne placez pas le mot de passe dans le dépôt, dans `render.yaml`, ni dans cette conversation.
4. Ouvrez l'adresse `.onrender.com` indiquée par Render et testez une inscription, l'administration et le billet QR. Chaque déploiement depuis GitHub réutilise la même base PostgreSQL.
5. Si GitHub Pages avait déjà été activé pour ce dépôt, désactivez-le dans **Settings → Pages** : le site publié précédemment depuis `docs/` est une ancienne version statique et ne contient pas l'application Django.

Le fichier `render.yaml` déclare un service `invasion-cardigan-django` et une base `invasion-cardigan-postgres`. Les données sont enregistrées dans cette base, sans clé Supabase dans le navigateur. Les déploiements ne peuvent pas être effectués automatiquement par ce dépôt tant que le Blueprint n'est pas relié au compte Render et au dépôt GitHub.

### Déploiement manuel alternatif

Sur un hébergeur Python avec PostgreSQL, installez les paquets de `requirements.txt`, définissez `DATABASE_URL`, une valeur aléatoire `SECRET_KEY`, `DEBUG=false` et les hôtes autorisés, puis exécutez :

```sh
python manage.py collectstatic --no-input
python manage.py migrate --noinput
gunicorn invasion_site.wsgi:application
```

## Tests

```powershell
python manage.py check
python manage.py makemigrations --check --dry-run
python manage.py test
```

Le workflow GitHub Actions `.github/workflows/django-ci.yml` exécute les vérifications Django et les tests à chaque modification. Le déploiement web se configure dans Render en connectant le dépôt ; le workflow de tests ne publie pas une version statique sur GitHub Pages.

## Notes sur l'ancien site

Les dossiers `docs/`, `public/` et les fichiers PHP/Supabase sont les anciennes versions du site. L'application Django utilise le design et les images de `app/static/` et n'appelle pas Supabase. Les anciennes inscriptions SQLite ou Supabase ne sont pas copiées automatiquement dans la nouvelle base PostgreSQL ; exportez les données nécessaires avant de changer de service.
