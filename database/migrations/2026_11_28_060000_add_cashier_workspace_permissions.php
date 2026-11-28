<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        DB::transaction(function (): void {
            $now = now();
            $roleId = DB::table('tagore_roles')->where('code', 'CASHIER')->value('id');
            if (!$roleId) return;

            foreach ([
                ['module' => 'payment', 'action' => 'export', 'code' => 'payment.export', 'description' => 'Export institution-scoped payment collection records'],
                ['module' => 'fee', 'action' => 'assign', 'code' => 'fee.assign', 'description' => 'Assign existing fee structures and generate institution-scoped student demands'],
                ['module' => 'fee', 'action' => 'remind', 'code' => 'fee.remind', 'description' => 'Send fee due reminders to linked parent accounts'],
            ] as $permission) {
                $permissionId = DB::table('tagore_permissions')->where('code', $permission['code'])->value('id');
                if (!$permissionId) {
                    $permissionId = DB::table('tagore_permissions')->insertGetId($permission + [
                        'created_at' => $now, 'updated_at' => $now,
                    ]);
                }
                DB::table('tagore_role_permissions')->insertOrIgnore([
                    'role_id' => $roleId, 'permission_id' => $permissionId,
                ]);
            }
        });
    }

    public function down(): void
    {
        $roleId = DB::table('tagore_roles')->where('code', 'CASHIER')->value('id');
        if (!$roleId) return;
        $permissionIds = DB::table('tagore_permissions')->whereIn('code', ['payment.export', 'fee.assign', 'fee.remind'])->pluck('id');
        DB::table('tagore_role_permissions')->where('role_id', $roleId)->whereIn('permission_id', $permissionIds)->delete();
    }
};
