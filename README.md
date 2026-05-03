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
| `make clear-cache` | Clear backend cache |

## API Documentation

Once the backend is running, you can access the API documentation at:
- Swagger UI: http://localhost/api/docs
- API Platform: http://localhost/api

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
