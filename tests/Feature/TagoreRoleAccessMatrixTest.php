<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

#[Group('e2e')]
class TagoreRoleAccessMatrixTest extends TestCase
{
    public function test_role_permission_matrix_covers_all_tagore_user_levels(): void
    {
        $expected = [
            'OWNER' => [
                'has' => ['user.manage', 'scope.manage', 'fee.edit', 'payment.refund'],
                'lacks' => [],
            ],
            'PRINCIPAL' => [
                'has' => ['dashboard.view', 'fee.manage', 'report.export', 'result.publish'],
                'lacks' => ['user.manage', 'scope.manage'],
            ],
            'COORDINATOR' => [
                'has' => ['dashboard.view', 'attendance.manage', 'result.manage', 'task.manage'],
                'lacks' => ['fee.manage', 'user.manage'],
            ],
            'TEACHER' => [
                'has' => ['dashboard.view', 'attendance.manage', 'result.manage', 'task.manage'],
                'lacks' => ['fee.manage', 'fee.edit', 'user.manage'],
            ],
            'PARENT' => [
                'has' => ['dashboard.view', 'fee.view', 'payment.view', 'feedback.submit'],
                'lacks' => ['fee.manage', 'fee.edit', 'user.manage'],
            ],
            'STUDENT' => [
                'has' => ['dashboard.view', 'attendance.view', 'result.view'],
                'lacks' => ['fee.manage', 'fee.edit', 'user.manage'],
            ],
            'ACCOUNTS' => [
                'has' => ['dashboard.view', 'fee.manage', 'payment.reconcile', 'report.export'],
                'lacks' => ['fee.edit', 'user.manage'],
            ],
            'CASHIER' => [
                'has' => ['dashboard.view', 'student.view', 'fee.view', 'payment.view'],
                'lacks' => ['fee.manage', 'fee.edit', 'payment.reconcile', 'payment.refund', 'user.manage', 'scope.manage'],
            ],
            'FEE_EDITOR' => [
                'has' => ['dashboard.view', 'fee.view', 'fee.edit', 'report.view'],
                'lacks' => ['fee.manage', 'payment.reconcile', 'user.manage'],
            ],
            'CASHIER' => [
                'has' => ['dashboard.view', 'fee.view', 'fee.collect', 'payment.view', 'admission.manage'],
                'lacks' => ['fee.manage', 'fee.edit', 'payment.reconcile', 'payment.refund', 'user.manage', 'scope.manage'],
            ],
        ];

        foreach ($expected as $roleCode => $checks) {
            $roleId = DB::table('tagore_roles')->where('code', $roleCode)->value('id');
            $this->assertNotNull($roleId, "Missing Tagore role {$roleCode}.");

            $permissions = DB::table('tagore_role_permissions as rp')
                ->join('tagore_permissions as p', 'p.id', '=', 'rp.permission_id')
                ->where('rp.role_id', $roleId)
                ->pluck('p.code')
                ->all();

            foreach ($checks['has'] as $permission) {
                $this->assertContains(
                    $permission,
                    $permissions,
                    "{$roleCode} must have {$permission}."
                );
            }

            foreach ($checks['lacks'] as $permission) {
                $this->assertNotContains(
                    $permission,
                    $permissions,
                    "{$roleCode} must not have {$permission}."
                );
            }
        }
    }

    public function test_each_demo_account_has_exactly_one_active_tagore_role(): void
    {
        $emails = [
            'owner@tagore-demo.local',
            'principal@tagore-demo.local',
            'coordinator@tagore-demo.local',
            'teacher@tagore-demo.local',
            'accounts@tagore-demo.local',
            'cashier@tagore-demo.local',
            'fees@tagore-demo.local',
            'parent@tagore-demo.local',
            'student@tagore-demo.local',
        ];

        foreach ($emails as $email) {
            $userId = DB::table('users')->where('email', $email)->whereNull('deleted_at')->value('id');
            $this->assertNotNull($userId, "Missing demo account {$email}.");

            $roles = DB::table('tagore_user_roles')
                ->where('user_id', $userId)
                ->where('status', 'active')
                ->pluck('role_id');

            $this->assertCount(1, $roles, "{$email} must have exactly one active Tagore role.");
        }
    }


    public function test_only_the_explicit_owner_fixture_has_the_owner_role(): void
    {
        $owners = DB::table('tagore_user_roles as ur')
            ->join('tagore_roles as r', 'r.id', '=', 'ur.role_id')
            ->join('users as u', 'u.id', '=', 'ur.user_id')
            ->where('ur.status', 'active')
            ->where('r.code', 'OWNER')
            ->distinct()
            ->pluck('u.email');

        $this->assertSame(['owner@tagore-demo.local'], $owners->sort()->values()->all());
    }

    public function test_owner_cannot_assign_the_owner_role_to_another_account(): void
    {
        $owner = User::query()->where('email', 'owner@tagore-demo.local')->whereNull('deleted_at')->firstOrFail();
        $principal = User::query()->where('email', 'principal@tagore-demo.local')->whereNull('deleted_at')->firstOrFail();
        $ownerRoleId = DB::table('tagore_roles')->where('code', 'OWNER')->value('id');
        $institutionId = DB::table('tagore_institutions')->where('school_id', $principal->school_id)->value('id');

        $this->actingAs($owner)->post('/tagore/admin/role', [
            'user_id' => $principal->id,
            'role_id' => $ownerRoleId,
            'institution_id' => $institutionId,
        ])->assertStatus(422);

        $this->assertDatabaseMissing('tagore_user_roles', [
            'user_id' => $principal->id,
            'role_id' => $ownerRoleId,
            'status' => 'active',
        ]);
    }


    public function test_owner_cannot_create_another_owner_account(): void
    {
        $owner = User::query()->where('email', 'owner@tagore-demo.local')->whereNull('deleted_at')->firstOrFail();
        $ownerRoleId = DB::table('tagore_roles')->where('code', 'OWNER')->value('id');
        $institutionId = DB::table('tagore_institutions')->where('school_id', $owner->school_id)->value('id');

        $this->actingAs($owner)->post('/tagore/admin/users', [
            'name' => 'Unauthorized Second Owner',
            'email' => 'second-owner@tagore-demo.local',
            'role_id' => $ownerRoleId,
            'institution_id' => $institutionId,
        ])->assertStatus(422);

        $this->assertDatabaseMissing('users', ['email' => 'second-owner@tagore-demo.local']);
    }

    public function test_owner_role_assignment_is_not_mutable_through_operational_role_assignment(): void
    {
        $owner = User::query()->where('email', 'owner@tagore-demo.local')->whereNull('deleted_at')->firstOrFail();
        $principal = User::query()->where('email', 'principal@tagore-demo.local')->whereNull('deleted_at')->firstOrFail();
        $principalRoleId = DB::table('tagore_roles')->where('code', 'PRINCIPAL')->value('id');
        $institutionId = DB::table('tagore_institutions')->where('school_id', $owner->school_id)->value('id');

        $this->actingAs($owner)->post('/tagore/admin/role', [
            'user_id' => $owner->id,
            'role_id' => $principalRoleId,
            'institution_id' => $institutionId,
        ])->assertStatus(422);
    }


    public function test_owner_operational_role_assignment_creates_an_audit_record(): void
    {
        $owner = User::query()->where('email', 'owner@tagore-demo.local')->whereNull('deleted_at')->firstOrFail();
        $principal = User::query()->where('email', 'principal@tagore-demo.local')->whereNull('deleted_at')->firstOrFail();
        $coordinatorRoleId = DB::table('tagore_roles')->where('code', 'COORDINATOR')->value('id');
        $institutionId = DB::table('tagore_institutions')->where('school_id', $principal->school_id)->value('id');

        $this->actingAs($owner)->post('/tagore/admin/role', [
            'user_id' => $principal->id,
            'role_id' => $coordinatorRoleId,
            'institution_id' => $institutionId,
        ])->assertRedirect();

        $this->assertDatabaseHas('tagore_user_admin_audits', [
            'actor_id' => $owner->id,
            'target_user_id' => $principal->id,
            'action' => 'role_assigned',
        ]);
    }

    public function test_owner_password_reset_revokes_target_sanctum_tokens(): void
    {
        $owner = User::query()->where('email', 'owner@tagore-demo.local')->whereNull('deleted_at')->firstOrFail();
        $principal = User::query()->where('email', 'principal@tagore-demo.local')->whereNull('deleted_at')->firstOrFail();
        $token = $principal->createToken('owner-reset-revocation')->accessToken;

        $this->actingAs($owner)->post('/tagore/admin/users/'.$principal->id.'/password')->assertRedirect();

        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $token->id]);
        $this->assertDatabaseHas('tagore_user_admin_audits', [
            'actor_id' => $owner->id,
            'target_user_id' => $principal->id,
            'action' => 'password_reset',
        ]);
    }

    public function test_owner_disabling_user_revokes_target_sanctum_tokens(): void
    {
        $owner = User::query()->where('email', 'owner@tagore-demo.local')->whereNull('deleted_at')->firstOrFail();
        $principal = User::query()->where('email', 'principal@tagore-demo.local')->whereNull('deleted_at')->firstOrFail();
        $token = $principal->createToken('owner-disable-revocation')->accessToken;

        $this->actingAs($owner)->patch('/tagore/admin/users/'.$principal->id.'/status', [
            'status' => 'disabled',
        ])->assertRedirect();

        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $token->id]);
        $this->assertDatabaseHas('users', ['id' => $principal->id, 'status' => 'inactive']);
        $this->assertDatabaseHas('tagore_user_admin_audits', [
            'actor_id' => $owner->id,
            'target_user_id' => $principal->id,
            'action' => 'disabled',
        ]);
    }

    public function test_fee_management_route_matches_the_declared_role_permission_matrix(): void
    {
        $expected = [
            'owner@tagore-demo.local' => 200,
            'principal@tagore-demo.local' => 200,
            'coordinator@tagore-demo.local' => 403,
            'teacher@tagore-demo.local' => 403,
            'accounts@tagore-demo.local' => 200,
            'cashier@tagore-demo.local' => 403,
            'fees@tagore-demo.local' => 403,
            'parent@tagore-demo.local' => 403,
            'student@tagore-demo.local' => 403,
        ];

        foreach ($expected as $email => $status) {
            $user = User::query()->where('email', $email)->whereNull('deleted_at')->firstOrFail();
            $this->actingAs($user)->get('/tagore/accounts/fees/manage')->assertStatus($status);
        }
    }
}
