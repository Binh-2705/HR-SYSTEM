# Database Migrations

This project uses SQL-file migrations with timestamped filenames.

## Naming convention
- `YYYY_MM_DD_NNN_description.sql`
- Example: `2026_04_04_001_account_security_and_mapping.sql`

## How to apply
1. Backup database.
2. Run each migration in lexical order.
3. Record applied files in your deployment notes.

## Current baseline
- Baseline schema is in `database.sql`.
- Newer changes should be added under `migrations/` instead of editing old migration files.
