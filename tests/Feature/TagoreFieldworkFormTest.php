<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TagoreFieldworkFormTest extends TestCase
{
    public function test_manager_can_publish_versioned_form_and_assign_campaign_to_teacher(): void
    {
        $owner = User::query()->where('email', 'owner@tagore-demo.local')->whereNull('deleted_at')->firstOrFail();
        $teacher = User::query()->where('email', 'teacher@tagore-demo.local')->whereNull('deleted_at')->firstOrFail();
        $institutionId = (int) DB::table('tagore_institutions')
            ->where('school_id', $owner->school_id)->where('status', 'active')->value('id');
        $this->assertGreaterThan(0, $institutionId);

        $fields = [
            ['key' => 'student_name', 'label' => 'Student name', 'type' => 'text', 'required' => true],
            ['key' => 'needs_hostel', 'label' => 'Hostel required?', 'type' => 'checkbox'],
            ['key' => 'hostel_notes', 'label' => 'Hostel details', 'type' => 'textarea', 'required' => true,
                'visible_if' => ['field' => 'needs_hostel', 'equals' => '1']],
        ];

        $created = $this->actingAs($owner)->postJson(route('tagore.fieldwork.forms.store'), [
            'institution_id' => $institutionId,
            'name' => 'Admission Field Enquiry QA',
            'description' => 'Automated acceptance form',
            'fields' => $fields,
        ])->assertCreated()->assertJsonPath('status', 'draft');

        $templateId = (int) $created->json('id');
        $published = $this->actingAs($owner)->postJson(route('tagore.fieldwork.forms.publish', $templateId), [
            'fields' => $fields,
        ])->assertCreated()->assertJsonPath('immutable', true);
        $versionId = (int) $published->json('form_version_id');

        // Publishing a changed schema creates a new version; it does not mutate the pinned version.
        $changedFields = $fields;
        $changedFields[0]['label'] = 'Applicant name';
        $second = $this->actingAs($owner)->postJson(route('tagore.fieldwork.forms.publish', $templateId), [
            'fields' => $changedFields,
        ])->assertCreated();
        $this->assertNotSame($versionId, (int) $second->json('form_version_id'));
        $this->assertDatabaseHas('tagore_form_versions', ['id' => $versionId, 'template_id' => $templateId]);

        $campaign = $this->actingAs($owner)->postJson(route('tagore.fieldwork.campaigns.store'), [
            'institution_id' => $institutionId,
            'template_id' => $templateId,
            'form_version_id' => $versionId,
            'name' => 'Gudha Gorji campus enquiry drive',
            'assigned_user_ids' => [$teacher->id],
        ])->assertCreated()->assertJsonPath('assigned_count', 1);
        $campaignId = (int) $campaign->json('campaign_id');

        $this->actingAs($teacher)->get(route('tagore.fieldwork.campaigns.mine'))
            ->assertOk()->assertJsonFragment(['id' => $campaignId]);

        $this->actingAs($teacher)->postJson(route('tagore.fieldwork.submissions.store', $campaignId), [
            'payload' => ['needs_hostel' => '1'],
            'finalize' => true,
        ])->assertStatus(422);

        $submission = $this->actingAs($teacher)->postJson(route('tagore.fieldwork.submissions.store', $campaignId), [
            'payload' => ['student_name' => 'QA Applicant', 'needs_hostel' => '1', 'hostel_notes' => 'Needs hostel details'],
            'finalize' => true,
        ])->assertCreated()->assertJsonPath('status', 'submitted');

        $this->assertDatabaseHas('tagore_form_submissions', [
            'id' => $submission->json('submission_id'),
            'campaign_id' => $campaignId,
            'form_version_id' => $versionId,
            'submitted_by' => $teacher->id,
            'status' => 'submitted',
        ]);
        $this->assertDatabaseHas('tagore_form_submission_events', [
            'submission_id' => $submission->json('submission_id'),
            'actor_id' => $teacher->id,
            'event_type' => 'submitted',
        ]);
    }

    public function test_teacher_can_open_assigned_fieldwork_but_cannot_manage_templates_or_campaigns(): void
    {
        $teacher = User::query()->where('email', 'teacher@tagore-demo.local')->whereNull('deleted_at')->firstOrFail();

        $this->actingAs($teacher)->get(route('tagore.fieldwork.studio'))
            ->assertOk()
            ->assertSee('My assigned fieldwork')
            ->assertDontSee('Form template');

        $this->actingAs($teacher)->get(route('tagore.fieldwork.forms.index'))->assertForbidden();
        $this->actingAs($teacher)->postJson(route('tagore.fieldwork.forms.store'), [
            'institution_id' => 1,
            'name' => 'Unauthorized form',
            'fields' => [['key' => 'name', 'label' => 'Name', 'type' => 'text']],
        ])->assertForbidden();
    }

    public function test_form_schema_rejects_duplicate_keys_and_invalid_conditional_references(): void
    {
        $owner = User::query()->where('email', 'owner@tagore-demo.local')->whereNull('deleted_at')->firstOrFail();
        $institutionId = (int) DB::table('tagore_institutions')
            ->where('school_id', $owner->school_id)->where('status', 'active')->value('id');

        $this->actingAs($owner)->postJson(route('tagore.fieldwork.forms.store'), [
            'institution_id' => $institutionId,
            'name' => 'Invalid QA form',
            'fields' => [
                ['key' => 'student_name', 'label' => 'Name', 'type' => 'text'],
                ['key' => 'student_name', 'label' => 'Duplicate', 'type' => 'text'],
            ],
        ])->assertStatus(422);

        $this->actingAs($owner)->postJson(route('tagore.fieldwork.forms.store'), [
            'institution_id' => $institutionId,
            'name' => 'Invalid conditional QA form',
            'fields' => [
                ['key' => 'student_name', 'label' => 'Name', 'type' => 'text',
                    'visible_if' => ['field' => 'missing_field', 'equals' => 'yes']],
            ],
        ])->assertStatus(422);
    }
}
