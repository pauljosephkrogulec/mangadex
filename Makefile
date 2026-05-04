.PHONY: up down build restart logs shell-frontend shell-backend migrate fresh setup

up:
	docker compose up -d

down:
	docker compose down

build:
	docker compose build

restart: down up

logs:
	docker compose logs -f

logs-frontend:
	docker compose logs -f frontend

logs-backend:
	docker compose logs -f backend

shell-frontend:
	docker compose exec frontend sh

shell-backend:
	docker compose exec backend sh

shell-db:
	docker compose exec db psql -U app -d manga_reader

migrate:
	docker compose exec backend php bin/console doctrine:migrations:migrate --no-interaction

migrate-diff:
	docker compose exec backend php bin/console doctrine:migrations:diff

migrate-rollback:
	docker compose exec backend php bin/console doctrine:migrations:rollback

install-frontend:
	docker compose exec frontend npm install

install-backend:
	docker compose exec backend composer install

fresh:
	docker compose down -v
	docker compose up -d --build
	docker compose exec backend php bin/console doctrine:migrations:migrate --no-interaction

setup: fresh
	@echo "Setup complete! Visit http://localhost"

lint-frontend:
	docker compose exec frontend npm run lint

build-frontend:
	docker compose exec frontend npm run build

start-frontend:
	docker compose exec frontend npm run start

test-backend:
	docker compose exec backend php bin/phpunit

test-frontend:
	docker compose exec frontend npm run test

test-frontend-coverage:
	docker compose exec frontend npm run test:coverage

test-coverage:
	docker compose exec backend php bin/phpunit --coverage-text
	docker compose exec frontend npm run test:coverage

clear-cache:
	docker compose exec backend php bin/console cache:clear
