# Développement local

Prérequis sur l'hôte : **Docker** (avec Compose), **make** et **Node.js** (pour les hooks Git uniquement).
PHP, Composer et les dépendances du front tournent dans les conteneurs.

## Premier démarrage

```bash
make install   # images, hooks Git, stack, dépendances Composer, node_modules, migrations
```

Ensuite :

| URL | Service |
|---|---|
| http://localhost | Front Next.js (et API sous `/api`) |
| http://localhost/api/health | Sonde de l'API |
| http://localhost:8025 | Mailpit (mails envoyés en dev) |
| http://localhost:9001 | Console RustFS (stockage S3) |

## Commandes courantes

`make` (ou `make help`) liste toutes les cibles.

| Commande | Effet |
|---|---|
| `make up` / `make down` | Démarre (en attendant que les services soient sains) / arrête la stack |
| `make logs s=php` | Suit les logs d'un service (tous si `s` est omis) |
| `make sh` / `make sh s=web` | Shell dans le conteneur `php` / `web` |
| `make lint` | Tous les linters, sans rien modifier : Composer (validate, audit), PHP-CS-Fixer, PHPStan niveau max, Rector, puis ESLint (type-aware, 0 warning toléré), Prettier, TypeScript strict |
| `make fix` | Corrections automatiques : Rector puis PHP-CS-Fixer, ESLint, Prettier |
| `make test` | PHPUnit (crée et migre la base `app_test`) puis Vitest |
| `make test-web-coverage` | Vitest avec couverture ; échoue sous 80 % (rapport HTML/lcov dans `web/coverage/`) |
| `make e2e` | Tests Playwright contre la stack, dans l'image officielle Playwright (aucun navigateur à installer) |
| `make db-reset` | Supprime et recrée la base de dev, migrations et fixtures (si installées) |
| `make db-reset ENV=test` | Idem pour la base de test |
| `make install-web` | Après un changement de `web/package-lock.json` : `make build` puis `make install-web` |

Les commandes passent par `scripts/compose-exec.sh <service> <commande>`. Il réutilise le conteneur si le service tourne, et sinon il en lance un temporaire.
Exemple : `scripts/compose-exec.sh php bin/console debug:router`.

## Pièges connus

- **`web` redémarre en boucle (`Cannot find module '/app/server.js'` ou `next: not found`)** : l'image locale `mangadex-web` est la version prod, par exemple après un `docker compose -f compose.yaml up`. Lance `make build install-web`.
- **Caddyfile modifié** : `docker compose restart proxy`. Caddy ne recharge pas sa config tout seul.
