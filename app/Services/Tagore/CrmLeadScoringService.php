<?php

namespace App\Services\Tagore;

use App\Models\TagoreAdmissionLead;

class CrmLeadScoringService
{
    /**
     * Deterministic, explainable engagement score for admissions and growth teams.
     * This is a rules-based prioritization signal, not a prediction of admission.
     */
    public function score(TagoreAdmissionLead $lead): array
    {
        $score = 0;
        $signals = [];

        $add = function (int $points, string $signal) use (&$score, &$signals): void {
            $score += $points;
            $signals[] = $signal;
        };

        if (filled($lead->mobile)) $add(20, 'Reachable mobile');
        if (filled($lead->email)) $add(10, 'Email available');
        if (filled($lead->parent_name)) $add(10, 'Guardian identified');
        if (filled($lead->class_name)) $add(10, 'Class interest captured');
        if (filled($lead->source)) $add(5, 'Lead source attributed');
        if (filled($lead->campaign)) $add(5, 'Campaign attributed');
        if ($lead->marketing_consent) $add(5, 'Marketing consent recorded');
        if ($lead->next_follow_up_at) $add(10, 'Follow-up scheduled');
        if ($lead->last_contacted_at && $lead->last_contacted_at->gte(now()->subDays(7))) $add(10, 'Recently contacted');
        if ((int) ($lead->activities_count ?? 0) > 0) $add(5, 'Activity history exists');
        if ((int) ($lead->calls_count ?? 0) > 0) $add(5, 'Cloud call history exists');
        if ($lead->status === 'qualified') $add(5, 'Qualified by admissions');

        if ($lead->status === 'admitted') {
            $score = 100;
            $signals[] = 'Admission completed';
        } elseif ($lead->status === 'lost') {
            $score = 0;
            $signals = ['Lead marked lost'];
        }

        $score = min(100, $score);
        $band = $score >= 70 ? 'hot' : ($score >= 40 ? 'warm' : 'cold');

        return ['score' => $score, 'band' => $band, 'signals' => $signals];
    }
}
