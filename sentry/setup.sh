#!/usr/bin/env bash
# Setup Sentry self-hosted alongside the MangaDex project.
# Run this once to install Sentry, then grab the DSN and add it to .env

set -euo pipefail

SENTRY_DIR="$HOME/sentry-self-hosted"
SENTRY_VERSION="25.5.1"

echo "==> Cloning sentry/self-hosted @ $SENTRY_VERSION"
if [ -d "$SENTRY_DIR" ]; then
  echo "    Directory $SENTRY_DIR already exists, skipping clone."
else
  git clone --branch "$SENTRY_VERSION" --depth 1 \
    https://github.com/getsentry/self-hosted.git "$SENTRY_DIR"
fi

cd "$SENTRY_DIR"

echo "==> Running Sentry install script (takes 10–20 min on first run)"
./install.sh

echo ""
echo "==> Starting Sentry"
docker compose up -d

echo ""
echo "==> Sentry is running at http://localhost:9000"
echo ""
echo "Next steps:"
echo "  1. Open http://localhost:9000 and complete the setup wizard."
echo "  2. Create an organisation (e.g. 'sentry') and a project called 'mangadex'."
echo "  3. Copy the DSN from Settings → Projects → mangadex → Client Keys."
echo "  4. Add to your MangaDex .env:"
echo "       SENTRY_DSN=http://<key>@localhost:9000/<project-id>"
echo "       SENTRY_ORG=sentry"
echo "       SENTRY_PROJECT=mangadex"
echo "  5. Generate an auth token at http://localhost:9000/settings/account/api/auth-tokens/"
echo "     with scopes: project:releases, org:read, project:read, project:write"
echo "  6. Add to .env:  SENTRY_AUTH_TOKEN=<token>"
echo "  7. Restart MangaDex:  docker compose up -d --build"
