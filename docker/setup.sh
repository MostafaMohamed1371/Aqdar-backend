#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

if [ ! -f .env.docker ]; then
  cp .env.docker.example .env.docker
  echo "Created .env.docker from .env.docker.example"
fi

echo "==> Building Docker images..."
docker compose build

echo "==> Installing Composer dependencies in container..."
docker compose run --rm app composer install --no-interaction

echo "==> Starting stack..."
docker compose up -d

echo ""
echo "AQDAR is running:"
echo "  App:      http://localhost:${DOCKER_HTTP_PORT:-8080}"
echo "  Postgres: localhost:${DOCKER_POSTGRES_PORT:-5433}"
echo ""
echo "Useful commands:"
echo "  docker compose logs -f app"
echo "  docker compose exec app php artisan test"
echo "  docker compose down"
