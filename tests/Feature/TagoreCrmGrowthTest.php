<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TagoreCrmGrowthTest extends TestCase
{
    private function owner(): User
    {
        $ownerId = DB::table('tagore_user_roles as ur')
            ->join('tagore_roles as r', 'r.id', '=', 'ur.role_id')
            ->where('r.code', 'OWNER')->where('ur.status', 'active')
            ->value('ur.user_id');

        return User::query()->findOrFail($ownerId);
    }

    private function lead(int $institutionId, string $name, ?string $campaign = null): int
    {
        return (int) DB::table('tagore_admission_leads')->insertGetId([
            'institution_id' => $institutionId,
            'lead_no' => 'QA-CRM-' . strtoupper(bin2hex(random_bytes(5))),
            'student_name' => $name,
            'parent_name' => 'CRM QA Parent',
            'mobile' => '98' . random_int(10000000, 99999999),
            'class_name' => 'XI',
            'source' => 'website',
            'campaign' => $campaign,
            'status' => 'qualified',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_owner_can_create_an_opportunity_from_an_institution_scoped_lead(): void
    {
        $owner = $this->owner();
        $institutionId = (int) DB::table('tagore_institutions')->where('status', 'active')->value('id');
        $leadId = $this->lead($institutionId, 'CRM Opportunity Acceptance Lead');

        $this->actingAs($owner)->post(route('tagore.crm.opportunities.store'), [
            'institution_id' => $institutionId,
            'lead_id' => $leadId,
            'name' => 'Class XI NEET 2027 opportunity',
            'opportunity_type' => 'coaching',
            'stage' => 'proposal',
            'amount' => 125000,
            'probability' => 60,
            'expected_close_date' => now()->addMonth()->toDateString(),
            'source' => 'website',
            'notes' => 'Family requested a counselling call.',
        ])->assertRedirect()->assertSessionHas('success');

        $this->assertDatabaseHas('tagore_crm_opportunities', [
            'institution_id' => $institutionId,
            'lead_id' => $leadId,
            'name' => 'Class XI NEET 2027 opportunity',
            'stage' => 'proposal',
            'status' => 'open',
            'amount' => '125000.00',
            'probability' => 60,
        ]);

        $this->actingAs($owner)->get(route('tagore.crm.index'))
            ->assertOk()->assertSee('CRM Growth Workspace')->assertSee('Class XI NEET 2027 opportunity');
    }

    public function test_campaign_attribution_calculates_cost_per_lead_and_won_revenue_roi(): void
    {
        $owner = $this->owner();
        $institutionId = (int) DB::table('tagore_institutions')->where('status', 'active')->value('id');
        $campaignCode = 'QA-CRM-' . strtoupper(bin2hex(random_bytes(4)));

        $this->actingAs($owner)->post(route('tagore.crm.campaigns.store'), [
            'institution_id' => $institutionId,
            'name' => 'QA JEE Admission Campaign',
            'campaign_code' => $campaignCode,
            'channel' => 'digital',
            'status' => 'active',
            'objective' => 'JEE admission enquiries',
            'budget' => 1000,
            'spend' => 500,
            'starts_on' => now()->subDays(2)->toDateString(),
            'ends_on' => now()->addDays(10)->toDateString(),
            'utm_source' => 'google',
            'utm_medium' => 'cpc',
        ])->assertRedirect()->assertSessionHas('success');

        $leadId = $this->lead($institutionId, 'CRM Campaign Converted Lead', $campaignCode);
        DB::table('tagore_admission_leads')->where('id', $leadId)->update(['status' => 'admitted']);

        $this->actingAs($owner)->post(route('tagore.crm.opportunities.store'), [
            'institution_id' => $institutionId,
            'lead_id' => $leadId,
            'name' => 'QA JEE Campaign Won Deal',
            'opportunity_type' => 'coaching',
            'stage' => 'won',
            'amount' => 2000,
            'probability' => 100,
            'campaign' => $campaignCode,
        ])->assertRedirect();

        $this->actingAs($owner)->get(route('tagore.crm.index'))
            ->assertOk()->assertSee($campaignCode)->assertSee('300.0%')->assertSee('2,000');
    }

    public function test_crm_tasks_and_support_cases_are_assigned_tracked_and_audited(): void
    {
        $owner = $this->owner();
        $institutionId = (int) DB::table('tagore_institutions')->where('status', 'active')->value('id');
        $leadId = $this->lead($institutionId, 'CRM Task and Case Lead');

        $this->actingAs($owner)->post(route('tagore.crm.tasks.store'), [
            'institution_id' => $institutionId,
            'lead_id' => $leadId,
            'title' => 'Call parent with counselling slot options',
            'task_type' => 'call',
            'priority' => 'high',
            'due_at' => now()->addHour()->toDateTimeString(),
            'assigned_to' => $owner->id,
            'description' => 'Offer two available counselling slots.',
        ])->assertRedirect()->assertSessionHas('success');

        $taskId = (int) DB::table('tagore_crm_tasks')->where('title', 'Call parent with counselling slot options')->value('id');
        $this->assertGreaterThan(0, $taskId);
        $this->actingAs($owner)->patch(route('tagore.crm.tasks.update', $taskId), [
            'status' => 'completed',
            'priority' => 'high',
            'due_at' => now()->toDateTimeString(),
            'assigned_to' => $owner->id,
            'outcome' => 'connected',
        ])->assertRedirect()->assertSessionHas('success');
        $this->assertDatabaseHas('tagore_crm_tasks', ['id' => $taskId, 'status' => 'completed']);
        $this->assertDatabaseHas('tagore_admission_activities', [
            'lead_id' => $leadId, 'type' => 'call', 'outcome' => 'connected',
        ]);

        $this->actingAs($owner)->post(route('tagore.crm.cases.store'), [
            'institution_id' => $institutionId,
            'lead_id' => $leadId,
            'subject' => 'Parent requested admission document clarification',
            'category' => 'admissions',
            'priority' => 'normal',
            'assigned_to' => $owner->id,
            'due_at' => now()->addDay()->toDateTimeString(),
            'description' => 'Clarify transfer certificate requirements.',
        ])->assertRedirect()->assertSessionHas('success');

        $caseId = (int) DB::table('tagore_crm_cases')->where('subject', 'Parent requested admission document clarification')->value('id');
        $this->assertGreaterThan(0, $caseId);
        $this->actingAs($owner)->patch(route('tagore.crm.cases.update', $caseId), [
            'status' => 'resolved',
            'priority' => 'normal',
            'assigned_to' => $owner->id,
            'due_at' => now()->toDateTimeString(),
            'resolution' => 'Document checklist sent to the parent and acknowledged.',
        ])->assertRedirect()->assertSessionHas('success');
        $this->assertDatabaseHas('tagore_crm_cases', [
            'id' => $caseId,
            'status' => 'resolved',
            'resolution' => 'Document checklist sent to the parent and acknowledged.',
        ]);
    }
    public function test_crm_rejects_a_lead_from_a_different_institution(): void
    {
        $owner = $this->owner();
        $institutionId = (int) DB::table('tagore_institutions')->where('status', 'active')->orderBy('id')->value('id');
        $schoolId = (int) DB::table('schools')->where('slug', 'tagore-acceptance-unlinked-school')->value('id');
        $groupId = (int) DB::table('tagore_groups')->where('code', 'TAGORE')->value('id');
        $otherInstitutionId = (int) (DB::table('tagore_institutions')->where('school_id', $schoolId)->value('id') ?: DB::table('tagore_institutions')->insertGetId([
            'tagore_group_id' => $groupId,
            'school_id' => $schoolId,
            'code' => 'QA-CRM-CROSS-INSTITUTION',
            'display_name' => 'QA CRM Cross Institution',
            'type' => 'school',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]));
        $leadId = $this->lead($otherInstitutionId, 'Cross Institution CRM Lead');

        $this->actingAs($owner)->post(route('tagore.crm.opportunities.store'), [
            'institution_id' => $institutionId,
            'lead_id' => $leadId,
            'name' => 'Must not cross institutions',
            'opportunity_type' => 'admission',
            'stage' => 'qualification',
            'amount' => 100,
            'probability' => 10,
        ])->assertStatus(422);

        $this->assertDatabaseMissing('tagore_crm_opportunities', ['name' => 'Must not cross institutions']);
    }

    public function test_teacher_cannot_access_group_crm_growth_workspace(): void
    {
        $teacher = User::query()->where('usergroup_id', 5)->whereNull('deleted_at')->orderBy('id')->firstOrFail();
        $this->actingAs($teacher)->get(route('tagore.crm.index'))->assertForbidden();
    }

    public function test_owner_can_create_and_complete_a_crm_follow_up_task(): void
    {
        $owner = $this->owner();
        $institutionId = (int) DB::table('tagore_institutions')->where('status', 'active')->value('id');
        $leadId = $this->lead($institutionId, 'CRM Task Acceptance Lead');

        $this->actingAs($owner)->post(route('tagore.crm.tasks.store'), [
            'institution_id' => $institutionId,
            'lead_id' => $leadId,
            'title' => 'Call parent for admission counselling',
            'task_type' => 'call',
            'priority' => 'high',
            'due_at' => now()->addDay()->toDateTimeString(),
            'assigned_to' => $owner->id,
            'description' => 'Discuss JEE and NEET programme options.',
        ])->assertRedirect()->assertSessionHas('success');

        $task = DB::table('tagore_crm_tasks')->where('lead_id', $leadId)->first();
        $this->assertNotNull($task);

        $this->actingAs($owner)->patch(route('tagore.crm.tasks.update', $task->id), [
            'status' => 'completed',
            'priority' => 'normal',
            'due_at' => now()->addDay()->toDateTimeString(),
            'assigned_to' => $owner->id,
            'outcome' => 'connected',
        ])->assertRedirect()->assertSessionHas('success');

        $this->assertDatabaseHas('tagore_crm_tasks', ['id' => $task->id, 'status' => 'completed']);
        $this->assertDatabaseHas('tagore_admission_activities', [
            'lead_id' => $leadId,
            'type' => 'call',
            'outcome' => 'connected',
        ]);
    }

    public function test_owner_can_open_and_resolve_a_customer_support_case(): void
    {
        $owner = $this->owner();
        $institutionId = (int) DB::table('tagore_institutions')->where('status', 'active')->value('id');
        $leadId = $this->lead($institutionId, 'CRM Case Acceptance Lead');

        $this->actingAs($owner)->post(route('tagore.crm.cases.store'), [
            'institution_id' => $institutionId,
            'lead_id' => $leadId,
            'subject' => 'Admission fee clarification requested',
            'category' => 'admissions',
            'priority' => 'high',
            'assigned_to' => $owner->id,
            'due_at' => now()->addDay()->toDateTimeString(),
            'description' => 'Family requires a written fee breakup.',
        ])->assertRedirect()->assertSessionHas('success');

        $case = DB::table('tagore_crm_cases')->where('lead_id', $leadId)->first();
        $this->assertNotNull($case);

        $this->actingAs($owner)->patch(route('tagore.crm.cases.update', $case->id), [
            'status' => 'resolved',
            'priority' => 'normal',
            'assigned_to' => $owner->id,
            'due_at' => now()->addDay()->toDateTimeString(),
            'resolution' => 'Fee structure shared and acknowledged by the family.',
        ])->assertRedirect()->assertSessionHas('success');

        $this->assertDatabaseHas('tagore_crm_cases', [
            'id' => $case->id,
            'status' => 'resolved',
            'resolution' => 'Fee structure shared and acknowledged by the family.',
        ]);
    }
}
