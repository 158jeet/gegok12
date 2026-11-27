<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        DB::transaction(function (): void {
            $now = now();

            $role = DB::table('tagore_roles')->where('code', 'CASHIER')->first(['id']);
            $roleValues = [
                'name' => 'Cashier',
                'description' => 'Institution-scoped fee account visibility and collection operations.',
                'is_system' => true,
                'status' => 'active',
                'updated_at' => $now,
            ];

            $roleId = $role
                ? (int) $role->id
                : (int) DB::table('tagore_roles')->insertGetId($roleValues + ['created_at' => $now]);

            if ($role) {
                DB::table('tagore_roles')->where('id', $roleId)->update($roleValues);
            }

            // Match the least-privilege CASHIER matrix already used by
            // TagorePrototypeSeeder. Do not run prototype/demo seeders in production.
            foreach ([
                ['dashboard', 'view'],
                ['student', 'view'],
                ['fee', 'view'],
                ['payment', 'view'],
            ] as [$module, $action]) {
                $permission = DB::table('tagore_permissions')
                    ->where('module', $module)
                    ->where('action', $action)
                    ->first(['id']);

                $permissionValues = [
                    'code' => $module . '.' . $action,
                    'description' => ucfirst($action) . ' ' . $module,
                    'updated_at' => $now,
                ];

                $permissionId = $permission
                    ? (int) $permission->id
                    : (int) DB::table('tagore_permissions')->insertGetId([
                        'module' => $module,
                        'action' => $action,
                    ] + $permissionValues + ['created_at' => $now]);

                if ($permission) {
                    DB::table('tagore_permissions')->where('id', $permissionId)->update($permissionValues);
                }

                DB::table('tagore_role_permissions')->insertOrIgnore([
                    'role_id' => $roleId,
                    'permission_id' => $permissionId,
                ]);
            }
        });
    }

    public function down(): void
    {
        $roleId = DB::table('tagore_roles')->where('code', 'CASHIER')->value('id');

        if (!$roleId || DB::table('tagore_user_roles')->where('role_id', $roleId)->exists()) {
            return;
        }

        DB::table('tagore_role_permissions')->where('role_id', $roleId)->delete();
        DB::table('tagore_roles')->where('id', $roleId)->delete();

        // Permission rows are shared by other roles and are intentionally retained.
    }
};
