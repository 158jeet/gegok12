<?php

namespace App\Http\Controllers\Tagore;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class BiometricDeviceController extends Controller
{
    private function context(Request $request): array
    {
        $userId = (int) $request->user()->id;
        $roles = DB::table('tagore_user_roles as ur')
            ->join('tagore_roles as r', 'r.id', '=', 'ur.role_id')
            ->where('ur.user_id', $userId)->where('ur.status', 'active')
            ->where(fn ($query) => $query->whereNull('ur.start_date')->orWhereDate('ur.start_date', '<=', today()))
            ->where(fn ($query) => $query->whereNull('ur.end_date')->orWhereDate('ur.end_date', '>=', today()))
            ->pluck('r.code')->unique()->values();
        $groupWide = $roles->intersect(['OWNER', 'ACCOUNTS'])->isNotEmpty();
        abort_unless($groupWide || $roles->contains('PRINCIPAL') || $roles->contains('HR'), 403);

        $ids = $groupWide
            ? DB::table('tagore_institutions')->where('status', 'active')->whereNull('deleted_at')->pluck('id')->map(fn ($id) => (int) $id)->all()
            : DB::table('tagore_user_roles as ur')->join('tagore_roles as r', 'r.id', '=', 'ur.role_id')
                ->where('ur.user_id', $userId)->where('ur.status', 'active')
                ->whereIn('r.code', ['PRINCIPAL', 'HR'])->whereNotNull('ur.institution_id')
                ->where(fn ($query) => $query->whereNull('ur.start_date')->orWhereDate('ur.start_date', '<=', today()))
                ->where(fn ($query) => $query->whereNull('ur.end_date')->orWhereDate('ur.end_date', '>=', today()))
                ->pluck('ur.institution_id')->map(fn ($id) => (int) $id)->unique()->values()->all();

        return [$userId, $roles, $ids];
    }

    public function index(Request $request): View
    {
        [$userId, $roles, $institutionIds] = $this->context($request);
        $institutions = DB::table('tagore_institutions')->whereIn('id', $institutionIds)
            ->where('status', 'active')->orderBy('display_name')->get(['id', 'display_name']);
        $devices = DB::table('tagore_biometric_devices as d')
            ->join('tagore_institutions as i', 'i.id', '=', 'd.institution_id')
            ->whereIn('d.institution_id', $institutionIds)
            ->orderBy('i.display_name')->orderBy('d.site_name')->orderBy('d.device_name')
            ->get(['d.id', 'd.institution_id', 'i.display_name as institution', 'd.site_name',
                'd.device_name', 'd.model', 'd.serial_number', 'd.firmware_version',
                'd.connection_mode', 'd.sync_enabled', 'd.last_synced_at', 'd.last_success_at', 'd.last_error']);
        $mappings = DB::table('tagore_biometric_employee_mappings as m')
            ->join('tagore_biometric_devices as d', 'd.id', '=', 'm.device_id')
            ->join('users as u', 'u.id', '=', 'm.employee_id')
            ->whereIn('d.institution_id', $institutionIds)->where('m.active', true)
            ->orderBy('d.device_name')->orderBy('m.device_user_id')
            ->get(['m.id', 'm.device_id', 'm.device_user_id', 'm.employee_id', 'u.name as employee_name', 'd.device_name']);
        return view('tagore.biometric.index', compact('institutions', 'devices', 'mappings', 'roles'));
    }

    public function store(Request $request)
    {
        [$userId, $roles, $institutionIds] = $this->context($request);
        $data = $request->validate([
            'institution_id' => ['required', 'integer'],
            'site_name' => ['required', 'string', 'max:120'],
            'device_name' => ['required', 'string', 'max:120'],
            'serial_number' => ['required', 'string', 'max:120', 'unique:tagore_biometric_devices,serial_number'],
            'firmware_version' => ['nullable', 'string', 'max:80'],
        ]);
        abort_unless(in_array((int) $data['institution_id'], $institutionIds, true), 403);
        DB::table('tagore_biometric_devices')->insert([
            'institution_id' => $data['institution_id'],
            'site_name' => $data['site_name'],
            'device_name' => $data['device_name'],
            'model' => 'Realtime T304F',
            'serial_number' => trim($data['serial_number']),
            'firmware_version' => $data['firmware_version'] ?? null,
            // Do not assume a vendor endpoint or protocol. Configure only after
            // the owner supplies the device/API contract and credentials.
            'connection_mode' => 'manual_config',
            'api_endpoint' => null,
            'poll_interval_minutes' => 5,
            'timezone' => 'Asia/Kolkata',
            'sync_enabled' => false,
            'created_by' => $userId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        return back()->with('success', 'Device registered. Sync remains disabled until vendor credentials and a read-only connection test are configured.');
    }

    public function mapEmployee(Request $request, int $deviceId)
    {
        [$userId, $roles, $institutionIds] = $this->context($request);
        $device = DB::table('tagore_biometric_devices')->where('id', $deviceId)
            ->whereIn('institution_id', $institutionIds)->first();
        abort_unless($device, 404);
        $data = $request->validate([
            'device_user_id' => ['required', 'string', 'max:100'],
            'employee_id' => ['required', 'integer'],
        ]);
        $schoolId = DB::table('tagore_institutions')->where('id', $device->institution_id)->value('school_id');
        $employee = DB::table('users')->where('id', $data['employee_id'])
            ->where('school_id', $schoolId)->whereNull('deleted_at')
            ->whereExists(function ($roles) use ($device) {
                $roles->selectRaw('1')
                    ->from('tagore_user_roles as ur')
                    ->join('tagore_roles as r', 'r.id', '=', 'ur.role_id')
                    ->whereColumn('ur.user_id', 'users.id')
                    ->where('ur.status', 'active')
                    ->whereIn('r.code', ['OWNER', 'PRINCIPAL', 'COORDINATOR', 'TEACHER', 'ACCOUNTS', 'CASHIER', 'HR', 'FEE_EDITOR'])
                    ->where(fn ($query) => $query->whereNull('ur.start_date')->orWhereDate('ur.start_date', '<=', today()))
                    ->where(fn ($query) => $query->whereNull('ur.end_date')->orWhereDate('ur.end_date', '>=', today()))
                    ->where(function ($scope) use ($device) {
                        $scope->where('ur.institution_id', $device->institution_id)
                            ->orWhere(fn ($owner) => $owner->where('r.code', 'OWNER')->whereNull('ur.institution_id'));
                    });
            })->first();
        abort_unless($employee, 422, 'Select an active staff/employee account belonging to this institution.');
        DB::table('tagore_biometric_employee_mappings')->updateOrInsert(
            ['device_id' => $deviceId, 'device_user_id' => trim($data['device_user_id'])],
            ['employee_id' => $employee->id, 'active' => true, 'created_by' => $userId, 'updated_at' => now(), 'created_at' => now()]
        );
        return back()->with('success', 'Device user mapped to the institution employee.');
    }
}
