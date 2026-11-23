<?php

namespace App\Services\Tagore;

use InvalidArgumentException;

class CbtScoringService
{
    /**
     * Score server-side answer data. The answer key must never be sent to the
     * candidate-facing exam player.
     *
     * Supported answer_key_json formats:
     * - single_choice: "B"
     * - multiple_choice: ["A", "C"] (all-or-nothing marking)
     * - numerical: {"value": 12.5, "tolerance": 0.01}
     */
    public function score(array $questions, array $responses): array
    {
        $total = 0.0;
        $max = 0.0;
        $correct = 0;
        $wrong = 0;
        $unanswered = 0;
        $details = [];

        foreach ($questions as $question) {
            $id = (string) $this->value($question, 'exam_question_id', $this->value($question, 'id', ''));
            $type = (string) $this->value($question, 'question_type', '');
            $positive = (float) $this->value($question, 'marks', 0);
            $negative = (float) $this->value($question, 'negative_marks', 0);
            if (!in_array($type, ['single_choice', 'multiple_choice', 'numerical'], true)
                || $positive < 0 || $negative < 0) {
                throw new InvalidArgumentException('Unsupported CBT question type or marking scheme.');
            }

            $key = $this->decode($this->value($question, 'answer_key_json'));
            $response = $responses[$id] ?? null;
            $answered = !($response === null || $response === '' || $response === []);
            if (!$answered) {
                $unanswered++;
                $awarded = 0.0;
                $isCorrect = null;
            } else {
                $isCorrect = $this->matches($type, $key, $response);
                if ($isCorrect) {
                    $correct++;
                    $awarded = $positive;
                } else {
                    $wrong++;
                    $awarded = -$negative;
                }
            }

            $max += $positive;
            $total += $awarded;
            $details[$id] = [
                'is_correct' => $isCorrect,
                'awarded_marks' => round($awarded, 2),
                'answered' => $answered,
            ];
        }

        $attempted = $correct + $wrong;
        return [
            'score' => round($total, 2),
            'max_score' => round($max, 2),
            'correct_count' => $correct,
            'wrong_count' => $wrong,
            'unanswered_count' => $unanswered,
            'accuracy' => $attempted > 0 ? round(($correct / $attempted) * 100, 2) : 0.0,
            'details' => $details,
        ];
    }

    private function matches(string $type, mixed $key, mixed $response): bool
    {
        if ($type === 'single_choice') {
            return strtoupper(trim((string) $key)) === strtoupper(trim((string) $response));
        }

        if ($type === 'multiple_choice') {
            if (!is_array($key) || !is_array($response)) return false;
            $expected = array_values(array_unique(array_map(fn ($v) => strtoupper(trim((string) $v)), $key)));
            $actual = array_values(array_unique(array_map(fn ($v) => strtoupper(trim((string) $v)), $response)));
            sort($expected);
            sort($actual);
            return $expected === $actual;
        }

        if (!is_array($key) || !array_key_exists('value', $key) || !is_numeric($key['value']) || !is_numeric($response)) {
            return false;
        }
        $tolerance = isset($key['tolerance']) && is_numeric($key['tolerance']) ? max(0.0, (float) $key['tolerance']) : 0.0;
        return abs((float) $response - (float) $key['value']) <= $tolerance;
    }

    private function decode(mixed $value): mixed
    {
        if (!is_string($value)) return $value;
        $decoded = json_decode($value, true);
        return json_last_error() === JSON_ERROR_NONE ? $decoded : $value;
    }

    private function value(array $source, string $key, mixed $default = null): mixed
    {
        return array_key_exists($key, $source) ? $source[$key] : $default;
    }
}
