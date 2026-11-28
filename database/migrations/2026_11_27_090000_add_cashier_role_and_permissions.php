<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        $now = now();

        DB::table('tagore_roles')->updateOrInsert(
            ['code' => 'CASHIER'],
            [
                'name' => 'Cashier',
                'description' => 'Institution-scoped fee collection and admissions operations.',
                'is_system' => true,
                'status' => 'active',
                'created_at' => $now,
                'updated_at' => $now,
            ]
        );

        $roleId = DB::table('tagore_roles')->where('code', 'CASHIER')->value('id');
        $permissions = [
            ['dashboard', 'view'],
            ['student', 'view'],
            ['parent', 'view'],
            ['fee', 'view'],
            ['fee', 'collect'],
            ['payment', 'view'],
            ['admission', 'view'],
            ['admission', 'manage'],
        ];

        foreach ($permissions as [$module, $action]) {
            DB::table('tagore_permissions')->updateOrInsert(
                ['module' => $module, 'action' => $action],
                [
                    'code' => $module . '.' . $action,
                    'description' => ucfirst($action) . ' ' . $module,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );

            $permissionId = DB::table('tagore_permissions')
                ->where('module', $module)
                ->where('action', $action)
                ->value('id');

            DB::table('tagore_role_permissions')->updateOrInsert(
                ['role_id' => $roleId, 'permission_id' => $permissionId],
                []
            );
        }
    }

    public function down(): void
    {
        $roleId = DB::table('tagore_roles')->where('code', 'CASHIER')->value('id');

        if (!$roleId) {
            return;
        }

        DB::table('tagore_role_permissions')->where('role_id', $roleId)->delete();

        // Preserve the role if it has been assigned to a real user.
        if (!DB::table('tagore_user_roles')->where('role_id', $roleId)->exists()) {
            DB::table('tagore_roles')->where('id', $roleId)->delete();
        }
    }
};
