# PantryPilot migration-ready schema

These SQL files are organized by module boundaries and can be applied by any SQL migration runner.

- `202603270001_core_identity.sql`: users and identity base.
- `202603270002_recipes_and_tags.sql`: recipe catalog tagging.
- `202603270003_bookings_and_ops.sql`: booking flow and campaigns.
- `202603270004_events_payments_admin.sql`: events, payments, files, audit.
- `202603270005_security_and_search.sql`: auth lockouts, RBAC, data scope, and search indexes.
- `202603270006_workflow_finance_compliance.sql`: booking constraints, operations, finance gateway, messaging, and governance.

For containerized startup, `docker/mysql/init/001_schema.sql` provides the same schema bootstrap in one file.
