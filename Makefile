.PHONY: up down build restart logs logs-backend logs-frontend shell-backend shell-frontend shell-db lint lint-backend lint-frontend fix-backend fix-frontend migrate migrate-diff migrate-rollback install-backend install-frontend test-backend test-frontend test-frontend-coverage clear-cache cleanup-files cleanup-files-dry-run fresh setup

up:
	docker compose up -d

down:
	docker compose down

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
	docker compose up -d --build
	docker compose exec backend php bin/console doctrine:migrations:migrate --no-interaction

setup: fresh
	@echo "Setup complete! Visit http://localhost"

test-backend:
	docker compose exec backend php bin/phpunit

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
