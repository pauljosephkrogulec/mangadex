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

# Image Playwright alignée sur la version de @playwright/test (web/package.json) : navigateurs inclus.
PLAYWRIGHT_IMAGE := mcr.microsoft.com/playwright:v$(shell sed -n 's/.*"@playwright\/test": "\([0-9.]*\)".*/\1/p' web/package.json)-noble

##@ Aide

.PHONY: help
help: ## Affiche cette aide
	@awk 'BEGIN {FS = ":.*## "} \
		/^##@/ { printf "\n\033[1m%s\033[0m\n", substr($$0, 5) } \
		/^[a-zA-Z0-9_-]+:.*## / { printf "  \033[36m%-18s\033[0m %s\n", $$1, $$2 }' $(MAKEFILE_LIST)
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

# Configs : api/.php-cs-fixer.dist.php, api/phpstan.dist.neon, api/rector.php (#17).
# Le conteneur compilé (var/cache/dev) est lu par PHPStan et Rector : on le génère d'abord.
lint-api: ## Composer (validate + audit), PHP-CS-Fixer, PHPStan (max), Rector (dry-run), conteneur Symfony
	$(PHP) composer validate --strict --no-check-publish
	$(PHP) composer audit
	$(CONSOLE) cache:warmup --env=dev --quiet
	$(PHP) vendor/bin/php-cs-fixer fix --dry-run --diff
	$(PHP) vendor/bin/phpstan analyse --no-progress --memory-limit=1G
	$(PHP) vendor/bin/rector process --dry-run --no-progress-bar
	$(CONSOLE) lint:container
	$(CONSOLE) lint:yaml config --parse-tags

lint-web: ## ESLint, Prettier (check) et TypeScript
	$(WEB) npm run lint
	$(WEB) npm run format:check
	$(WEB) npm run typecheck

fix: fix-api fix-web ## Corrige automatiquement (Rector, PHP-CS-Fixer, ESLint, Prettier)

# Rector d'abord : ses changements sont ensuite remis au style par PHP-CS-Fixer.
fix-api: ## Rector puis PHP-CS-Fixer
	$(CONSOLE) cache:warmup --env=dev --quiet
	$(PHP) vendor/bin/rector process --no-progress-bar
	$(PHP) vendor/bin/php-cs-fixer fix

fix-web: ## ESLint --fix puis Prettier
	$(WEB) npm run lint -- --fix
	$(WEB) npm run format

##@ Tests

.PHONY: test test-api test-web test-web-coverage e2e

test: test-api test-web ## Lance tous les tests (hors E2E)

# La base de test (app_test, cf. dbname_suffix) est créée et migrée avant PHPUnit, comme en CI.
test-api: ## PHPUnit (prépare la base de test)
	$(COMPOSE) up -d --wait database
	$(CONSOLE) --env=test doctrine:database:create --if-not-exists --no-interaction
	$(CONSOLE) --env=test doctrine:migrations:migrate --no-interaction --allow-no-migration
	$(PHP) vendor/bin/phpunit

test-web: ## Vitest
	$(WEB) npm test

test-web-coverage: ## Vitest avec couverture (échoue sous 80 %, rapport dans web/coverage/)
	$(WEB) npm run test:coverage

# Playwright tourne dans son image officielle (navigateurs inclus), sur le réseau de la stack :
# rien à installer sur l'hôte. node_modules vit dans un volume nommé (Linux, réutilisé entre runs).
# Les rapports (web/playwright-report, web/test-results) sont rendus à l'utilisateur de l'hôte.
e2e: up ## Tests E2E Playwright contre la stack (options : `make e2e args="--grep proxy"`)
	docker run --rm --init \
		--network mangadex_default \
		-v "$(CURDIR)/web:/app" -v mangadex_playwright_node_modules:/app/node_modules -w /app \
		-e E2E_BASE_URL=http://proxy \
		$(PLAYWRIGHT_IMAGE) \
		sh -c 'npm ci --no-audit --no-fund --loglevel=error && npx playwright test $(args); status=$$?; \
			chown -R $(shell id -u):$(shell id -g) playwright-report test-results 2>/dev/null; exit $$status'

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
