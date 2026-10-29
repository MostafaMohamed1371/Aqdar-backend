#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
cd "$ROOT"

CREDS="$ROOT/qa/jmeter/credentials.env"
if [[ ! -f "$CREDS" ]]; then
  echo "Missing $CREDS — copy qa/jmeter/credentials.env.example and set LOGIN_EMAIL / LOGIN_PASSWORD"
  exit 1
fi

# shellcheck disable=SC1090
source "$CREDS"

: "${LOGIN_EMAIL:?LOGIN_EMAIL is required in credentials.env}"
: "${LOGIN_PASSWORD:?LOGIN_PASSWORD is required in credentials.env}"

mkdir -p storage/app/qa storage/logs
rm -rf storage/app/qa/jmeter-report storage/app/qa/jmeter-results.jtl

# Verify login works with credentials from credentials.env
export LOGIN_EMAIL LOGIN_PASSWORD
php artisan structer:jmeter-prepare --ansi || exit 1

(lsof -ti:8000 | xargs kill -9 2>/dev/null || true)
STRUCTER_JMETER_LOAD_TEST=1 php artisan serve --host=127.0.0.1 --port=8000 > storage/logs/jmeter-server.log 2>&1 &
SERVER_PID=$!

cleanup() {
  kill "$SERVER_PID" 2>/dev/null || true
}
trap cleanup EXIT

for _ in $(seq 1 30); do
  if curl -sf http://127.0.0.1:8000/up >/dev/null; then
    echo "Server ready (pid $SERVER_PID)"
    break
  fi
  sleep 1
done

THREADS="${THREADS:-10}"
LOOPS="${LOOPS:-20}"

echo "Running JMeter: ${THREADS} threads × ${LOOPS} loops as ${LOGIN_EMAIL}"

jmeter -n -t qa/jmeter/structer-load-test.jmx \
  -JHOST=127.0.0.1 \
  -JPORT=8000 \
  -JPROTOCOL=http \
  -JTHREADS="$THREADS" \
  -JLOOPS="$LOOPS" \
  -JLOGIN_EMAIL="$LOGIN_EMAIL" \
  -JLOGIN_PASSWORD="$LOGIN_PASSWORD" \
  -l storage/app/qa/jmeter-results.jtl \
  -e -o storage/app/qa/jmeter-report

echo ""
echo "Report: storage/app/qa/jmeter-report/index.html"
