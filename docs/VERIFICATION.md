# Package Verification

The build was statically verified before packaging:

- all PHP source/migration/config files parsed with `php -l`
- all inline frontend JavaScript blocks parsed with `node --check`
- shared frontend JavaScript parsed with `node --check`
- every controller referenced from `routes/api.php` exists
- every referenced controller method exists
- bundled legacy PostgreSQL snapshot row counts were reconciled
- checked legacy foreign-key relationships report zero orphans
- local frontend API base points to Laravel `/api`, not the old Node port

A full Laravel runtime migration test could not be executed in the build container because Composer is not installed there. Run `composer install`, `php artisan migrate`, and the bundled importer on the target Mac as described in `LOCAL_MAC_SETUP.md`.
