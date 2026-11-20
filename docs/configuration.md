# Configuration par environnement

Ce document liste les variables d'environnement du projet et indique où chacune est définie, pour chaque environnement.

## Principes

| Environnement | API Symfony | Front Next.js | Base de données |
|---|---|---|---|
| **dev** | Docker Compose (local) | Docker Compose (local) | PostgreSQL du Compose |
| **test** | CI GitHub Actions / PHPUnit | CI GitHub Actions | PostgreSQL du service CI |
| **staging** | Render (branche `dev`) | Vercel, déploiement de preview de la branche `dev` | Neon, branche `staging` |
| **prod** | Render (tags `vX.Y.Z`) | Vercel, déploiement de production (`main`) | Neon, branche `main` |

- **Les valeurs par défaut non sensibles** sont dans des fichiers versionnés : `api/.env`, `api/.env.dev`, `api/.env.test`.
- **Les surcharges locales** vont dans des fichiers non versionnés : `.env` à la racine (modèle `.env.example`), `api/.env.local`, `web/.env.local` (modèle `web/.env.example`).
- **Les secrets de staging et de prod ne sont jamais dans le dépôt ni dans l'image Docker.**
  Ils sont uniquement définis comme variables d'environnement de la plateforme (Render, Vercel, GitHub Environments).
- Une vraie variable d'environnement l'emporte toujours sur les fichiers `.env`.
- **Pourquoi pas le Vault Symfony** : staging et prod tournent avec la **même image** en `APP_ENV=prod`.
  Un Vault `prod` obligerait à donner au staging la clé qui déchiffre les secrets de prod.
  Avec des variables par plateforme, chaque environnement a ses propres secrets, sans rien de partagé.

## API (Symfony)

| Variable | Rôle | Secret | dev | test | staging / prod |
|---|---|---|---|---|---|
| `APP_ENV` | Environnement Symfony | non | `dev` (image `frankenphp_dev`) | `test` (phpunit.dist.xml) | `prod` (image `frankenphp_prod`) |
| `APP_SECRET` | Signature CSRF, cookies, URLs signées | **oui** | `api/.env.dev` | `api/.env.test` | Render, valeur **différente** par environnement |
| `DATABASE_URL` | Connexion PostgreSQL | **oui** (mot de passe) | `compose.yaml` (service `database`) | workflow `api-tests.yml` | Render : URL **pooled** de la branche Neon |
| `DEFAULT_URI` | Base des URL générées hors requête (CLI, mails) | non | `api/.env` (`http://localhost`) | `api/.env` | Render : URL publique du front (Vercel) |
| `TRUSTED_PROXIES` | Proxys dont on accepte les en-têtes `X-Forwarded-*` | non | vide | vide | Render : `REMOTE_ADDR` |

### Valeurs à générer

```bash
# APP_SECRET : une valeur différente pour le staging et pour la prod
openssl rand -hex 32
```

### URL Neon

Neon fournit deux URL par branche :
- l'URL **pooled** (hôte en `-pooler`), pour l'application ;
- l'URL **directe**, pour les migrations Doctrine (le pooler ne gère pas toutes les commandes de session).

```
postgresql://<user>:<password>@<host>/<db>?sslmode=require&serverVersion=17&charset=utf8
```

`serverVersion` doit correspondre à la version de Postgres de Neon : Doctrine s'en sert pour générer le bon SQL.

## Front (Next.js)

| Variable | Rôle | Secret | dev | staging / prod |
|---|---|---|---|---|
| `API_URL` | URL de l'API, cible du rewrite `/api/*` (côté serveur uniquement) | non | inutile (Caddy route `/api`) | Vercel : environnement *Preview* (branche `dev`) → API de staging ; *Production* → API de prod |
| `NEXT_TELEMETRY_DISABLED` | Désactive la télémétrie Next.js | non | image Docker | CI |

Une variable préfixée par `NEXT_PUBLIC_` est **intégrée au bundle JavaScript envoyé au navigateur**. N'y mets jamais de secret.

## Docker Compose (local)

Toutes les variables ont une valeur par défaut, voir `.env.example`. Pour en changer une, copie ce fichier en `.env` à la racine.

| Variable | Défaut | Rôle |
|---|---|---|
| `HTTP_PORT` | `80` | Port de Caddy sur l'hôte |
| `POSTGRES_VERSION` / `POSTGRES_DB` / `POSTGRES_USER` / `POSTGRES_PASSWORD` | `16` / `app` / `app` / `!ChangeMe!` | Base locale, également utilisées pour construire `DATABASE_URL` |
| `UID` / `GID` | `1000` | Utilisateur du conteneur `php` en dev, aligné sur l'hôte |
| `XDEBUG_MODE` | `off` | `debug` pour activer le débogueur |

## CI/CD (GitHub)

| Nom | Type | Où | Usage |
|---|---|---|---|
| `RELEASE_TOKEN` | secret | dépôt | PAT utilisé par les workflows de release (PR `dev → main`, tag) |
| `CODECOV_TOKEN` | secret | dépôt | Envoi de la couverture de tests |

Les secrets de déploiement (deploy hooks Render, URL directes Neon pour les migrations) seront ajoutés dans des **GitHub Environments** `staging` et `production`, avec les issues #10 et #11.
