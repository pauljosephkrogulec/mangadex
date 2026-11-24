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
| http://localhost/_profiler | Web Profiler Symfony (dev). Chaque réponse de l'API donne son lien dans l'en-tête `X-Debug-Token-Link` |
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
Maker (dev) : `scripts/compose-exec.sh php bin/console make:entity`, `make:controller`, etc. (`bin/console list make`).

Logs (Monolog) : en dev dans `api/var/log/dev.log` ; en prod sur `stderr` au format JSON (seulement autour d'une erreur, via `fingers_crossed`).

## Conventions de l'API

- **Identifiants** : toutes les entités utilisent le trait `App\Entity\Trait\UuidV7Id` (UUID v7, colonne `uuid` PostgreSQL, généré au `persist()`). Pas d'entier auto-incrémenté : un UUID v7 est triable par date de création et ne laisse pas deviner les autres identifiants.
- **Dates** : jamais `new \DateTimeImmutable()`, `time()` ou `date()` pour « maintenant » (PHPStan les refuse, via `spaze/phpstan-disallowed-calls`). On injecte `Symfony\Component\Clock\ClockInterface` et on appelle `$clock->now()`. En test : `ClockSensitiveTrait::mockTime('2026-01-01')` ou une `MockClock`. Les entités horodatées implémentent `TimestampableInterface` avec le trait `Timestampable` : `createdAt` et `updatedAt` sont remplis par `TimestampableListener`.
- **Schéma** : on modifie l'entité, puis `scripts/compose-exec.sh php bin/console doctrine:migrations:diff` génère la migration dans `api/migrations/`. On relit le SQL et on vérifie le `down()` (`doctrine:migrations:migrate prev`). La CI rejoue toutes les migrations sur une base vide puis lance `doctrine:schema:validate`.
- **Données de test** : fabriques Foundry dans `api/src/Factory/`, jeux de données dans `api/src/Story/` (`AppStory`, chargée par `make db-reset`). Dans un test : traits `Factories` + `ResetDatabase`, puis `SeriesFactory::createOne([...])`.
- **Erreurs** : toute erreur de l'API est rendue en `application/problem+json` (RFC 7807 : `type`, `title`, `status`, `detail`), y compris pour les contrôleurs Symfony classiques. Un client Hydra (`Accept: application/ld+json`) reçoit la variante JSON-LD, et un navigateur garde la page HTML. Pour une erreur métier, on lance une sous-classe de `App\Exception\BusinessException` : `ResourceNotFoundException` (404), `ConflictException` (409) ou `BusinessRuleViolationException` (422). Son message devient le `detail` montré au client, il ne doit donc rien contenir d'interne. Les autres exceptions sans statut HTTP propre se mappent dans `exception_to_status` (`config/packages/api_platform.yaml`). Hors debug, le `detail` d'une erreur 500 est masqué.
- **Configuration métier** (importeurs, traitements d'images…) : fichiers YAML versionnés dans `api/config/app/`, chargés par `App\Config\YamlConfigLoader` et validés par OptionsResolver. Chaque consommateur déclare ses options dans une méthode statique `configureOptions()` : requises, types, valeurs permises, défauts, normalisation. Toute option inconnue ou invalide lève une `InvalidConfigurationException` qui nomme le fichier. Les tests chargent les fichiers livrés, donc une faute de frappe fait échouer la CI. Exemples : `ImportersConfig` (`importers.yaml`) et `ImageProcessingConfig` (`image_processing.yaml`).

## Pièges connus

- **`web` redémarre en boucle (`Cannot find module '/app/server.js'` ou `next: not found`)** : l'image locale `mangadex-web` est la version prod, par exemple après un `docker compose -f compose.yaml up`. Lance `make build install-web`.
- **Caddyfile modifié** : `docker compose restart proxy`. Caddy ne recharge pas sa config tout seul.
