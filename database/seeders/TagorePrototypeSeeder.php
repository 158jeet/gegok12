<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TagorePrototypeSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();
        $groupId = DB::table('tagore_groups')->where('code', 'TAGORE')->value('id');
        if (!$groupId) {
            $groupId = DB::table('tagore_groups')->insertGetId([
                'name' => 'Tagore Group', 'code' => 'TAGORE', 'status' => 'active',
                'created_at' => $now, 'updated_at' => $now,
            ]);
        }

        $roles = [
            ['Owner', 'OWNER'], ['Principal', 'PRINCIPAL'], ['Coordinator', 'COORDINATOR'],
            ['Teacher', 'TEACHER'], ['Parent', 'PARENT'], ['Student', 'STUDENT'], ['Accounts', 'ACCOUNTS'],
        ];
        foreach ($roles as [$name, $code]) {
            DB::table('tagore_roles')->updateOrInsert(
                ['code' => $code],
                ['name' => $name, 'is_system' => true, 'status' => 'active', 'updated_at' => $now, 'created_at' => $now]
            );
        }

        $permissions = [
            ['dashboard', 'view'], ['student', 'view'], ['parent', 'view'],
            ['attendance', 'view'], ['attendance', 'manage'], ['fee', 'view'], ['fee', 'manage'],
            ['payment', 'view'], ['payment', 'refund'], ['payment', 'reconcile'],
            ['result', 'view'], ['result', 'manage'], ['result', 'publish'],
            ['feedback', 'view'], ['feedback', 'submit'], ['feedback', 'respond'], ['feedback', 'moderate'],
            ['report', 'view'], ['report', 'export'], ['user', 'manage'], ['scope', 'manage'],
        ];
        foreach ($permissions as [$module, $action]) {
            DB::table('tagore_permissions')->updateOrInsert(
                ['module' => $module, 'action' => $action],
                ['code' => $module.'.'.$action, 'description' => ucfirst($action).' '.$module, 'created_at' => $now, 'updated_at' => $now]
            );
        }

        foreach ($roles as [$name, $code]) {
            $roleId = DB::table('tagore_roles')->where('code', $code)->value('id');
            $permissionCodes = match ($code) {
                'OWNER' => DB::table('tagore_permissions')->pluck('code')->all(),
                'PRINCIPAL' => ['dashboard.view','student.view','parent.view','attendance.view','attendance.manage','fee.view','fee.manage','payment.view','result.view','result.manage','result.publish','feedback.view','feedback.respond','report.view','report.export'],
                'COORDINATOR' => ['dashboard.view','student.view','parent.view','attendance.view','attendance.manage','result.view','result.manage','feedback.view','feedback.respond','report.view'],
                'TEACHER' => ['dashboard.view','student.view','attendance.view','attendance.manage','result.view','result.manage','feedback.view','report.view'],
                'PARENT' => ['dashboard.view','student.view','attendance.view','fee.view','payment.view','result.view','feedback.view','feedback.submit'],
                'STUDENT' => ['dashboard.view','attendance.view','result.view','feedback.view'],
                'ACCOUNTS' => ['dashboard.view','student.view','fee.view','fee.manage','payment.view','payment.reconcile','report.view','report.export'],
                default => [],
            };
            foreach ($permissionCodes as $codeValue) {
                $permissionId = DB::table('tagore_permissions')->where('code', $codeValue)->value('id');
                if ($permissionId) {
                    DB::table('tagore_role_permissions')->updateOrInsert(['role_id' => $roleId, 'permission_id' => $permissionId], []);
                }
            }
        }

        DB::table('tagore_feedback_categories')->upsert([
            ['name' => 'Academic', 'code' => 'ACADEMIC', 'status' => 'active', 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Transport', 'code' => 'TRANSPORT', 'status' => 'active', 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'General', 'code' => 'GENERAL', 'status' => 'active', 'created_at' => $now, 'updated_at' => $now],
        ], ['code'], ['name', 'status', 'updated_at']);
    }
}
