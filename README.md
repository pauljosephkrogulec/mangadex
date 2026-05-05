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
manga-reader/
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

## Available Commands

| Command | Description |
|---------|-------------|
| `make up` | Start all containers |
| `make down` | Stop all containers |
| `make build` | Rebuild containers |
| `make restart` | Restart all containers |
| `make logs` | View all logs |
| `make logs-frontend` | View frontend logs |
| `make logs-backend` | View backend logs |
| `make shell-frontend` | Access frontend container shell |
| `make shell-backend` | Access backend container shell |
| `make shell-db` | Access PostgreSQL shell |
| `make migrate` | Run database migrations |
| `make migrate-diff` | Generate new migration |
| `make fresh` | Reset everything and start fresh |
| `make install-frontend` | Install frontend dependencies |
| `make install-backend` | Install backend dependencies |
| `make lint-frontend` | Run frontend linter |
| `make test-backend` | Run backend tests |
| `make test-frontend` | Run frontend tests |
| `make test-frontend-coverage` | Run frontend tests with coverage |
| `make clear-cache` | Clear backend cache |

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

### Running Tests

```bash
make test-coverage
```

This runs both backend and frontend tests with coverage reports:
- **Backend**: PHPUnit with PCOV coverage driver
- **Frontend**: Jest with coverage thresholds (90%)

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
