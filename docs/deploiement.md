# Déploiement

| | Front | API | Base de données |
|---|---|---|---|
| **Staging** | Vercel, preview de la branche `dev` | Render, service de staging (image `mangadex-php:dev`) | Neon, branche `staging` |
| **Prod** | Vercel, production (`main`) | Render, service de prod (tags `vX.Y.Z`), voir #11 | Neon, branche `main` |

Le navigateur ne parle qu'à Vercel : Next.js relaie `/api/*` vers Render (rewrite piloté par `API_URL`).
Front et API sont donc sur la **même origine** : pas de CORS, et les cookies sont first-party.

## Staging : flux automatique

Workflow `.github/workflows/deploy-staging.yml`, déclenché à chaque push sur `dev`. On peut aussi le relancer à la main (*Run workflow*).

1. **Build** de l'image API (cible `frankenphp_prod`, `APP_VERSION` = commit), poussée sur GHCR avec les tags `dev` et `sha-<commit>`.
2. **Migrations Doctrine** sur Neon `staging`, avec l'URL directe.
3. **Render** : le deploy hook reçoit le **digest exact** de l'image. Le workflow attend ensuite que `/api/health` renvoie le nouveau commit.
4. **Smoke tests**, effectués *via le front Vercel* :
   - la page d'accueil répond ;
   - `/api/health` (rewrite → Render → Symfony) renvoie `status: ok` et le bon commit.

En parallèle, Vercel déploie lui-même le front de `dev` grâce à son intégration Git.

## Staging : mise en place (une seule fois)

### 1. GHCR
Après le premier run du workflow, le package `mangadex-php` apparaît dans l'onglet *Packages* du dépôt.
Passe-le en **public** (*Package settings → Change visibility*) pour que Render puisse télécharger l'image sans identifiants.
Le dépôt et les images ne contiennent aucun secret (voir `configuration.md`).

### 2. Neon
- Projet en région Frankfurt, avec une **branche `staging`**.
- Récupère ses deux URL de connexion :
  - **pooled** (hôte en `-pooler`) → pour Render ;
  - **directe** → pour les migrations en CI.
- Ajoute `&serverVersion=<version Postgres de Neon>&charset=utf8` aux deux URL (`sslmode=require` est déjà présent).

### 3. Render, service `mangadex-api-staging`
- *New → Web Service → Existing image* : `ghcr.io/pauljosephkrogulec/mangadex-php:dev`, plan **Free**, région **Frankfurt**.
- **Environment** :

  | Variable | Valeur |
  |---|---|
  | `PORT` | `8080` (port d'écoute de FrankenPHP) |
  | `APP_SECRET` | `openssl rand -hex 32`, propre au staging |
  | `DATABASE_URL` | URL **pooled** de la branche Neon `staging` |
  | `DEFAULT_URI` | URL du front de staging (voir ci-dessous) |
  | `TRUSTED_PROXIES` | `REMOTE_ADDR` |

- *Settings → Health Check Path* : `/api/health`.
- *Settings → Auto-Deploy* : **No**. C'est le workflow qui déploie, après les migrations.
- *Settings → Deploy Hook* : copie l'URL, elle servira de secret GitHub.

### 4. Vercel
- **URL du staging** : *Deployments*, puis filtre sur la branche `dev`. L'URL fixe de branche ressemble à `https://mangadex-web-git-dev-<équipe>.vercel.app`.
- *Settings → Environment Variables* : `API_URL` = URL Render du staging (`https://mangadex-api-staging.onrender.com`), environnement **Preview**, branche **`dev`**.
- *Settings → Deployment Protection → Protection Bypass for Automation* : génère un secret.

### 5. GitHub : environnement `staging`
*Settings → Environments → New environment* : `staging`.

| Type | Nom | Valeur |
|---|---|---|
| Secret | `DATABASE_URL_DIRECT` | URL **directe** de la branche Neon `staging` |
| Secret | `RENDER_DEPLOY_HOOK_URL` | Deploy hook du service Render de staging |
| Secret | `VERCEL_AUTOMATION_BYPASS_SECRET` | Secret de contournement de la protection Vercel |
| Variable | `API_URL` | `https://mangadex-api-staging.onrender.com` |
| Variable | `FRONT_URL` | URL fixe de la branche `dev` sur Vercel |

Ensuite, relance le workflow : *Actions → CD — Déploiement staging → Run workflow* sur `dev`.

## Limites du gratuit (staging)

- **Render Free** : le service s'endort après 15 minutes sans requête. Le réveil prend environ 1 minute, et le workflow en tient compte.
  Les 750 h par mois sont partagées par tout le compte, donc seule la prod sera maintenue éveillée (#11).
- **Neon Free** : la base se met en veille après 5 minutes. `/api/health` ne l'interroge pas, pour ne pas consommer d'heures de calcul.
- **Vercel Hobby** : réservé à un usage personnel, non commercial.
