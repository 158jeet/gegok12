<?php

namespace Tests\Feature;

use App\Models\TagoreAdmissionLead;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class OlympiadRegistrationTest extends TestCase
{
    private function configureIntake(): int
    {
        $institutionId = (int) DB::table('tagore_institutions')->where('status', 'active')->value('id');
        $this->assertGreaterThan(0, $institutionId, 'A seeded active Tagore institution is required.');
        config([
            'services.olympiad.enabled' => true,
            'services.olympiad.institution_id' => $institutionId,
            'services.olympiad.exam_url' => null,
        ]);

        return $institutionId;
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'student_name' => 'Olympiad QA Student ' . uniqid(),
            'parent_name' => 'Olympiad QA Guardian',
            'mobile' => '9876543210',
            'email' => 'olympiad-qa@example.test',
            'class_name' => '8',
            'medium' => 'hindi',
            'school_name' => 'QA School',
            'city' => 'Gudha Gorji',
            'utm_source' => 'facebook',
            'utm_medium' => 'social',
            'utm_content' => 'navratri-post',
            'utm_term' => 'class-8',
            'campaign' => 'tagore-olympiad-2026',
        ], $overrides);
    }

    public function test_registration_creates_a_campaign_attributed_admissions_lead_without_marketing_consent(): void
    {
        $institutionId = $this->configureIntake();
        $payload = $this->payload();

        $response = $this->post(route('olympiad.register.store'), $payload);
        $response->assertRedirect(route('olympiad.registered'));

        $lead = TagoreAdmissionLead::query()->where('institution_id', $institutionId)
            ->where('student_name', $payload['student_name'])->firstOrFail();

        try {
            $this->assertStringStartsWith('OLY-', $lead->lead_no);
            $this->assertSame('olympiad', $lead->source);
            $this->assertSame('tagore-olympiad-2026', $lead->campaign);
            $this->assertSame('hindi', $lead->medium);
            $this->assertSame('facebook', $lead->utm_source);
            $this->assertFalse((bool) $lead->marketing_consent);
            $this->assertNull($lead->consented_at);
            $this->assertSame('2026-10-13', $lead->privacy_notice_version);
        } finally {
            $lead->forceDelete();
        }
    }

    public function test_marketing_consent_is_recorded_only_when_explicitly_selected(): void
    {
        $institutionId = $this->configureIntake();
        $payload = $this->payload([
            'student_name' => 'Olympiad Consent QA ' . uniqid(),
            'marketing_consent' => '1',
        ]);

        $this->post(route('olympiad.register.store'), $payload)->assertRedirect(route('olympiad.registered'));
        $lead = TagoreAdmissionLead::query()->where('institution_id', $institutionId)
            ->where('student_name', $payload['student_name'])->firstOrFail();

        try {
            $this->assertTrue((bool) $lead->marketing_consent);
            $this->assertNotNull($lead->consented_at);
        } finally {
            $lead->forceDelete();
        }
    }

    public function test_intake_is_hidden_when_the_feature_flag_is_disabled(): void
    {
        config(['services.olympiad.enabled' => false]);
        $this->get('/olympiad/register')->assertNotFound();
    }
}