<?php

namespace App\Services\Tagore;

use Illuminate\Support\Facades\DB;

class StudentParentMigrationService
{
    public function preview(?int $schoolId = null): array
    {
        $students = DB::table('users')->where('usergroup_id', 6)->whereNull('deleted_at')->when($schoolId, fn ($q) => $q->where('school_id', $schoolId));
        $parents = DB::table('users')->where('usergroup_id', 7)->whereNull('deleted_at')->when($schoolId, fn ($q) => $q->where('school_id', $schoolId));
        $links = DB::table('student_parent_links as l')
            ->join('users as s', 's.id', '=', 'l.student_id')
            ->join('users as p', 'p.id', '=', 'l.parent_id')
            ->where('l.status', 'active')
            ->when($schoolId, fn ($q) => $q->where('s.school_id', $schoolId));

        return [
            'students_source' => (clone $students)->count(),
            'students_tagore' => DB::table('tagore_user_roles as ur')->join('tagore_roles as r', 'r.id', '=', 'ur.role_id')->join('users as u', 'u.id', '=', 'ur.user_id')->where('r.code', 'STUDENT')->where('ur.status', 'active')->where('u.usergroup_id', 6)->when($schoolId, fn ($q) => $q->where('u.school_id', $schoolId))->distinct('u.id')->count('u.id'),
            'parents_source' => (clone $parents)->count(),
            'parents_tagore' => DB::table('tagore_user_roles as ur')->join('tagore_roles as r', 'r.id', '=', 'ur.role_id')->join('users as u', 'u.id', '=', 'ur.user_id')->where('r.code', 'PARENT')->where('ur.status', 'active')->where('u.usergroup_id', 7)->when($schoolId, fn ($q) => $q->where('u.school_id', $schoolId))->distinct('u.id')->count('u.id'),
            'links_source' => (clone $links)->count(),
            'links_tagore' => DB::table('tagore_parent_students as t')->join('users as s', 's.id', '=', 't.student_id')->where('t.status', 'active')->when($schoolId, fn ($q) => $q->where('s.school_id', $schoolId))->count(),
        ];
    }

    public function sync(?int $schoolId = null, ?int $actorId = null): array
    {
        return DB::transaction(function () use ($schoolId, $actorId) {
            $now = now();
            $roleIds = DB::table('tagore_roles')->whereIn('code', ['STUDENT', 'PARENT'])->pluck('id', 'code');
            $institutionBySchool = DB::table('tagore_institutions')->where('status', 'active')->pluck('id', 'school_id');
            $roleCount = 0;
            $linkCount = 0;

            DB::table('users')->whereIn('usergroup_id', [6, 7])->whereNull('deleted_at')->when($schoolId, fn ($q) => $q->where('school_id', $schoolId))->orderBy('id')->chunkById(500, function ($users) use (&$roleCount, $roleIds, $institutionBySchool, $now) {
                foreach ($users as $user) {
                    $roleCode = (int) $user->usergroup_id === 6 ? 'STUDENT' : 'PARENT';
                    $institutionId = $institutionBySchool[(int) $user->school_id] ?? null;
                    if (!$institutionId || !isset($roleIds[$roleCode])) continue;
                    DB::table('tagore_user_roles')->updateOrInsert(
                        ['user_id' => $user->id, 'role_id' => $roleIds[$roleCode], 'institution_id' => $institutionId],
                        ['status' => 'active', 'updated_at' => $now, 'created_at' => $now]
                    );
                    $roleCount++;
                }
            });

            if (DB::getSchemaBuilder()->hasTable('student_parent_links')) {
                DB::table('student_parent_links as l')
                    ->join('users as s', 's.id', '=', 'l.student_id')
                    ->join('users as p', 'p.id', '=', 'l.parent_id')
                    ->where('l.status', 'active')
                    ->when($schoolId, fn ($q) => $q->where('s.school_id', $schoolId))
                    ->select(['l.parent_id', 'l.student_id'])
                    ->orderBy('l.student_id')
                    ->chunkById(500, function ($links) use (&$linkCount, $now) {
                        foreach ($links as $link) {
                            DB::table('tagore_parent_students')->updateOrInsert(
                                ['parent_user_id' => $link->parent_id, 'student_id' => $link->student_id],
                                ['relationship' => 'Guardian', 'is_primary' => true, 'is_guardian' => true, 'status' => 'active', 'updated_at' => $now, 'created_at' => $now]
                            );
                            $linkCount++;
                        }
                    }, 'l.student_id');
            }

            if ($actorId) {
                DB::table('tagore_audit_events')->insert([
                    'user_id' => $actorId,
                    'action' => 'student_parent.sync',
                    'entity_type' => 'TagoreMigration',
                    'old_values_json' => json_encode(['school_id' => $schoolId]),
                    'new_values_json' => json_encode(['roles_synced' => $roleCount, 'parent_links_synced' => $linkCount]),
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            return ['roles_synced' => $roleCount, 'parent_links_synced' => $linkCount];
        });
    }
}
