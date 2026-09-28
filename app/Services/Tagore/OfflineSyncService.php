<?php

namespace App\Services\Tagore;

use App\Models\Attendance;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class OfflineSyncService
{
    public function registerDevice(User $user, array $data): array
    {
        $device = DB::table('tagore_sync_devices')->updateOrInsert(
            ['device_uuid' => $data['device_uuid']],
            [
                'user_id' => $user->id,
                'school_id' => $user->school_id,
                'platform' => $data['platform'] ?? 'windows',
                'app_version' => $data['app_version'] ?? null,
                'last_seen_at' => now(),
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );

        $deviceRow = DB::table('tagore_sync_devices')->where('device_uuid', $data['device_uuid'])->first();
        DB::table('tagore_sync_cursors')->insertOrIgnore([
            'device_id' => $deviceRow->id,
            'last_server_change_id' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return ['device_id' => $deviceRow->id, 'device_uuid' => $deviceRow->device_uuid];
    }

    public function bootstrap(User $user, string $deviceUuid): array
    {
        $device = DB::table('tagore_sync_devices')
            ->where('device_uuid', $deviceUuid)
            ->where('user_id', $user->id)
            ->first();

        abort_unless($device, 403);

        $academicYear = DB::table('academic_years')
            ->where('school_id', $user->school_id)
            ->where('status', 'active')
            ->orderByDesc('id')
            ->first();

        $links = DB::table('class_teacher_links')
            ->where('school_id', $user->school_id)
            ->when($academicYear, fn ($q) => $q->where('academic_year_id', $academicYear->id))
            ->where('teacher_id', $user->id)
            ->get();

        $standardLinkIds = $links->pluck('standardLink_id')->filter()->unique()->values();

        $students = DB::table('student_academics as sa')
            ->join('users as u', 'u.id', '=', 'sa.user_id')
            ->where('sa.school_id', $user->school_id)
            ->when($academicYear, fn ($q) => $q->where('sa.academic_year_id', $academicYear->id))
            ->whereIn('sa.standardLink_id', $standardLinkIds->all())
            ->where('u.status', 'active')
            ->select('u.id', 'u.name', 'u.email', 'sa.standardLink_id', 'sa.roll_number')
            ->orderBy('sa.standardLink_id')
            ->orderBy('sa.roll_number')
            ->get();

        $today = now()->toDateString();
        $attendance = Attendance::where('school_id', $user->school_id)
            ->when($academicYear, fn ($q) => $q->where('academic_year_id', $academicYear->id))
            ->whereDate('date', $today)
            ->whereIn('user_id', $students->pluck('id'))
            ->get(['id', 'user_id', 'standardLink_id', 'date', 'session', 'status', 'remarks'])
            ->values();

        DB::table('tagore_sync_devices')->where('id', $device->id)->update(['last_seen_at' => now(), 'updated_at' => now()]);

        return [
            'server_time' => now()->toIso8601String(),
            'academic_year' => $academicYear,
            'teacher' => ['id' => $user->id, 'name' => $user->name, 'school_id' => $user->school_id],
            'classes' => $links,
            'students' => $students,
            'today_attendance' => $attendance,
        ];
    }

    public function push(User $user, string $deviceUuid, array $changes): array
    {
        $device = DB::table('tagore_sync_devices')
            ->where('device_uuid', $deviceUuid)
            ->where('user_id', $user->id)
            ->first();
        abort_unless($device, 403);

        $results = [];
        foreach ($changes as $change) {
            $existing = DB::table('tagore_sync_changes')->where('client_change_id', $change['client_change_id'])->first();
            if ($existing) {
                $results[] = ['client_change_id' => $change['client_change_id'], 'status' => $existing->status, 'server_change_id' => $existing->id];
                continue;
            }

            try {
                DB::transaction(function () use ($user, $device, $change) {
                    $this->applyChange($user, $change);

                    DB::table('tagore_sync_changes')->insert([
                        'user_id' => $user->id,
                        'school_id' => $user->school_id,
                        'device_id' => $device->id,
                        'client_change_id' => $change['client_change_id'],
                        'entity' => $change['entity'],
                        'operation' => $change['operation'],
                        'entity_id' => $change['entity_id'] ?? null,
                        'payload' => json_encode($change['payload'], JSON_THROW_ON_ERROR),
                        'status' => 'applied',
                        'client_occurred_at' => $change['occurred_at'] ?? null,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                });

                $row = DB::table('tagore_sync_changes')->where('client_change_id', $change['client_change_id'])->first();
                $results[] = ['client_change_id' => $change['client_change_id'], 'status' => 'applied', 'server_change_id' => $row->id];
            } catch (\Throwable $e) {
                $id = DB::table('tagore_sync_changes')->insertGetId([
                    'user_id' => $user->id,
                    'school_id' => $user->school_id,
                    'device_id' => $device->id,
                    'client_change_id' => $change['client_change_id'],
                    'entity' => $change['entity'],
                    'operation' => $change['operation'],
                    'entity_id' => $change['entity_id'] ?? null,
                    'payload' => json_encode($change['payload'], JSON_THROW_ON_ERROR),
                    'status' => 'failed',
                    'error_message' => Str::limit($e->getMessage(), 1000),
                    'client_occurred_at' => $change['occurred_at'] ?? null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $results[] = ['client_change_id' => $change['client_change_id'], 'status' => 'failed', 'server_change_id' => $id, 'error' => Str::limit($e->getMessage(), 300)];
            }
        }

        DB::table('tagore_sync_devices')->where('id', $device->id)->update(['last_seen_at' => now(), 'updated_at' => now()]);
        return $results;
    }

    private function applyChange(User $user, array $change): void
    {
        if (($change['entity'] ?? '') !== 'attendance' || ($change['operation'] ?? '') !== 'upsert') {
            throw ValidationException::withMessages(['entity' => 'Only attendance upserts are currently enabled for offline sync.']);
        }

        $payload = $change['payload'];
        foreach (['student_id', 'standard_link_id', 'date', 'session', 'status'] as $key) {
            if (!array_key_exists($key, $payload)) {
                throw ValidationException::withMessages([$key => 'Required for attendance sync.']);
            }
        }

        abort_unless(in_array((int) $user->usergroup_id, [5, 3, 4, 2], true), 403);

        $student = DB::table('student_academics')
            ->where('school_id', $user->school_id)
            ->where('user_id', $payload['student_id'])
            ->where('standardLink_id', $payload['standard_link_id'])
            ->first();
        abort_unless($student, 403);

        $academicYearId = $student->academic_year_id;
        Attendance::updateOrCreate(
            [
                'school_id' => $user->school_id,
                'academic_year_id' => $academicYearId,
                'standardLink_id' => $payload['standard_link_id'],
                'user_id' => $payload['student_id'],
                'date' => $payload['date'],
                'session' => $payload['session'],
            ],
            [
                'status' => (int) $payload['status'],
                'reason_id' => $payload['reason_id'] ?? 0,
                'remarks' => $payload['remarks'] ?? null,
                'recorded_by' => $user->id,
            ]
        );
    }
}