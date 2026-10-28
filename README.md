# INVASION Launch

Application d'inscription et de gestion des participants à l'événement de lancement d'Invasion de Cardigan.

L'application complète est en PHP. Elle comprend la vitrine dynamique, les inscriptions, les billets QR, la confirmation de présence, la liste filtrable et l'administration. Le dossier `docs/` reste une vitrine statique publiée séparément sur GitHub Pages.

## Prérequis

- PHP 8.1 ou ultérieur
- Composer
- Extensions PHP `mbstring`, `PDO` et un pilote de base (`pdo_sqlite`, `pdo_mysql` ou `pdo_pgsql`)

## Lancement local sous Windows

```powershell
cd C:\Users\Paul-Samuel\Invation
composer install
Copy-Item .env.example .env
```

Modifiez `.env` pour définir le mot de passe administrateur, puis démarrez le serveur :

```powershell
php -S 127.0.0.1:5000 -t public public/router.php
```

Vous pouvez aussi lancer `start.bat`. Le site sera disponible sur [http://127.0.0.1:5000](http://127.0.0.1:5000).

## Configuration

Les variables peuvent être définies dans `.env` en local ou dans le panneau de configuration de l'hébergeur.

| Variable | Description |
|----------|-------------|
| `ADMIN_PASSWORD` | Mot de passe administrateur long et unique |
| `DATABASE_URL` | `sqlite:///invasion.db` par défaut, ou définir `DATABASE_DSN` pour PDO |
| `DATABASE_DSN` | DSN PDO, par exemple `mysql:host=localhost;dbname=invasion;charset=utf8mb4` |
| `DATABASE_URL` | Accepte également les URL PostgreSQL `postgres://` et `postgresql://` |
| `DATABASE_USER` | Utilisateur de la base MySQL |
| `DATABASE_PASSWORD` | Mot de passe de la base MySQL |
| `APP_TIMEZONE` | Fuseau horaire ; `Europe/Paris` par défaut |

La base SQLite est stockée à la racine du projet, en dehors du document root `public/`. Elle est créée automatiquement si elle n'existe pas. Sur un hébergement, configurez le chemin de base de données ou utilisez MySQL et assurez-vous que la base et ses sauvegardes sont persistantes.

Ne publiez jamais `.env` ni les fichiers de base de données. Ils sont exclus par `.gitignore`.

## Héberger l'application PHP

GitHub Pages ne peut pas exécuter PHP. Pour conserver les inscriptions, les billets QR et l'administration, choisissez un hébergeur qui prend en charge PHP 8.1+, Composer et PDO.

1. Connectez le dépôt GitHub à votre hébergeur PHP ou téléversez le projet.
2. Installez les dépendances avec `composer install --no-dev --optimize-autoloader`.
3. Configurez le document root sur le dossier `public/` si l'hébergeur le permet. Sinon, utilisez la racine du dépôt : `.htaccess` redirige l'application et ses fichiers statiques vers `public/`. Apache doit autoriser `mod_rewrite` ; sous Nginx, configurez l'équivalent.
4. Configurez `ADMIN_PASSWORD` et une base persistante. Pour MySQL, définissez `DATABASE_DSN`, `DATABASE_USER` et `DATABASE_PASSWORD` ; une URL PostgreSQL est aussi prise en charge.
5. Activez HTTPS et vérifiez les pages d'inscription, de participation et d'administration.

Le dossier `docs/` et le workflow `.github/workflows/pages.yml` continuent de publier une vitrine statique sur GitHub Pages. Cette vitrine n'exécute pas le backend PHP et ne reçoit pas les inscriptions.
