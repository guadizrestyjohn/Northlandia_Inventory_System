# Database setup

## Fresh installation

Import the root `northlandia_db.sql`. It contains the current schema, including Records Management tables, production completion fields, Stock Transaction Order/Job Order references, and their keys and foreign keys. It is schema-only and does not seed users, roles, categories, or application records. The legacy `business_records` copy steps are in `records_management_migration.sql`; run that migration against an existing database when legacy rows need to be copied.

## Existing installation

Back up the database, then run these migrations against the selected database in this order:

1. `records_management_migration.sql`
2. `production_release_workflow_migration.sql`
3. `stock_out_order_reference_migration.sql`

The migration scripts are additive and designed to be rerun. They do not replace importing the full dump for a fresh installation. Do not import `northlandia_db.sql` over a database containing application data.
