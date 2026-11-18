<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class Phase1FoundationSeeder extends Seeder
{
    public function run(): void
    {
        $priorities = [['CRITICAL', 'Critical', 1, 'danger', 5, 10], ['HIGH', 'High', 2, 'warning', 15, 30], ['MEDIUM', 'Medium', 3, 'info', 60, 120], ['LOW', 'Low', 4, 'secondary', 240, 480]];
        foreach ($priorities as [$code,$name,$rank,$color,$response,$esc]) {
            DB::table('alert_priorities')->updateOrInsert(['code' => $code], ['name' => $name, 'severity_rank' => $rank, 'color_key' => $color, 'notify_in_app' => 1, 'notify_email' => 0, 'notify_sms' => 0, 'response_minutes' => $response, 'escalation_minutes' => $esc, 'active' => 1, 'created_at' => now(), 'updated_at' => now()]);
        }
        $roleNames = ['admin' => 'Administrator', 'management' => 'Management', 'maintenance' => 'Department Head / Maintenance', 'operator' => 'Shift-in-Charge / Operator'];
        foreach ($roleNames as $code => $name) {
            DB::table('roles')->updateOrInsert(['code' => $code], ['name' => $name, 'active' => 1, 'created_at' => now(), 'updated_at' => now()]);
        }
        $perms = ['equipment.view', 'equipment.create', 'equipment.edit', 'equipment.delete', 'locations.view', 'locations.manage', 'components.view', 'components.manage', 'jobs.view', 'jobs.create', 'jobs.assign', 'jobs.close', 'masters.manage', 'users.manage', 'breakdowns.view', 'breakdowns.update', 'reports.view', 'audit.view'];
        foreach ($perms as $p) {
            [$mod] = explode('.', $p);
            DB::table('permissions')->updateOrInsert(['code' => $p], ['name' => ucwords(str_replace(['.', '_'], ' ', $p)), 'module' => $mod, 'created_at' => now(), 'updated_at' => now()]);
        }
        $admin = DB::table('roles')->where('code', 'admin')->value('id');
        if ($admin) {
            foreach (DB::table('permissions')->pluck('id') as $pid) {
                DB::table('role_permissions')->updateOrInsert(['role_id' => $admin, 'permission_id' => $pid], []);
            }
        }
        foreach (DB::table('users')->get() as $u) {
            $rid = DB::table('roles')->where('code', $u->role)->value('id');
            $plantId = DB::table('plants')->where('code', $u->plant)->value('id');
            $deptId = null;
            if ($u->department && $plantId) {
                $deptId = DB::table('departments')->where('name', $u->department)->where('plant_id', $plantId)->value('id');
            }$shiftId = null;
            if ($u->shift && $plantId) {
                $shiftId = DB::table('shifts')->where('code', $u->shift)->where('plant_id', $plantId)->value('id');
            }DB::table('users')->where('id', $u->id)->update(['plant_id' => $plantId, 'department_id' => $deptId, 'shift_id' => $shiftId, 'primary_role_id' => $rid]);
            if ($rid) {
                DB::table('user_roles')->updateOrInsert(['user_id' => $u->id, 'role_id' => $rid, 'plant_id' => $plantId, 'department_id' => $deptId], []);
            }
        }
        foreach (DB::table('equipment')->orderBy('id')->get() as $e) {
            $plantId = DB::table('plants')->where('code', $e->plant)->value('id');
            $locationId = $e->location_id;
            if (! $locationId && $e->location) {
                $code = Str::upper(Str::slug($e->plant.'-'.$e->location, '-'));
                $locationId = DB::table('locations')->where('plant_id', $plantId)->where('code', $code)->value('id');
                if (! $locationId) {
                    $locationId = DB::table('locations')->insertGetId(['plant_id' => $plantId, 'parent_id' => null, 'code' => $code, 'name' => $e->location, 'location_type' => 'AREA', 'active' => 1, 'created_at' => now(), 'updated_at' => now()]);
                }
            }
            $asset = $e->asset_uid ?: 'ASSET-'.Str::upper(Str::slug($e->plant.'-'.$e->code, '-'));
            $tag = $e->machine_tag ?: $e->code;
            // collision-safe backfill
            if (DB::table('equipment')->where('id', '!=', $e->id)->where('asset_uid', $asset)->exists()) {
                $asset .= '-'.$e->id;
            }if (DB::table('equipment')->where('id', '!=', $e->id)->where('machine_tag', $tag)->exists()) {
                $tag .= '-'.$e->id;
            }
            DB::table('equipment')->where('id', $e->id)->update(['location_id' => $locationId, 'asset_uid' => $asset, 'machine_tag' => $tag]);
        }
    }
}
