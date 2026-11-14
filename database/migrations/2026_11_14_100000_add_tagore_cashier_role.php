<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        $now = now();
        DB::table('tagore_roles')->updateOrInsert(
            ['code' => 'CASHIER'],
            ['name' => 'Cashier', 'description' => 'Institution-scoped fee collection and daily payment-status monitoring.', 'is_system' => true, 'status' => 'active', 'updated_at' => $now, 'created_at' => $now]
        );
        DB::table('tagore_permissions')->updateOrInsert(
            ['code' => 'payment.collect'],
            ['module' => 'payment', 'action' => 'collect', 'description' => 'Collect offline student payments.', 'updated_at' => $now, 'created_at' => $now]
        );

        $roleId = DB::table('tagore_roles')->where('code', 'CASHIER')->value('id');
        $permissionCodes = ['dashboard.view', 'student.view', 'fee.view', 'payment.view', 'payment.collect'];
        foreach (DB::table('tagore_permissions')->whereIn('code', $permissionCodes)->pluck('id') as $permissionId) {
            DB::table('tagore_role_permissions')->updateOrInsert(
                ['role_id' => $roleId, 'permission_id' => $permissionId],
                []
            );
        }
    }

    public function down(): void
    {
        $roleId = DB::table('tagore_roles')->where('code', 'CASHIER')->value('id');
        if ($roleId) {
            DB::table('tagore_role_permissions')->where('role_id', $roleId)->delete();
            DB::table('tagore_user_roles')->where('role_id', $roleId)->delete();
            DB::table('tagore_roles')->where('id', $roleId)->delete();
        }

        $permissionId = DB::table('tagore_permissions')->where('code', 'payment.collect')->value('id');
        if ($permissionId && !DB::table('tagore_role_permissions')->where('permission_id', $permissionId)->exists()) {
            DB::table('tagore_permissions')->where('id', $permissionId)->delete();
        }
    }
};
