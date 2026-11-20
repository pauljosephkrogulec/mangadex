#!/bin/sh
# Exécute une commande dans le conteneur `php` (PHP 8.5 n'a pas besoin d'être installé sur l'hôte).
# Le répertoire de travail du conteneur est /app = api/ : passer des chemins relatifs à api/.
#   scripts/docker-php.sh vendor/bin/phpstan analyse src
# Réutilise le conteneur si la stack tourne, sinon en lance un temporaire (sans la base).
set -e

if [ -n "$(docker compose ps --status running --quiet php 2>/dev/null)" ]; then
	exec docker compose exec -T php "$@"
fi

exec docker compose run --rm --no-deps -T php "$@"
