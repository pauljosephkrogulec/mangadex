#!/bin/sh
set -e

# Premier argument de type option (ex. `--config`) : on lance FrankenPHP avec.
if [ "${1#-}" != "$1" ]; then
	set -- frankenphp run "$@"
fi

if [ "$1" = 'frankenphp' ] || [ "$1" = 'php' ] || [ "$1" = 'bin/console' ]; then
	# En dev, le code est monté depuis l'hôte : installe les dépendances si absentes.
	if [ ! -f vendor/autoload_runtime.php ]; then
		composer install --prefer-dist --no-progress --no-interaction
	fi
fi

exec docker-php-entrypoint "$@"
