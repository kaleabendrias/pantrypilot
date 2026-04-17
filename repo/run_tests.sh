#!/usr/bin/env bash
set -euo pipefail

echo "== PantryPilot container test runner =="

diag() {
  echo "\n--- Diagnostics: docker compose ps ---"
  docker compose ps || true
  echo "\n--- Diagnostics: api logs (last 120 lines) ---"
  docker compose logs --no-color --tail=120 api || true
  echo "\n--- Diagnostics: mysql logs (last 120 lines) ---"
  docker compose logs --no-color --tail=120 mysql || true
}

ensure_running() {
  local running
  running=$(docker compose ps --services --filter status=running 2>/dev/null || true)
  if ! echo "$running" | grep -q "^api$" || ! echo "$running" | grep -q "^mysql$"; then
    echo "\n>> Stack not running — starting with docker compose up --build -d"
    docker compose up --build -d
    echo "\n>> Waiting for containers to become healthy"
    local deadline=$(( $(date +%s) + 120 ))
    until docker compose ps --services --filter status=running 2>/dev/null | grep -q "^api$" && \
          docker compose ps --services --filter status=running 2>/dev/null | grep -q "^mysql$"; do
      if [ "$(date +%s)" -ge "$deadline" ]; then
        echo "ERROR: containers did not become healthy within 120s"
        diag
        exit 1
      fi
      sleep 3
    done
  fi
}

step() {
  local label="$1"
  shift
  echo "\n>> ${label}"
  if ! "$@"; then
    echo "FAILED: ${label}"
    diag
    exit 1
  fi
}

# Wrap a PHP invocation so it runs with the runtime-generated secrets in scope.
# docker-exec sessions don't inherit exports from PID 1 (the entrypoint), so we
# source the file the entrypoint wrote and exec the given command inside bash.
secrets_exec() {
  docker compose exec -T api bash -c '. /run/pantrypilot-runtime.env && exec '"$*"
}

ensure_running

step "Wait for DNS + DB readiness from api" \
  docker compose exec -T api bash -c 'php /var/www/html/scripts/wait_for_mysql.php --host=${DB_HOST:-mysql} --port=${DB_PORT:-3306} --db=${DB_NAME:-pantrypilot} --user=${DB_USER:-pantry} --pass=${DB_PASS:-pantrypass} --timeout=120'

step "Reset deterministic seed data" \
  docker compose exec -T api php /var/www/html/scripts/reset_test_data.php

step "Run unit tests (domain)" \
  secrets_exec "php /workspace/tests/Unit/domain_tests.php"

step "Run unit tests (service)" \
  secrets_exec "php /workspace/tests/Unit/service_tests.php"

step "Run unit tests (service logic)" \
  secrets_exec "php /workspace/tests/Unit/service_logic_tests.php"

step "Run API integration tests" \
  secrets_exec "env PANTRYPILOT_TEST_NOW='2026-01-15 10:30:00' php /workspace/tests/Integration/run_api_tests.php"

step "Run reconciliation isolated tests" \
  secrets_exec "php /workspace/tests/Integration/run_reconcile_tests.php"

step "Run frontend unit tests" \
  secrets_exec "node /workspace/frontend/tests/app.test.js"

step "Run FE-BE end-to-end tests" \
  secrets_exec "php /workspace/tests/Integration/run_e2e_tests.php"

echo "\nAll tests passed"
