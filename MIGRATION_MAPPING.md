# PostgreSQL → MySQL Mapping Applied

| PostgreSQL | MySQL / Laravel |
|---|---|
| integer + sequence | INT UNSIGNED AUTO_INCREMENT |
| bigint + sequence | BIGINT UNSIGNED AUTO_INCREMENT |
| boolean | TINYINT(1) / boolean |
| jsonb | JSON |
| text[] (`jobs.photos`) | JSON array |
| timestamptz | DATETIME(6), normalized to UTC by ETL |
| timestamp without time zone | DATETIME(6) |
| numeric(10,2) | DECIMAL(10,2) |
| partial unique index: equipment_id WHERE status='OPEN' | generated nullable `open_equipment_key` + UNIQUE |
| PostgreSQL shift log trigger | MySQL BEFORE UPDATE trigger |
| EXTRACT(EPOCH...) view expression | TIMESTAMPDIFF(...) |

Existing IDs are inserted explicitly and every target AUTO_INCREMENT is reset to MAX(id)+1 after import.
