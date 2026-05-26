SENTRY_DIR ?= $(HOME)/sentry-self-hosted

.PHONY: up down build restart logs logs-backend logs-frontend shell-backend shell-frontend shell-db lint lint-backend lint-frontend fix-backend fix-frontend migrate migrate-diff migrate-rollback install-backend install-frontend test-backend test-backend-coverage test-frontend test-frontend-coverage clear-cache cleanup-files cleanup-files-dry-run fresh setup fixtures fixtures-users generate-fixtures-data fix lighthouse sentry-up sentry-down

sentry-up:
	@if [ -d "$(SENTRY_DIR)" ]; then \
		echo "Starting Sentry self-hosted..."; \
		docker compose -f $(SENTRY_DIR)/docker-compose.yml up -d; \
	else \
		echo "Sentry not installed. Run: bash sentry/setup.sh"; \
	fi

sentry-down:
	@if [ -d "$(SENTRY_DIR)" ]; then \
		docker compose -f $(SENTRY_DIR)/docker-compose.yml down; \
	fi

up: sentry-up
	docker compose up -d

down:
	docker compose down
	$(MAKE) sentry-down

build:
	docker compose build

restart: down up

logs:
	docker compose logs -f

logs-backend:
	docker compose logs -f backend

logs-frontend:
	docker compose logs -f frontend

shell-backend:
	docker compose exec backend sh

shell-frontend:
	docker compose exec frontend sh

shell-db:
	docker compose exec db psql -U app -d manga_reader

migrate:
	docker compose exec backend php bin/console doctrine:migrations:migrate --no-interaction

migrate-diff:
	docker compose exec backend php bin/console doctrine:migrations:diff

migrate-rollback:
	docker compose exec backend php bin/console doctrine:migrations:rollback

install-backend:
	docker compose exec backend composer install

install-frontend:
	docker compose exec frontend npm install

fresh:
	docker compose down -v
	$(MAKE) sentry-up
	docker compose up -d --build
	docker compose exec backend php bin/console doctrine:migrations:migrate --no-interaction
	$(MAKE) fixtures

setup: fresh
	@echo "Setup complete! Visit http://localhost:8080"

fixtures:
	docker compose exec backend php bin/console doctrine:fixtures:load --no-interaction

fixtures-users:
	docker compose exec backend php bin/console doctrine:fixtures:load --group=users --no-interaction

generate-fixtures-data:
	docker compose exec backend php bin/console app:generate-fixtures-data

test-backend:
	-docker compose exec backend rm -f var/test.db
	docker compose exec backend bash -c 'DATABASE_URL="sqlite:///$$(pwd)/var/test.db" php bin/console doctrine:schema:create --env=test --no-interaction'
	docker compose exec backend php bin/phpunit
	-docker compose exec backend rm -f var/test.db

test-backend-coverage:
	-docker compose exec backend rm -f var/test.db
	docker compose exec backend bash -c 'DATABASE_URL="sqlite:///$$(pwd)/var/test.db" php bin/console doctrine:schema:create --env=test --no-interaction'
	docker compose exec backend php bin/phpunit --coverage-text
	-docker compose exec backend rm -f var/test.db

lint: lint-backend lint-frontend

lint-backend:
	docker compose exec backend php vendor/bin/php-cs-fixer fix --dry-run --diff

lint-frontend:
	docker compose exec frontend npm run lint

fix: fix-backend fix-frontend

fix-frontend:
	docker compose exec frontend npm run lint -- --fix

fix-backend:
	docker compose exec backend php vendor/bin/php-cs-fixer fix

test-frontend:
	docker compose exec frontend npm test

test-frontend-coverage:
	docker compose exec frontend npm run test:coverage

clear-cache:
	docker compose exec backend php bin/console cache:clear

cleanup-files:
	docker compose exec backend php bin/console app:cleanup-orphaned-files --no-interaction

cleanup-files-dry-run:
	docker compose exec backend php bin/console app:cleanup-orphaned-files --dry-run

lighthouse:
	@echo "Building production frontend bundle..."
	docker compose exec -e NODE_ENV=production frontend npm run build
	@echo "Temporarily switching frontend to production mode..."
	python3 -c "\
import re; f=open('docker-compose.override.yml','r+'); c=f.read();\
c=c.replace('npm install && npm run dev','npm run start');\
c=c.replace('NODE_ENV=development','NODE_ENV=production');\
f.seek(0); f.write(c); f.truncate(); f.close()"
	docker compose up -d frontend
	@sleep 10
	@echo "Running Lighthouse audit against http://localhost:8080 ..."
	@npx --yes lighthouse http://localhost:8080 \
		--chrome-flags="--headless --no-sandbox --disable-dev-shm-usage" \
		--only-categories=performance,accessibility,best-practices,seo \
		--output=json --quiet 2>/dev/null | python3 -c "\
import json,sys; d=json.load(sys.stdin); cats=d['categories'];\
[print(f'{k}: {round(v[\"score\"]*100)}') for k,v in cats.items()]"
	@echo "Restoring dev server..."
	python3 -c "\
import re; f=open('docker-compose.override.yml','r+'); c=f.read();\
c=c.replace('npm run start','npm install && npm run dev');\
c=c.replace('NODE_ENV=production','NODE_ENV=development');\
f.seek(0); f.write(c); f.truncate(); f.close()"
	docker compose up -d frontend
