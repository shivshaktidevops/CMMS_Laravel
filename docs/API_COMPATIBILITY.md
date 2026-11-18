# API Compatibility

The Laravel backend preserves the legacy Express route families under `/api/*` and mirrors them under `/api/v1/*`.

## Legacy route families preserved

- `/auth`
- `/equipment`
- `/shift-logs`
- `/breakdowns`
- `/dashboard`
- `/admin`
- `/shift-monitoring`
- `/jobs`
- `/masters`
- `/reports`
- `/load-logs`
- `/eq-monitoring`
- `/general-shift-jobs`
- `/upload`

Existing Bearer JWT authorization is preserved.

## Phase 2 additive endpoints

- `GET /api/locations`
- `GET /api/locations/tree`
- `POST /api/locations`
- `PUT /api/locations/{id}`
- `DELETE /api/locations/{id}`
- `GET /api/equipment/tag/{tag}`
- `GET /api/equipment/{equipmentId}/components`
- `GET /api/equipment/{equipmentId}/components/tree`
- `POST /api/equipment/{equipmentId}/components`
- `PUT /api/equipment/{equipmentId}/components/{id}`
- `DELETE /api/equipment/{equipmentId}/components/{id}`

The same Phase 2 endpoints are available beneath `/api/v1`.
