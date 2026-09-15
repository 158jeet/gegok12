<?php

namespace App\Services\Tagore;

use Illuminate\Support\Facades\DB;
use RuntimeException;

class ScopeService
{
    public function can(int $userId, string $permission, ?string $scopeType = null, ?int $scopeId = null): bool
    {
        $query = DB::table('tagore_user_roles as ur')
            ->join('tagore_role_permissions as rp', 'rp.role_id', '=', 'ur.role_id')
            ->join('tagore_permissions as p', 'p.id', '=', 'rp.permission_id')
            ->where('ur.user_id', $userId)
            ->where('ur.status', 'active')
            ->where('p.code', $permission);

        if ($query->exists()) {
            $isOwner = DB::table('tagore_user_roles as ur')
                ->join('tagore_roles as r', 'r.id', '=', 'ur.role_id')
                ->where('ur.user_id', $userId)->where('ur.status', 'active')->where('r.code', 'OWNER')->exists();
            if ($isOwner) return true;
        } else {
            return false;
        }

        if (!$scopeType || $scopeId === null) return true;

        return DB::table('tagore_user_scopes as us')
            ->where('us.user_id', $userId)
            ->where('us.scope_type', strtoupper($scopeType))
            ->where('us.scope_id', $scopeId)
            ->where(function ($q) use ($permission) {
                $q->whereNull('us.permission_id')
                  ->orWhere('us.permission_id', DB::table('tagore_permissions')->where('code', $permission)->value('id'));
            })
            ->exists();
    }

    public function assertCan(int $userId, string $permission, ?string $scopeType = null, ?int $scopeId = null): void
    {
        if (!$this->can($userId, $permission, $scopeType, $scopeId)) {
            throw new RuntimeException('TagoreK12 authorization denied.');
        }
    }

    public function parentCanAccessStudent(int $parentUserId, int $studentId): bool
    {
        return DB::table('tagore_parent_students')
            ->where('parent_user_id', $parentUserId)
            ->where('student_id', $studentId)
            ->where('status', 'active')
            ->exists();
    }

    public function institutionForSchool(int $schoolId): ?int
    {
        return DB::table('tagore_institutions')->where('school_id', $schoolId)->value('id');
    }
}
