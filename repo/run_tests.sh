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

require_running() {
  local svc="$1"
  if ! docker compose ps --services --filter status=running | grep -q "^${svc}$"; then
    echo "ERROR: service '${svc}' is not running."
    echo "Start stack first with: docker compose up"
    exit 1
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

require_running api
require_running mysql

step "Wait for DNS + DB readiness from api" \
  docker compose exec -T api php /var/www/html/scripts/wait_for_mysql.php --host=mysql --port=3306 --db=pantrypilot --user=pantry --pass=pantrypass --timeout=120

step "Reset deterministic seed data" \
  docker compose exec -T api php /var/www/html/scripts/reset_test_data.php

step "Run unit tests (domain)" \
  docker compose exec -T api php /workspace/tests/Unit/domain_tests.php

step "Run unit tests (service)" \
  docker compose exec -T api php /workspace/tests/Unit/service_tests.php

step "Run unit tests (service logic)" \
  docker compose exec -T api php /workspace/tests/Unit/service_logic_tests.php

step "Run API integration tests" \
  docker compose exec -T api env PANTRYPILOT_TEST_NOW="2026-01-15 10:30:00" php /workspace/tests/Integration/run_api_tests.php

echo "\nAll tests passed"
