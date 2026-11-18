<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TagoreCbtManagementTest extends TestCase
{
    public function test_management_routes_are_registered(): void
    {
        foreach ([
            'tagore.cbt.manage',
            'tagore.cbt.banks.store',
            'tagore.cbt.questions.store',
            'tagore.cbt.questions.import',
            'tagore.cbt.questions.update',
            'tagore.cbt.questions.delete',
            'tagore.cbt.exams.store',
            'tagore.cbt.exams.questions.attach',
            'tagore.cbt.exams.publish',
            'tagore.cbt.exams.schedule',
            'tagore.cbt.exams.analytics',
        ] as $name) {
            $this->assertNotNull(app('router')->getRoutes()->getByName($name), "Missing CBT management route {$name}");
        }
    }

    public function test_owner_can_open_management_workspace_but_student_cannot(): void
    {
        $owner = User::query()->where('email', 'owner@tagore-demo.local')->firstOrFail();
        $student = User::query()->where('email', 'student@tagore-demo.local')->firstOrFail();

        $this->actingAs($owner)->get(route('tagore.cbt.manage'))->assertOk()->assertSee('JEE / NEET CBT Management');
        $this->actingAs($student)->get(route('tagore.cbt.manage'))->assertForbidden();
    }

    public function test_owner_can_create_bank_import_questions_and_create_draft_exam(): void
    {
        $owner = User::query()->where('email', 'owner@tagore-demo.local')->firstOrFail();
        $institutionId = (int) DB::table('tagore_institutions')->where('status', 'active')->value('id');
        $this->assertGreaterThan(0, $institutionId);

        $bankResponse = $this->actingAs($owner)->postJson(route('tagore.cbt.banks.store'), [
            'institution_id' => $institutionId,
            'name' => 'Acceptance CBT Bank',
            'exam_track' => 'JEE_MAIN',
        ])->assertCreated()->assertJsonPath('created', true);

        $bankId = (int) $bankResponse->json('bank_id');
        $this->assertGreaterThan(0, $bankId);

        $question = [
            'subject_code' => 'physics',
            'chapter' => 'Kinematics',
            'difficulty' => 'medium',
            'question_type' => 'single_choice',
            'stem' => 'A body starts from rest. Which quantity is zero initially?',
            'options' => ['A' => 'Velocity', 'B' => 'Acceleration'],
            'answer_key' => 'A',
            'explanation' => 'The body starts from rest.',
            'default_marks' => 4,
            'default_negative_marks' => 1,
        ];

        $this->postJson(route('tagore.cbt.questions.import', $bankId), [
            'questions' => [$question],
        ])->assertCreated()->assertJsonPath('imported', 1);

        $this->assertDatabaseHas('tagore_cbt_questions', [
            'question_bank_id' => $bankId,
            'subject_code' => 'physics',
            'status' => 'approved',
        ]);

        $examResponse = $this->postJson(route('tagore.cbt.exams.store'), [
            'institution_id' => $institutionId,
            'title' => 'Acceptance JEE Main Mock',
            'exam_track' => 'JEE_MAIN',
            'duration_minutes' => 180,
        ])->assertCreated()->assertJsonPath('created', true);

        $this->assertDatabaseHas('tagore_cbt_exams', [
            'id' => (int) $examResponse->json('exam_id'),
            'status' => 'draft',
            'exam_track' => 'JEE_MAIN',
        ]);
    }

    public function test_bulk_question_import_rolls_back_when_any_question_is_invalid(): void
    {
        $owner = User::query()->where('email', 'owner@tagore-demo.local')->firstOrFail();
        $institutionId = (int) DB::table('tagore_institutions')->where('status', 'active')->value('id');

        $bank = $this->actingAs($owner)->postJson(route('tagore.cbt.banks.store'), [
            'institution_id' => $institutionId,
            'name' => 'Atomic Import Test',
            'exam_track' => 'NEET',
        ])->assertCreated()->json('bank_id');

        $valid = [
            'subject_code' => 'biology',
            'difficulty' => 'easy',
            'question_type' => 'single_choice',
            'stem' => 'Which is a cell organelle?',
            'options' => ['A' => 'Mitochondrion', 'B' => 'Cell wall'],
            'answer_key' => 'A',
            'default_marks' => 4,
            'default_negative_marks' => 1,
        ];

        $this->postJson(route('tagore.cbt.questions.import', $bank), [
            'questions' => [$valid, ['stem' => 'Invalid second question']],
        ])->assertUnprocessable();

        $this->assertDatabaseMissing('tagore_cbt_questions', [
            'question_bank_id' => $bank,
            'stem' => 'Which is a cell organelle?',
        ]);
    }
}
