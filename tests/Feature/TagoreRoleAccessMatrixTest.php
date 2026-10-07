<?php

namespace Tests\Feature;

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
            'FEE_EDITOR' => [
                'has' => ['dashboard.view', 'fee.view', 'fee.edit', 'report.view'],
                'lacks' => ['fee.manage', 'payment.reconcile', 'user.manage'],
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
}
