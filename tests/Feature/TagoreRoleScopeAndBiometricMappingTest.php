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

    public function test_central_accounts_payroll_scope_is_group_wide(): void
    {
        $institutions = DB::table('tagore_institutions')->where('status', 'active')->orderBy('id')->limit(2)->get();
        $this->assertCount(2, $institutions);

        $accounts = User::factory()->create([
            'name' => 'Central Accounts',
            'email' => 'central-accounts-' . bin2hex(random_bytes(4)) . '@example.invalid',
            'school_id' => $institutions[0]->school_id,
            'usergroup_id' => 4,
        ]);
        $accountsRoleId = DB::table('tagore_roles')->where('code', 'ACCOUNTS')->value('id');
        DB::table('tagore_user_roles')->insert([
            'user_id' => $accounts->id,
            'role_id' => $accountsRoleId,
            'institution_id' => null,
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($accounts)->get(route('tagore.payroll.index'))->assertOk();
        foreach ($institutions as $institution) {
            $response->assertSee($institution->display_name);
        }
    }

    public function test_owner_device_registration_keeps_sync_disabled_without_vendor_contract(): void
    {
        $owner = User::query()->where('email', 'owner@tagore-demo.local')->firstOrFail();
        $institution = DB::table('tagore_institutions')
            ->where('school_id', $owner->school_id)->where('status', 'active')->firstOrFail();

        $this->actingAs($owner)
            ->post(route('tagore.biometric.store'), [
                'institution_id' => $institution->id,
                'site_name' => 'Main Campus',
                'device_name' => 'Main Gate T304F',
                'serial_number' => 'QA-' . strtoupper(bin2hex(random_bytes(6))),
                'firmware_version' => 'Unconfirmed',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $device = DB::table('tagore_biometric_devices')->where('device_name', 'Main Gate T304F')->first();
        $this->assertNotNull($device);
        $this->assertFalse((bool) $device->sync_enabled);
        $this->assertSame('manual_config', $device->connection_mode);
        $this->assertNull($device->api_endpoint);
        $this->assertNull($device->encrypted_credentials);
    }

    public function test_hr_manager_sees_only_devices_for_the_assigned_institution(): void
    {
        $owner = User::query()->where('email', 'owner@tagore-demo.local')->firstOrFail();
        $institutions = DB::table('tagore_institutions')->where('status', 'active')->orderBy('id')->limit(2)->get();
        $this->assertCount(2, $institutions);

        $hr = User::factory()->create([
            'name' => 'Tagore HR Manager',
            'email' => 'hr-scope-' . bin2hex(random_bytes(4)) . '@example.invalid',
            'school_id' => $institutions[0]->school_id,
            'usergroup_id' => 4,
        ]);
        $hrRoleId = DB::table('tagore_roles')->where('code', 'HR')->value('id');
        DB::table('tagore_user_roles')->insert([
            'user_id' => $hr->id,
            'role_id' => $hrRoleId,
            'institution_id' => $institutions[0]->id,
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        foreach ($institutions as $index => $institution) {
            DB::table('tagore_biometric_devices')->insert([
                'institution_id' => $institution->id,
                'site_name' => 'Site ' . $index,
                'device_name' => 'HR Scope Device ' . $index,
                'model' => 'Realtime T304F',
                'serial_number' => 'HR-' . strtoupper(bin2hex(random_bytes(6))),
                'connection_mode' => 'manual_config',
                'sync_enabled' => false,
                'created_by' => $owner->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $this->actingAs($hr)->get(route('tagore.biometric.index'))
            ->assertOk()
            ->assertSee('HR Scope Device 0')
            ->assertDontSee('HR Scope Device 1');
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
