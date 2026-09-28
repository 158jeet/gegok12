<?php

namespace App\Services\Tagore;

use App\Models\Attendance;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class SyncService
{
    public function registerDevice(int $userId, array $data): void
    {
        DB::table('tagore_sync_devices')->updateOrInsert(
            ['user_id' => $userId, 'device_id' => $data['device_id']],
            [
                'platform' => $data['platform'] ?? null,
                'app_version' => $data['app_version'] ?? null,
                'last_seen_at' => now(),
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );
    }

    public function process(int $userId, array $events): array
    {
        $accepted = [];
        $failed = [];

        foreach ($events as $event) {
            $uuid = (string) ($event['event_uuid'] ?? Str::uuid());

            if (DB::table('tagore_sync_events')->where('event_uuid', $uuid)->exists()) {
                $accepted[] = ['event_uuid' => $uuid, 'status' => 'already_synced'];
                continue;
            }

            $row = [
                'user_id' => $userId,
                'device_id' => (string) $event['device_id'],
                'event_uuid' => $uuid,
                'entity_type' => (string) $event['entity_type'],
                'operation' => (string) $event['operation'],
                'payload' => json_encode($event['payload'] ?? [], JSON_THROW_ON_ERROR),
                'occurred_at' => $event['occurred_at'] ?? now(),
                'created_at' => now(),
                'updated_at' => now(),
            ];

            try {
                DB::transaction(function () use ($row, $event, $uuid, $userId) {
                    DB::table('tagore_sync_events')->insert($row);
                    $this->apply($userId, $event);
                    DB::table('tagore_sync_events')->where('event_uuid', $uuid)->update([
                        'status' => 'synced',
                        'processed_at' => now(),
                        'updated_at' => now(),
                    ]);
                });
                $accepted[] = ['event_uuid' => $uuid, 'status' => 'synced'];
            } catch (\Throwable $e) {
                DB::table('tagore_sync_events')->where('event_uuid', $uuid)->updateOrInsert(
                    ['event_uuid' => $uuid],
                    array_merge($row, ['status' => 'failed', 'error_message' => $e->getMessage(), 'updated_at' => now()])
                );
                $failed[] = ['event_uuid' => $uuid, 'status' => 'failed', 'message' => $e->getMessage()];
            }
        }

        DB::table('tagore_sync_devices')
            ->where('user_id', $userId)
            ->whereIn('device_id', collect($events)->pluck('device_id')->filter()->unique()->values())
            ->update(['last_sync_at' => now(), 'last_seen_at' => now(), 'updated_at' => now()]);

        return ['accepted' => $accepted, 'failed' => $failed, 'server_time' => now()->toIso8601String()];
    }

    private function apply(int $userId, array $event): void
    {
        $payload = $event['payload'] ?? [];

        if (($event['entity_type'] ?? '') === 'attendance' && ($event['operation'] ?? '') === 'upsert') {
            $studentId = (int) ($payload['user_id'] ?? 0);
            abort_unless($studentId > 0, 422, 'Attendance student is required.');

            $schoolId = (int) auth()->user()->school_id;
            abort_unless(DB::table('users')->where('id', $studentId)->where('school_id', $schoolId)->exists(), 403);

            $academicYearId = (int) ($payload['academic_year_id'] ?? 0);
            abort_unless($academicYearId > 0, 422, 'Academic year is required.');

            $record = Attendance::updateOrCreate(
                [
                    'school_id' => $schoolId,
                    'academic_year_id' => $academicYearId,
                    'standardLink_id' => (int) ($payload['standardLink_id'] ?? 0),
                    'user_id' => $studentId,
                    'date' => $payload['date'],
                    'session' => (string) ($payload['session'] ?? 'regular'),
                ],
                [
                    'status' => (int) ($payload['status'] ?? 1),
                    'reason_id' => $payload['reason_id'] ?? null,
                    'remarks' => $payload['remarks'] ?? null,
                    'recorded_by' => $userId,
                ]
            );

            return;
        }

        throw new RuntimeException('Unsupported offline sync entity: '.($event['entity_type'] ?? 'unknown'));
    }

    public function bootstrap(int $userId): array
    {
        $user = DB::table('users')->where('id', $userId)->first(['id', 'school_id', 'name', 'email']);
        $students = DB::table('users')
            ->where('school_id', $user->school_id)
            ->where('usergroup_id', 5)
            ->whereNull('deleted_at')
            ->orderBy('name')
            ->limit(2000)
            ->get(['id', 'name', 'email', 'registration_no']);

        return [
            'user' => $user,
            'students' => $students,
            'server_time' => now()->toIso8601String(),
            'sync_version' => 1,
        ];
    }
}
