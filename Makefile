.PHONY: up down build restart logs logs-backend logs-frontend shell-backend shell-frontend shell-db lint lint-backend lint-frontend fix-backend fix-frontend migrate migrate-diff migrate-rollback install-backend install-frontend test-backend test-backend-coverage test-frontend test-frontend-coverage clear-cache cleanup-files cleanup-files-dry-run fresh setup fixtures

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
	$(MAKE) fixtures
	$(MAKE) generate-covers

setup: fresh
	@echo "Setup complete! Visit http://localhost:8080"

fixtures:
	docker compose exec backend php bin/console doctrine:fixtures:load --no-interaction

generate-covers:
	docker compose exec backend php -r '
	require "/app/vendor/autoload.php";
	$$kernel = new \App\Kernel("dev", true);
	$$kernel->boot();
	$$em = $$kernel->getContainer()->get("doctrine.orm.entity_manager");
	$$mangas = $$em->getRepository(\App\Entity\Manga::class)->findAll();
	$$covers = $$em->getRepository(\App\Entity\CoverArt::class)->findBy(["isPrimary" => true]);
	$$uploadDir = "/app/public/uploads";
	$$srand = 42;
	foreach ($$covers as $$cover) {
	    $$manga = $$cover->getManga();
	    $$title = $$manga->getTitle();
	    $$vol = $$cover->getVolume() ?? "Vol 1";
	    $$path = $$cover->getImagePath();
	    $$fullPath = $$uploadDir . "/" . ltrim($$path, "/");
	    $$dir = dirname($$fullPath);
	    if (!is_dir($$dir)) mkdir($$dir, 0777, true);
	    $$hash = crc32($$title);
	    $$r = (($$hash >> 16) & 0xFF) % 200 + 30;
	    $$g = (($$hash >> 8) & 0xFF) % 200 + 30;
	    $$b = ($$hash & 0xFF) % 200 + 30;
	    $$im = imagecreatetruecolor(400, 600);
	    $$bg = imagecolorallocate($$im, $$r, $$g, $$b);
	    imagefill($$im, 0, 0, $$bg);
	    $$light = imagecolorallocate($$im, 255, 255, 255);
	    imagefilledrectangle($$im, 40, 200, 360, 260, imagecolorallocatealpha($$im, 0, 0, 0, 60));
	    $$truncated = mb_strlen($$title) > 30 ? mb_substr($$title, 0, 27) . "..." : $$title;
	    imagestring($$im, 5, 60, 215, $$truncated, $$light);
	    imagestring($$im, 3, 60, 245, $$vol, $$light);
	    imagepng($$im, $$fullPath);
	    imagedestroy($$im);
	    echo "Created: $$path\n";
	}
	'

test-backend:
	-docker compose exec backend rm -f var/test.db
	docker compose exec backend bash -c 'DATABASE_URL="sqlite:///$$(pwd)/var/test.db" php bin/console doctrine:schema:create --env=test --no-interaction'
	docker compose exec backend php bin/phpunit
	-docker compose exec backend rm -f var/test.db

test-backend-coverage:
	docker compose exec backend php bin/phpunit --coverage-html coverage/html --coverage-text

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
