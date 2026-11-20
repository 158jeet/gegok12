<?php

namespace App\Http\Controllers\Tagore;

use App\Http\Controllers\Controller;
use App\Services\Tagore\StudentParentMigrationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class StudentParentMigrationController extends Controller
{
    public function index(Request $request, StudentParentMigrationService $service): View
    {
        $this->owner($request);
        $schools = DB::table('schools')->whereNull('deleted_at')->orderBy('name')->get(['id', 'name']);
        $schoolId = $request->integer('school_id') ?: null;
        $summary = $service->preview($schoolId);
        return view('tagore.student-parent-migration', compact('schools', 'schoolId', 'summary'));
    }

    public function sync(Request $request, StudentParentMigrationService $service)
    {
        $this->owner($request);
        $data = $request->validate(['school_id' => ['nullable', 'integer', 'exists:schools,id']]);
        $result = $service->sync($data['school_id'] ?? null, (int) $request->user()->id);
        return back()->with('success', "Student/parent sync completed: {$result['roles_synced']} roles and {$result['parent_links_synced']} parent-child links processed.");
    }

    private function owner(Request $request): void
    {
        abort_unless(DB::table('tagore_user_roles as ur')->join('tagore_roles as r', 'r.id', '=', 'ur.role_id')->where('ur.user_id', $request->user()->id)->where('ur.status', 'active')->where('r.code', 'OWNER')->exists(), 403);
    }
}
