# syntax=docker/dockerfile:1

# Image PHP de l'API : FrankenPHP (serveur Caddy + PHP 8.5 embarqué).
# Stages :
#   frankenphp_base  : commun à l'exécution (extensions, config PHP, utilisateur non-root, healthcheck)
#   composer_tools   : base + Composer, git, unzip (outils de build, absents de l'image de prod)
#   frankenphp_dev   : dev (php.ini-development, Xdebug, code monté en volume)
#   frankenphp_build : installe vendor/, compile le cache Symfony et le fichier de preload
#   frankenphp_prod  : prod (php.ini-production, OPcache + preload), ne reçoit que /app du build

FROM dunglas/frankenphp:1-php8.5 AS frankenphp_upstream

# ---------------------------------------------------------------------------
FROM frankenphp_upstream AS frankenphp_base

WORKDIR /app

# Extensions PHP (OPcache est intégré à PHP depuis la 8.5).
RUN set -eux; \
	install-php-extensions \
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
# `setcap -r` : l'image upstream donne au binaire la capability NET_BIND_SERVICE (écoute sur 80/443
# en non-root). Inutile ici (port 8080) et bloquant sur les plateformes qui retirent cette capability
# aux conteneurs (Render) : le noyau refuse alors de lancer le binaire (« Operation not permitted »).
ARG UID=1000
ARG GID=1000
RUN groupadd --gid "$GID" app \
	&& useradd --uid "$UID" --gid "$GID" --create-home app \
	&& chown -R app:app /app /config/caddy /data/caddy \
	&& setcap -r /usr/local/bin/frankenphp

COPY --link --chmod=755 frankenphp/docker-entrypoint.sh /usr/local/bin/docker-entrypoint

# Sonde applicative (HealthController) : vérifie que Caddy *et* PHP/Symfony répondent,
# sans toucher à la base. start-period large : en dev, le 1er démarrage lance `composer install`.
HEALTHCHECK --start-period=120s --start-interval=2s --interval=30s --timeout=5s --retries=3 \
	CMD curl -fsS http://localhost:8080/api/health > /dev/null || exit 1

ENTRYPOINT ["docker-entrypoint"]
CMD ["frankenphp", "run", "--config", "/etc/frankenphp/Caddyfile", "--adapter", "caddyfile"]

# ---------------------------------------------------------------------------
FROM frankenphp_base AS composer_tools

# git/unzip : nécessaires à Composer pour installer certains paquets.
RUN apt-get update \
	&& apt-get install -y --no-install-recommends git unzip \
	&& rm -rf /var/lib/apt/lists/*

COPY --link --from=composer/composer:2-bin /composer /usr/local/bin/composer

# ---------------------------------------------------------------------------
FROM composer_tools AS frankenphp_dev

ENV APP_ENV=dev
# Xdebug installé mais désactivé par défaut (XDEBUG_MODE=debug pour l'activer).
ENV XDEBUG_MODE=off

RUN mv "$PHP_INI_DIR/php.ini-development" "$PHP_INI_DIR/php.ini"

RUN set -eux; install-php-extensions xdebug

COPY --link frankenphp/conf.d/20-app.dev.ini $PHP_INI_DIR/app.conf.d/

USER app

# ---------------------------------------------------------------------------
FROM composer_tools AS frankenphp_build

ENV APP_ENV=prod

# Même php.ini que la prod : le cache Symfony est compilé dans les conditions d'exécution.
RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

USER app

# Dépendances d'abord : cette couche reste en cache tant que composer.* ne change pas.
COPY --chown=app:app composer.json composer.lock symfony.lock ./
RUN composer install --no-cache --prefer-dist --no-dev --no-autoloader --no-scripts --no-progress

COPY --chown=app:app . ./

# - classmap autoritaire : pas de recherche de fichier à l'exécution ;
# - dump-env : .env* compilés en .env.local.php (plus de parsing à chaque requête) ;
# - post-install-cmd : cache:clear + warmup, qui génère var/cache/prod/*.preload.php (lu par config/preload.php).
RUN set -eux; \
	mkdir -p var/cache var/log var/share; \
	composer dump-autoload --classmap-authoritative --no-dev; \
	composer dump-env prod; \
	composer run-script --no-dev post-install-cmd; \
	test -n "$(ls var/cache/prod/*.preload.php)"; \
	chmod +x bin/console;

# ---------------------------------------------------------------------------
FROM frankenphp_base AS frankenphp_prod

ENV APP_ENV=prod

RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

COPY --link frankenphp/conf.d/20-app.prod.ini $PHP_INI_DIR/app.conf.d/

# Code, vendor/ et cache compilé uniquement : ni Composer, ni git, ni sources de build.
COPY --from=frankenphp_build --chown=app:app /app /app

USER app

# Version déployée, exposée par GET /api/health (commit pour le staging, tag pour la prod).
# Déclarée en dernier : changer de version n'invalide pas le cache des couches précédentes.
ARG APP_VERSION=dev
ENV APP_VERSION=${APP_VERSION}
