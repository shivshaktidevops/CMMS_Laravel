# Plant CMMS — Laravel + MySQL (Phase 1 + Phase 2)

This package replaces the legacy Node.js/Express + PostgreSQL backend with Laravel 12 + MySQL while preserving the existing CMMS frontend and legacy API contract. Phase 1 foundation and Phase 2 Equipment Master enhancements are included.

## Included

### Existing CMMS modules migrated
- JWT login/authentication and existing role restrictions
- Plants, departments and shifts masters
- Users and stop-reason administration
- Equipment
- Shift logs and automatic breakdown creation
- Breakdown management
- Jobs, multi-department assignment, history and reassignment
- Shift/equipment/load monitoring
- General shift jobs
- Management dashboard APIs
- Reports and CSV export
- Audit log
- Photo uploads
- `/api/*` and `/api/v1/*` compatibility routes

### Phase 1 foundation
- Normalized locations
- Job types
- Alert priorities
- Roles/permissions foundation
- Normalized user references
- Extended job foundation fields

### Phase 2
- Unique `asset_uid` and `machine_tag`
- Structured Equipment Master location mapping
- Parent/child equipment relationship
- Manufacturer/model/serial/commissioning metadata
- Per-equipment critical downtime threshold
- Hierarchical machine/component master
- Component location mapping
- Tag/asset lookup API
- Location and Component management integrated into the existing Admin screen

The existing visual design is retained. Only fields/controls required to manage Phase 2 data were added to the existing Admin UI.

## Fresh local setup

See `docs/LOCAL_MAC_SETUP.md`.

Quick path:

```bash
composer install
cp .env.example .env
php artisan key:generate

mysql -u root
```

```sql
CREATE DATABASE plant_cmms CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
exit;
```

Then:

```bash
php artisan optimize:clear
php artisan migrate
php artisan legacy:import-bundled --truncate
php artisan serve
```

Open `http://127.0.0.1:8000`.

Bundled legacy admin credentials after import:

- Username: `admin`
- Password: `12345678`

## Existing Phase 1 database upgrade

Keep your existing `.env` and database backup, replace the application source, then run:

```bash
composer install
php artisan optimize:clear
php artisan migrate
php artisan db:seed --class='Database\\Seeders\\Phase1FoundationSeeder' --force
php artisan serve
```

Do **not** run `legacy:import-bundled --truncate` against a database containing data you want to keep.

## Important

Set a strong `JWT_SECRET` before production deployment. The default `.env.example` value is for local setup only.
