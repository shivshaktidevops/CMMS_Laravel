<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
return new class extends Migration {
 public function up(): void {
  DB::statement("CREATE OR REPLACE VIEW v_dashboard_today AS SELECT e.department, SUM(CASE WHEN b.status='OPEN' THEN 1 ELSE 0 END) AS open_breakdowns, SUM(CASE WHEN b.status='CLOSED' AND DATE(b.start_time)=CURRENT_DATE THEN 1 ELSE 0 END) AS closed_today, COALESCE(SUM(CASE WHEN DATE(b.start_time)=CURRENT_DATE AND b.status='CLOSED' THEN b.downtime_minutes ELSE 0 END),0) AS downtime_today_min, COUNT(DISTINCT e.id) AS total_equipment, SUM(CASE WHEN e.current_status='STOPPED' THEN 1 ELSE 0 END) AS equipment_stopped_now FROM equipment e LEFT JOIN breakdowns b ON b.equipment_id=e.id GROUP BY e.department");
  DB::statement("CREATE OR REPLACE VIEW v_open_breakdowns AS SELECT b.id,b.bd_number,b.equipment_id,e.code AS equipment_code,e.name AS equipment_name,e.department,e.location,e.plant,sr.description AS initial_reason,sr.category AS reason_category,b.start_time,ROUND(TIMESTAMPDIFF(SECOND,b.start_time,NOW())/60) AS current_downtime_minutes,b.findings,b.actions_taken,b.status FROM breakdowns b JOIN equipment e ON e.id=b.equipment_id LEFT JOIN stop_reasons sr ON sr.id=b.initial_reason_id WHERE b.status='OPEN' ORDER BY b.start_time");
 }
 public function down(): void { DB::statement('DROP VIEW IF EXISTS v_open_breakdowns'); DB::statement('DROP VIEW IF EXISTS v_dashboard_today'); }
};
