#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

BASE_URL="${QA_BASE_URL:-http://127.0.0.1:8000}"
HOST="${QA_HOST:-127.0.0.1}"
PORT="${QA_PORT:-8000}"

echo "==> Structer QA — full run"
echo "    Base URL: $BASE_URL"

# Start Laravel if not running
if ! curl -sf "$BASE_URL/up" > /dev/null 2>&1; then
  echo "==> Starting Laravel server..."
  php artisan serve --host="$HOST" --port="$PORT" > storage/logs/qa-server.log 2>&1 &
  SERVER_PID=$!
  trap 'kill $SERVER_PID 2>/dev/null || true' EXIT
  sleep 2
fi

echo "==> PHPUnit Unit tests"
php artisan test --testsuite=Unit

echo "==> PHPUnit Integration tests"
php artisan test --testsuite=Integration

if command -v python3 &>/dev/null; then
  echo "==> Python QA (integration + BDD + performance)"
  cd qa/python
  pip3 install -q -r requirements.txt 2>/dev/null || true
  QA_BASE_URL="$BASE_URL" python3 -m pytest integration bdd/step_defs performance -m "not e2e" -v
  cd "$ROOT"

  if [[ "${QA_RUN_E2E:-0}" == "1" ]]; then
    echo "==> Selenium E2E"
    cd qa/python
    QA_BASE_URL="$BASE_URL" python3 -m pytest e2e -m e2e -v
    cd "$ROOT"
  fi
else
  echo "==> Python not found — skipping Python QA"
fi

if [[ "${QA_RUN_JMETER:-0}" == "1" ]] && command -v jmeter &>/dev/null; then
  echo "==> JMeter load test"
  php artisan structer:qa --jmeter --skip-python
fi

echo "==> QA complete"
