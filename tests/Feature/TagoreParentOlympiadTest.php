<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TagoreParentOlympiadTest extends TestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_parent_can_register_launch_submit_and_view_linked_child_olympiad(): void
    {
        $parent = User::query()->where('email', 'parent@tagore-demo.local')->firstOrFail();
        $student = User::query()->where('email', 'student@tagore-demo.local')->firstOrFail();
        $schoolId = (int) $student->school_id;
        $institutionId = (int) DB::table('tagore_institutions')->where('school_id', $schoolId)->where('status', 'active')->value('id');
        $academicYearId = (int) DB::table('academic_years')->where('school_id', $schoolId)->where('status', 1)->orderByDesc('id')->value('id');
        $standard = DB::table('standards')->where('school_id', $schoolId)->where('status', 1)->where('order', 9)->first();
        if (!$standard) $standard = DB::table('standards')->where('school_id', $schoolId)->where('status', 1)->orderBy('order')->skip(8)->first();
        $sectionId = (int) DB::table('sections')->where('school_id', $schoolId)->where('status', 1)->value('id');
        $teacherId = (int) DB::table('users')->where('email', 'teacher@tagore-demo.local')->value('id');
        $this->assertGreaterThan(0, $institutionId);
        $this->assertGreaterThan(0, $academicYearId);
        $this->assertNotNull($standard);
        $this->assertGreaterThan(0, $sectionId);
        $this->assertGreaterThan(0, $teacherId);

        $standardLinkId = DB::table('standards_link')->insertGetId([
            'school_id' => $schoolId, 'academic_year_id' => $academicYearId, 'class_teacher_id' => $teacherId,
            'standard_id' => $standard->id, 'section_id' => $sectionId, 'no_of_students' => 1,
            'status' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('student_academics')->insert([
            'school_id' => $schoolId, 'academic_year_id' => $academicYearId, 'user_id' => $student->id,
            'standardLink_id' => $standardLinkId, 'roll_number' => 'QA-PARENT-OLY-001',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $grade = (int) preg_replace('/\D+/', '', (string) $standard->name);
        if ($grade < 1 || $grade > 12) $grade = (int) $standard->order - 3;
        $this->assertGreaterThanOrEqual(1, $grade);
        $this->assertLessThanOrEqual(12, $grade);

        DB::table('tagore_parent_students')->updateOrInsert(
            ['parent_user_id' => $parent->id, 'student_id' => $student->id],
            ['relationship' => 'Parent', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]
        );

        $now = now();
        $eventId = DB::table('tagore_olympiads')->insertGetId([
            'institution_id' => $institutionId, 'title' => 'Parent Flow QA Olympiad',
            'code' => 'PARENTQA-' . strtoupper(substr(md5((string) microtime(true)), 0, 8)),
            'description' => 'Synthetic isolated parent flow test', 'target_classes' => json_encode([(string) $grade]),
            'registration_opens_at' => $now->copy()->subDay(), 'registration_closes_at' => $now->copy()->addMinutes(10),
            'duration_minutes' => 30, 'max_marks' => 1, 'exam_starts_at' => $now->copy()->subMinutes(5),
            'exam_ends_at' => $now->copy()->addHour(), 'status' => 'published', 'created_by' => $parent->id,
            'created_at' => $now, 'updated_at' => $now,
        ]);
        $questionId = DB::table('tagore_olympiad_questions')->insertGetId([
            'olympiad_id' => $eventId, 'question_text' => 'What is 2 + 2?',
            'options' => json_encode(['A' => '3', 'B' => '4', 'C' => '5', 'D' => '6']),
            'correct_option' => 'B', 'marks' => 1, 'sort_order' => 1, 'source' => 'parent_runtime_acceptance',
            'metadata' => null, 'approved_by' => $parent->id, 'approved_at' => $now,
            'created_at' => $now, 'updated_at' => $now,
        ]);

        Carbon::setTestNow($now->copy()->subMinutes(10));
        $this->actingAs($parent)->get(route('tagore.parent.olympiad.index', ['studentId' => $student->id]))
            ->assertOk()->assertSee('Parent Flow QA Olympiad');
        $this->actingAs($parent)->get(route('tagore.olympiad.exam', ['olympiadId' => $eventId]))->assertForbidden();

        $this->actingAs($parent)->post(route('tagore.parent.olympiad.register', ['studentId' => $student->id, 'olympiadId' => $eventId]))
            ->assertRedirect(route('tagore.parent.olympiad.index', ['studentId' => $student->id]));

        Carbon::setTestNow($now);
        $launch = $this->actingAs($parent)->post(route('tagore.parent.olympiad.launch', ['studentId' => $student->id, 'olympiadId' => $eventId]));
        $launch->assertRedirect();
        $examUrl = $launch->headers->get('Location');
        $this->assertNotEmpty($examUrl);
        $path = parse_url($examUrl, PHP_URL_PATH);
        $this->assertMatchesRegularExpression('#/tagore/parent/olympiad/' . $student->id . '/' . $eventId . '/[A-Za-z0-9]+/exam$#', $path);
        preg_match('#/tagore/parent/olympiad/' . $student->id . '/' . $eventId . '/([A-Za-z0-9]+)/exam$#', $path, $matches);
        $token = $matches[1] ?? '';
        $this->assertNotEmpty($token);

        $this->post(route('tagore.parent.olympiad.answers.save', [
            'studentId' => $student->id, 'olympiadId' => $eventId, 'token' => $token,
        ]), ['answers' => [$questionId => 'B']])->assertOk()->assertJsonPath('saved', true);
        $savedAnswers = json_decode((string) DB::table('tagore_olympiad_registrations')
            ->where('olympiad_id', $eventId)->where('student_user_id', $student->id)->value('answers'), true);
        $this->assertSame([(string) $questionId => 'B'], $savedAnswers);

        $this->get($examUrl)->assertOk()->assertSee('What is 2 + 2?')->assertSee('Parent-supervised')->assertSee('value="B" checked', false);
        $this->post(route('tagore.parent.olympiad.submit', ['studentId' => $student->id, 'olympiadId' => $eventId, 'token' => $token]), [
            'answers' => [$questionId => 'B'],
        ])->assertRedirect(route('tagore.parent.olympiad.result', ['studentId' => $student->id, 'olympiadId' => $eventId]));

        $this->get(route('tagore.parent.olympiad.result', ['studentId' => $student->id, 'olympiadId' => $eventId]))
            ->assertOk()->assertSee('Parent Flow QA Olympiad')->assertSee('1.00 / 1.00');
    }

    public function test_parent_cannot_access_an_unlinked_students_olympiad(): void
    {
        $parent = User::query()->where('email', 'parent@tagore-demo.local')->firstOrFail();
        $unlinkedStudent = User::query()->where('email', 'teacher@tagore-demo.local')->firstOrFail();
        $this->actingAs($parent)->get(route('tagore.parent.olympiad.index', ['studentId' => $unlinkedStudent->id]))
            ->assertNotFound();
    }
}
