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
| `APP_VERSION` | Version servie par `GET /api/health` | non | `api/.env` (`dev`) | `api/.env` | Injectée au build de l'image (commit en staging, tag en prod) |
| `PORT` | Port qu'utilise Render pour joindre le service | non | — | — | Render : `8080` |
| `REDIS_URL` | Redis : cache, locks, transport Messenger | **oui** (si mot de passe) | `compose.yaml` (`redis://redis:6379`) | — | à définir avec #47 / #51 / #58 |
| `MAILER_DSN` | Transport des mails | **oui** | `compose.override.yaml` (Mailpit) | — | à définir avec #43 |
| `S3_ENDPOINT` / `S3_REGION` / `S3_BUCKET` | Stockage objet compatible S3 | non | `compose.yaml` (RustFS, bucket `mangadex`) | — | à définir avec #46 |
| `S3_ACCESS_KEY` / `S3_SECRET_KEY` | Identifiants S3 | **oui** | `compose.yaml` | — | à définir avec #46 |
| `MERCURE_URL` | URL du hub pour publier depuis PHP (réseau interne) | non | `compose.yaml` (`http://mercure/.well-known/mercure`) | — | à définir avec #52 |
| `MERCURE_PUBLIC_URL` | URL du hub pour les navigateurs (via Caddy) | non | `compose.yaml` | — | à définir avec #52 |
| `MERCURE_JWT_SECRET` / `MERCURE_JWT_ISSUER` | Clé de signature et émetteur (`iss`) des JWT Mercure | **oui** (clé) | `compose.yaml` | — | à définir avec #52 |

Ces variables sont déjà fournies par Docker Compose (#14), mais aucun bundle ne les lit encore.
Les issues citées installeront les bundles et ajouteront leurs valeurs par défaut dans `api/.env`.

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
| `S3_ACCESS_KEY` / `S3_SECRET_KEY` | `mangadex` / `!ChangeMe!` | Identifiants root de RustFS, transmis aussi à l'API |
| `MERCURE_JWT_SECRET` | `!ChangeThisMercureHubJWTSecretKey!` | Clé HMAC partagée par le hub et l'API |
| `MERCURE_JWT_ISSUER` | `http://localhost` | Émetteur (`iss`) de confiance pour le hub |
| `MERCURE_PUBLIC_URL` | `http://localhost/.well-known/mercure` | URL publique du hub, qui sert aussi d'audience (`aud`) des JWT. À adapter si `HTTP_PORT` ≠ 80 |
| `MAILPIT_PORT` | `8025` | Interface web de Mailpit sur l'hôte (dev) |
| `STORAGE_CONSOLE_PORT` | `9001` | Console web de RustFS sur l'hôte (dev) |

### Services annexes

| Service | Image | Rôle | Accès en dev |
|---|---|---|---|
| `redis` | `redis:8.8-alpine` | Cache, locks, transport Messenger | `redis://redis:6379` (réseau interne) |
| `storage` | `rustfs/rustfs:1.0.6` | Stockage objet S3 | `http://storage:9000`, console sur http://localhost:9001 |
| `storage-init` | `rustfs/rustfs:1.0.6` | Crée le bucket `mangadex` puis s'arrête | — |
| `mercure` | `dunglas/mercure:v1.1` | Hub temps réel (SSE) | http://localhost/.well-known/mercure (via Caddy) |
| `mailpit` | `axllent/mailpit:v1.29` | Faux SMTP, **dev uniquement** | SMTP `mailpit:1025`, interface sur http://localhost:8025 |

- **MinIO → RustFS** : MinIO ne publie plus d'images Docker (le dépôt `minio/minio` a disparu de Docker Hub).
  RustFS est compatible S3 et sous licence Apache 2.0. Le code passera par l'API S3 standard (#46), il ne dépend donc pas de ce choix.
- **Mercure v1** n'accepte que des JWT OAuth 2.0 : en-tête `typ: at+jwt`, claims `iss`, `aud` et `exp`
  obligatoires, droits dans `authorization_details` (l'ancien claim `mercure` n'est plus reconnu).
  L'audience est fixée à `MERCURE_PUBLIC_URL` (`resource_identifier`). Sans cela, elle dépendrait du `Host` de la requête,
  et un même jeton ne servirait pas à la fois pour publier depuis PHP et pour s'abonner via Caddy.
  Côté abonnement, le paramètre est `?match=<topic>` (et non plus `?topic=`).
- Après une modification de `docker/caddy/Caddyfile`, lance `docker compose restart proxy` (Caddy ne recharge pas la config tout seul).

## CI/CD (GitHub)

| Nom | Type | Où | Usage |
|---|---|---|---|
| `RELEASE_TOKEN` | secret | dépôt | PAT utilisé par les workflows de release (PR `dev → main`, tag) |
| `CODECOV_TOKEN` | secret | dépôt | Envoi de la couverture de tests |

Les secrets de déploiement sont rangés dans des **GitHub Environments** : `staging` (#10, voir `deploiement.md`) et `production` (#11).
