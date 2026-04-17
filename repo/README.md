# PantryPilot

A fullstack, containerized pantry operations platform for kiosk and local Wi-Fi retail environments. Covers recipe browsing, booking management, payment reconciliation, notifications, file governance, operations dashboards, and role-based administration — all running entirely in Docker with no external service dependencies.

## Architecture & Tech Stack

* **Frontend:** Layui SPA (vanilla JS, offline-first queue, role-based tab visibility)
* **Backend:** ThinkPHP 8 on PHP 8.2-Apache (`api` service)
* **Database:** MySQL 8.0 (`mysql` service)
* **Proxy:** nginx 1.27-alpine — serves frontend static files and proxies `/api/*` to backend (`web` service)
* **Containerization:** Docker & Docker Compose (required)

## Project Structure

```text
.
├── backend/                # ThinkPHP API source, scripts, migrations
├── frontend/               # Layui SPA static files
├── docker/                 # nginx, PHP, and MySQL Docker configs
├── tests/
│   ├── Unit/               # Domain and service unit tests (PHP)
│   └── Integration/        # Real-HTTP API integration tests + FE-BE E2E tests (PHP)
├── docker-compose.yml      # Multi-container orchestration
└── run_tests.sh            # Standardized test execution script
```

## Prerequisites

This project runs entirely inside containers. Install:

* [Docker](https://docs.docker.com/get-docker/)
* [Docker Compose](https://docs.docker.com/compose/install/)

No local PHP, Node.js, or database tooling is required.

## Running the Application

1. **Build and start all containers:**

   ```bash
   docker-compose up --build -d
   ```

2. **Access the application:**

   * Frontend UI: `http://localhost:8080`
   * Backend API: `http://localhost:8000`
   * MySQL: `localhost:3307`

3. **Stop and remove containers:**

   ```bash
   docker-compose down -v
   ```

## Testing

All tests (unit, integration, and FE-BE end-to-end) run through a single script. The script auto-starts the stack if containers are not running.

```bash
chmod +x run_tests.sh
./run_tests.sh
```

The script will:

1. Start the Docker stack if not already running
2. Wait for MySQL readiness
3. Reset deterministic seed data
4. Run backend domain unit tests
5. Run backend service unit tests
6. Run backend service logic unit tests
7. Run API integration tests (real HTTP, no mocks)
8. Run frontend unit tests (Node.js, inside container)
9. Run FE-BE end-to-end tests (through nginx proxy)

Exit code `0` on full pass; non-zero on any failure.

## Seeded Credentials

The database is pre-seeded with the following users every time the test suite runs (via `reset_test_data.php`). Use these credentials to log in immediately — no setup or password rotation required.

| Role | Username | Password | Scope | Notes |
| :--- | :--- | :--- | :--- | :--- |
| **Admin** | `admin` | `admin12345` | Global | Full access to all modules, ACL administration, and audit logs. |
| **Ops Staff** | `scoped_user` | `scope123456` | Store 1 / Warehouse 1 / Department 1 | Recipes, bookings, notifications — data-scoped. |
| **Manager** | `manager_user` | `manager12345` | Store 1 / Warehouse 1 / Department 1 | Recipes, bookings, operations, notifications, files, reporting — read/write with booking approval. |
| **Finance** | `finance_user` | `finance12345` | Store 1 / Warehouse 1 / Department 1 | Full payment access (read/write/approve), reporting read, booking read. |
| **Customer** | `customer_user` | `cust12345pp` | None (global access) | Recipe browse, booking create/read, own notification inbox only. |

> Passwords meet the system policy (≥ 10 characters, letters + numbers). To rotate a password after login, `POST /api/v1/identity/rotate-password` with `username`, `current_password`, and `new_password`.

## Role-Permission Matrix

| Resource | Admin | Ops Staff | Manager | Finance | Customer |
| :--- | :---: | :---: | :---: | :---: | :---: |
| `recipe` | read, write, approve | read | read, write | — | read |
| `booking` | read, write, approve | read, write | read, write, approve | read | read, write |
| `booking_ops` | read, write, approve | read, write, approve | — | — | — |
| `operations` | read, write, approve | read, write | read, write | — | — |
| `payment` | read, write, approve | — | — | read, write, approve | — |
| `notification` | read, write, approve | read, write | read, write | — | — |
| `notification_self` | read, write, approve | read, write | read, write | — | read, write |
| `file` | read, write, approve | read, approve | read, write | — | — |
| `reporting` | read, write, approve | read | read, write | read | — |
| `admin` | read, write, approve | — | — | — | — |

> **Data scoping**: Admin sees all data globally. Ops Staff, Manager, and Finance are restricted to Store 1 / Warehouse 1 / Department 1. Customer has no scope restriction but can only access their own bookings and inbox.

## Manual Verification

Start the stack, then run the seed script so all demo users are present:

```bash
docker-compose up --build -d
docker-compose exec -T api php /var/www/html/scripts/reset_test_data.php
```

### Step 1 — Login and obtain a token

```bash
# Admin login
curl -s -X POST http://localhost:8000/api/v1/identity/login \
  -H 'Content-Type: application/json' \
  -d '{"username":"admin","password":"admin12345"}' | jq .

# Expected: {"success":true,"data":{"token":"<TOKEN>","user":{...}},"message":"..."}
```

Save the token for subsequent requests:

```bash
TOKEN="<paste token here>"
```

### Step 2 — Browse recipes

```bash
curl -s http://localhost:8000/api/v1/recipes \
  -H "Authorization: Bearer $TOKEN" | jq .data.items[].name
```

### Step 3 — Search recipes (synonym resolution)

```bash
# "garbanzo" resolves to "chickpea" via the synonym table
curl -s "http://localhost:8000/api/v1/recipes/search?ingredient=garbanzo" \
  -H "Authorization: Bearer $TOKEN" | jq .
# Expected: returns Chickpea Stew
```

### Step 4 — List pickup points

```bash
curl -s http://localhost:8000/api/v1/bookings/pickup-points \
  -H "Authorization: Bearer $TOKEN" | jq .data.items[].name
```

### Step 5 — Create a booking

```bash
# Replace <RECIPE_ID> and <PICKUP_POINT_ID> with IDs from earlier calls
# Replace <SLOT_START>/<SLOT_END> with tomorrow's slot (e.g. "2026-04-18 10:00:00")
curl -s -X POST http://localhost:8000/api/v1/bookings \
  -H "Authorization: Bearer $TOKEN" \
  -H 'Content-Type: application/json' \
  -d '{
    "recipe_id": 1,
    "pickup_point_id": 1,
    "pickup_at": "2026-04-18 10:00:00",
    "slot_start": "2026-04-18 10:00:00",
    "slot_end": "2026-04-18 10:30:00",
    "quantity": 1
  }' | jq .
# Expected: {"success":true,"data":{"id":...,"booking_code":"BKG-..."},...}
```

### Step 6 — Finance role: view and reconcile payments

```bash
# Login as finance_user
FIN_TOKEN=$(curl -s -X POST http://localhost:8000/api/v1/identity/login \
  -H 'Content-Type: application/json' \
  -d '{"username":"finance_user","password":"finance12345"}' | jq -r .data.token)

# List payments
curl -s http://localhost:8000/api/v1/payments \
  -H "Authorization: Bearer $FIN_TOKEN" | jq .data.items | head

# Run daily reconciliation
curl -s -X POST http://localhost:8000/api/v1/payments/reconcile/daily \
  -H "Authorization: Bearer $FIN_TOKEN" \
  -H 'Content-Type: application/json' \
  -d "{\"date\":\"$(date +%Y-%m-%d)\"}" | jq .
```

### Step 7 — Verify role access boundaries

```bash
# Customer login — must be denied admin endpoint with HTTP 403
CUST_TOKEN=$(curl -s -X POST http://localhost:8000/api/v1/identity/login \
  -H 'Content-Type: application/json' \
  -d '{"username":"customer_user","password":"cust12345pp"}' | jq -r .data.token)

curl -s http://localhost:8000/api/v1/admin/users \
  -H "Authorization: Bearer $CUST_TOKEN" | jq .
# Expected: {"success":false,"message":"Forbidden",...} with HTTP 403

# Manager login — must be denied payment endpoint with HTTP 403
MGR_TOKEN=$(curl -s -X POST http://localhost:8000/api/v1/identity/login \
  -H 'Content-Type: application/json' \
  -d '{"username":"manager_user","password":"manager12345"}' | jq -r .data.token)

curl -s http://localhost:8000/api/v1/payments \
  -H "Authorization: Bearer $MGR_TOKEN" | jq .
# Expected: HTTP 403 — manager does not have payment:read permission
```

### Step 8 — UI navigation flow

1. Open `http://localhost:8080` in a browser.
2. Log in with `admin / admin12345`. All 8 tabs (Dashboard, Recipes, Bookings, Operations, Finance, Notifications, Files, Admin) should be visible.
3. Log out; log in with `scoped_user / scope123456`. Tabs for Finance and Admin must be hidden.
4. Log in with `customer_user / cust12345pp`. Only Dashboard, Recipes, and Bookings tabs should be visible.
5. Log in with `finance_user / finance12345`. Only Dashboard and Finance tabs should be visible.
