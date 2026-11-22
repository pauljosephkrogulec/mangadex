#!/bin/sh
# Exécute une commande dans le conteneur `php` (PHP 8.5 n'a pas besoin d'être installé sur l'hôte).
# Le répertoire de travail du conteneur est /app = api/ : passer des chemins relatifs à api/.
#   scripts/docker-php.sh vendor/bin/phpstan analyse src
# Utilisé par les hooks Git (lefthook.yml) et le Makefile. Voir scripts/compose-exec.sh.
exec "$(dirname "$0")/compose-exec.sh" php "$@"
