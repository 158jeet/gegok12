<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\TagoreAdmissionLead;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TagoreAdmissionsTest extends TestCase
{
    public function test_authorized_staff_can_create_and_view_admission_lead(): void
    {
        $owner=User::query()->where('email','demoschool@mailinator.com')->firstOrFail();
        $institutionId=(int)DB::table('tagore_institutions')->value('id');

        $response=$this->actingAs($owner)->post(route('tagore.admissions.store'),[
            'institution_id'=>$institutionId,'student_name'=>'QA Admission Student',
            'parent_name'=>'QA Parent','mobile'=>'9999999999','class_name'=>'IX',
            'source'=>'QA','notes'=>'Automated test lead',
        ]);

        $response->assertRedirect();
        $leadId=(int)DB::table('tagore_admission_leads')->where('student_name','QA Admission Student')->value('id');
        $this->assertGreaterThan(0,$leadId);
        $this->assertDatabaseHas('tagore_admission_activities',['lead_id'=>$leadId,'type'=>'created']);

        $this->actingAs($owner)->get(route('tagore.admissions.show',$leadId))
            ->assertOk()->assertSee('QA Admission Student');
    }

    public function test_duplicate_student_contact_is_flagged_without_blocking_sibling_shared_mobile(): void
    {
        $owner = User::query()->where('email', 'demoschool@mailinator.com')->firstOrFail();
        $institutionId = (int) DB::table('tagore_institutions')->where('status', 'active')->value('id');
        $mobile = '+91 98765 43210';
        $existingLeadId = DB::table('tagore_admission_leads')->insertGetId([
            'institution_id' => $institutionId,
            'lead_no' => 'QA-DUPLICATE-' . uniqid(),
            'student_name' => 'Duplicate Detection Student',
            'parent_name' => 'Shared Parent',
            'mobile' => $mobile,
            'email' => 'duplicate.student@example.test',
            'status' => 'follow_up',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($owner)->post(route('tagore.admissions.store'), [
            'institution_id' => $institutionId,
            'student_name' => 'Duplicate Detection Student',
            'parent_name' => 'Shared Parent',
            'mobile' => '9876543210',
            'email' => 'DUPLICATE.STUDENT@example.test',
            'class_name' => 'XI',
        ])->assertRedirect(route('tagore.admissions.show', $existingLeadId))
            ->assertSessionHas('warning');

        $this->assertSame(1, DB::table('tagore_admission_leads')
            ->where('institution_id', $institutionId)
            ->where('student_name', 'Duplicate Detection Student')->count());

        $this->actingAs($owner)->post(route('tagore.admissions.store'), [
            'institution_id' => $institutionId,
            'student_name' => 'Sibling With Shared Parent Mobile',
            'parent_name' => 'Shared Parent',
            'mobile' => '9876543210',
            'class_name' => 'VIII',
        ])->assertRedirect()->assertSessionMissing('warning');

        $this->assertDatabaseHas('tagore_admission_leads', [
            'institution_id' => $institutionId,
            'student_name' => 'Sibling With Shared Parent Mobile',
        ]);
    }

    public function test_lead_engagement_score_is_explainable_and_uses_activity_and_call_history(): void
    {
        $owner = User::query()->where('email', 'demoschool@mailinator.com')->firstOrFail();
        $institutionId = (int) DB::table('tagore_institutions')->where('status', 'active')->value('id');
        $leadId = DB::table('tagore_admission_leads')->insertGetId([
            'institution_id' => $institutionId,
            'lead_no' => 'QA-SCORE-' . uniqid(),
            'student_name' => 'High Intent CRM Student',
            'parent_name' => 'High Intent Parent',
            'mobile' => '+919876543210',
            'email' => 'high.intent@example.test',
            'class_name' => 'XI',
            'source' => 'website',
            'campaign' => 'JEE-2027',
            'status' => 'qualified',
            'marketing_consent' => true,
            'next_follow_up_at' => now()->addDay(),
            'last_contacted_at' => now()->subHour(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('tagore_admission_activities')->insert([
            'lead_id' => $leadId, 'user_id' => $owner->id, 'type' => 'call',
            'outcome' => 'connected', 'notes' => 'Parent requested counselling.',
            'completed_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);
        $accountId = DB::table('tagore_crm_telephony_accounts')->insertGetId([
            'institution_id' => $institutionId, 'provider' => 'exotel',
            'account_sid' => 'qa-lead-score-account', 'credentials' => json_encode([]),
            'caller_id' => '+911234567890', 'webhook_token_hash' => hash('sha256', 'qa-score-token'),
            'record_calls' => false, 'is_active' => true, 'created_by' => $owner->id,
            'created_at' => now(), 'updated_at' => now(),
        ]);        DB::table('tagore_crm_calls')->insert([
            'institution_id' => $institutionId, 'telephony_account_id' => $accountId,
            'lead_id' => $leadId, 'provider_call_id' => 'QA-LEAD-SCORE-CALL',
            'direction' => 'outbound', 'status' => 'completed', 'duration_seconds' => 90,
            'started_at' => now()->subMinutes(2), 'ended_at' => now(),
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $lead = TagoreAdmissionLead::query()->withCount(['activities', 'calls'])->findOrFail($leadId);
        $score = app(\App\Services\Tagore\CrmLeadScoringService::class)->score($lead);

        $this->assertSame('hot', $score['band']);
        $this->assertGreaterThanOrEqual(90, $score['score']);
        $this->assertContains('Cloud call history exists', $score['signals']);
    }
    public function test_teacher_cannot_access_admissions_module(): void
    {
        $teacher=User::query()->where('usergroup_id',5)->whereNull('deleted_at')->orderBy('id')->firstOrFail();
        $this->actingAs($teacher)->get(route('tagore.admissions.index'))->assertForbidden();
    }

    public function test_admission_activity_is_institution_scoped(): void
    {
        $owner=User::query()->where('email','demoschool@mailinator.com')->firstOrFail();
        $institutionId=(int)DB::table('tagore_institutions')->value('id');

        $leadId=DB::table('tagore_admission_leads')->insertGetId([
            'institution_id'=>$institutionId,'lead_no'=>'QA-SCOPE-'.uniqid(),'student_name'=>'Scope Test',
            'status'=>'new','created_at'=>now(),'updated_at'=>now(),
        ]);

        $this->actingAs($owner)->post(route('tagore.admissions.activity',$leadId),[
            'type'=>'call','outcome'=>'connected','notes'=>'QA follow-up','status'=>'follow_up',
        ])->assertRedirect();

        $this->assertDatabaseHas('tagore_admission_activities',['lead_id'=>$leadId,'type'=>'call','outcome'=>'connected']);
        $this->assertDatabaseHas('tagore_admission_leads',['id'=>$leadId,'status'=>'follow_up']);
    }
    public function test_manager_can_assign_lead_and_filter_follow_up_queue(): void
    {
        $owner=User::query()->where('email','demoschool@mailinator.com')->firstOrFail();
        $institutionId=(int)DB::table('tagore_institutions')->value('id');
        $teacher=User::query()->where('usergroup_id',5)->whereNull('deleted_at')->orderBy('id')->firstOrFail();

        $leadId=DB::table('tagore_admission_leads')->insertGetId([
            'institution_id'=>$institutionId,'lead_no'=>'QA-FUNNEL-'.uniqid(),
            'student_name'=>'Funnel Student','mobile'=>'9888888888','source'=>'Meta',
            'campaign'=>'JEE 2027','status'=>'follow_up','next_follow_up_at'=>now()->subHour(),
            'created_at'=>now(),'updated_at'=>now(),
        ]);

        $this->actingAs($owner)->patch(route('tagore.admissions.assign',$leadId),[
            'assigned_to'=>$teacher->id,
        ])->assertRedirect();

        $this->assertDatabaseHas('tagore_admission_leads',[
            'id'=>$leadId,'assigned_to'=>$teacher->id,'campaign'=>'JEE 2027',
        ]);

        $this->actingAs($owner)->get(route('tagore.admissions.index',['follow_up'=>'overdue']))
            ->assertOk()->assertSee('Funnel Student')->assertSee('JEE 2027');
    }

    public function test_activity_records_contact_and_conversion_or_lost_reason(): void
    {
        $owner=User::query()->where('email','demoschool@mailinator.com')->firstOrFail();
        $institutionId=(int)DB::table('tagore_institutions')->value('id');
        $leadId=DB::table('tagore_admission_leads')->insertGetId([
            'institution_id'=>$institutionId,'lead_no'=>'QA-CONV-'.uniqid(),
            'student_name'=>'Conversion Student','mobile'=>'9777777777','status'=>'new',
            'created_at'=>now(),'updated_at'=>now(),
        ]);

        $this->actingAs($owner)->post(route('tagore.admissions.activity',$leadId),[
            'type'=>'call','outcome'=>'Interested','notes'=>'Parent wants campus visit.',
            'status'=>'follow_up','next_follow_up_at'=>now()->addDay(),
        ])->assertRedirect();

        $this->assertDatabaseHas('tagore_admission_leads',[
            'id'=>$leadId,'status'=>'follow_up',
        ]);
        $lead=TagoreAdmissionLead::findOrFail($leadId);
        $this->assertNotNull($lead->last_contacted_at);

        $this->actingAs($owner)->post(route('tagore.admissions.activity',$leadId),[
            'type'=>'meeting','outcome'=>'Admission confirmed','status'=>'qualified',
        ])->assertRedirect();

        $this->assertNull($lead->fresh()->converted_at, 'Admitted status alone must not pretend a student was created.');

        $institution=DB::table('tagore_institutions')->where('id',$institutionId)->first();
        $schoolId=(int)$institution->school_id;
        $academicYearId=DB::table('academic_years')->insertGetId([
            'school_id'=>$schoolId,'name'=>'QA Conversion Year '.uniqid(),
            'start_date'=>now()->startOfYear(),'end_date'=>now()->endOfYear(),
            'status'=>1,'created_at'=>now(),'updated_at'=>now(),
        ]);
        $standardId=DB::table('standards')->insertGetId([
            'school_id'=>$schoolId,'name'=>'QA Conversion Class','slug'=>'qa-conversion-'.uniqid(),
            'order'=>99,'status'=>1,'created_at'=>now(),'updated_at'=>now(),
        ]);
        $sectionId=DB::table('sections')->insertGetId([
            'school_id'=>$schoolId,'name'=>'QA','status'=>1,'created_at'=>now(),'updated_at'=>now(),
        ]);
        $classId=DB::table('standards_link')->insertGetId([
            'school_id'=>$schoolId,'academic_year_id'=>$academicYearId,'class_teacher_id'=>$owner->id,
            'standard_id'=>$standardId,'section_id'=>$sectionId,'status'=>1,
            'created_at'=>now(),'updated_at'=>now(),
        ]);
        $class=(object)['id'=>$classId];
        $conversionResponse = $this->actingAs($owner)->post(route('tagore.admissions.convert',$leadId),['standard_link_id'=>$class->id]);
        $conversionResponse->assertRedirect()->assertSessionHas('issued_student_credentials');
        $issued = session('issued_student_credentials');
        $this->assertNotEmpty($issued['registration_number']);
        $this->assertNotEmpty($issued['initial_password']);
        $studentId=(int)DB::table('tagore_admission_conversions')->where('lead_id',$leadId)->value('student_id');
        $this->assertGreaterThan(0,$studentId);
        $this->assertDatabaseHas('users',['id'=>$studentId,'school_id'=>$institution->school_id,'usergroup_id'=>6,'name'=>'Conversion Student']);
        $this->assertNotEmpty(DB::table('users')->where('id',$studentId)->value('registration_number'));
        $storedPassword = DB::table('users')->where('id',$studentId)->value('password');
        $this->assertNotSame($issued['initial_password'], $storedPassword);
        $this->assertTrue(\Illuminate\Support\Facades\Hash::check($issued['initial_password'], $storedPassword));
        $this->assertDatabaseHas('userprofiles',['user_id'=>$studentId,'firstname'=>'Conversion Student']);
        $this->assertDatabaseHas('student_academics',['user_id'=>$studentId,'standardLink_id'=>$class->id]);
        $studentRoleId=DB::table('tagore_roles')->where('code','STUDENT')->value('id');
        $this->assertNotNull($studentRoleId);
        $this->assertDatabaseHas('tagore_user_roles',['user_id'=>$studentId,'role_id'=>$studentRoleId,'institution_id'=>$institutionId,'status'=>'active']);
        $this->assertNotNull($lead->fresh()->converted_at);
        $retryResponse = $this->actingAs($owner)->post(route('tagore.admissions.convert',$leadId),['standard_link_id'=>$class->id]);
        $retryResponse->assertRedirect()->assertSessionMissing('issued_student_credentials');
        $this->assertSame(1,DB::table('tagore_admission_conversions')->where('lead_id',$leadId)->count());
        $this->assertSame(1,DB::table('student_academics')->where('user_id',$studentId)->count());

        $this->actingAs($owner)->post(route('tagore.admissions.activity',$leadId),[
            'type'=>'call','outcome'=>'Not proceeding','status'=>'lost','lost_reason'=>'Fee concern',
        ])->assertRedirect();

        $this->assertSame('Fee concern',$lead->fresh()->lost_reason);
    }


    public function test_authorized_staff_can_link_existing_parent_to_student_with_audit(): void
    {
        $owner = User::query()->where('email', 'demoschool@mailinator.com')->firstOrFail();
        $institution = DB::table('tagore_institutions')->first();
        $this->assertNotNull($institution);

        $student = User::query()->where('usergroup_id', 6)
            ->where('school_id', $institution->school_id)->whereNull('deleted_at')->firstOrFail();
        $parent = User::query()->where('usergroup_id', 7)
            ->where('school_id', $institution->school_id)->whereNull('deleted_at')->firstOrFail();

        $mobile = '98' . str_pad((string) $parent->id, 8, '0', STR_PAD_LEFT);
        DB::table('users')->where('id', $parent->id)->update(['mobile_no' => $mobile, 'mobile_verified' => true]);

        $leadId = DB::table('tagore_admission_leads')->insertGetId([
            'institution_id' => $institution->id,
            'lead_no' => 'QA-FAMILY-' . uniqid(),
            'student_name' => 'Family Link Acceptance Student',
            'status' => 'new',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($owner)->post(route('tagore.admissions.link-family', $leadId), [
            'student_id' => $student->id,
            'parent_mobile' => $mobile,
            'relationship' => 'Guardian',
        ])->assertRedirect();

        $this->assertDatabaseHas('tagore_parent_students', [
            'parent_user_id' => $parent->id,
            'student_id' => $student->id,
            'relationship' => 'Guardian',
            'status' => 'active',
        ]);
        $this->assertDatabaseHas('tagore_audit_events', [
            'user_id' => $owner->id,
            'action' => 'admissions.family_link',
            'entity_type' => 'Student',
            'entity_id' => $student->id,
        ]);

        // Repeating the same family-link action must be idempotent, not create duplicate links.
        $this->actingAs($owner)->post(route('tagore.admissions.link-family', $leadId), [
            'student_id' => $student->id,
            'parent_mobile' => $mobile,
            'relationship' => 'Guardian',
        ])->assertRedirect();

        $this->assertSame(1, DB::table('tagore_parent_students')
            ->where('parent_user_id', $parent->id)
            ->where('student_id', $student->id)
            ->where('status', 'active')
            ->count());
    }

    public function test_family_link_rejects_an_unmatched_parent_mobile_without_creating_a_link(): void
    {
        $owner = User::query()->where('email', 'demoschool@mailinator.com')->firstOrFail();
        $institution = DB::table('tagore_institutions')->first();
        $this->assertNotNull($institution);

        $student = User::query()->where('usergroup_id', 6)
            ->where('school_id', $institution->school_id)->whereNull('deleted_at')->firstOrFail();

        $leadId = DB::table('tagore_admission_leads')->insertGetId([
            'institution_id' => $institution->id,
            'lead_no' => 'QA-FAMILY-INVALID-' . uniqid(),
            'student_name' => 'Invalid Family Link Student',
            'status' => 'new',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        do {
            $invalidMobile = '9000' . str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        } while (DB::table('users')->where('mobile_no', $invalidMobile)->exists());

        $linksBefore = DB::table('tagore_parent_students')->where('student_id', $student->id)->count();

        $this->actingAs($owner)->post(route('tagore.admissions.link-family', $leadId), [
            'student_id' => $student->id,
            'parent_mobile' => $invalidMobile,
            'relationship' => 'Guardian',
        ])->assertStatus(422);

        $this->assertSame($linksBefore, DB::table('tagore_parent_students')
            ->where('student_id', $student->id)
            ->count(), 'A rejected family-link attempt must not change parent/student links.');
    }

    public function test_family_link_rejects_matching_but_unverified_parent_mobile(): void
    {
        $owner = User::query()->where('email', 'demoschool@mailinator.com')->firstOrFail();
        $institution = DB::table('tagore_institutions')->first();
        $this->assertNotNull($institution);
        $student = User::query()->where('usergroup_id', 6)->where('school_id', $institution->school_id)->whereNull('deleted_at')->firstOrFail();
        $parent = User::query()->where('usergroup_id', 7)->whereNull('deleted_at')->firstOrFail();
        $mobile = '97' . str_pad((string) $parent->id, 8, '0', STR_PAD_LEFT);
        DB::table('users')->where('id', $parent->id)->update(['mobile_no' => $mobile, 'mobile_verified' => false]);
        $leadId = DB::table('tagore_admission_leads')->insertGetId([
            'institution_id' => $institution->id, 'lead_no' => 'QA-UNVERIFIED-' . uniqid(),
            'student_name' => 'Unverified Parent Student', 'status' => 'new',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $existingLinks = DB::table('tagore_parent_students')
            ->where('parent_user_id', $parent->id)
            ->where('student_id', $student->id)
            ->count();

        $this->actingAs($owner)->post(route('tagore.admissions.link-family', $leadId), [
            'student_id' => $student->id, 'parent_mobile' => $mobile, 'relationship' => 'Guardian',
        ])->assertStatus(422);

        $this->assertSame($existingLinks, DB::table('tagore_parent_students')
            ->where('parent_user_id', $parent->id)
            ->where('student_id', $student->id)
            ->count(), 'An unverified mobile must not create or modify a parent-child link.');
    }

    public function test_family_link_rejects_ambiguous_verified_parent_mobile(): void
    {
        $owner = User::query()->where('email', 'demoschool@mailinator.com')->firstOrFail();
        $institution = DB::table('tagore_institutions')->first();
        $this->assertNotNull($institution);
        $student = User::query()->where('usergroup_id', 6)->where('school_id', $institution->school_id)->whereNull('deleted_at')->firstOrFail();

        $firstParent = User::factory()->parent()->create(['school_id' => $institution->school_id]);
        $secondParent = User::factory()->parent()->create(['school_id' => $institution->school_id]);
        $sharedVerifiedMobile = '96' . str_pad((string) $firstParent->id, 8, '0', STR_PAD_LEFT);
        DB::table('users')->whereIn('id', [$firstParent->id, $secondParent->id])->update([
            'mobile_no' => $sharedVerifiedMobile,
            'mobile_verified' => true,
        ]);

        $leadId = DB::table('tagore_admission_leads')->insertGetId([
            'institution_id' => $institution->id,
            'lead_no' => 'QA-AMBIGUOUS-' . uniqid(),
            'student_name' => 'Ambiguous Parent Student',
            'status' => 'new',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($owner)->post(route('tagore.admissions.link-family', $leadId), [
            'student_id' => $student->id,
            'parent_mobile' => $sharedVerifiedMobile,
            'relationship' => 'Guardian',
        ])->assertStatus(422);

        $this->assertDatabaseMissing('tagore_parent_students', [
            'parent_user_id' => $firstParent->id,
            'student_id' => $student->id,
        ]);
        $this->assertDatabaseMissing('tagore_parent_students', [
            'parent_user_id' => $secondParent->id,
            'student_id' => $student->id,
        ]);
    }

}
