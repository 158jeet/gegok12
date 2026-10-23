<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class TagoreDemoAccountsSeeder extends Seeder
{
    public function run(): void
    {
        // Demo identities must never be created or modified on a production installation.
        if (app()->environment('production')) {
            $this->command?->warn('Skipping Tagore demo accounts in production.');
            return;
        }

        $now = now();
        $school = DB::table('schools')->whereNull('deleted_at')->orderBy('id')->first(['id','name']);
        if (!$school) return;

        $institutionId = DB::table('tagore_institutions')->where('school_id', $school->id)->value('id');
        if (!$institutionId) return;

        $roleIds = DB::table('tagore_roles')->pluck('id','code')->all();
        $accounts = [
            ['owner@tagore-demo.local','Tagore Demo Owner',3,'OWNER'],
            ['principal@tagore-demo.local','Tagore Demo Principal',4,'PRINCIPAL'],
            ['coordinator@tagore-demo.local','Tagore Demo Coordinator',13,'COORDINATOR'],
            ['teacher@tagore-demo.local','Tagore Demo Teacher',5,'TEACHER'],
            ['accounts@tagore-demo.local','Tagore Demo Accounts',11,'ACCOUNTS'],
            ['fees@tagore-demo.local','Tagore Demo Fee Editor',13,'FEE_EDITOR'],
            ['parent@tagore-demo.local','Tagore Demo Parent',7,'PARENT'],
            ['student@tagore-demo.local','Tagore Demo Student',6,'STUDENT'],
        ];

        $ids = [];
        foreach ($accounts as [$email,$name,$group,$role]) {
            // Preserve existing accounts and credentials; only create missing local demo fixtures.
            $existing = DB::table('users')->whereNull('deleted_at')->where('email', $email)->first(['id']);
            if ($existing) {
                $id = $existing->id;
            } else {
                $id = DB::table('users')->insertGetId([
                    'name' => $name,
                    'email' => $email,
                    'password' => Hash::make('Tagore@2026!'),
                    'school_id' => $school->id,
                    'usergroup_id' => $group,
                    'status' => 'active',
                    'email_verified' => true,
                    'email_verified_at' => $now,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
            $ids[$role] = $id;
            $roleId = $roleIds[$role] ?? null;
            if ($roleId) {
                DB::table('tagore_user_roles')->updateOrInsert(
                    ['user_id' => $id, 'role_id' => $roleId, 'institution_id' => $institutionId],
                    ['status' => 'active', 'updated_at' => $now, 'created_at' => $now]
                );
            }
        }

        if (isset($ids['PARENT'], $ids['STUDENT'])) {
            DB::table('tagore_parent_students')->updateOrInsert(
                ['parent_user_id' => $ids['PARENT'], 'student_id' => $ids['STUDENT']],
                ['relationship' => 'Guardian', 'is_primary' => true, 'is_guardian' => true, 'status' => 'active', 'updated_at' => $now, 'created_at' => $now]
            );
        }
    }
}
