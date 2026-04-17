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

All tests (unit, integration, and FE-BE end-to-end) run through a single script. The script requires the stack to be running first.

```bash
docker-compose up --build -d
chmod +x run_tests.sh
./run_tests.sh
```

The script will:

1. Wait for MySQL readiness
2. Reset deterministic seed data
3. Run backend domain unit tests
4. Run backend service unit tests
5. Run backend service logic unit tests
6. Run API integration tests (real HTTP, no mocks)
7. Run frontend unit tests (Node.js, inside container)
8. Run FE-BE end-to-end tests (through nginx proxy)

Exit code `0` on full pass; non-zero on any failure.

## Seeded Credentials

The database is pre-seeded with the following test users on startup. Use these credentials to verify authentication and role-based access controls.

| Role | Username | Password | Notes |
| :--- | :--- | :--- | :--- |
| **Admin** | `admin` | `admin12345` | Full access to all modules including ACL administration. |
| **Ops Staff** | `scoped_user` | `scope123456` | Operations role: recipes, bookings, notifications (scoped to store 1). |
