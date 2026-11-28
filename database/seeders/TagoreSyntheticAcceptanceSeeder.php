<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

/**
 * Synthetic multi-institution Tagore acceptance dataset.
 * Safety guard: runs only against the dedicated disposable release-test database.
 * Never use this seeder against production or real school data.
 */
class TagoreSyntheticAcceptanceSeeder extends Seeder
{
    public function run(): void
    {
        if (!app()->environment('testing') || config('database.connections.mysql.database') !== 'tagore_release_test') {
            throw new RuntimeException('Safety stop: synthetic Tagore fixtures may only seed the testing environment database tagore_release_test.');
        }

        foreach (['schools', 'academic_years', 'sections', 'standards', 'tagore_groups', 'tagore_institutions'] as $table) {
            if (!Schema::hasTable($table)) {
                throw new RuntimeException("Required table missing: {$table}");
            }
        }

        $now = now();
        $definitions = [
            [
                'code' => 'TAGORE-PUBLIC-CBSE',
                'name' => 'Tagore Public School CBSE',
                'type' => 'school',
                'board' => 'CBSE',
                'grades' => range(1, 12),
                'programs' => ['CBSE School'],
            ],
            [
                'code' => 'TAGORE-BAL-NIKETAN-RBSE',
                'name' => 'Tagore Bal Niketan Sr. Sec. School RBSE',
                'type' => 'school',
                'board' => 'RBSE',
                'grades' => range(6, 12),
                'programs' => ['Hindi Medium', 'English Medium'],
            ],
            [
                'code' => 'TAGORE-SMART-RBSE',
                'name' => 'Tagore Smart School RBSE',
                'type' => 'school',
                'board' => 'RBSE',
                'grades' => range(1, 5),
                'programs' => ['Hindi Medium', 'English Medium', 'Kindergarten'],
            ],
            [
                'code' => 'TAGORE-VEDANT-ACADEMY',
                'name' => 'Tagore Vedant Academy',
                'type' => 'academy',
                'board' => null,
                'grades' => range(4, 12),
                'programs' => ['Pre-Foundation Classes 6-10', 'MNS / Military / Navodaya / Sainik preparation Classes 4-5', 'IIT-JEE Classes 11-12', 'NEET Classes 11-12', 'Target JET'],
            ],
            [
                'code' => 'TAGORE-HOSTEL',
                'name' => 'Tagore Hostel',
                'type' => 'hostel',
                'board' => null,
                'grades' => [],
                'programs' => ['Residential student services'],
            ],
            [
                'code' => 'TAGORE-PG-COLLEGE',
                'name' => 'Tagore PG College',
                'type' => 'college',
                'board' => null,
                'grades' => [],
                'programs' => ['BA', 'BSc', 'BCom', 'BBA', 'BCA', 'BEd'],
            ],
        ];

        DB::transaction(function () use ($definitions, $now): void {
            $groupId = DB::table('tagore_groups')->where('code', 'TAGORE')->value('id');
            if (!$groupId) {
                $groupId = DB::table('tagore_groups')->insertGetId([
                    'name' => 'Tagore Group of Institutions',
                    'code' => 'TAGORE',
                    'legal_name' => 'Tagore Group of Institutions',
                    'status' => 'active',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            foreach ($definitions as $index => $item) {
                $slug = strtolower(preg_replace('/[^a-z0-9]+/i', '-', $item['name']));
                $schoolId = DB::table('schools')->where('name', $item['name'])->value('id');
                if (!$schoolId) {
                    $schoolId = DB::table('schools')->insertGetId([
                        'name' => $item['name'],
                        'email' => null,
                        'phone' => null,
                        'address' => 'Synthetic acceptance-test data — Gudha Gorji, Jhunjhunu, Rajasthan',
                        'status' => 1,
                        'slug' => trim($slug, '-'),
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }

                $settings = [
                    'synthetic_test_data' => true,
                    'board' => $item['board'],
                    'programs' => $item['programs'],
                    'mediums' => str_contains($item['name'], 'Bal Niketan') || str_contains($item['name'], 'Smart School')
                        ? ['Hindi', 'English']
                        : (str_contains($item['name'], 'Public School') ? ['English'] : []),
                ];

                $institutionId = DB::table('tagore_institutions')
                    ->where('school_id', $schoolId)
                    ->value('id');

                if (!$institutionId) {
                    $institutionId = DB::table('tagore_institutions')->insertGetId([
                        'tagore_group_id' => $groupId,
                        'school_id' => $schoolId,
                        'code' => $item['code'],
                        'display_name' => $item['name'],
                        'type' => $item['type'],
                        'status' => 'active',
                        'settings_json' => json_encode($settings),
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                } else {
                    DB::table('tagore_institutions')->where('id', $institutionId)->update([
                        'tagore_group_id' => $groupId,
                        'code' => $item['code'],
                        'display_name' => $item['name'],
                        'type' => $item['type'],
                        'status' => 'active',
                        'settings_json' => json_encode($settings),
                        'updated_at' => $now,
                    ]);
                }

                // Create one synthetic student per institution so cross-institution
                // access controls can be exercised without touching real school records.
                $studentRoleId = DB::table('tagore_roles')->where('code', 'STUDENT')->value('id');
                $studentEmail = 'acceptance-student-' . $slug . '@tagore-demo.local';
                $studentId = DB::table('users')->where('email', $studentEmail)->value('id');
                if (!$studentId) {
                    $studentId = DB::table('users')->insertGetId([
                        'name' => $item['name'] . ' Acceptance Student',
                        'email' => $studentEmail,
                        'password' => Hash::make('AcceptanceOnly!2026'),
                        'school_id' => $schoolId,
                        'usergroup_id' => 6,
                        'status' => 'active',
                        'email_verified' => true,
                        'email_verified_at' => $now,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
                if ($studentRoleId) {
                    DB::table('tagore_user_roles')->updateOrInsert(
                        ['user_id' => $studentId, 'role_id' => $studentRoleId, 'institution_id' => $institutionId],
                        ['status' => 'active', 'updated_at' => $now, 'created_at' => $now]
                    );
                }

                $yearId = DB::table('academic_years')
                    ->where('school_id', $schoolId)
                    ->where('name', '2026-27')
                    ->value('id');
                if (!$yearId) {
                    $yearId = DB::table('academic_years')->insertGetId([
                        'school_id' => $schoolId,
                        'name' => '2026-27',
                        'description' => 'Synthetic acceptance-test academic year',
                        'start_date' => '2026-04-01 00:00:00',
                        'end_date' => '2027-03-31 23:59:59',
                        'status' => 1,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }

                $sectionIds = [];
                foreach (['A', 'B'] as $sectionName) {
                    $sectionId = DB::table('sections')->where('school_id', $schoolId)->where('name', $sectionName)->value('id');
                    if (!$sectionId) {
                        $sectionId = DB::table('sections')->insertGetId([
                            'school_id' => $schoolId,
                            'name' => $sectionName,
                            'status' => 1,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ]);
                    }
                    $sectionIds[$sectionName] = $sectionId;
                }

                foreach ($item['grades'] as $grade) {
                    $gradeName = $grade === 1 ? '1' : (string) $grade;
                    $standardId = DB::table('standards')->where('school_id', $schoolId)->where('name', $gradeName)->value('id');
                    if (!$standardId) {
                        $standardId = DB::table('standards')->insertGetId([
                            'school_id' => $schoolId,
                            'name' => $gradeName,
                            'slug' => $slug . '-class-' . $gradeName,
                            'order' => $grade,
                            'status' => 1,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ]);
                    }
                }
            }
        });
    }
}
