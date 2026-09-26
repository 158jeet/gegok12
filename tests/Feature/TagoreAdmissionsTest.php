<?php

namespace Tests\Feature;

use App\Models\User;
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
            'type'=>'meeting','outcome'=>'Admission confirmed','status'=>'admitted',
        ])->assertRedirect();

        $this->assertNotNull($lead->fresh()->converted_at);

        $this->actingAs($owner)->post(route('tagore.admissions.activity',$leadId),[
            'type'=>'call','outcome'=>'Not proceeding','status'=>'lost','lost_reason'=>'Fee concern',
        ])->assertRedirect();

        $this->assertSame('Fee concern',$lead->fresh()->lost_reason);
    }

}
