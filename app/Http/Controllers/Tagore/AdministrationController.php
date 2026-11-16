<?php

namespace App\Http\Controllers\Tagore;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
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
        $departments = DB::table('tagore_departments as d')->join('tagore_institutions as i','i.id','=','d.institution_id')->where('d.status','active')->where('i.status','active')->orderBy('i.display_name')->orderBy('d.name')->get(['d.id','d.name','d.code','d.institution_id','i.school_id','i.display_name as institution']);
        $staff = DB::table('users as u')->join('schools as s','s.id','=','u.school_id')->whereNull('u.deleted_at')->whereIn('u.school_id',$institutions->pluck('school_id')->all())->orderBy('u.name')->limit(500)->get(['u.id','u.name','u.email','u.school_id','s.name as school_name']);
        $assignments = DB::table('tagore_user_roles as ur')
            ->join('users as u', 'u.id', '=', 'ur.user_id')
            ->join('tagore_roles as r', 'r.id', '=', 'ur.role_id')
            ->leftJoin('tagore_institutions as i', 'i.id', '=', 'ur.institution_id')
            ->where('ur.status', 'active')
            ->orderBy('u.name')
            ->limit(250)
            ->get(['ur.id', 'u.id as user_id', 'u.name', 'u.email', 'u.status', 'r.name as role_name', 'r.code as role_code', 'i.display_name as institution']);

        $coordinators = DB::table('tagore_user_roles as ur')
            ->join('tagore_roles as r', 'r.id', '=', 'ur.role_id')
            ->join('users as u', 'u.id', '=', 'ur.user_id')
            ->where('r.code', 'COORDINATOR')->where('ur.status', 'active')->where('u.status', 'active')
            ->whereNull('u.deleted_at')->orderBy('u.name')->get(['u.id', 'u.name', 'u.email', 'u.school_id']);
        $classScopes = DB::table('standards_link as sl')
            ->join('standards as st', 'st.id', '=', 'sl.standard_id')
            ->join('sections as se', 'se.id', '=', 'sl.section_id')
            ->join('tagore_institutions as i', 'i.school_id', '=', 'sl.school_id')
            ->where('sl.status', 1)->whereNull('sl.deleted_at')->where('i.status', 'active')
            ->orderBy('i.display_name')->orderBy('st.order')->orderBy('se.name')
            ->get(['sl.id', 'sl.school_id', 'sl.academic_year_id', 'i.display_name as institution', 'st.name as standard_name', 'se.name as section_name']);
        $feeViewPermissionId = DB::table('tagore_permissions')->where('code', 'fee.view')->value('id');
        $scopeAssignments = DB::table('tagore_user_scopes as us')
            ->join('users as u', 'u.id', '=', 'us.user_id')
            ->join('tagore_permissions as p', 'p.id', '=', 'us.permission_id')
            ->join('standards_link as sl', 'sl.id', '=', 'us.scope_id')
            ->join('standards as st', 'st.id', '=', 'sl.standard_id')
            ->join('sections as se', 'se.id', '=', 'sl.section_id')
            ->where('us.scope_type', 'standard_link')->where('p.code', 'fee.view')
            ->orderBy('u.name')->orderBy('sl.id')
            ->get(['us.id', 'us.user_id', 'u.name as user_name', 'u.email', 'us.scope_id', 'st.name as standard_name', 'se.name as section_name', 'sl.academic_year_id']);
        $scopeAudits = DB::table('tagore_user_admin_audits as a')
            ->join('users as actor', 'actor.id', '=', 'a.actor_id')
            ->leftJoin('users as target', 'target.id', '=', 'a.target_user_id')
            ->whereIn('a.action', ['fee_scope_granted', 'fee_scope_revoked'])
            ->orderByDesc('a.created_at')->limit(50)
            ->get(['a.id', 'a.action', 'a.metadata', 'a.created_at', 'actor.name as actor_name', 'target.name as target_name']);

        return view('tagore.administration', compact('group', 'institutions', 'availableSchools', 'years', 'rolesList', 'assignments', 'departments', 'staff', 'coordinators', 'classScopes', 'feeViewPermissionId', 'scopeAssignments', 'scopeAudits'));
    }

    public function storeUser(Request $request)
    {
        $this->owner($request);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', 'max:190', 'unique:users,email'],
            'mobile_no' => ['nullable', 'string', 'max:30'],
            'role_id' => ['required', 'integer', 'exists:tagore_roles,id'],
            'institution_id' => ['required', 'integer', 'exists:tagore_institutions,id'],
        ]);
        $role = DB::table('tagore_roles')->where('id', $data['role_id'])->where('status', 'active')->first(['id', 'code']);
        abort_unless($role && $role->code !== 'OWNER', 422, 'The Owner role is reserved for the existing Owner account and cannot be assigned here.');
        $institution = DB::table('tagore_institutions')->where('id', $data['institution_id'])->where('status', 'active')->first(['id', 'school_id']);
        abort_unless($institution, 422);
        $group = match ($role->code) {
            'PRINCIPAL' => 4,
            'TEACHER', 'COORDINATOR', 'FEE_EDITOR' => 5,
            'PARENT' => 7,
            'STUDENT' => 6,
            'ACCOUNTS' => 11,
            default => 13,
        };
        $password = 'Tg!' . \Illuminate\Support\Str::random(5) . '-' . \Illuminate\Support\Str::random(8);
        DB::transaction(function () use ($institution, $group, $data, $password, $role, $request) {
            $id = DB::table('users')->insertGetId([
                'school_id' => $institution->school_id,
                'usergroup_id' => $group,
                'name' => trim($data['name']),
                'email' => strtolower(trim($data['email'])),
                'mobile_no' => $data['mobile_no'] ?? null,
                'password' => Hash::make($password),
                'email_verified' => 1,
                'email_verified_at' => now(),
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            DB::table('tagore_user_roles')->insert([
                'user_id' => $id,
                'role_id' => $role->id,
                'institution_id' => $institution->id,
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            DB::table('tagore_user_admin_audits')->insert([
                'actor_id' => $request->user()->id,
                'target_user_id' => $id,
                'action' => 'created',
                'metadata' => json_encode(['role' => $role->code, 'institution_id' => $institution->id]),
                'created_at' => now(),
            ]);
        });
        return back()->with('issued_credentials', [
            'name' => $data['name'],
            'email' => strtolower(trim($data['email'])),
            'password' => $password,
            'message' => 'Copy this generated password now; it will not be shown again.',
        ]);
    }

    public function resetUserPassword(Request $request, int $userId)
    {
        $this->owner($request);
        $user = DB::table('users')->where('id', $userId)->whereNull('deleted_at')->first(['id', 'name', 'email']);
        abort_unless($user, 404);
        $password = 'Tg!' . \Illuminate\Support\Str::random(5) . '-' . \Illuminate\Support\Str::random(8);
        DB::transaction(function () use ($request, $userId, $password) {
            $target = User::query()->whereKey($userId)->whereNull('deleted_at')->lockForUpdate()->firstOrFail();
            $target->tokens()->delete();
            DB::table('users')->where('id', $userId)->update([
                'password' => Hash::make($password),
                'remember_token' => null,
                'updated_at' => now(),
            ]);
            DB::table('tagore_user_admin_audits')->insert([
                'actor_id' => $request->user()->id,
                'target_user_id' => $userId,
                'action' => 'password_reset',
                'metadata' => null,
                'created_at' => now(),
            ]);
        });
        return back()->with('issued_credentials', [
            'name' => $user->name,
            'email' => $user->email,
            'password' => $password,
            'message' => 'New password generated. Share securely and ask the user to change it after signing in.',
        ]);
    }

    public function updateUserStatus(Request $request, int $userId)
    {
        $this->owner($request);
        $data=$request->validate(['status'=>['required','in:active,disabled']]);
        abort_if((int)$userId===(int)$request->user()->id,422,'You cannot disable your own account.');
        abort_unless(DB::table('users')->where('id',$userId)->whereNull('deleted_at')->exists(),404);
        DB::transaction(function () use ($request, $userId, $data) {
            $target = User::query()->whereKey($userId)->whereNull('deleted_at')->lockForUpdate()->firstOrFail();
            if ($data['status'] === 'disabled') {
                $target->tokens()->delete();
            }
            DB::table('users')->where('id',$userId)->update(['status'=>$data['status']==='disabled'?'inactive':'active','remember_token'=>null,'updated_at'=>now()]);
            DB::table('tagore_user_admin_audits')->insert(['actor_id'=>$request->user()->id,'target_user_id'=>$userId,'action'=>$data['status']==='active'?'enabled':'disabled','metadata'=>null,'created_at'=>now()]);
        });
        return back()->with('success','User account status updated.');
    }

    public function grantFeeScope(Request $request)
    {
        $this->owner($request);
        $data = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'standard_link_id' => ['required', 'integer'],
            'reason' => ['required', 'string', 'min:8', 'max:250'],
        ]);
        $target = DB::table('users')->where('id', $data['user_id'])->whereNull('deleted_at')->first(['id', 'school_id']);
        abort_unless($target && DB::table('tagore_user_roles as ur')
            ->join('tagore_roles as r', 'r.id', '=', 'ur.role_id')
            ->where('ur.user_id', $target->id)->where('ur.status', 'active')->where('r.code', 'COORDINATOR')->exists(), 422,
            'Select an active Coordinator account.');
        $class = DB::table('standards_link')->where('id', $data['standard_link_id'])
            ->where('school_id', $target->school_id)->where('status', 1)->whereNull('deleted_at')
            ->first(['id', 'school_id']);
        abort_unless($class, 422, 'The selected class must belong to the Coordinator school and be active.');
        $permissionId = DB::table('tagore_permissions')->where('code', 'fee.view')->value('id');
        abort_unless($permissionId, 422, 'The read-only fee permission is not configured.');

        DB::transaction(function () use ($request, $data, $target, $class, $permissionId) {
            $exists = DB::table('tagore_user_scopes')->where('user_id', $target->id)
                ->where('scope_type', 'standard_link')->where('scope_id', $class->id)
                ->where('permission_id', $permissionId)->exists();
            if ($exists) return;
            DB::table('tagore_user_scopes')->insert([
                'user_id' => $target->id, 'scope_type' => 'standard_link',
                'scope_id' => $class->id, 'permission_id' => $permissionId,
                'created_at' => now(), 'updated_at' => now(),
            ]);
            DB::table('tagore_user_admin_audits')->insert([
                'actor_id' => $request->user()->id, 'target_user_id' => $target->id,
                'action' => 'fee_scope_granted',
                'metadata' => json_encode(['scope_type' => 'standard_link', 'scope_id' => $class->id, 'permission' => 'fee.view', 'reason' => trim($data['reason'])]),
                'created_at' => now(),
            ]);
        });
        return back()->with('success', 'Read-only fee visibility scope saved.');
    }

    public function revokeFeeScope(Request $request, int $scopeId)
    {
        $this->owner($request);
        $data = $request->validate(['reason' => ['required', 'string', 'min:8', 'max:250']]);
        $permissionId = DB::table('tagore_permissions')->where('code', 'fee.view')->value('id');
        $scope = DB::table('tagore_user_scopes')->where('id', $scopeId)
            ->where('scope_type', 'standard_link')->where('permission_id', $permissionId)->first();
        abort_unless($scope, 404);
        abort_unless(DB::table('tagore_user_roles as ur')->join('tagore_roles as r', 'r.id', '=', 'ur.role_id')
            ->where('ur.user_id', $scope->user_id)->where('ur.status', 'active')->where('r.code', 'COORDINATOR')->exists(), 422,
            'Only Coordinator fee-view scopes can be revoked here.');

        DB::transaction(function () use ($request, $scope, $data) {
            DB::table('tagore_user_admin_audits')->insert([
                'actor_id' => $request->user()->id, 'target_user_id' => $scope->user_id,
                'action' => 'fee_scope_revoked',
                'metadata' => json_encode(['scope_type' => 'standard_link', 'scope_id' => $scope->scope_id, 'permission' => 'fee.view', 'reason' => trim($data['reason'])]),
                'created_at' => now(),
            ]);
            DB::table('tagore_user_scopes')->where('id', $scope->id)->delete();
        });
        return back()->with('success', 'Fee visibility scope revoked and audited.');
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
        $code = strtoupper(trim($data['code']));
        abort_if(DB::table('tagore_institutions')->where('tagore_group_id', $groupId)->whereRaw('UPPER(code) = ?', [$code])->exists(), 422, 'Institution code already exists.');
        DB::table('tagore_institutions')->insert([
            'tagore_group_id' => $groupId,
            'school_id' => $data['school_id'],
            'code' => $code,
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
        $institution = DB::table('tagore_institutions')->where('id', $data['institution_id'])->where('status', 'active')->first(['id', 'school_id']);
        abort_unless($institution, 422, 'Select an active institution.');
        $schoolId = $institution->school_id;
        abort_if(DB::table('academic_years')->where('school_id', $schoolId)->where('name', trim($data['name']))->exists(), 422, 'Academic year already exists for this institution.');
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

    public function storeDepartment(Request $request)
    {
        $this->owner($request);
        $data = $request->validate([
            'institution_id' => ['required', 'integer', 'exists:tagore_institutions,id'],
            'name' => ['required', 'string', 'max:100'],
            'code' => ['required', 'string', 'max:50'],
        ]);
        abort_unless(DB::table('tagore_institutions')->where('id', $data['institution_id'])->where('status', 'active')->exists(), 422);
        $code = strtoupper(trim($data['code']));
        abort_if(DB::table('tagore_departments')->where('institution_id', $data['institution_id'])->where('code', $code)->exists(), 422, 'Department code already exists in this institution.');
        DB::table('tagore_departments')->insert([
            'institution_id' => $data['institution_id'], 'name' => trim($data['name']), 'code' => $code,
            'status' => 'active', 'created_at' => now(), 'updated_at' => now(),
        ]);
        return back()->with('success', 'Department added.');
    }

    public function assignDepartment(Request $request)
    {
        $this->owner($request);
        $data = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'department_id' => ['required', 'integer', 'exists:tagore_departments,id'],
            'designation' => ['nullable', 'string', 'max:120'],
            'is_primary' => ['nullable', 'boolean'],
        ]);
        $department = DB::table('tagore_departments as d')->join('tagore_institutions as i','i.id','=','d.institution_id')
            ->where('d.id',$data['department_id'])->where('d.status','active')->where('i.status','active')
            ->first(['d.id','d.institution_id','i.school_id']);
        abort_unless($department, 422);
        abort_unless(DB::table('users')->where('id',$data['user_id'])->where('school_id',$department->school_id)->exists(), 422, 'User must belong to the selected institution school.');
        if (!empty($data['is_primary'])) {
            DB::table('tagore_user_departments')->where('user_id',$data['user_id'])->where('status','active')->update(['is_primary'=>false,'updated_at'=>now()]);
        }
        DB::table('tagore_user_departments')->updateOrInsert(
            ['user_id'=>$data['user_id'],'department_id'=>$data['department_id']],
            ['designation'=>$data['designation'] ?? null,'is_primary'=>!empty($data['is_primary']),'status'=>'active','created_at'=>now(),'updated_at'=>now()]
        );
        return back()->with('success', 'Department assignment saved.');
    }

    public function assignRole(Request $request)
    {
        $this->owner($request);
        $data = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'role_id' => ['required', 'integer', 'exists:tagore_roles,id'],
            'institution_id' => ['nullable', 'integer', 'exists:tagore_institutions,id'],
        ]);
        $role = DB::table('tagore_roles')->where('id', $data['role_id'])->where('status', 'active')->first(['id', 'code']);
        abort_unless($role, 422, 'Select an active role.');
        abort_unless($role->code !== 'OWNER', 422, 'The Owner role is reserved for the existing Owner account and cannot be assigned here.');

        $targetIsOwner = DB::table('tagore_user_roles as ur')
            ->join('tagore_roles as r', 'r.id', '=', 'ur.role_id')
            ->where('ur.user_id', $data['user_id'])
            ->where('ur.status', 'active')
            ->where('r.code', 'OWNER')
            ->exists();
        abort_unless(!$targetIsOwner, 422, 'The existing Owner account role assignment is protected.');

        abort_unless(!empty($data['institution_id']), 422, 'An institution is required for this role.');
        if ($data['institution_id']) {
            $schoolId = DB::table('tagore_institutions')->where('id', $data['institution_id'])->where('status', 'active')->value('school_id');
            abort_unless($schoolId, 422, 'Select an active institution.');
            abort_unless(DB::table('users')->where('id', $data['user_id'])->where('school_id', $schoolId)->exists(), 422, 'User must belong to the selected institution school.');
        }
        DB::transaction(function () use ($request, $data, $role) {
            DB::table('tagore_user_roles')->updateOrInsert(
                ['user_id' => $data['user_id'], 'role_id' => $data['role_id'], 'institution_id' => $data['institution_id']],
                ['status' => 'active', 'updated_at' => now(), 'created_at' => now()]
            );

            DB::table('tagore_user_admin_audits')->insert([
                'actor_id' => $request->user()->id,
                'target_user_id' => $data['user_id'],
                'action' => 'role_assigned',
                'metadata' => json_encode([
                    'role_code' => $role->code,
                    'institution_id' => $data['institution_id'],
                ]),
                'created_at' => now(),
            ]);
        });
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
