<?php

namespace Tests\Unit\Tagore;

use App\Services\Tagore\CbtScoringService;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class CbtScoringServiceTest extends TestCase
{
    public function test_scores_jee_style_single_multiple_and_numerical_questions_with_negative_marking(): void
    {
        $questions = [
            ['exam_question_id' => 11, 'question_type' => 'single_choice', 'answer_key_json' => '"B"', 'marks' => 4, 'negative_marks' => 1],
            ['exam_question_id' => 12, 'question_type' => 'multiple_choice', 'answer_key_json' => '["A","C"]', 'marks' => 4, 'negative_marks' => 1],
            ['exam_question_id' => 13, 'question_type' => 'numerical', 'answer_key_json' => '{"value":12.5,"tolerance":0.05}', 'marks' => 4, 'negative_marks' => 0],
            ['exam_question_id' => 14, 'question_type' => 'single_choice', 'answer_key_json' => '"D"', 'marks' => 4, 'negative_marks' => 1],
            ['exam_question_id' => 15, 'question_type' => 'single_choice', 'answer_key_json' => '"A"', 'marks' => 4, 'negative_marks' => 1],
        ];

        $result = app(CbtScoringService::class)->score($questions, [
            '11' => 'b',
            '12' => ['C', 'A', 'A'],
            '13' => '12.53',
            '14' => 'B',
        ]);

        $this->assertSame(11.0, $result['score']);
        $this->assertSame(20.0, $result['max_score']);
        $this->assertSame(3, $result['correct_count']);
        $this->assertSame(1, $result['wrong_count']);
        $this->assertSame(1, $result['unanswered_count']);
        $this->assertSame(75.0, $result['accuracy']);
        $this->assertSame(-1.0, $result['details']['14']['awarded_marks']);
        $this->assertSame(0.0, $result['details']['15']['awarded_marks']);
        $this->assertNull($result['details']['15']['is_correct']);
        $this->assertFalse($result['details']['15']['answered']);
    }

    public function test_multiple_choice_is_all_or_nothing_and_numerical_respects_tolerance(): void
    {
        $questions = [
            ['id' => 1, 'question_type' => 'multiple_choice', 'answer_key_json' => ['A', 'C'], 'marks' => 4, 'negative_marks' => 1],
            ['id' => 2, 'question_type' => 'numerical', 'answer_key_json' => ['value' => 2.5, 'tolerance' => 0.01], 'marks' => 4, 'negative_marks' => 0],
        ];
        $result = app(CbtScoringService::class)->score($questions, ['1' => ['A'], '2' => 2.7]);

        $this->assertSame(-1.0, $result['score']);
        $this->assertSame(0, $result['correct_count']);
        $this->assertSame(2, $result['wrong_count']);
        $this->assertSame(0, $result['unanswered_count']);
    }

    public function test_rejects_unknown_question_types_and_negative_mark_values(): void
    {
        $this->expectException(InvalidArgumentException::class);
        app(CbtScoringService::class)->score([
            ['id' => 1, 'question_type' => 'essay', 'answer_key_json' => null, 'marks' => 4, 'negative_marks' => 0],
        ], []);
    }

    public function test_empty_responses_produce_zero_score_and_zero_accuracy(): void
    {
        $result = app(CbtScoringService::class)->score([
            ['id' => 1, 'question_type' => 'single_choice', 'answer_key_json' => '"A"', 'marks' => 4, 'negative_marks' => 1],
        ], []);

        $this->assertSame(0.0, $result['score']);
        $this->assertSame(0.0, $result['accuracy']);
        $this->assertSame(1, $result['unanswered_count']);
    }
}
