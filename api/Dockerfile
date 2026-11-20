# syntax=docker/dockerfile:1

# Image PHP de l'API : FrankenPHP (serveur Caddy + PHP 8.5 embarqué).
# Stages :
#   frankenphp_base : commun (extensions, config PHP, utilisateur non-root)
#   frankenphp_dev  : dev (php.ini-development, Xdebug, code monté en volume)
#   frankenphp_prod : prod (php.ini-production, code + vendor copiés dans l'image)

FROM dunglas/frankenphp:1-php8.5 AS frankenphp_upstream

# ---------------------------------------------------------------------------
FROM frankenphp_upstream AS frankenphp_base

WORKDIR /app

# git/unzip : nécessaires à Composer pour installer certains paquets.
RUN apt-get update \
	&& apt-get install -y --no-install-recommends git unzip \
	&& rm -rf /var/lib/apt/lists/*

# Extensions PHP (OPcache est intégré à PHP depuis la 8.5).
RUN set -eux; \
	install-php-extensions \
		@composer \
		apcu \
		intl \
		pdo_pgsql \
		zip \
	;

# Config PHP propre à l'appli, chargée en plus de la config par défaut.
ENV PHP_INI_SCAN_DIR=":$PHP_INI_DIR/app.conf.d"
COPY --link frankenphp/conf.d/10-app.ini $PHP_INI_DIR/app.conf.d/

# FrankenPHP écoute en HTTP simple sur un port non privilégié :
# le TLS et le routage sont gérés par le service `proxy`.
ENV SERVER_NAME=:8080
EXPOSE 8080

# Utilisateur non-root. UID/GID alignés sur l'utilisateur de l'hôte en dev
# pour que les fichiers créés dans le volume (var/, vendor/) lui appartiennent.
ARG UID=1000
ARG GID=1000
RUN groupadd --gid "$GID" app \
	&& useradd --uid "$UID" --gid "$GID" --create-home app \
	&& chown -R app:app /app /config/caddy /data/caddy

COPY --link --chmod=755 frankenphp/docker-entrypoint.sh /usr/local/bin/docker-entrypoint

# API d'admin Caddy (localhost uniquement) : répond tant que le serveur tourne.
HEALTHCHECK --start-period=60s CMD curl -fsS http://localhost:2019/config/ > /dev/null || exit 1

ENTRYPOINT ["docker-entrypoint"]
CMD ["frankenphp", "run", "--config", "/etc/frankenphp/Caddyfile", "--adapter", "caddyfile"]

# ---------------------------------------------------------------------------
FROM frankenphp_base AS frankenphp_dev

ENV APP_ENV=dev
# Xdebug installé mais désactivé par défaut (XDEBUG_MODE=debug pour l'activer).
ENV XDEBUG_MODE=off

RUN mv "$PHP_INI_DIR/php.ini-development" "$PHP_INI_DIR/php.ini"

RUN set -eux; install-php-extensions xdebug

COPY --link frankenphp/conf.d/20-app.dev.ini $PHP_INI_DIR/app.conf.d/

USER app

# ---------------------------------------------------------------------------
FROM frankenphp_base AS frankenphp_prod

ENV APP_ENV=prod

RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

COPY --link frankenphp/conf.d/20-app.prod.ini $PHP_INI_DIR/app.conf.d/

USER app

# Dépendances d'abord : cette couche reste en cache tant que composer.* ne change pas.
COPY --chown=app:app composer.json composer.lock symfony.lock ./
RUN composer install --no-cache --prefer-dist --no-dev --no-autoloader --no-scripts --no-progress

COPY --chown=app:app . ./

RUN set -eux; \
	mkdir -p var/cache var/log var/share; \
	composer dump-autoload --classmap-authoritative --no-dev; \
	composer dump-env prod; \
	composer run-script --no-dev post-install-cmd; \
	chmod +x bin/console; \
	sync;
