# API Compatibility

The Laravel application mirrors the legacy Express mount points under both `/api` and `/api/v1`.

Modules migrated:

- auth
- equipment
- shift-logs
- breakdowns
- dashboard
- admin
- shift-monitoring
- eq-monitoring
- load-logs
- jobs
- masters
- reports
- general-shift-jobs
- upload
- health

Authentication remains `Authorization: Bearer <JWT>` and API errors retain the legacy `{ "success": false, "message": "..." }` shape.

The visual frontend files are preserved. Only the API host configuration in `public/login.html` and `public/shared/cmms.js` was changed to relative `/api` so Laravel serves both UI and API from the same origin.
