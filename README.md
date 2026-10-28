# INVASION Launch

Site web pour le **lancement de la marque INVASION**, avec une interface d’administration pour le suivi des participants existants.

## Installation

```powershell
cd C:\Users\Paul-Samuel\Invation
python -m venv env
.\env\Scripts\activate
pip install -r requirements.txt
```

## Lancer le site

```powershell
.\env\Scripts\activate
python run.py
```

Ou `start.bat` — puis [http://127.0.0.1:5000](http://127.0.0.1:5000).

## Configuration

Copiez `.env.example` vers `.env`, puis définissez vos propres valeurs. Le fichier `.env` est local et ne doit pas être partagé.

| Variable | Description |
|----------|-------------|
| `SECRET_KEY` | Clé de session Flask ; utilisez une valeur aléatoire longue |
| `DATABASE_URL` | Base SQLAlchemy (défaut : `invasion.db`) |
| `ADMIN_PASSWORD` | Mot de passe obligatoire pour accéder à l’administration |

Le fichier `.env` est chargé au démarrage de `python run.py` et de `start.bat`. Après toute modification, redémarrez le serveur.

Identité de marche dans `config.py` : `BRAND_NAME`, `BRAND_TAGLINE`, `LAUNCH_EVENT`.

Les visiteurs peuvent s'inscrire à l'événement et renseigner leurs informations, leur présence et leurs préférences. Les participants déjà enregistrés peuvent aussi modifier leur présence depuis la page « Participation », avec leur référence et leur nom de famille.

## Déployer l'application Flask sur Vercel

Le dépôt est configuré pour Vercel : `index.py` expose l'application Flask, `.python-version` sélectionne Python 3.13 et `public/static/` contient les fichiers statiques servis par Vercel.

1. Importez le dépôt GitHub `pltraore41-art/Invasion` dans Vercel, en sélectionnant la racine du dépôt comme **Root Directory**.
2. Créez ou connectez une base PostgreSQL persistante via une intégration Vercel compatible, puis ajoutez dans **Settings → Environment Variables** :
   - `DATABASE_URL` : URL PostgreSQL de la base. Les formats `postgres://` et `postgresql://` sont convertis automatiquement.
   - `SECRET_KEY` : une valeur aléatoire longue et privée.
   - `ADMIN_PASSWORD` : un mot de passe admin long et unique.
3. Activez les variables pour **Production** et **Preview**, puis lancez **Redeploy**.

Ne déployez pas avec SQLite sur Vercel : le système de fichiers des fonctions n'est pas un stockage persistant et les inscriptions pourraient être perdues. Le site GitHub Pages du dossier `docs/` est une vitrine statique séparée ; pour les formulaires et l'administration, déployez l'application Flask ci-dessus.

## Publier la vitrine statique sur GitHub Pages

Le dossier `docs/` contient une vitrine statique et le workflow `.github/workflows/pages.yml` la publie automatiquement sur GitHub Pages à chaque mise à jour de la branche `main`. Cette vitrine n'exécute pas Flask : les inscriptions, les confirmations, les billets QR et l'administration ne sont pas disponibles sur Pages.

1. Créez un dépôt GitHub et envoyez-y le contenu du projet. Ne publiez jamais le fichier `.env` ni les bases de données ; ils sont exclus par `.gitignore`.
2. Dans le dépôt GitHub, ouvrez **Settings → Pages** et sélectionnez **GitHub Actions** comme source de déploiement.
3. Envoyez un commit sur `main` ou lancez le workflow **Deploy static site to GitHub Pages** dans l'onglet **Actions**.
4. L'adresse du site apparaît dans **Settings → Pages** et dans l'exécution réussie du workflow.
