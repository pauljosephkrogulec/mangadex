# Déploiement

| | Front | API | Base de données |
|---|---|---|---|
| **Staging** | Vercel, preview de la branche `dev` | Render, service de staging (image `mangadex-php:dev`) | Neon, branche `staging` |
| **Prod** | Vercel, production (`main`) | Render, service de prod (tags `vX.Y.Z`) | Neon, branche `main` |

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

## Production : flux automatique

Le déploiement en prod est **100 % automatique** : merger la PR de release (avec les 6 checks requis verts) vaut approbation.

1. *Actions → Release — Préparer* → `vX.Y.Z` : une PR `dev → main` `chore(release): vX.Y.Z` est créée avec le label `release`.
2. Merge de la PR en **Create a merge commit** :
   - Vercel déploie le front de prod depuis `main`, grâce à son intégration Git ;
   - `release-tag.yml` pose le tag et crée la GitHub Release, avec des notes classées par catégories (`.github/release.yml`).
3. Le tag déclenche `release-images.yml` : images `php` et `web` publiées, avec SBOM et signature cosign.
4. Puis `deploy-prod.yml` (job *Deploy production*) :
   - **migrations** Doctrine sur Neon `main` ;
   - déploiement du **digest** de l'image sur Render ;
   - attente de la nouvelle version (`/api/health`) ;
   - **smoke tests** via le front de prod.

### Rollback

*Actions → CD — Rollback production → Run workflow* → version précédente, par exemple `v1.2.2`. Le workflow redéploie l'image de cette version, **sans** migrations, et relance les smoke tests.

- **Front** : Vercel → *Deployments* → déploiement précédent de `main` → **Instant Rollback**.
- **Base de données : non restaurée.** Les migrations de la version fautive restent appliquées.
  - Règle à respecter : une migration doit rester compatible avec la version précédente du code. On ajoute une colonne d'abord, et on ne la supprime que dans une release suivante.
  - En cas de besoin : Neon → branche `main` → *Restore*, dans la limite d'une fenêtre de 6 h sur le plan gratuit.

### Garder l'API éveillée

`keep-alive.yml` pinge `/api/health` toutes les 10 min, **de 6 h à 23 h UTC** seulement, soit environ 530 h par mois.
Les 750 h gratuites de Render sont partagées avec le staging : le garder éveillé 24 h/24 ferait dépasser le quota en fin de mois.
La nuit, la première visite attend donc environ 1 min.

## Production : mise en place (une seule fois)

### 1. Neon
Utilise la branche **`main`** du projet. Récupère ses URL **pooled** et **directe**, et ajoute `&serverVersion=<version>&charset=utf8` à chacune.

### 2. Render, service `mangadex-api`
Mêmes réglages que le staging, avec ces différences :
- **Image** : `ghcr.io/pauljosephkrogulec/mangadex-php:0.1.0`, ou la dernière version publiée. Le workflow la remplacera à chaque release.
- **Variables d'environnement** :
  - `APP_SECRET` : une valeur **différente** de celle du staging ;
  - `DATABASE_URL` : l'URL pooled de la branche Neon **`main`** ;
  - `DEFAULT_URI` : l'URL de prod Vercel.
- **Projet / environnement Render** : de préférence séparé du staging, par exemple `mangadex-prod` / `production`.
- Health check `/api/health`, Auto-Deploy **No**. Copie le **Deploy Hook**.

### 3. Vercel
- *Settings → Environments → Production → Branch Tracking* : **`main`**.
- `API_URL` = URL Render de prod, dans l'environnement **Production**.
- La production n'est pas protégée, donc pas besoin de secret de contournement.

### 4. GitHub
**Environnement `production`** (*Settings → Environments*) :

| Type | Nom | Valeur |
|---|---|---|
| Secret | `DATABASE_URL_DIRECT` | URL **directe** de la branche Neon `main` |
| Secret | `RENDER_DEPLOY_HOOK_URL` | Deploy hook du service Render de prod |
| Variable | `API_URL` | `https://mangadex-api.onrender.com` |
| Variable | `FRONT_URL` | URL de prod Vercel (`https://mangadex-web.vercel.app`) |

**Variable de dépôt** (*Settings → Secrets and variables → Actions → Variables*) : `PROD_API_URL`, avec la même valeur que `API_URL`. Elle sert à `keep-alive.yml`.

`RENDER_DEPLOY_HOOK_URL` et `DATABASE_URL_DIRECT` doivent être des secrets **d'environnement** (`staging` et `production`), et non des secrets du dépôt. Sinon, staging et prod partageraient la même valeur.
