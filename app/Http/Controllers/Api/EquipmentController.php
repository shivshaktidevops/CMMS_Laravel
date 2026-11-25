<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Equipment;
use App\Models\Location;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class EquipmentController extends Controller
{
    private function enrich(object $equipment, string $shift = 'A'): object
    {
        $openBreakdown = DB::table('breakdowns')
            ->where('equipment_id', $equipment->id)
            ->where('status', 'OPEN')
            ->orderByDesc('start_time')
            ->first();

        $equipment->open_bd_number = $openBreakdown->bd_number ?? null;
        $equipment->breakdown_start = $openBreakdown->start_time ?? null;
        $equipment->current_downtime_min = $openBreakdown
            ? round((time() - strtotime($openBreakdown->start_time)) / 60)
            : null;

        foreach (['rpm', 'load'] as $kind) {
            $type = DB::table('equipment_monitoring_types')
                ->where('equipment_id', $equipment->id)
                ->where('is_active', 1)
                ->whereRaw('LOWER(monitoring_name) LIKE ?', ['%'.$kind.'%'])
                ->orderBy('id')
                ->first();

            $standard = null;
            $actual = null;

            if ($type) {
                $refs = json_decode($type->reference_values ?? '[]', true) ?: [];
                $standard = $refs[0]['value'] ?? null;

                $log = DB::table('eq_monitoring_logs')
                    ->where('equipment_id', $equipment->id)
                    ->where('monitoring_id', $type->id)
                    ->where('shift', $shift)
                    ->orderByDesc('logged_at')
                    ->first();

                if ($log) {
                    $readings = json_decode($log->readings ?? '[]', true) ?: [];
                    if ($kind === 'load') {
                        foreach ($readings as $reading) {
                            if (strtolower((string) ($reading['label'] ?? '')) === 'red') {
                                $actual = $reading['value'] ?? null;
                                break;
                            }
                        }
                    } else {
                        $actual = $readings[0]['value'] ?? null;
                    }
                }
            }

            $equipment->{'standard_'.$kind} = $standard;
            $equipment->{'actual_'.$kind} = $actual;
        }

        $lastJob = DB::table('jobs as j')
            ->leftJoin('users as u', 'u.id', '=', 'j.assigned_to')
            ->where('j.equipment_id', $equipment->id)
            ->where('j.status', 'CLOSED')
            ->select('j.title', 'j.closed_at', 'u.full_name as closed_by_name')
            ->orderByDesc('j.closed_at')
            ->first();

        $equipment->last_job_title = $lastJob->title ?? null;
        $equipment->last_job_closed_at = $lastJob->closed_at ?? null;
        $equipment->last_job_closed_by = $lastJob->closed_by_name ?? null;

        $activeJob = DB::table('jobs')
            ->where('equipment_id', $equipment->id)
            ->where('status', '!=', 'CLOSED')
            ->orderByDesc('created_at')
            ->first(['job_number', 'title', 'status']);

        $equipment->active_job_number = $activeJob->job_number ?? null;
        $equipment->active_job_title = $activeJob->title ?? null;
        $equipment->active_job_status = $activeJob->status ?? null;

        if ($equipment->location_id ?? null) {
            $location = DB::table('locations')->find($equipment->location_id);
            $equipment->location_name = $location->name ?? null;
            $equipment->location_code = $location->code ?? null;
            $equipment->location_type = $location->location_type ?? null;
        }

        $equipment->component_count = DB::table('equipment_components')
            ->where('equipment_id', $equipment->id)
            ->where('active', 1)
            ->count();

        return $equipment;
    }

    public function index_backup(Request $request)
    {
        $query = DB::table('equipment')->where('active', 1);
        if ($request->filled('plant')) {
            $query->where('plant', $request->plant);
        }
        if ($request->filled('department')) {
            $query->where('department', $request->department);
        }

        $rows = $query->orderBy('department')->orderBy('name')->get()
            ->map(fn ($equipment) => $this->enrich($equipment, $request->shift ?: 'A'));

        return response()->json(['success' => true, 'data' => $rows]);
    }


    
    public function index_latest_backup(Request $request)
    {
        // Get latest operating log ID for each equipment
        $latestLogs = DB::table('equipment_operating_logs')
            ->select(
                'equipment_id',
                DB::raw('MAX(id) as latest_id')
            )
            ->groupBy('equipment_id');

        // Main equipment query
        $query = DB::table('equipment as e')
            ->leftJoinSub(
                $latestLogs,
                'latest_logs',
                function ($join) {
                    $join->on('e.id', '=', 'latest_logs.equipment_id');
                }
            )
            ->leftJoin(
                'equipment_operating_logs as operating_log',
                'operating_log.id',
                '=',
                'latest_logs.latest_id'
            )
            ->select(
                'e.*',
                'operating_log.actual_rpm as latest_actual_rpm',
                'operating_log.actual_load as latest_actual_load',
                'operating_log.logged_at as latest_logged_at',
                'operating_log.shift as latest_shift'
            )
            ->where('e.active', 1);

        // Existing Plant Filter
        if ($request->filled('plant')) {
            $query->where('e.plant', $request->plant);
        }

        // Existing Department Filter
        if ($request->filled('department')) {
            $query->where('e.department', $request->department);
        }

        // Get equipment and enrich existing data
        $rows = $query
            ->orderBy('e.department')
            ->orderBy('e.name')
            ->get()
            ->map(function ($equipment) use ($request) {

                $enriched = $this->enrich(
                    $equipment,
                    $request->shift ?: 'A'
                );

                // Preserve existing enriched data and append latest log data
                return (object) array_merge(
                    (array) $enriched,
                    [
                        'latest_actual_rpm'  => $equipment->latest_actual_rpm,
                        'latest_actual_load' => $equipment->latest_actual_load,
                        'latest_logged_at'   => $equipment->latest_logged_at,
                        'latest_shift'       => $equipment->latest_shift,
                    ]
                );
            });

        return response()->json([
            'success' => true,
            'data' => $rows
        ]);
    }


    public function index(Request $request)
    {
        // Get latest operating log ID for each equipment
        $latestLogs = DB::table('equipment_operating_logs')
            ->select(
                'equipment_id',
                DB::raw('MAX(id) as latest_id')
            )
            ->groupBy('equipment_id');

        // Main equipment query
        $query = DB::table('equipment as e')
            ->leftJoinSub(
                $latestLogs,
                'latest_logs',
                function ($join) {
                    $join->on('e.id', '=', 'latest_logs.equipment_id');
                }
            )
            ->leftJoin(
                'equipment_operating_logs as operating_log',
                'operating_log.id',
                '=',
                'latest_logs.latest_id'
            )
            ->select(
                'e.*',
                'operating_log.actual_rpm as latest_actual_rpm',
                'operating_log.actual_load as latest_actual_load',
                'operating_log.logged_at as latest_logged_at',
                'operating_log.shift as latest_shift'
            )
            ->where('e.active', 1);

        // Existing Plant Filter
        if ($request->filled('plant')) {
            $query->where('e.plant', $request->plant);
        }

        // Existing Department Filter
        if ($request->filled('department')) {
            $query->where('e.department', $request->department);
        }

        $equipmentList = $query
            ->orderBy('e.department')
            ->orderBy('e.name')
            ->get();

        $equipmentIds = $equipmentList->pluck('id')->filter()->values();

        // Latest STOPPED shift_log per equipment_id (with stop_reasons description/code)
        $latestStoppedLogsMap = collect();
        if ($equipmentIds->isNotEmpty()) {
            $latestStoppedIds = DB::table('shift_logs')
                ->whereIn('equipment_id', $equipmentIds)
                ->where('status', 'STOPPED')
                ->select('equipment_id', DB::raw('MAX(id) as max_id'))
                ->groupBy('equipment_id');

            $latestStoppedLogsMap = DB::table('shift_logs as sl')
                ->joinSub($latestStoppedIds, 'latest_sl', function ($join) {
                    $join->on('sl.id', '=', 'latest_sl.max_id');
                })
                ->leftJoin('stop_reasons as sr', 'sl.stop_reason_id', '=', 'sr.id')
                ->select(
                    'sl.equipment_id',
                    'sl.stop_reason_id',
                    'sl.remarks as stop_remarks',
                    'sr.description as stop_reason_desc',
                    'sr.code as stop_reason_code'
                )
                ->get()
                ->keyBy('equipment_id');
        }

        // Latest OPEN breakdown per equipment_id (fallback if stop_reason is on breakdown)
        $latestOpenBreakdownsMap = collect();
        if ($equipmentIds->isNotEmpty()) {
            $latestOpenBdIds = DB::table('breakdowns')
                ->whereIn('equipment_id', $equipmentIds)
                ->where('status', 'OPEN')
                ->select('equipment_id', DB::raw('MAX(id) as max_id'))
                ->groupBy('equipment_id');

            $latestOpenBreakdownsMap = DB::table('breakdowns as b')
                ->joinSub($latestOpenBdIds, 'latest_bd', function ($join) {
                    $join->on('b.id', '=', 'latest_bd.max_id');
                })
                ->leftJoin('stop_reasons as sr', 'b.initial_reason_id', '=', 'sr.id')
                ->select(
                    'b.equipment_id',
                    'b.initial_reason_id',
                    'sr.description as bd_reason_desc',
                    'sr.code as bd_reason_code'
                )
                ->get()
                ->keyBy('equipment_id');
        }

        // Get equipment and enrich existing data
        $rows = $equipmentList->map(function ($equipment) use (
            $request,
            $latestStoppedLogsMap,
            $latestOpenBreakdownsMap
        ) {
            $enriched = $this->enrich(
                $equipment,
                $request->shift ?: 'A'
            );

            $enrichedArr = (array) $enriched;
            $stopLog = $latestStoppedLogsMap->get($equipment->id);
            $openBd  = $latestOpenBreakdownsMap->get($equipment->id);

            $stopReason = ($stopLog->stop_reason_desc ?? null)
                ?: ($stopLog->stop_reason_code ?? null)
                ?: ($openBd->bd_reason_desc ?? null)
                ?: ($openBd->bd_reason_code ?? null)
                ?: ($enrichedArr['stop_reason'] ?? null)
                ?: null;

            $stopRemarks = ($stopLog->stop_remarks ?? null)
                ?: ($enrichedArr['stop_remarks'] ?? null)
                ?: ($enrichedArr['latest_remarks'] ?? null)
                ?: null;

            $stopReasonId = ($stopLog->stop_reason_id ?? null)
                ?: ($openBd->initial_reason_id ?? null)
                ?: ($enrichedArr['stop_reason_id'] ?? null)
                ?: null;

            // Preserve existing enriched data and append latest log + stop reason/remarks data
            return (object) array_merge(
                $enrichedArr,
                [
                    'latest_actual_rpm'  => $equipment->latest_actual_rpm,
                    'latest_actual_load' => $equipment->latest_actual_load,
                    'latest_logged_at'   => $equipment->latest_logged_at,
                    'latest_shift'       => $equipment->latest_shift,
                    'stop_reason_id'     => $stopReasonId,
                    'stop_reason'        => $stopReason,
                    'stop_remarks'       => $stopRemarks,
                    'latest_remarks'     => $stopRemarks,
                ]
            );
        });

        return response()->json([
            'success' => true,
            'data' => $rows
        ]);
    }


    public function show($id)
    {
        $equipment = DB::table('equipment')->find($id);
        if (! $equipment) {
            return response()->json(['success' => false, 'message' => 'Not found'], 404);
        }

        $equipment = $this->enrich($equipment);
        $equipment->components = DB::table('equipment_components')
            ->where('equipment_id', $id)
            ->where('active', 1)
            ->orderBy('component_code')
            ->get();

        return response()->json(['success' => true, 'data' => $equipment]);
    }

    public function byTag($tag)
    {
        $tag = trim((string) $tag);
        $equipment = DB::table('equipment')
            ->where('machine_tag', $tag)
            ->orWhere('asset_uid', $tag)
            ->orWhere('code', $tag)
            ->first();

        if (! $equipment) {
            return response()->json(['success' => false, 'message' => 'Equipment not found'], 404);
        }

        return response()->json(['success' => true, 'data' => $this->enrich($equipment)]);
    }

    private function nullableNumber(mixed $value): mixed
    {
        return ($value === '' || $value === null) ? null : $value;
    }

    private function payload(Request $request, ?Equipment $existing = null): array
    {
        $locationId = $request->has('location_id')
            ? $request->input('location_id')
            : $existing?->location_id;

        $locationText = $request->has('location')
            ? $request->input('location')
            : $existing?->location;

        if ($locationId) {
            $location = Location::find($locationId);
            if ($location) {
                $locationText = $location->name;
            }
        }

        $code = strtoupper(trim((string) $request->input('code', $existing?->code)));
        $plant = trim((string) $request->input('plant', $existing?->plant ?? '100TPD'));

        $assetUid = $request->has('asset_uid') ? trim((string) $request->input('asset_uid')) : (string) ($existing?->asset_uid ?? '');
        $machineTag = $request->has('machine_tag') ? trim((string) $request->input('machine_tag')) : (string) ($existing?->machine_tag ?? '');

        if ($assetUid === '' && $code !== '') {
            $assetUid = 'ASSET-'.Str::upper(Str::slug($plant.'-'.$code, '-'));
        }
        if ($machineTag === '' && $code !== '') {
            $machineTag = $code;
        }

        return [
            'code' => $code,
            'asset_uid' => $assetUid !== '' ? $assetUid : null,
            'machine_tag' => $machineTag !== '' ? $machineTag : null,
            'name' => $request->input('name', $existing?->name),
            'location' => $locationText ?: null,
            'location_id' => $locationId ?: null,
            'parent_equipment_id' => $request->has('parent_equipment_id') ? ($request->input('parent_equipment_id') ?: null) : $existing?->parent_equipment_id,
            'department' => $request->input('department', $existing?->department),
            'type' => $request->input('type', $existing?->type) ?: null,
            'capacity' => $request->input('capacity', $existing?->capacity) ?: null,
            'plant' => $plant ?: '100TPD',
            'hourly_entry' => $request->boolean('hourly_entry', (bool) ($existing?->hourly_entry ?? false)),
            'dashboard_list' => $request->boolean('dashboard_list',(bool) ($existing?->dashboard_list ?? false)),
            'kw_capacity' => $this->nullableNumber($request->has('kw_capacity') ? $request->input('kw_capacity') : $existing?->kw_capacity),
            'load_100pct' => $this->nullableNumber($request->has('load_100pct') ? $request->input('load_100pct') : $existing?->load_100pct),
            'critical_load' => $this->nullableNumber($request->has('critical_load') ? $request->input('critical_load') : $existing?->critical_load),
            'rpm_speed' => $this->nullableNumber($request->has('rpm_speed') ? $request->input('rpm_speed') : $existing?->rpm_speed),
            'load_speed' => $this->nullableNumber($request->has('load_speed') ? $request->input('load_speed') : $existing?->load_speed),
            'load_monitoring' => $request->boolean('load_monitoring', (bool) ($existing?->load_monitoring ?? false)),
            'serial_number' => $request->input('serial_number', $existing?->serial_number) ?: null,
            'manufacturer' => $request->input('manufacturer', $existing?->manufacturer) ?: null,
            'model_number' => $request->input('model_number', $existing?->model_number) ?: null,
            'commissioned_at' => $request->input('commissioned_at', $existing?->commissioned_at) ?: null,
            'critical_downtime_minutes' => $this->nullableNumber($request->has('critical_downtime_minutes') ? $request->input('critical_downtime_minutes') : $existing?->critical_downtime_minutes),
            'updated_at' => now(),
        ];
    }

    private function validateHierarchy(Request $request, ?int $id = null)
    {
        if ($request->filled('location_id')) {
            $location = Location::whereKey($request->location_id)->where('active', 1)->first();
            if (! $location) {
                return response()->json(['success' => false, 'message' => 'Invalid location_id'], 400);
            }

            $plantCode = $request->input('plant');
            if ($plantCode && $location->plant_id) {
                $locationPlantCode = DB::table('plants')->where('id', $location->plant_id)->value('code');
                if ($locationPlantCode && $locationPlantCode !== $plantCode) {
                    return response()->json(['success' => false, 'message' => 'Selected location belongs to a different plant'], 400);
                }
            }
        }

        $parentId = $request->input('parent_equipment_id');
        if (! $parentId) {
            return null;
        }
        if ($id && (int) $parentId === (int) $id) {
            return response()->json(['success' => false, 'message' => 'Equipment cannot be its own parent'], 400);
        }

        $parent = Equipment::find($parentId);
        if (! $parent) {
            return response()->json(['success' => false, 'message' => 'Parent equipment not found'], 400);
        }
        if ($request->filled('plant') && $parent->plant !== $request->plant) {
            return response()->json(['success' => false, 'message' => 'Parent equipment must belong to the same plant'], 400);
        }

        if ($id) {
            $cursor = $parent;
            while ($cursor) {
                if ((int) $cursor->id === (int) $id) {
                    return response()->json(['success' => false, 'message' => 'Equipment hierarchy cycle is not allowed'], 400);
                }
                $cursor = $cursor->parent_equipment_id ? Equipment::find($cursor->parent_equipment_id) : null;
            }
        }

        return null;
    }

    private function validateBase(Request $request, ?int $id = null)
    {
        if (! $request->input('code') || ! $request->input('name') || ! $request->input('department')) {
            return response()->json(['success' => false, 'message' => 'code, name, department required'], 400);
        }

        return $this->validateHierarchy($request, $id);
    }

    public function store(Request $request)
    {
        if ($response = $this->validateBase($request)) {
            return $response;
        }

        try {
            $data = $this->payload($request);
            $data['active'] = 1;
            $data['created_at'] = now();

            $equipment = Equipment::create($data);
            AuditService::log($request->user()->id, 'CREATE_EQUIPMENT', 'equipment', $equipment->id, $request->all(), $request->ip());

            return response()->json(['success' => true, 'data' => $equipment], 201);
        } catch (\Throwable $e) {
            $duplicate = str_contains(strtolower($e->getMessage()), 'duplicate');

            return response()->json([
                'success' => false,
                'message' => $duplicate ? 'Equipment code / asset UID / machine tag already exists' : $e->getMessage(),
            ], $duplicate ? 409 : 500);
        }
    }

    public function update(Request $request, $id)
    {
        $equipment = Equipment::find($id);
        if (! $equipment) {
            return response()->json(['success' => false, 'message' => 'Not found'], 404);
        }
        if ($response = $this->validateBase($request, (int) $id)) {
            return $response;
        }

        try {
            $equipment->fill($this->payload($request, $equipment))->save();
            AuditService::log($request->user()->id, 'UPDATE_EQUIPMENT', 'equipment', $equipment->id, $request->all(), $request->ip());

            return response()->json(['success' => true, 'data' => $equipment->fresh()]);
        } catch (\Throwable $e) {
            $duplicate = str_contains(strtolower($e->getMessage()), 'duplicate');

            return response()->json([
                'success' => false,
                'message' => $duplicate ? 'Equipment code / asset UID / machine tag already exists' : $e->getMessage(),
            ], $duplicate ? 409 : 500);
        }
    }

    public function destroy(Request $request, $id)
    {
        $equipment = Equipment::find($id);
        if (! $equipment) {
            return response()->json(['success' => false, 'message' => 'Equipment not found'], 404);
        }

        DB::transaction(function () use ($id) {
            $jobIds = DB::table('jobs')->where('equipment_id', $id)->pluck('id');
            if ($jobIds->isNotEmpty()) {
                DB::table('job_assignments')->whereIn('job_id', $jobIds)->delete();
                DB::table('job_history')->whereIn('job_id', $jobIds)->delete();
                DB::table('jobs')->whereIn('id', $jobIds)->delete();
            }

            DB::table('breakdowns')->where('equipment_id', $id)->delete();
            DB::table('shift_logs')->where('equipment_id', $id)->delete();
            DB::table('shift_monitoring_logs')->where('equipment_id', $id)->delete();
            DB::table('eq_monitoring_logs')->where('equipment_id', $id)->delete();
            DB::table('monitoring_logs')->where('equipment_id', $id)->delete();
            DB::table('load_logs')->where('equipment_id', $id)->delete();
            DB::table('equipment_monitoring_params')->where('equipment_id', $id)->delete();
            DB::table('equipment_monitoring_types')->where('equipment_id', $id)->delete();
            DB::table('equipment_monitoring')->where('equipment_id', $id)->delete();
            DB::table('equipment_components')->where('equipment_id', $id)->delete();
            DB::table('equipment')->where('id', $id)->delete();
        });

        AuditService::log($request->user()->id, 'DELETE_EQUIPMENT', 'equipment', (int) $id, ['deleted_equipment' => $equipment->toArray()], $request->ip());

        return response()->json(['success' => true, 'message' => 'Equipment deleted successfully']);
    }
}
