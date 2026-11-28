<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Tagore\FeeService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class TagoreCashierAccessTest extends TestCase
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

    public function test_cashier_permission_matrix_is_limited_to_collections_and_admissions(): void
    {
        $roleId = DB::table('tagore_roles')->where('code', 'CASHIER')->where('status', 'active')->value('id');
        $this->assertNotNull($roleId, 'Cashier role must be seeded and active.');

        $permissions = DB::table('tagore_role_permissions as rp')
            ->join('tagore_permissions as p', 'p.id', '=', 'rp.permission_id')
            ->where('rp.role_id', $roleId)
            ->pluck('p.code')
            ->all();

        foreach (['dashboard.view', 'fee.view', 'fee.collect', 'payment.view', 'admission.view', 'admission.manage'] as $permission) {
            $this->assertContains($permission, $permissions, "Cashier must have {$permission}.");
        }

        foreach (['user.manage', 'scope.manage', 'fee.manage', 'fee.edit', 'payment.refund', 'payment.reconcile', 'report.export'] as $permission) {
            $this->assertNotContains($permission, $permissions, "Cashier must not have {$permission}.");
        }
    }

    public function test_owner_can_create_an_institution_scoped_cashier_and_issue_one_time_credentials(): void
    {
        $owner = User::query()->where('email', 'owner@tagore-demo.local')->firstOrFail();
        $institution = DB::table('tagore_institutions')
            ->where('school_id', $owner->school_id)
            ->where('status', 'active')
            ->firstOrFail();
        $cashierRoleId = DB::table('tagore_roles')->where('code', 'CASHIER')->where('status', 'active')->value('id');

        $this->assertNotNull($cashierRoleId, 'Cashier role must be seeded and active.');
        $this->actingAs($owner)->get(route('tagore.admin'))->assertOk()->assertSee('Cashier');

        $email = 'cashier-created-' . bin2hex(random_bytes(4)) . '@tagore-demo.local';

        $this->actingAs($owner)->post(route('tagore.admin.users.store'), [
            'name' => 'Acceptance Cashier',
            'email' => $email,
            'mobile_no' => (string) (9000000000 + random_int(0, 999999999)),
            'role_id' => $cashierRoleId,
            'institution_id' => $institution->id,
        ])->assertRedirect()->assertSessionHas('issued_credentials.email', $email);

        $cashier = User::query()->where('email', $email)->firstOrFail();
        $this->assertSame(13, (int) $cashier->usergroup_id);
        $this->assertDatabaseHas('tagore_user_roles', [
            'user_id' => $cashier->id,
            'role_id' => $cashierRoleId,
            'institution_id' => $institution->id,
            'status' => 'active',
        ]);

        $issued = session('issued_credentials');
        $this->assertNotEmpty($issued['password'] ?? null);
        $this->assertTrue(Hash::check($issued['password'], $cashier->password));

        Auth::logout();
        $this->post(route('login'), [
            'email' => $email,
            'password' => $issued['password'],
        ])->assertRedirect(route('tagore.dashboard'));
        $this->assertAuthenticatedAs($cashier);

        $this->get(route('tagore.dashboard'))->assertOk()->assertSee('tagore-logout')->assertSee('Logout');
        $this->actingAs($cashier)->get(route('tagore.admin'))->assertForbidden();
        $this->actingAs($cashier)->get(route('tagore.tasks.index'))->assertForbidden();
        $this->actingAs($cashier)->get(route('tagore.operations.index'))->assertForbidden();
        $this->actingAs($cashier)->get(route('tagore.reports.index'))->assertForbidden();
        $this->actingAs($cashier)->get(route('tagore.communication.index'))->assertForbidden();
        $this->actingAs($cashier)->get(route('tagore.fees.manage'))->assertForbidden();
        $this->actingAs($cashier)->get(route('tagore.fees.editor'))->assertForbidden();
    }

    public function test_cashier_can_collect_fees_and_work_admissions_only_inside_assigned_institution(): void
    {
        $owner = User::query()->where('email', 'owner@tagore-demo.local')->firstOrFail();
        $cashier = User::query()->where('email', 'cashier@tagore-demo.local')->firstOrFail();
        $institution = DB::table('tagore_institutions')
            ->where('school_id', $cashier->school_id)
            ->where('status', 'active')
            ->firstOrFail();
        $student = User::query()
            ->where('email', 'student@tagore-demo.local')
            ->where('school_id', $institution->school_id)
            ->firstOrFail();

        $feeService = app(FeeService::class);
        $obligationId = $feeService->createObligation(
            (int) $student->id,
            (int) $institution->id,
            [
                'due_date' => now()->addDay()->toDateString(),
                'frequency' => 'one_time',
                'gross_amount' => 100.00,
                'items' => [[
                    'fee_head' => 'Cashier acceptance tuition',
                    'code' => 'QA-CASHIER-TUITION',
                    'gross_amount' => 100.00,
                    'discount_amount' => 0,
                    'concession_amount' => 0,
                ]],
            ],
            (int) $owner->id,
        );

        $this->actingAs($cashier)->get(route('tagore.fees.accounts'))->assertOk()->assertSee('Collect fee')->assertSee('Record payment')->assertDontSee('Reconcile settlement');
        $this->actingAs($cashier)->get(route('tagore.fees.wallet'))->assertOk();

        $this->actingAs($cashier)->post(route('tagore.fees.payment', ['studentId' => $student->id]), [
            'amount' => 35.00,
            'payment_mode' => 'cash',
            'reference_number' => 'QA-CASHIER-' . bin2hex(random_bytes(4)),
        ])->assertRedirect()->assertSessionHas('success');

        $updatedObligation = DB::table('tagore_fee_obligations')->where('id', $obligationId)->first();
        $this->assertNotNull($updatedObligation);
        $this->assertEquals(35.00, (float) $updatedObligation->paid_amount);
        $this->assertEquals(65.00, (float) $updatedObligation->outstanding_amount);
        $this->assertSame('partial', $updatedObligation->status);
        $this->assertDatabaseHas('tagore_audit_events', [
            'user_id' => $cashier->id,
            'action' => 'FEE_PAYMENT_RECORDED',
            'entity_type' => 'tagore_payments',
        ]);

        $this->actingAs($cashier)->get(route('tagore.admissions.index'))->assertOk();
        $this->actingAs($cashier)->post(route('tagore.admissions.store'), [
            'institution_id' => $institution->id,
            'student_name' => 'Cashier Admission Acceptance',
            'parent_name' => 'Acceptance Guardian',
            'mobile' => '9000000002',
            'class_name' => 'Class 6',
            'source' => 'walk_in',
            'notes' => 'Created by cashier acceptance test.',
        ])->assertRedirect();

        $this->assertDatabaseHas('tagore_admission_leads', [
            'institution_id' => $institution->id,
            'student_name' => 'Cashier Admission Acceptance',
        ]);

        $this->actingAs($cashier)->post(route('tagore.fees.wallet.credit'), [
            'institution_id' => $institution->id,
            'student_id' => $student->id,
            'amount' => 10.00,
        ])->assertForbidden();

        $suffix = strtoupper(bin2hex(random_bytes(3)));
        $otherSchoolId = DB::table('schools')->insertGetId([
            'name' => 'Cashier Scope QA School ' . $suffix,
            'email' => null,
            'phone' => null,
            'address' => 'Synthetic acceptance-only school',
            'status' => 1,
            'slug' => 'cashier-scope-qa-' . strtolower($suffix),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $otherInstitutionId = DB::table('tagore_institutions')->insertGetId([
            'tagore_group_id' => $institution->tagore_group_id,
            'school_id' => $otherSchoolId,
            'code' => 'CASHIER-QA-' . $suffix,
            'display_name' => 'Cashier Scope QA Institution',
            'type' => 'school',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $otherStudentId = DB::table('users')->insertGetId([
            'name' => 'Out of scope acceptance student',
            'email' => 'cashier-scope-' . strtolower($suffix) . '@tagore-demo.local',
            'password' => Hash::make('AcceptanceOnly!2026'),
            'school_id' => $otherSchoolId,
            'usergroup_id' => 6,
            'status' => 'active',
            'email_verified' => true,
            'email_verified_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $studentRoleId = DB::table('tagore_roles')->where('code', 'STUDENT')->value('id');
        DB::table('tagore_user_roles')->insert([
            'user_id' => $otherStudentId,
            'role_id' => $studentRoleId,
            'institution_id' => $otherInstitutionId,
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($cashier)->post(route('tagore.fees.payment', ['studentId' => $otherStudentId]), [
            'amount' => 10.00,
            'payment_mode' => 'cash',
        ])->assertForbidden();

        $this->actingAs($cashier)->post(route('tagore.fees.wallet.pay'), [
            'student_id' => $otherStudentId,
            'institution_id' => $otherInstitutionId,
            'amount' => 1.00,
        ])->assertForbidden();

        $this->actingAs($cashier)->post(route('tagore.admissions.store'), [
            'institution_id' => $otherInstitutionId,
            'student_name' => 'Out of scope acceptance',
            'parent_name' => 'Acceptance Guardian',
            'mobile' => '9000000003',
            'class_name' => 'Class 6',
        ])->assertForbidden();
    }
}
