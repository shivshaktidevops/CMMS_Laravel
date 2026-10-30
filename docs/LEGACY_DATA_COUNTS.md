# Supplied `cmms_temp` Source Snapshot Counts

These counts came from the supplied backup inventory and are useful as the first reconciliation baseline.

| Table | Rows |
|---|---:|
| audit_log | 2737 |
| breakdowns | 20 |
| departments | 6 |
| eq_monitoring_logs | 457 |
| equipment | 378 |
| equipment_monitoring | 0 |
| equipment_monitoring_params | 30 |
| equipment_monitoring_types | 654 |
| general_shift_jobs | 30 |
| job_assignments | 28 |
| job_history | 159 |
| jobs | 41 |
| monitoring_logs | 0 |
| plants | 2 |
| shift_logs | 43 |
| shift_monitoring_logs | 273 |
| shifts | 6 |
| stop_reasons | 21 |
| users | 25 |

`load_logs` is referenced by the current Express source but is not present in the supplied PostgreSQL snapshot. The MySQL target table is included, and the ETL skips source tables that do not exist.
