#!/bin/sh
# Exécute une commande dans un service Docker Compose (php, web…), sans rien installer sur l'hôte.
#   scripts/compose-exec.sh web npm run lint
# Réutilise le conteneur si le service tourne, sinon en lance un temporaire (sans ses dépendances).
# Le répertoire de travail est celui du conteneur (/app = api/ pour php, web/ pour web).
set -e

if [ "$#" -lt 2 ]; then
	echo "Usage : $0 <service> <commande> [arguments...]" >&2
	exit 64
fi

service=$1
shift

if [ -n "$(docker compose ps --status running --quiet "$service" 2>/dev/null)" ]; then
	exec docker compose exec -T "$service" "$@"
fi

exec docker compose run --rm --no-deps -T "$service" "$@"
