<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        $tables = [
            'payroll'=>['employee_id','month','gross_amount','deductions','net_amount','status'],
            'inventory'=>['item_name','category','sku','quantity','unit','reorder_level','unit_cost','vendor','status'],
            'expense'=>['expense_date','category','description','amount','vendor','status','approved_by'],
            'transport'=>['vehicle_no','route_name','driver_name','capacity','gps_device_id','status'],
            'hostel'=>['hostel_name','room_no','bed_no','student_id','check_in','check_out','status'],
            'alumni'=>['student_id','passout_year','course','phone','email','current_org','notes'],
            'visitor'=>['visitor_name','phone','purpose','host_name','check_in','check_out','otp','status'],
            'gatepass'=>['student_id','pass_type','reason','valid_from','valid_until','approved_by','status'],
            'survey'=>['title','audience','is_anonymous','questions_json','status','published_at'],
            'notification'=>['channel','audience','title','message','scheduled_at','status','sent_count','failed_count'],
            'document'=>['owner_type','owner_id','title','document_type','path','version','access_json','status'],
            'course'=>['title','code','description','grade_range','capacity','fee','status'],
            'content'=>['title','content_type','path','grade_range','subject','access_json','status'],
            'report'=>['name','module','definition_json','schedule','format','recipients_json','status'],
            'social'=>['author_id','post_type','title','body','group_name','attachment_path','status'],
            'website'=>['slug','title','body','meta_title','meta_description','published_at','status'],
            'store'=>['product_name','sku','category','price','stock','reorder_level','status'],
            'fee_plan'=>['name','description','amount','tenure','emi_amount','provider','status'],
            'integration'=>['name','provider','type','config_json','last_sync_at','status'],
            'automation'=>['name','module','trigger','action','schedule','last_run_at','next_run_at','status'],
            'creative'=>['name','template_type','template_json','brand_json','status'],
        ];
        foreach($tables as $name=>$columns){
            $table='tagore_'.$name.'_records';
            if(Schema::hasTable($table)) continue;
            Schema::create($table,function(Blueprint $t) use($columns){
                $t->id();
                $t->unsignedBigInteger('institution_id')->nullable()->index();
                foreach($columns as $column){
                    $type=str_ends_with($column,'_json')||in_array($column,['questions_json','access_json','recipients_json','definition_json','config_json','template_json','brand_json'])?'json':'string';
                    if($type==='json') $t->json($column)->nullable();
                    elseif($column==='status') $t->string($column,30)->default('active');
                    elseif(in_array($column,['gross_amount','deductions','net_amount','unit_cost','amount','fee','emi_amount','price'])) $t->decimal($column,12,2)->nullable();
                    elseif(in_array($column,['quantity','reorder_level','capacity','stock','version','sent_count','failed_count'])) $t->integer($column)->default(0);
                    else $t->text($column)->nullable();
                }
                $t->timestamps();
                $t->index(['institution_id','status']);
            });
        }
    }

    public function down(): void
    {
        foreach(['payroll','inventory','expense','transport','hostel','alumni','visitor','gatepass','survey','notification','document','course','content','report','social','website','store','fee_plan','integration','automation','creative'] as $name) Schema::dropIfExists('tagore_'.$name.'_records');
    }
};