# Local Mac Setup

## Prerequisites

```bash
brew install php composer mysql
brew services start mysql
```

Check:

```bash
php -v
composer -V
mysql --version
```

Laravel 12 requires PHP 8.2+.

## 1. Extract and enter project

```bash
cd ~/Downloads
unzip plant-cmms-laravel-phase2-complete.zip
cd plant-cmms-laravel-phase2-complete
```

## 2. Install PHP dependencies

```bash
composer install
cp .env.example .env
php artisan key:generate
```

## 3. Create MySQL database

```bash
mysql -u root
```

```sql
DROP DATABASE IF EXISTS plant_cmms;
CREATE DATABASE plant_cmms CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
exit;
```

## 4. Configure `.env`

Typical Homebrew MySQL settings:

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
JWT_SECRET=replace-this-with-a-long-random-value
JWT_TTL=720
```

## 5. Build schema and load bundled legacy data

```bash
php artisan optimize:clear
php artisan migrate
php artisan legacy:import-bundled --truncate
```

The bundled import contains the existing data from the supplied PostgreSQL snapshot and preserves original IDs/relationships.

## 6. Start

```bash
php artisan serve
```

Open:

`http://127.0.0.1:8000`

Default imported admin:

- `admin`
- `12345678`

## Upgrade an existing local Phase 1 database

Back up your database first. Keep the existing `.env`, then run:

```bash
composer install
php artisan optimize:clear
php artisan migrate
php artisan db:seed --class='Database\\Seeders\\Phase1FoundationSeeder' --force
php artisan serve
```

Do not run the bundled importer with `--truncate` on a database you want to retain.
