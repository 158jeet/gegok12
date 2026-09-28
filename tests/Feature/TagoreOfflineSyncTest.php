<?php

namespace Tests\Feature;

use App\Models\School;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TagoreOfflineSyncTest extends TestCase
{
    use RefreshDatabase;

    public function test_offline_device_can_register_and_bootstrap(): void
    {
        $school = School::factory()->create();
        $teacher = User::factory()->teacher()->for($school)->create();

        $this->actingAs($teacher, 'sanctum')
            ->postJson('/api/v2/tagore/offline/device', [
                'device_id' => 'test-device-001',
                'platform' => 'windows',
                'app_version' => '1.0.0',
            ])
            ->assertOk()
            ->assertJson(['ok' => true]);

        $this->assertDatabaseHas('tagore_sync_devices', [
            'user_id' => $teacher->id,
            'device_id' => 'test-device-001',
            'platform' => 'windows',
        ]);

        $this->actingAs($teacher, 'sanctum')
            ->getJson('/api/v2/tagore/offline/bootstrap')
            ->assertOk()
            ->assertJsonStructure(['user', 'students', 'server_time', 'sync_version']);
    }

    public function test_sync_is_idempotent_and_records_failures(): void
    {
        $school = School::factory()->create();
        $teacher = User::factory()->teacher()->for($school)->create();
        $event = [
            'event_uuid' => '11111111-1111-4111-8111-111111111111',
            'device_id' => 'test-device-002',
            'entity_type' => 'future_module',
            'operation' => 'upsert',
            'payload' => ['example' => true],
        ];

        $response = $this->actingAs($teacher, 'sanctum')
            ->postJson('/api/v2/tagore/sync', ['events' => [$event]])
            ->assertOk()
            ->json();

        $this->assertSame('failed', $response['failed'][0]['status']);
        $this->assertDatabaseHas('tagore_sync_events', [
            'event_uuid' => $event['event_uuid'],
            'status' => 'failed',
        ]);

        $this->actingAs($teacher, 'sanctum')
            ->postJson('/api/v2/tagore/sync', ['events' => [$event]])
            ->assertOk();

        $this->assertSame(1, DB::table('tagore_sync_events')->where('event_uuid', $event['event_uuid'])->count());
    }
}
