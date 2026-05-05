.PHONY: up down build restart logs-backend migrate fresh setup

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

install-backend:
	docker compose exec backend composer install

fresh:
	docker compose down -v
	docker compose up -d --build
	docker compose exec backend php bin/console doctrine:migrations:migrate --no-interaction

setup: fresh
	@echo "Setup complete! Visit http://localhost"

test-backend:
	docker compose exec backend php bin/phpunit

clear-cache:
	docker compose exec backend php bin/console cache:clear
