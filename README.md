# Plant CMMS — Complete Laravel + MySQL Migration

This project is the Laravel/MySQL replacement for the supplied Node.js/Express + PostgreSQL CMMS.

## What is preserved

- Existing frontend screens, layout, styling and interaction flow.
- Existing `/api/*` endpoints and mirrored `/api/v1/*` endpoints.
- Existing JWT Bearer-token login flow and 12-hour token TTL.
- Roles: `operator`, `maintenance`, `management`, `admin`.
- Plant/department/shift restrictions and role-specific workflows.
- Equipment status / immutable shift-log / automatic breakdown workflow.
- Breakdown maintenance and close workflow.
- Job assignment, multi-department assignment, department status, reassignment and history.
- Shift monitoring, equipment monitoring, load monitoring and general-shift jobs.
- Management dashboard and reports, including CSV export.
- Admin users, stop reasons, masters and audit log.
- Photo upload endpoint and `/uploads/*` URLs.
- Existing PostgreSQL IDs and historical data.

Only the frontend API base was changed from the retired Node server address to relative `/api`; this does not alter the visual UI.

## Requirements

- PHP 8.2+
- Composer
- MySQL 8.0+
- PHP extensions: pdo_mysql, mbstring, openssl, json, fileinfo

## Fast local setup on macOS

```bash
cd CMMS_Laravel
composer install
cp .env.example .env
php artisan key:generate
```

Create the database:

```bash
mysql -u root
```

```sql
CREATE DATABASE plant_cmms CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
exit;
```

Confirm `.env` has:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=plant_cmms
DB_USERNAME=root
DB_PASSWORD=
CACHE_STORE=file
SESSION_DRIVER=file
QUEUE_CONNECTION=sync
JWT_SECRET=cmms_secret_change_in_production
JWT_TTL=720
```

Create the schema and import the supplied legacy PostgreSQL snapshot directly into MySQL:

```bash
php artisan optimize:clear
php artisan migrate
php artisan legacy:import-bundled --truncate
```

Run the app:

```bash
php artisan serve
```

Open:

`http://127.0.0.1:8000`

### Login from bundled snapshot

- Username: `admin`
- Password: `12345678`

## Data migration choices

### Local/offline migration from the supplied project archive

The project includes `database/legacy/cmms_temp_data.json`, generated from the supplied `cmms_temp.sql` snapshot. Import it with:

```bash
php artisan legacy:import-bundled --truncate
```

This requires only MySQL and preserves source primary keys.

### Final/live cutover directly from PostgreSQL

Configure the `LEGACY_DB_*` variables and run:

```bash
php artisan legacy:import --truncate
php artisan legacy:import --validate-only
```

The direct importer reads PostgreSQL and writes MySQL in dependency order, preserving IDs and reconciling source/target counts.

## Important local reset

For a development database only, to rebuild from zero:

```bash
php artisan migrate:fresh
php artisan legacy:import-bundled --truncate
```

Do not use `migrate:fresh` on production.
