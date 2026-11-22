# Raccourcis de développement (issue #16). `make` ou `make help` liste les cibles.
# Tout s'exécute dans les conteneurs Docker : seuls Docker, make et Node (hooks Git) sont requis sur l'hôte.

.DEFAULT_GOAL := help
SHELL := /bin/sh

COMPOSE := docker compose
PHP     := scripts/compose-exec.sh php
CONSOLE := $(PHP) bin/console
WEB     := scripts/compose-exec.sh web

# Environnement Symfony ciblé par les commandes de base de données : `make db-reset ENV=test`.
ENV ?= dev

##@ Aide

.PHONY: help
help: ## Affiche cette aide
	@awk 'BEGIN {FS = ":.*## "} \
		/^##@/ { printf "\n\033[1m%s\033[0m\n", substr($$0, 5) } \
		/^[a-zA-Z0-9_-]+:.*## / { printf "  \033[36m%-14s\033[0m %s\n", $$1, $$2 }' $(MAKEFILE_LIST)
	@echo

##@ Stack Docker

.PHONY: build up down restart ps logs sh

build: ## Construit les images de dev
	$(COMPOSE) build --pull

up: ## Démarre la stack et attend que les services soient sains
	$(COMPOSE) up -d --wait

down: ## Arrête la stack (les données sont conservées)
	$(COMPOSE) down --remove-orphans

restart: down up ## Redémarre la stack

ps: ## État des services
	$(COMPOSE) ps -a

logs: ## Suit les logs (un service : `make logs s=php`)
	$(COMPOSE) logs -f --tail=100 $(s)

sh: ## Ouvre un shell dans un service (défaut : php ; `make sh s=web`)
	$(COMPOSE) exec $(or $(s),php) sh

##@ Installation

.PHONY: install install-api install-web install-hooks

install: build install-hooks up install-api install-web db-migrate ## Installe tout : images, dépendances, hooks Git, base

install-api: ## Installe les dépendances Composer
	$(PHP) composer install --prefer-dist --no-interaction

# node_modules du front vit dans un volume anonyme initialisé depuis l'image :
# après un changement de package-lock.json, on le recrée à partir de l'image reconstruite.
install-web: ## Réinstalle node_modules du front depuis l'image
	$(COMPOSE) up -d --wait --renew-anon-volumes web

install-hooks: ## Installe les hooks Git (lefthook + commitlint, sur l'hôte)
	npm ci

##@ Qualité

.PHONY: lint lint-api lint-web fix fix-api fix-web

lint: lint-api lint-web ## Lance tous les linters (sans rien modifier)

lint-api: ## PHP-CS-Fixer (dry-run), PHPStan, validation Composer et conteneur Symfony
	$(PHP) composer validate --strict --no-check-publish
	$(PHP) vendor/bin/php-cs-fixer fix --config=.php-cs-fixer.dist.php --dry-run --diff
	$(PHP) vendor/bin/phpstan analyse --no-progress --memory-limit=1G src tests
	$(CONSOLE) lint:container
	$(CONSOLE) lint:yaml config --parse-tags

lint-web: ## ESLint, Prettier (check) et TypeScript
	$(WEB) npm run lint
	$(WEB) npm run format:check
	$(WEB) npm run typecheck

fix: fix-api fix-web ## Corrige automatiquement le style (PHP-CS-Fixer, ESLint, Prettier)

fix-api: ## PHP-CS-Fixer
	$(PHP) vendor/bin/php-cs-fixer fix --config=.php-cs-fixer.dist.php

fix-web: ## ESLint --fix puis Prettier
	$(WEB) npm run lint -- --fix
	$(WEB) npm run format

##@ Tests

.PHONY: test test-api test-web

test: test-api test-web ## Lance tous les tests

# La base de test (app_test, cf. dbname_suffix) est créée et migrée avant PHPUnit, comme en CI.
test-api: ## PHPUnit (prépare la base de test)
	$(COMPOSE) up -d --wait database
	$(CONSOLE) --env=test doctrine:database:create --if-not-exists --no-interaction
	$(CONSOLE) --env=test doctrine:migrations:migrate --no-interaction --allow-no-migration
	$(PHP) vendor/bin/phpunit

test-web: ## Vitest
	$(WEB) npm test

##@ Base de données

.PHONY: db-migrate db-reset

db-migrate: ## Applique les migrations Doctrine (`ENV=test` pour la base de test)
	$(COMPOSE) up -d --wait database
	$(CONSOLE) --env=$(ENV) doctrine:migrations:migrate --no-interaction --allow-no-migration

# Les fixtures sont chargées seulement si DoctrineFixturesBundle est installé (#21).
db-reset: ## Supprime et recrée la base, migrations + fixtures (`ENV=test` pour la base de test)
	$(COMPOSE) up -d --wait database
	$(CONSOLE) --env=$(ENV) doctrine:database:drop --force --if-exists --no-interaction
	$(CONSOLE) --env=$(ENV) doctrine:database:create --no-interaction
	$(CONSOLE) --env=$(ENV) doctrine:migrations:migrate --no-interaction --allow-no-migration
	@if $(CONSOLE) --env=$(ENV) list doctrine:fixtures >/dev/null 2>&1; then \
		$(CONSOLE) --env=$(ENV) doctrine:fixtures:load --no-interaction; \
	else \
		echo "DoctrineFixturesBundle absent : pas de fixtures à charger."; \
	fi
