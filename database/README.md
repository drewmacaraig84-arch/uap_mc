# Database setup

This project uses a migration and seed workflow based on the application schema and SQL upgrade files already in the repo.

## Included SQL sources

The migration files are aligned with:
- schema.sql
- upgrade.sql
- upgrade2.sql

## Run full setup

```bash
php database/setup.php
```

This runs migrations first, then the seed files.

## Run migrations only

```bash
php database/migrate.php
```

## Run seeds only

```bash
php database/seed.php
```

## Default admin account

- ID Number: ADMIN001
- Password: admin123

This is created by the seed script if it does not exist yet.
