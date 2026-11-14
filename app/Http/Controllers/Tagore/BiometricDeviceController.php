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
            ->pluck('r.code')->unique()->values();
        abort_unless($roles->intersect(['OWNER', 'ACCOUNTS'])->isNotEmpty() || $roles->intersect(['PRINCIPAL', 'HR'])->isNotEmpty(), 403);

        $ids = $roles->intersect(['OWNER', 'ACCOUNTS'])->isNotEmpty()
            ? DB::table('tagore_institutions')->where('status', 'active')->pluck('id')->map(fn ($id) => (int) $id)->all()
            : DB::table('tagore_user_roles')->where('user_id', $userId)->where('status', 'active')
                ->whereNotNull('institution_id')->pluck('institution_id')->map(fn ($id) => (int) $id)->unique()->values()->all();

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
            'connection_mode' => 'realtime_api',
            'api_endpoint' => 'https://apirealtime.com/RealtimeBioConnect.asmx',
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
            ->where('school_id', $schoolId)->whereNull('deleted_at')->first();
        abort_unless($employee, 422, 'Employee must belong to the same institution.');
        DB::table('tagore_biometric_employee_mappings')->updateOrInsert(
            ['device_id' => $deviceId, 'device_user_id' => trim($data['device_user_id'])],
            ['employee_id' => $employee->id, 'active' => true, 'created_by' => $userId, 'updated_at' => now(), 'created_at' => now()]
        );
        return back()->with('success', 'Device user mapped to the institution employee.');
    }
}
