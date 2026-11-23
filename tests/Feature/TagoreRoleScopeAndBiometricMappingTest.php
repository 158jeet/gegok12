<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TagoreRoleScopeAndBiometricMappingTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        DB::beginTransaction();
    }

    protected function tearDown(): void
    {
        if (DB::transactionLevel() > 0) {
            DB::rollBack();
        }
        parent::tearDown();
    }

    public function test_coordinator_cannot_view_student_fees_without_explicit_class_fee_scope(): void
    {
        $owner = User::query()->where('email', 'owner@tagore-demo.local')->firstOrFail();
        $institution = DB::table('tagore_institutions')
            ->where('school_id', $owner->school_id)->where('status', 'active')->firstOrFail();
        $student = User::query()->where('school_id', $institution->school_id)
            ->where('usergroup_id', 6)->whereNull('deleted_at')->firstOrFail();

        $coordinator = User::factory()->create([
            'name' => 'Coordinator Scope Acceptance',
            'email' => 'coordinator-scope-' . bin2hex(random_bytes(4)) . '@example.invalid',
            'school_id' => $institution->school_id,
            'usergroup_id' => 4,
        ]);
        $roleId = DB::table('tagore_roles')->where('code', 'COORDINATOR')->value('id');
        DB::table('tagore_user_roles')->insert([
            'user_id' => $coordinator->id,
            'role_id' => $roleId,
            'institution_id' => $institution->id,
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($coordinator)
            ->get(route('tagore.fees.student', ['studentId' => $student->id]))
            ->assertForbidden();
    }

    public function test_biometric_mapping_rejects_a_student_account(): void
    {
        $owner = User::query()->where('email', 'owner@tagore-demo.local')->firstOrFail();
        $institution = DB::table('tagore_institutions')
            ->where('school_id', $owner->school_id)->where('status', 'active')->firstOrFail();
        $student = User::query()->where('school_id', $institution->school_id)
            ->where('usergroup_id', 6)->whereNull('deleted_at')->firstOrFail();

        $deviceId = DB::table('tagore_biometric_devices')->insertGetId([
            'institution_id' => $institution->id,
            'site_name' => 'Acceptance Site',
            'device_name' => 'Acceptance T304F',
            'model' => 'Realtime T304F',
            'serial_number' => 'QA-' . strtoupper(bin2hex(random_bytes(6))),
            'connection_mode' => 'manual_config',
            'sync_enabled' => false,
            'created_by' => $owner->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($owner)
            ->post(route('tagore.biometric.mapping.store', ['deviceId' => $deviceId]), [
                'device_user_id' => 'STUDENT-001',
                'employee_id' => $student->id,
            ])
            ->assertStatus(422);

        $this->assertDatabaseMissing('tagore_biometric_employee_mappings', [
            'device_id' => $deviceId,
            'employee_id' => $student->id,
        ]);
    }
}
