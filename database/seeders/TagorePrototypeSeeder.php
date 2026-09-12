<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class TagorePrototypeSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();
        $groupId = DB::table('tagore_groups')->where('code', 'TAGORE')->value('id') ?: DB::table('tagore_groups')->insertGetId(['name'=>'Tagore Group','code'=>'TAGORE','status'=>'active','created_at'=>$now,'updated_at'=>$now]);
        $roles = [['Owner','OWNER'],['Principal','PRINCIPAL'],['Coordinator','COORDINATOR'],['Teacher','TEACHER'],['Parent','PARENT'],['Student','STUDENT'],['Accounts','ACCOUNTS']];
        foreach ($roles as [$name,$code]) DB::table('tagore_roles')->updateOrInsert(['code'=>$code],['name'=>$name,'is_system'=>true,'status'=>'active','updated_at'=>$now,'created_at'=>$now]);
        $permissions = [['dashboard','view'],['student','view'],['parent','view'],['attendance','view'],['attendance','manage'],['fee','view'],['fee','manage'],['payment','view'],['payment','refund'],['payment','reconcile'],['result','view'],['result','manage'],['result','publish'],['feedback','view'],['feedback','submit'],['feedback','respond'],['feedback','moderate'],['report','view'],['report','export'],['user','manage'],['scope','manage']];
        foreach ($permissions as [$module,$action]) DB::table('tagore_permissions')->updateOrInsert(['module'=>$module,'action'=>$action],['code'=>$module.'.'.$action,'description'=>ucfirst($action).' '.$module,'created_at'=>$now,'updated_at'=>$now]);
        foreach ($roles as [$name,$code]) {
            $roleId=DB::table('tagore_roles')->where('code',$code)->value('id');
            $codes=match($code){'OWNER'=>DB::table('tagore_permissions')->pluck('code')->all(),'PRINCIPAL'=>['dashboard.view','student.view','parent.view','attendance.view','attendance.manage','fee.view','fee.manage','payment.view','result.view','result.manage','result.publish','feedback.view','feedback.respond','report.view','report.export'],'COORDINATOR'=>['dashboard.view','student.view','parent.view','attendance.view','attendance.manage','result.view','result.manage','feedback.view','feedback.respond','report.view'],'TEACHER'=>['dashboard.view','student.view','attendance.view','attendance.manage','result.view','result.manage','feedback.view','report.view'],'PARENT'=>['dashboard.view','student.view','attendance.view','fee.view','payment.view','result.view','feedback.view','feedback.submit'],'STUDENT'=>['dashboard.view','attendance.view','result.view','feedback.view'],'ACCOUNTS'=>['dashboard.view','student.view','fee.view','fee.manage','payment.view','payment.reconcile','report.view','report.export'],default=>[]};
            foreach($codes as $permissionCode){$permissionId=DB::table('tagore_permissions')->where('code',$permissionCode)->value('id');if($permissionId)DB::table('tagore_role_permissions')->updateOrInsert(['role_id'=>$roleId,'permission_id'=>$permissionId],[]);}
        }
        DB::table('tagore_feedback_categories')->upsert([['name'=>'Academic','code'=>'ACADEMIC','status'=>'active','created_at'=>$now,'updated_at'=>$now],['name'=>'Transport','code'=>'TRANSPORT','status'=>'active','created_at'=>$now,'updated_at'=>$now],['name'=>'General','code'=>'GENERAL','status'=>'active','created_at'=>$now,'updated_at'=>$now]],['code'],['name','status','updated_at']);

        // Reuse existing GegoK12 schools and users; no new credentials are created.
        foreach(DB::table('schools')->whereNull('deleted_at')->orderBy('id')->limit(3)->get() as $school){
            $institutionId=DB::table('tagore_institutions')->where('school_id',$school->id)->value('id');
            if(!$institutionId)$institutionId=DB::table('tagore_institutions')->insertGetId(['tagore_group_id'=>$groupId,'school_id'=>$school->id,'code'=>'TAGORE-'.$school->id,'display_name'=>$school->name,'type'=>'school','status'=>'active','created_at'=>$now,'updated_at'=>$now]);
            foreach(DB::table('users')->where('school_id',$school->id)->whereNull('deleted_at')->get(['id','usergroup_id']) as $user){
                $roleCode=match((int)$user->usergroup_id){1,2,3=>'OWNER',4=>'PRINCIPAL',5=>'TEACHER',6=>'STUDENT',7=>'PARENT',11=>'ACCOUNTS',default=>null};
                if($roleCode){$roleId=DB::table('tagore_roles')->where('code',$roleCode)->value('id');DB::table('tagore_user_roles')->updateOrInsert(['user_id'=>$user->id,'role_id'=>$roleId,'institution_id'=>$institutionId],['status'=>'active','updated_at'=>$now,'created_at'=>$now]);}
            }
        }
        if(Schema::hasTable('student_parent_links')) foreach(DB::table('student_parent_links')->where('status','active')->get(['parent_id','student_id']) as $link) DB::table('tagore_parent_students')->updateOrInsert(['parent_user_id'=>$link->parent_id,'student_id'=>$link->student_id],['relationship'=>'Guardian','is_primary'=>true,'is_guardian'=>true,'status'=>'active','updated_at'=>$now,'created_at'=>$now]);

        foreach(DB::table('users')->where('usergroup_id',6)->whereNull('deleted_at')->orderBy('id')->limit(12)->get(['id','school_id']) as $student){
            $institutionId=DB::table('tagore_institutions')->where('school_id',$student->school_id)->value('id');if(!$institutionId)continue;
            if(!DB::table('tagore_fee_obligations')->where('student_id',$student->id)->exists())DB::table('tagore_fee_obligations')->insert(['student_id'=>$student->id,'institution_id'=>$institutionId,'due_date'=>$now->copy()->addDays(20)->toDateString(),'gross_amount'=>45000,'discount_amount'=>5000,'concession_amount'=>0,'net_amount'=>40000,'paid_amount'=>20000,'outstanding_amount'=>20000,'status'=>'partial','created_at'=>$now,'updated_at'=>$now]);
            if(!DB::table('tagore_results')->where('student_id',$student->id)->exists())foreach([['Unit Test 1','English',78,'B+'],['Unit Test 1','Mathematics',91,'A+'],['Unit Test 1','Science',84,'A']] as [$exam,$subject,$marks,$grade])DB::table('tagore_results')->insert(['student_id'=>$student->id,'institution_id'=>$institutionId,'exam_name'=>$exam,'subject'=>$subject,'marks'=>$marks,'max_marks'=>100,'grade'=>$grade,'status'=>'published','created_at'=>$now,'updated_at'=>$now]);
        }
    }
}
