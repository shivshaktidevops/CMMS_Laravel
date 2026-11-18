<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MasterController extends Controller
{
    public function plants()
    {
        return response()->json(['success' => true, 'data' => DB::table('plants')->where('is_active', 1)->orderBy('name')->get()]);
    }

    public function storePlant(Request $r)
    {
        if (! $r->name || ! $r->code) {
            return response()->json(['success' => false, 'message' => 'name and code required'], 400);
        } try {
            $id = DB::table('plants')->insertGetId(['name' => $r->name, 'code' => strtoupper($r->code), 'is_active' => 1, 'created_at' => now()]);

            return response()->json(['success' => true, 'data' => DB::table('plants')->find($id)], 201);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function updatePlant(Request $r, $id)
    {
        $n = DB::table('plants')->where('id', $id)->update(['name' => $r->name, 'code' => strtoupper((string) $r->code), 'is_active' => (bool) $r->is_active]);
        if (! $n) {
            return response()->json(['success' => false, 'message' => 'Plant not found'], 404);
        }

return response()->json(['success' => true, 'data' => DB::table('plants')->find($id)]);
    }

    public function deletePlant($id)
    {
        DB::table('plants')->where('id', $id)->update(['is_active' => 0]);

        return response()->json(['success' => true, 'message' => 'Plant deactivated']);
    }

    public function departments(Request $r)
    {
        $q = DB::table('departments as d')->join('plants as p', 'p.id', '=', 'd.plant_id')->where('d.is_active', 1)->select('d.*', 'p.name as plant_name', 'p.code as plant_code');
        if ($r->filled('plant_id')) {
            $q->where('d.plant_id', $r->plant_id);
        }

return response()->json(['success' => true, 'data' => $q->orderBy('p.name')->orderBy('d.name')->get()]);
    }

    public function storeDepartment(Request $r)
    {
        if (! $r->name || ! $r->plant_id) {
            return response()->json(['success' => false, 'message' => 'name and plant_id required'], 400);
        }$id = DB::table('departments')->insertGetId(['name' => $r->name, 'plant_id' => $r->plant_id, 'is_active' => 1, 'created_at' => now()]);

        return response()->json(['success' => true, 'data' => DB::table('departments')->find($id)], 201);
    }

    public function updateDepartment(Request $r, $id)
    {
        $n = DB::table('departments')->where('id', $id)->update(['name' => $r->name, 'is_active' => (bool) $r->is_active]);
        if (! $n) {
            return response()->json(['success' => false, 'message' => 'Department not found'], 404);
        }

return response()->json(['success' => true, 'data' => DB::table('departments')->find($id)]);
    }

    public function deleteDepartment($id)
    {
        DB::table('departments')->where('id', $id)->update(['is_active' => 0]);

        return response()->json(['success' => true, 'message' => 'Department deactivated']);
    }

    public function shifts(Request $r)
    {
        $q = DB::table('shifts as s')->join('plants as p', 'p.id', '=', 's.plant_id')->where('s.is_active', 1)->select('s.*', 'p.name as plant_name', 'p.code as plant_code');
        if ($r->filled('plant_id')) {
            $q->where('s.plant_id', $r->plant_id);
        }

return response()->json(['success' => true, 'data' => $q->orderBy('p.name')->orderBy('s.code')->get()]);
    }

    public function storeShift(Request $r)
    {
        if (! $r->name || ! $r->code || ! $r->plant_id) {
            return response()->json(['success' => false, 'message' => 'name, code and plant_id required'], 400);
        }$id = DB::table('shifts')->insertGetId(['name' => $r->name, 'code' => strtoupper($r->code), 'plant_id' => $r->plant_id, 'is_active' => 1, 'created_at' => now()]);

        return response()->json(['success' => true, 'data' => DB::table('shifts')->find($id)], 201);
    }

    public function updateShift(Request $r, $id)
    {
        $n = DB::table('shifts')->where('id', $id)->update(['name' => $r->name, 'code' => strtoupper((string) $r->code), 'is_active' => (bool) $r->is_active]);
        if (! $n) {
            return response()->json(['success' => false, 'message' => 'Shift not found'], 404);
        }

return response()->json(['success' => true, 'data' => DB::table('shifts')->find($id)]);
    }

    public function deleteShift($id)
    {
        DB::table('shifts')->where('id', $id)->update(['is_active' => 0]);

        return response()->json(['success' => true, 'message' => 'Shift deactivated']);
    }
}
