# Manga Reader

A manga reading platform inspired by [MangaDex](https://mangadex.org), built with modern web technologies.

## Tech Stack

### Frontend
- **Next.js 16** with React 19
- **TypeScript**
- **Tailwind CSS 4**
- **App Router** architecture

### Backend
- **Symfony 8.0** (PHP 8.4+)
- **API Platform 4.3** for REST API
- **Doctrine ORM** with PostgreSQL
- **Lexik JWT Authentication Bundle** for JWT auth
- **Nelmio CORS Bundle** for cross-origin requests

### Infrastructure
- **PostgreSQL 15** database
- **Nginx** reverse proxy
- **Docker Compose** for containerization

## Project Structure

```
mangadex/
├── backend/          # Symfony API backend
├── frontend/         # Next.js frontend
├── nginx/            # Nginx configuration
├── docker-compose.yml
├── Makefile
└── README.md
```

## Prerequisites

- Docker & Docker Compose
- Make (optional, for using Makefile commands)

## Getting Started

### Quick Start (using Make)

```bash
make setup
```

This will:
1. Build and start all containers
2. Run database migrations
3. Make the app available at http://localhost

### Manual Start

```bash
docker compose up -d --build
docker compose exec backend php bin/console doctrine:migrations:migrate --no-interaction
```

Then visit http://localhost in your browser.

### Database Migrations

The database schema is managed via a single Doctrine migration that creates all tables from the entity mappings:

```bash
# Generate migration from entities (run after changing entities)
make migrate-diff

# Apply pending migrations
make migrate

# Reset database and start fresh (drops all data)
make fresh
```

## Available Commands

| Command | Description |
|---------|-------------|
| `make up` | Start all containers |
| `make down` | Stop all containers |
| `make build` | Rebuild containers |
| `make restart` | Restart all containers |
| `make logs` | View all logs |
| `make logs-backend` | View backend logs |
| `make logs-frontend` | View frontend logs |
| `make shell-backend` | Access backend container shell |
| `make shell-frontend` | Access frontend container shell |
| `make shell-db` | Access PostgreSQL shell |
| `make migrate` | Run database migrations |
| `make migrate-diff` | Generate new migration from entities |
| `make migrate-rollback` | Rollback last migration |
| `make fresh` | Reset everything and start fresh |
| `make install-backend` | Install backend dependencies |
| `make install-frontend` | Install frontend dependencies |
| `make lint` | Run linter on backend and frontend |
| `make lint-backend` | Run PHP-CS-Fixer on backend (dry-run) |
| `make lint-frontend` | Run ESLint on frontend |
| `make fix-backend` | Auto-fix backend code style |
| `make fix-frontend` | Auto-fix frontend code style |
| `make test-backend` | Run backend tests |
| `make clear-cache` | Clear backend cache |
| `make cleanup-files` | Remove orphaned upload files |
| `make cleanup-files-dry-run` | List orphaned files without deleting |

## File Cleanup

Orphaned files in `uploads/` (files not referenced in the database) are handled automatically:

- **Automatic**: When a `CoverArt` or `Chapter` entity is deleted, files are automatically removed via Doctrine event subscriber
- **Manual cleanup**: Use the CLI command to scan and remove orphaned files

### CLI Command

```bash
# List orphaned files without deleting (dry run)
docker compose exec backend php bin/console app:cleanup-orphaned-files --dry-run

# Delete orphaned files
docker compose exec backend php bin/console app:cleanup-orphaned-files

# Clean only covers or chapters
docker compose exec backend php bin/console app:cleanup-orphaned-files --covers-only
docker compose exec backend php bin/console app:cleanup-orphaned-files --chapters-only
```

**Schedule daily cleanup** (add to crontab):
```cron
0 2 * * * cd /path/to/mangadex && docker compose exec -T backend php bin/console app:cleanup-orphaned-files --no-interaction
```

## Testing

### Linting

```bash
# Lint both backend and frontend
make lint

# Lint backend only (PHP-CS-Fixer)
make lint-backend

# Lint frontend only (ESLint)
make lint-frontend

# Auto-fix backend code style (PHP-CS-Fixer)
make fix-backend

# Auto-fix frontend code style (ESLint)
make fix-frontend
```

### Running Tests

```bash
# Backend tests
make test-backend

# Frontend tests (from frontend directory)
cd frontend && npm test

# Frontend tests with coverage
cd frontend && npm run test:coverage
```

### Test Coverage

| Component | Coverage |
|-----------|----------|
| Backend (JwtAuthService) | 100% lines, 100% methods |
| Frontend (auth.ts, LoginForm.tsx) | 100% branches, 100% lines |

### Fixtures

Backend uses `doctrine/doctrine-fixtures-bundle` for test fixtures:
- `src/DataFixtures/AppFixtures.php` - Minimal fixture (JWT auth uses in-memory user)

## API Documentation

Once the backend is running, you can access the API documentation at:
- Swagger UI: http://localhost/api/docs
- API Platform: http://localhost/api

## JWT Authentication

The API uses JWT (JSON Web Token) authentication for secure access to protected endpoints.

### Login

Send a POST request to `/api/login_check` with email and password:

```bash
curl -X POST http://localhost/api/login_check \
  -H "Content-Type: application/json" \
  -d '{"email":"test@example.com","password":"password123"}'
```

Response:
```json
{"token":"eyJ0eXAiOiJKV1QiLCJhbGc..."}
```

### Using the Token

Include the token in the `Authorization` header for protected endpoints:

```bash
curl -X GET http://localhost/api \
  -H "Authorization: Bearer YOUR_JWT_TOKEN"
```

### Frontend Integration

The frontend includes a login form component at `frontend/src/components/auth/LoginForm.tsx` and authentication utilities at `frontend/src/lib/auth.ts`.

## Environment Variables

### Frontend (.env.local)
```
NEXT_PUBLIC_API_URL=http://localhost/api
```

### Backend
Configured via Docker Compose:
```
DATABASE_URL=postgresql://app:app@db:5432/manga_reader?serverVersion=15&charset=utf8
```

## Development

### Frontend Development
The frontend runs in development mode with hot reload enabled. Changes to files in `frontend/src/` will automatically refresh the browser.

### Backend Development
The backend source code is mounted as a volume, so changes are reflected immediately. Use `make shell-backend` to run Symfony commands.

### Database
- Default credentials: `app` / `app`
- Database name: `manga_reader`
- Port: `5432` (exposed to host)

## License

Proprietary
