<?php

namespace App\\Http\\Controllers\\Tagore;

use App\\Http\\Controllers\\Controller;
use Illuminate\\Http\\Request;
use Illuminate\\Support\\Facades\\DB;
use Illuminate\\Validation\\ValidationException;

class StudentLifecycleController extends Controller
{
    private const STAFF_ROLES = ['OWNER', 'PRINCIPAL', 'COORDINATOR'];

    /**
     * Promote a bounded batch without rewriting the source-year academic records.
     * Existing destination records are treated as idempotent skips.
     */
    public function promote(Request $request)
    {
        $userId = (int) $request->user()->id;
        $roles = DB::table('tagore_user_roles as ur')
            ->join('tagore_roles as r', 'r.id', '=', 'ur.role_id')
            ->where('ur.user_id', $userId)
            ->where('ur.status', 'active')
            ->pluck('r.code');

        abort_unless($roles->intersect(self::STAFF_ROLES)->isNotEmpty(), 403);

        $data = $request->validate([
            'institution_id' => ['required', 'integer'],
            'source_academic_year_id' => ['required', 'integer', 'different:target_academic_year_id'],
            'target_academic_year_id' => ['required', 'integer'],
            'target_standard_link_id' => ['required', 'integer'],
            'student_ids' => ['required', 'array', 'min:1', 'max:200'],
            'student_ids.*' => ['required', 'integer', 'distinct'],
        ]);

        $institution = DB::table('tagore_institutions')
            ->where('id', $data['institution_id'])
            ->where('status', 'active')
            ->first();
        abort_unless($institution, 404);

        if (!$roles->contains('OWNER')) {
            abort_unless(DB::table('tagore_user_roles')
                ->where('user_id', $userId)
                ->where('institution_id', $institution->id)
                ->where('status', 'active')
                ->exists(), 403);
        }

        $schoolId = (int) $institution->school_id;
        $sourceYear = DB::table('academic_years')->where('id', $data['source_academic_year_id'])
            ->where('school_id', $schoolId)->whereNull('deleted_at')->first();
        $targetYear = DB::table('academic_years')->where('id', $data['target_academic_year_id'])
            ->where('school_id', $schoolId)->whereNull('deleted_at')->first();
        abort_unless($sourceYear && $targetYear, 422, 'Both academic years must belong to the selected institution.');

        $targetLink = DB::table('standards_link')->where('id', $data['target_standard_link_id'])
            ->where('school_id', $schoolId)->where('academic_year_id', $targetYear->id)
            ->where('status', 1)->whereNull('deleted_at')->first();
        abort_unless($targetLink, 422, 'The destination class and section must be active in the destination academic year.');

        $result = DB::transaction(function () use ($data, $schoolId, $targetYear, $targetLink, $userId) {
            $ids = array_values(array_unique(array_map('intval', $data['student_ids'])));
            $sourceRows = DB::table('student_academics')
                ->where('school_id', $schoolId)
                ->where('academic_year_id', $data['source_academic_year_id'])
                ->whereIn('user_id', $ids)
                ->whereNull('deleted_at')
                ->lockForUpdate()
                ->get()
                ->keyBy('user_id');

            if ($sourceRows->count() !== count($ids)) {
                throw ValidationException::withMessages([
                    'student_ids' => 'One or more students do not have an active record in the selected source school and academic year.',
                ]);
            }

            $created = 0;
            $skipped = 0;
            foreach ($ids as $studentId) {
                $source = $sourceRows->get($studentId);
                $alreadyPromoted = DB::table('tagore_student_lifecycle_events')
                    ->where('student_id', $studentId)
                    ->where('source_academic_year_id', $data['source_academic_year_id'])
                    ->where('target_academic_year_id', $targetYear->id)
                    ->where('event_type', 'promotion')
                    ->exists();

                $destination = DB::table('student_academics')
                    ->where('school_id', $schoolId)
                    ->where('academic_year_id', $targetYear->id)
                    ->where('user_id', $studentId)
                    ->whereNull('deleted_at')
                    ->first();

                if ($alreadyPromoted || $destination) {
                    $skipped++;
                    continue;
                }

                $attributes = (array) $source;
                unset($attributes['id'], $attributes['created_at'], $attributes['updated_at'], $attributes['deleted_at']);
                $attributes['academic_year_id'] = $targetYear->id;
                $attributes['standardLink_id'] = $targetLink->id;
                $attributes['academic_status'] = null;
                $attributes['created_at'] = now();
                $attributes['updated_at'] = now();
                $destinationId = DB::table('student_academics')->insertGetId($attributes);

                DB::table('tagore_student_lifecycle_events')->insert([
                    'institution_id' => $data['institution_id'],
                    'student_id' => $studentId,
                    'event_type' => 'promotion',
                    'source_academic_year_id' => $data['source_academic_year_id'],
                    'target_academic_year_id' => $targetYear->id,
                    'source_student_academic_id' => $source->id,
                    'target_student_academic_id' => $destinationId,
                    'target_standard_link_id' => $targetLink->id,
                    'performed_by' => $userId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $created++;
            }

            return ['created' => $created, 'skipped' => $skipped];
        }, 3);

        return back()->with('success', "Promotion batch processed. Created: {$result['created']}; already present/skipped: {$result['skipped']}.");
    }
}
