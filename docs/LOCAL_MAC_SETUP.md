# macOS Local Setup

```bash
brew update
brew install php composer mysql
brew services start mysql

cd ~/Downloads/plant-cmms-laravel-complete
composer install
cp .env.example .env
php artisan key:generate

mysql -u root -e "CREATE DATABASE IF NOT EXISTS plant_cmms CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

php artisan optimize:clear
php artisan migrate
php artisan legacy:import-bundled --truncate
php artisan serve
```

Open `http://127.0.0.1:8000`.

Bundled snapshot admin login: `admin` / `12345678`.
