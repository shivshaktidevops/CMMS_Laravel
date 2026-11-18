#!/usr/bin/env bash
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
find "$ROOT" -name '*.php' -print0 | xargs -0 -n1 php -l >/tmp/cmms-php-lint.txt
if grep -v 'No syntax errors detected' /tmp/cmms-php-lint.txt; then exit 1; fi
node --check "$ROOT/public/shared/cmms.js"
php "$ROOT/artisan" route:list >/tmp/cmms-routes.txt
php "$ROOT/artisan" migrate:status
printf 'Static syntax and Laravel route boot checks passed.\n'
