# PostgreSQL -> MySQL / Laravel Mapping

## Core conversion rules

| PostgreSQL | MySQL / Laravel |
| --- | --- |
| `SERIAL` / sequence | `BIGINT UNSIGNED AUTO_INCREMENT` |
| `BIGSERIAL` | `BIGINT UNSIGNED AUTO_INCREMENT` |
| `BOOLEAN` | MySQL boolean / `TINYINT(1)` |
| `JSONB` | `JSON` |
| `TIMESTAMPTZ` | UTC MySQL timestamp/datetime-compatible value |
| PostgreSQL arrays used for photos | JSON arrays |
| PostgreSQL trigger for immutable shift logs | MySQL `BEFORE UPDATE` trigger |

Original integer IDs are explicitly preserved during import. MySQL auto-increment counters continue after the highest imported ID.

## Legacy tables carried over

`plants`, `departments`, `shifts`, `users`, `stop_reasons`, `equipment`, `equipment_monitoring`, `equipment_monitoring_types`, `equipment_monitoring_params`, `shift_logs`, `breakdowns`, `shift_monitoring_logs`, `eq_monitoring_logs`, `monitoring_logs`, `jobs`, `job_assignments`, `job_history`, `general_shift_jobs`, `audit_log`.

`load_logs` is provided because it is used by the legacy application code, even though it is absent from the supplied PostgreSQL snapshot.

## Phase 1/2 additions

`locations`, `job_types`, `alert_priorities`, `roles`, `permissions`, `role_permissions`, `user_roles`, `equipment_components`, plus additive compatibility fields on `users`, `equipment` and `jobs`.

Use `php artisan legacy:import-bundled --truncate` for the supplied snapshot, or `php artisan legacy:import --truncate` when the old PostgreSQL database is available directly.
