# Migration Runbook

1. Install PHP 8.2+, Composer 2, MySQL 8, and PHP `pdo_mysql` + `pdo_pgsql` extensions.
2. Copy `.env.example` to `.env` and configure MySQL + legacy PostgreSQL credentials.
3. Run `composer install`.
4. Run `php artisan key:generate`.
5. Create an empty MySQL database and run `php artisan migrate`.
6. Take a fresh PostgreSQL backup immediately before migration/cutover.
7. Run `php artisan legacy:import --truncate`.
8. Require every reconciliation line to report `OK` and every orphan count to be `0`.
9. Run `php artisan legacy:import --validate-only` independently after import.
10. Do not point production UI traffic at Laravel until API rewrite compatibility testing is complete.

## Rollback
The Node/PostgreSQL application is not modified by this package. If validation fails, discard the new MySQL database, correct the migration issue, rerun migrations, and rerun ETL from the untouched PostgreSQL source.
