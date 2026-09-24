<?php

namespace App\Http\Controllers\Tagore;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class AdministrationController extends Controller
{
    public function index(Request $request): View
    {
        $userId = (int) $request->user()->id;
        $roles = $this->roles($userId);
        abort_unless($roles->contains('OWNER'), 403);

        $group = DB::table('tagore_groups')->where('code', 'TAGORE')->first();
        $availableSchools = DB::table('schools as s')
            ->whereNull('s.deleted_at')
            ->whereNotExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('tagore_institutions as ti')
                    ->whereColumn('ti.school_id', 's.id');
            })
            ->orderBy('s.name')
            ->get(['s.id', 's.name']);

        $institutions = DB::table('tagore_institutions as i')
            ->join('schools as s', 's.id', '=', 'i.school_id')
            ->where('i.status', 'active')
            ->orderBy('i.display_name')
            ->get(['i.id', 'i.code', 'i.display_name', 'i.type', 'i.status', 's.id as school_id', 's.name as school_name']);

        $years = DB::table('academic_years as ay')
            ->join('schools as s', 's.id', '=', 'ay.school_id')
            ->leftJoin('tagore_institutions as i', 'i.school_id', '=', 's.id')
            ->orderByDesc('ay.start_date')
            ->get(['ay.id', 'ay.school_id', 'ay.name', 'ay.start_date', 'ay.end_date', 'ay.status', 'i.id as institution_id', 'i.display_name as institution']);

        $rolesList = DB::table('tagore_roles')->where('status', 'active')->orderBy('name')->get(['id', 'name', 'code', 'description']);
        $assignments = DB::table('tagore_user_roles as ur')
            ->join('users as u', 'u.id', '=', 'ur.user_id')
            ->join('tagore_roles as r', 'r.id', '=', 'ur.role_id')
            ->leftJoin('tagore_institutions as i', 'i.id', '=', 'ur.institution_id')
            ->where('ur.status', 'active')
            ->orderBy('u.name')
            ->limit(250)
            ->get(['ur.id', 'u.id as user_id', 'u.name', 'r.name as role_name', 'r.code as role_code', 'i.display_name as institution']);

        return view('tagore.administration', compact('group', 'institutions', 'availableSchools', 'years', 'rolesList', 'assignments'));
    }

    public function storeInstitution(Request $request)
    {
        $this->owner($request);
        $data = $request->validate([
            'school_id' => ['required', 'integer', 'exists:schools,id'],
            'code' => ['required', 'string', 'max:50'],
            'display_name' => ['required', 'string', 'max:150'],
            'type' => ['nullable', 'string', 'max:50'],
        ]);
        $groupId = DB::table('tagore_groups')->where('code', 'TAGORE')->value('id');
        abort_unless($groupId, 422);
        abort_if(DB::table('tagore_institutions')->where('school_id', $data['school_id'])->exists(), 422, 'This GegoK12 school is already linked to a Tagore institution.');
        abort_if(DB::table('tagore_institutions')->where('tagore_group_id', $groupId)->where('code', $data['code'])->exists(), 422, 'Institution code already exists.');
        DB::table('tagore_institutions')->insert([
            'tagore_group_id' => $groupId,
            'school_id' => $data['school_id'],
            'code' => strtoupper($data['code']),
            'display_name' => $data['display_name'],
            'type' => $data['type'] ?: 'school',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        return back()->with('success', 'Institution added.');
    }

    public function storeAcademicYear(Request $request)
    {
        $this->owner($request);
        $data = $request->validate([
            'institution_id' => ['required', 'integer', 'exists:tagore_institutions,id'],
            'name' => ['required', 'string', 'max:100'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after:start_date'],
            'status' => ['required', 'in:active,inactive'],
        ]);
        $schoolId = DB::table('tagore_institutions')->where('id', $data['institution_id'])->value('school_id');
        abort_unless($schoolId, 422);
        DB::table('academic_years')->insert([
            'school_id' => $schoolId,
            'name' => $data['name'],
            'start_date' => $data['start_date'],
            'end_date' => $data['end_date'],
            'status' => $data['status'],
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        return back()->with('success', 'Academic year added.');
    }

    public function assignRole(Request $request)
    {
        $this->owner($request);
        $data = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'role_id' => ['required', 'integer', 'exists:tagore_roles,id'],
            'institution_id' => ['nullable', 'integer', 'exists:tagore_institutions,id'],
        ]);
        $role = DB::table('tagore_roles')->where('id', $data['role_id'])->first(['id', 'code']);
        if ($role->code === 'OWNER') $data['institution_id'] = null;
        if ($data['institution_id']) {
            $schoolId = DB::table('tagore_institutions')->where('id', $data['institution_id'])->value('school_id');
            abort_unless($schoolId, 422);
            abort_unless(DB::table('users')->where('id', $data['user_id'])->where('school_id', $schoolId)->exists(), 422, 'User must belong to the selected institution school.');
        }
        DB::table('tagore_user_roles')->updateOrInsert(
            ['user_id' => $data['user_id'], 'role_id' => $data['role_id'], 'institution_id' => $data['institution_id']],
            ['status' => 'active', 'updated_at' => now(), 'created_at' => now()]
        );
        return back()->with('success', 'Role assignment saved.');
    }

    private function owner(Request $request): void
    {
        abort_unless($this->roles((int) $request->user()->id)->contains('OWNER'), 403);
    }

    private function roles(int $userId)
    {
        return DB::table('tagore_user_roles as ur')->join('tagore_roles as r', 'r.id', '=', 'ur.role_id')->where('ur.user_id', $userId)->where('ur.status', 'active')->pluck('r.code')->unique()->values();
    }
}
