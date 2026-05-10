# Manga Reader

A manga reading platform inspired by [MangaDex](https://mangadex.org), built with modern web technologies.

## Tech Stack

### Frontend
- **Next.js 16** with React 19
- **TypeScript** (strict mode)
- **Tailwind CSS 4** with custom MangaDex theme
- **Axios** for HTTP client with JWT interceptor
- **Vitest** + **Testing Library** for tests

### Backend
- **Symfony 8.0** (PHP 8.4+)
- **API Platform 4.3** for REST API (JSON-LD / Hydra)
- **Doctrine ORM 3** with PostgreSQL 15
- **Lexik JWTAuthenticationBundle** for JWT auth
- **Nelmio CORS Bundle** for cross-origin requests
- **PHP-CS-Fixer** (@Symfony rules), **PHPStan** (level max)

### Infrastructure
- **Docker Compose** for containerization
- **Nginx** reverse proxy with security headers
- **PostgreSQL 15** database

## Project Structure

```
mangadex/
├── .env                          # Environment variables (DB, secrets)
├── Makefile                      # All dev commands (docker compose wrappers)
├── docker-compose.yml            # Production service definitions
├── docker-compose.override.yml   # Dev overrides (hot reload, port 8080)
│
├── backend/                      # Symfony API application
│   ├── Dockerfile                # Multi-stage PHP 8.4 FPM build
│   ├── composer.json             # PHP dependencies
│   ├── phpunit.xml.dist          # PHPUnit config
│   ├── phpstan.neon              # PHPStan at level max
│   ├── .php-cs-fixer.dist.php    # PHP-CS-Fixer config
│   ├── openapi.yaml              # Hand-written OpenAPI 3.1 spec
│   ├── config/
│   │   ├── bundles.php           # Registered bundles
│   │   ├── services.yaml         # Service autowiring + decorators
│   │   ├── packages/             # Bundle configs (doctrine, api_platform, security, etc.)
│   │   └── routes/               # Route imports
│   ├── migrations/               # Doctrine migrations
│   ├── public/                   # Front controller + uploads
│   ├── src/
│   │   ├── Kernel.php
│   │   ├── Entity/               # Doctrine ORM entities (9)
│   │   ├── Controller/           # Custom controllers (5)
│   │   ├── EventSubscriber/      # Event subscribers (4)
│   │   ├── Service/              # Business logic services (2)
│   │   ├── State/                # API Platform providers/processors (5)
│   │   ├── Dto/                  # Data transfer objects (2)
│   │   ├── Serializer/           # Serialization context builder
│   │   ├── Command/              # CLI commands (1)
│   │   └── DataFixtures/         # Doctrine fixtures (9 + JSON data)
│   └── tests/                    # PHPUnit test suite
│
├── frontend/                     # Next.js application
│   ├── Dockerfile                # Multi-stage Node 20 Alpine build
│   ├── package.json              # JavaScript dependencies
│   ├── tsconfig.json             # TypeScript config
│   ├── next.config.ts            # Next.js config
│   ├── tailwind.config.ts        # Tailwind theme
│   ├── vitest.config.ts          # Vitest config
│   ├── eslint.config.mjs         # ESLint config
│   ├── public/                   # Static assets
│   └── src/
│       ├── app/                  # Next.js App Router
│       │   ├── layout.tsx        # Root layout (Geist font, metadata)
│       │   ├── LayoutClient.tsx  # Client layout (navbar, sidebar)
│       │   ├── page.tsx          # Home page
│       │   ├── error.tsx         # Global error boundary
│       │   ├── loading.tsx       # Root loading spinner
│       │   └── globals.css       # Tailwind + custom CSS vars
│       ├── components/           # UI components (8)
│       │   └── __tests__/        # Component tests (2 files)
│       └── lib/                  # Utilities
│           ├── api.ts            # Axios client + helpers
│           ├── types.ts          # TypeScript interfaces
│           └── __tests__/        # API client tests
│
├── nginx/
│   └── default.conf              # Reverse proxy config
└── img/
    └── brand/
        └── mangadex-logo.svg
```

## Backend Architecture

### Entities

#### Manga
The core entity. Fields: `id`, `createdAt`, `title`, `altTitles` (JSON), `description`, `status` (ongoing/completed/hiatus/cancelled), `year`, `contentRating` (safe/suggestive/erotica/pornographic), `demographic` (shounen/shoujo/josei/seinen/none). Relations: ManyToMany `Creator`, ManyToMany `Tag`, OneToMany `Chapter`, OneToMany `CoverArt`, OneToMany `MangaFollow`, ManyToMany `CustomList`.

#### Chapter
Belongs to a Manga. Fields: `id`, `createdAt`, `volume`, `chapterNumber`, `title`, `language`, `pages` (JSON array of image paths). Relations: ManyToOne `Manga`, ManyToOne `ScanlationGroup`.

#### CoverArt
Belongs to a Manga. Fields: `id`, `createdAt`, `imagePath`, `volume`, `isPrimary`. Served at `/uploads/{imagePath}` or via `/api/covers/{id}` / `/api/mangas/{id}/primary-cover`.

#### Creator
Fields: `id`, `createdAt`, `name`, `type` (author/artist). ManyToMany with Manga.

#### Tag
Fields: `id`, `createdAt`, `name`, `description`, `groupName` (genre/theme/format), `isPrimary`. ManyToMany with Manga.

#### ScanlationGroup
Fields: `id`, `createdAt`, `name` (unique), `website`. OneToMany with Chapter.

#### CustomList
User-owned named manga lists. Fields: `id`, `createdAt`, `name`, `visibility` (public/private/hidden). Relations: ManyToOne `User`, ManyToMany `Manga`.

#### MangaFollow
User-manga follow join entity. Fields: `id`, `followedAt`. Unique constraint on (user, manga). Relations: ManyToOne `User`, ManyToOne `Manga`.

#### User
Fields: `id`, `createdAt`, `email` (unique), `username` (unique), `roles` (JSON), `password`. Relations: OneToMany `CustomList`, OneToMany `MangaFollow`.

### API Endpoints

#### Authentication
| Method | Route | Auth | Description |
|--------|-------|------|-------------|
| POST | `/api/login_check` | Public | Login with email/password, returns JWT |

#### Manga
| Method | Route | Auth | Description |
|--------|-------|------|-------------|
| GET | `/api/mangas` | Public | List mangas (paginated, filterable) |
| GET | `/api/mangas/{id}` | Public | Get manga details |
| GET | `/api/mangas/{id}/feed` | Public | Get chapter feed |
| POST | `/api/mangas/{id}/follow` | Auth | Follow a manga |
| DELETE | `/api/mangas/{id}/follow` | Auth | Unfollow a manga |
| GET | `/api/mangas/{id}/follow` | Auth | Check follow status |
| POST/PUT/PATCH/DELETE | `/api/mangas{/*}` | Admin | Full CRUD |

#### Chapters
| Method | Route | Auth | Description |
|--------|-------|------|-------------|
| GET | `/api/chapters` | Public | List chapters (filterable) |
| GET | `/api/chapters/{id}` | Public | Get chapter details |
| GET | `/api/chapters/{id}/pages/{pageNum}` | Public | Serve chapter page image |
| POST/PUT/PATCH/DELETE | `/api/chapters{/*}` | Admin | Full CRUD |

#### Cover Art
| Method | Route | Auth | Description |
|--------|-------|------|-------------|
| GET | `/api/cover_arts` | Public | List cover arts |
| GET | `/api/cover_arts/{id}` | Public | Get cover art details |
| GET | `/api/covers/{id}` | Public | Serve cover image (30d cache) |
| GET | `/api/mangas/{id}/primary-cover` | Public | Serve primary cover for manga |
| POST | `/api/covers/upload` | Admin | Upload cover image |
| POST/PUT/PATCH/DELETE | `/api/cover_arts{/*}` | Admin | Full CRUD |

#### Users
| Method | Route | Auth | Description |
|--------|-------|------|-------------|
| POST | `/api/users` | Public | Register (rate limited) |
| GET | `/api/users` | Admin | List users |
| GET/PUT/DELETE | `/api/users/{id}` | Self/Admin | User CRUD |
| GET | `/api/users/{id}/follows` | Self/Admin | Get user's followed mangas |

#### Creators / Tags / Scanlation Groups
| Method | Route | Auth | Description |
|--------|-------|------|-------------|
| GET | `/api/creators` | Public | List creators (filterable) |
| GET | `/api/tags` | Public | List tags (filterable) |
| GET | `/api/scanlation_groups` | Public | List groups (filterable) |
| GET | `/api/{entity}/{id}` | Public | Get single resource |

#### Custom Lists
| Method | Route | Auth | Description |
|--------|-------|------|-------------|
| POST | `/api/custom_lists` | Auth | Create list |
| GET | `/api/custom_lists` | Admin | List all |
| GET/PUT/PATCH/DELETE | `/api/custom_lists/{id}` | Owner/Admin | Full CRUD |
| POST | `/api/custom_lists/{id}/mangas/{mangaId}` | Owner/Admin | Add manga to list |
| DELETE | `/api/custom_lists/{id}/mangas/{mangaId}` | Owner/Admin | Remove manga from list |

#### Relationships & Inclusions
Many endpoints support `?include=coverArt,creators,tags,chapters` to sideload related entities via serializer groups (handled by `IncludeParameterSubscriber` + `IncludeContextBuilder`).

### Controllers

| Controller | Routes | Description |
|------------|--------|-------------|
| `CoverArtController` | `/api/covers/{id}`, `/api/mangas/{id}/primary-cover` | Serve cover images with caching |
| `ChapterPageController` | `/api/chapters/{id}/pages/{pageNum}` | Serve chapter page images |
| `MangaFollowController` | `/api/mangas/{id}/follow` (GET/POST/DELETE) | Follow/unfollow manga |
| `UploadController` | `/api/covers/upload`, `/api/chapters/{id}/upload-pages` | File uploads |
| `CustomListMangaController` | `/api/custom_lists/{id}/mangas/{mangaId}` (POST/DELETE) | Manage custom list items |

### Event Subscribers

| Subscriber | Event | Purpose |
|------------|-------|---------|
| `JwtCookieSubscriber` | `KernelEvents::RESPONSE` | Sets JWT as HTTP-only cookie on login |
| `IncludeParameterSubscriber` | `KernelEvents::REQUEST` | Processes `?include=` query param |
| `RateLimiterSubscriber` | `KernelEvents::REQUEST` | Applies rate limits (login: 5/min, registration: token bucket, API: 60/min) |
| `FileCleanupSubscriber` | Doctrine `postRemove` | Auto-deletes orphaned files on entity removal |

### Services

| Service | Purpose |
|---------|---------|
| `FileStorageService` | File save/delete operations with path resolution |
| `FileUploadValidator` | Validates uploaded files (size, type, dimensions) |

### State Providers & Processors (API Platform)

| Class | Type | Purpose |
|-------|------|---------|
| `MangaFeedProvider` | Provider | Custom chapter feed for manga |
| `UserFollowsProvider` | Provider | User's followed mangas |
| `CustomListProcessor` | Processor | Auto-assigns current user on custom list creation |
| `UserRegistrationProcessor` | Processor | Hashes password on user creation |
| `UserUpdateProcessor` | Processor | Hashes password on user update |

### DataFixtures

Nine JSON-driven fixture files read from `json/*.json` data files: `JsonUserFixtures`, `JsonMangaFixtures`, `JsonChapterFixtures`, `JsonCoverArtFixtures`, `JsonCreatorFixtures`, `JsonTagFixtures`, `JsonScanlationGroupFixtures`, `JsonCustomListFixtures`, `JsonMangaFollowFixtures`. Manga data includes titles like "Berserk", "One Piece", "Attack on Titan", etc.

## Frontend Architecture

### Pages & Layout

| Route | File | Description |
|-------|------|-------------|
| `/` | `app/page.tsx` | Home page: Topbar + MangaGrid + Footer |
| (error) | `app/error.tsx` | Global error boundary with retry button |
| (loading) | `app/loading.tsx` | Root loading spinner |

The app uses Next.js App Router. The root layout (`app/layout.tsx`) is a server component setting up Geist font, dark theme CSS variables, and metadata. It wraps content in `LayoutClient` which manages the sidebar state and renders `Navbar` + `Sidebar` with a main content area.

### Components

| Component | Props | Description |
|-----------|-------|-------------|
| `Navbar` | `{ onToggleSidebar }` | Fixed top nav with hamburger, search (Ctrl+K), notifications, user avatar |
| `Sidebar` | `{ open, onClose }` | Slide-out navigation with grouped links, closes on Escape/overlay click |
| `Topbar` | none | Home page header with "Home" title and "Welcome back" greeting |
| `MangaGrid` | none | Client component fetching latest manga; renders responsive grid with load/error states |
| `MangaCard` | `{ manga }` | Card with cover image, title (2-line clamp), status badge; links to `/manga/{id}` |
| `MangaCardSkeleton` | none | Loading placeholder with `animate-pulse` matching MangaCard dimensions |
| `Footer` | none | Site footer with links, social icons, copyright |

### Data Fetching

The API layer (`lib/api.ts`) provides:
- Pre-configured **Axios instance** with base URL from `NEXT_PUBLIC_API_URL`
- **Request interceptor** attaching JWT from `localStorage`
- **Response interceptor** normalizing errors to strings
- **`handleResponse<T>()`** utility returning `ApiResult<T>` (discriminated union: `{ success, data }` | `{ success, error }`)

### TypeScript Types (`lib/types.ts`)

```typescript
interface CoverArt { id, imagePath, volume, isPrimary }
interface Manga { id, title, createdAt, status, year, contentRating, demographic, coverArts? }
interface HydraCollection<T> { "@context", "@id", "@type", totalItems, member: T[] }
```

### Styling

Built with **Tailwind CSS 4** using a custom dark theme defined via CSS variables in `globals.css`:

| Variable | CSS Custom Property | Tailwind Token |
|----------|-------------------|----------------|
| Background | `--md-background` | `bg-md-background` |
| Surface | `--md-surface` | `bg-md-surface` |
| Surface hover | `--md-surface-hover` | `bg-md-surface-hover` |
| Border | `--md-border` | `border-md-border` |
| Text primary | `--md-text-primary` | `text-md-text-primary` |
| Text secondary | `--md-text-secondary` | `text-md-text-secondary` |
| Accent | `--md-accent` | `color-md-accent` (orange) |

## Infrastructure

### Docker Services

| Service | Image | Ports (dev) | Purpose |
|---------|-------|-------------|---------|
| `frontend` | Node 20 (multi-stage) | 3000 | Next.js SSR with hot reload |
| `backend` | PHP 8.4 FPM (multi-stage) | 9000 | Symfony API |
| `nginx` | nginx:alpine | 8080 | Reverse proxy with security headers |
| `db` | postgres:15-alpine | 5432 | PostgreSQL database |

### Nginx Routing

| Pattern | Destination | Caching |
|---------|-------------|---------|
| `/uploads/*` | Static files from `backend/public/uploads/` | 30d, immutable |
| `/bundles/*` | API Platform static assets | max |
| `/api/*`, `/docs` | Backend PHP-FPM | - |
| `/` (all else) | Frontend Next.js | - |

Security headers: X-Content-Type-Options, X-Frame-Options, X-XSS-Protection, Referrer-Policy, HSTS, CSP.

## Getting Started

### Prerequisites

- Docker & Docker Compose
- Make (optional, for using Makefile commands)

### Quick Start

```bash
make setup
```

This builds all containers, starts services, runs migrations, and makes the app available at `http://localhost:8080` (dev).

### Manual Start

```bash
# Build and start
docker compose up -d --build

# Run migrations
docker compose exec backend php bin/console doctrine:migrations:migrate --no-interaction

# Load development fixtures
docker compose exec backend php bin/console doctrine:fixtures:load --no-interaction
```

Visit `http://localhost:8080` in your browser.

## Available Commands

### Containers
| Command | Description |
|---------|-------------|
| `make up` | Start all containers |
| `make down` | Stop and remove all containers |
| `make build` | Rebuild all images |
| `make restart` | Down then up |
| `make logs` | View all logs |
| `make logs-backend` | View backend logs |
| `make logs-frontend` | View frontend logs |
| `make shell-backend` | Open backend container shell |
| `make shell-frontend` | Open frontend container shell |
| `make shell-db` | Open PostgreSQL shell |

### Database
| Command | Description |
|---------|-------------|
| `make migrate` | Run pending migrations |
| `make migrate-diff` | Generate migration from entity changes |
| `make migrate-rollback` | Rollback last migration |
| `make fixtures` | Load development fixtures |
| `make fresh` | Reset DB, rebuild, migrate, fixtures |
| `make setup` | Fresh + success message |

### Linting & Static Analysis
| Command | Description |
|---------|-------------|
| `make lint` | Lint both backend and frontend |
| `make lint-backend` | PHP-CS-Fixer dry-run |
| `make lint-frontend` | ESLint |
| `make fix-backend` | Auto-fix backend code style |
| `make fix-frontend` | Auto-fix frontend code style |
| `make fix` | Auto-fix both |

### Testing
| Command | Description |
|---------|-------------|
| `make test-backend` | Run PHPUnit tests |
| `make test-backend-coverage` | Run PHPUnit with coverage text |
| `make test-frontend` | Run Vitest tests |
| `make test-frontend-coverage` | Run Vitest with coverage |

### Utilities
| Command | Description |
|---------|-------------|
| `make install-backend` | Install composer deps |
| `make install-frontend` | Install npm deps |
| `make clear-cache` | Clear Symfony cache |
| `make cleanup-files` | Delete orphaned upload files |
| `make cleanup-files-dry-run` | List orphaned files |

## Testing

### Backend (PHPUnit)
- Config: `backend/phpunit.xml.dist`
- Test DB: SQLite (`backend/var/test.db`)
- Coverage: HTML, text, Clover XML
- Run: `make test-backend`

Test suites cover:
- **Entities**: Manga, Chapter, Creator, User validation
- **Controllers**: CoverArt, MangaFollow, Upload, CustomListManga
- **Services**: FileStorage, FileUploadValidator
- **DTOs**: UserRegistration, UserUpdate validation
- **Processors**: UserRegistration, UserUpdate
- **Providers**: MangaFeed, UserFollows
- **Subscribers**: JwtCookie, IncludeParameter, FileCleanup
- **Commands**: CleanupOrphanedFiles

### Frontend (Vitest)
- Config: `frontend/vitest.config.ts`
- Environment: jsdom
- Run: `make test-frontend` or `npm test`

Test suites cover:
- **api.ts**: Axios config, auth interceptor (localStorage JWT), response interceptor (error normalization), `handleResponse` utility, integration tests
- **MangaCard**: Title rendering, cover image, primary cover fallback, placeholder, status badge, link target, all status variants
- **MangaCardSkeleton**: Rendering, CSS class presence

### Test Coverage
| Component | Branches | Lines |
|-----------|----------|-------|
| Backend | Full | 100% |
| Frontend API layer | 93.75% | 100% |
| Frontend components | 87.5% | 100% |

## JWT Authentication

### Login
```bash
curl -X POST http://localhost:8080/api/login_check \
  -H "Content-Type: application/json" \
  -d '{"email":"test@example.com","password":"password123"}'
```

Response: `{"token":"eyJ0eXAiOiJKV1QiLCJhbGc..."}`

The token is also set as an HTTP-only cookie (`mangadex_jwt_token`).

### Using the Token
```bash
curl -X GET http://localhost:8080/api/mangas \
  -H "Authorization: Bearer YOUR_JWT_TOKEN"
```

The frontend Axios client automatically attaches the token from `localStorage` via request interceptor. Token TTL: 3600 seconds (1 hour).

### Test Credentials
- Email: `test@example.com`
- Password: `password123`

### Rate Limiting
- Login: 5 attempts per minute per IP
- Registration: 3 per IP (token bucket, 1 token per 3 minutes burst)
- API: 60 requests per minute per IP

## API Querying

### Pagination
```bash
GET /api/mangas?page=1
```
API Platform Hydra collections include `totalItems`, `member`, and `view` with `first`, `last`, `next`, `previous` links. Default page size: 20.

### Filtering
```bash
# Filter by status
GET /api/mangas?status=ongoing

# Search by title (partial match)
GET /api/mangas?title=naruto

# Filter by year range
GET /api/mangas?year[gte]=2000&year[lte]=2020

# Filter by content rating
GET /api/mangas?contentRating=safe

# Filter by demographic
GET /api/mangas?demographic=shounen

# Filter by tag
GET /api/mangas?tags.id=1
```

### Sorting
```bash
GET /api/mangas?order[createdAt]=desc
GET /api/mangas?order[title]=asc
GET /api/mangas?order[year]=desc
```

### Including Related Data
```bash
# Include cover art
GET /api/mangas?include=coverArt

# Include multiple relations
GET /api/mangas?include=coverArt,creators,tags,chapters
```

### Chapter Feed
```bash
# Get chapters for a manga, sorted by chapter number
GET /api/mangas/1/feed?order[chapterNumber]=desc

# Filter by language
GET /api/mangas/1/feed?language=en
```

## Environment Variables

### Frontend (`.env.local`)
```
NEXT_PUBLIC_API_URL=/api
```

### Backend (`.env`)
```
DATABASE_URL=postgresql://app:app@db:5432/manga_reader?serverVersion=15&charset=utf8
APP_SECRET=your_app_secret
JWT_PASSPHRASE=your_jwt_passphrase
```

### Database Defaults
- User: `app`
- Password: `app`
- Database: `manga_reader`
- Port: `5432`

## API Documentation

Once running:
- **Swagger UI**: `http://localhost:8080/api/docs`
- **ReDoc**: `http://localhost:8080/api/docs?ui=redoc`
- **Scalar**: `http://localhost:8080/api/docs?ui=scalar`
- **API entrypoint**: `http://localhost:8080/api`
- **Hand-written OpenAPI spec**: `backend/openapi.yaml`

## File Cleanup

Orphaned files in `uploads/` (files not referenced in the database) are handled automatically:

- **Automatic**: When a `CoverArt` or `Chapter` entity is deleted, files are automatically removed via Doctrine `FileCleanupSubscriber`
- **Manual cleanup**: CLI command to scan and remove orphaned files
  ```bash
  make cleanup-files-dry-run
  make cleanup-files
  ```

## Development

### Frontend
The frontend runs in dev mode with hot reload (`npm run dev`). Changes to files in `frontend/src/` automatically refresh the browser. The Next.js dev server runs on port 3000 and is proxied through nginx on port 8080.

### Backend
The backend source is mounted as a volume, so changes are reflected immediately. Use `make shell-backend` to run Symfony commands, Doctrine migrations, or fixtures.

### Database
Database is PostgreSQL 15 in Docker with a named volume for persistence. Reset with `make fresh`.
