<?php

namespace App\Services\Tagore;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

class LegacyFeeMigrationService
{
    public function preview(string $path, int $institutionId, ?int $academicYearId, int $actorId): array
    {
        $book=IOFactory::load($path); $items=[];
        foreach($book->getWorksheetIterator() as $sheet){
            $type=$this->sheetType($sheet->getTitle()); if(!$type || $type==='students') continue;
            foreach($this->sheetRows($sheet,$type) as $n=>$row) $items[]=['type'=>$type,'sheet'=>$sheet->getTitle(),'row'=>$n,'data'=>$row];
        }
        if(!$items) throw ValidationException::withMessages(['file'=>'No supported 2026-27 fee sheets found.']);
        $batch=DB::table('tagore_fee_import_batches')->insertGetId([
            'institution_id'=>$institutionId,'academic_year_id'=>$academicYearId,'created_by'=>$actorId,'source_name'=>basename($path),
            'source_type'=>strtolower(pathinfo($path,PATHINFO_EXTENSION)?:'xlsx'),'import_type'=>'fee_2026_27','status'=>'previewed','row_count'=>count($items),
            'mapping_json'=>json_encode(['recognized_sheets'=>$this->recognizedSheets($book),'matching'=>'explicit legacy mapping for numeric identifiers; unique exact-name fallback; ALL LEDGER is reconciliation-only'],JSON_UNESCAPED_UNICODE),
            'created_at'=>now(),'updated_at'=>now()
        ]);
        $ready=0;$errors=0;
        foreach($items as $item){
            $row=$item['data']; $type=$item['type']; [$student,$matchError]=$this->matchStudent($institutionId,$row);
            $error=in_array($type,['fee_structure','transport_route'],true)?$this->nonStudentError($type,$row):null;
            if(in_array($type,['student_fee','concession','opening_balance','ledger_reference'],true)&&!$student) $error=$matchError;
            $status=$error?'error':($this->meaningful($type,$row)?'ready':'skipped');
            $hash=hash('sha256',$institutionId.'|'.($academicYearId??'').'|'.$type.'|'.$item['sheet'].'|'.$item['row'].'|'.json_encode($row,JSON_UNESCAPED_UNICODE));
            if($status==='ready')$ready++; if($status==='error')$errors++;
            DB::table('tagore_fee_import_rows')->insert([
                'batch_id'=>$batch,'row_type'=>$type,'source_hash'=>$hash,'row_number'=>$item['row'],'external_student_key'=>$this->studentKey($row),'student_id'=>$student,
                'gross_amount'=>$this->money($this->v($row,['TOTAL','TOTAL FEE','GROSS','FEE AMOUNT','PAYABLE','AMOUNT'])),
                'discount_amount'=>$this->money($this->v($row,['DISCOUNT','DISCOUNT AMOUNT'])),'concession_amount'=>$this->money($this->v($row,['CONCESSION','CONCESSION AMOUNT'])),
                'paid_amount'=>$this->money($this->v($row,['RECEIVED','RECEIVED AMOUNT','PAID','AMOUNT RECEIVED'])),'opening_balance'=>$type==='opening_balance'?$this->opening($row):null,
                'status'=>$status,'error_message'=>$error,'raw_json'=>json_encode(['sheet'=>$item['sheet'],'data'=>$row],JSON_UNESCAPED_UNICODE),'created_at'=>now(),'updated_at'=>now()
            ]);
        }
        DB::table('tagore_fee_import_batches')->where('id',$batch)->update(['success_count'=>$ready,'error_count'=>$errors,'status'=>$errors?'needs_review':'ready','updated_at'=>now()]);
        return ['batchId'=>$batch,'success'=>$ready,'errors'=>$errors,'row_count'=>count($items)];
    }

    public function apply(int $batchId,int $actorId):array
    {
        return DB::transaction(function()use($batchId,$actorId){
            $batch=DB::table('tagore_fee_import_batches')->where('id',$batchId)->lockForUpdate()->first(); abort_unless($batch,404);
            if($batch->status!=='ready') throw ValidationException::withMessages(['batch'=>'Import batch is not ready.']);
            $rows=DB::table('tagore_fee_import_rows')->where('batch_id',$batchId)->where('status','ready')->lockForUpdate()->get();
            $c=['fee_structures'=>0,'student_fees'=>0,'payments'=>0,'opening_balances'=>0,'concessions'=>0,'transport_routes'=>0,'transport_assignments'=>0];
            foreach($rows as $r){$p=json_decode($r->raw_json,true)?:[];$row=$p['data']??$p;switch($r->row_type){case'fee_structure':$this->feeStructure($batch,$r,$row,$c);break;case'transport_route':$this->transport($batch,$r,$row,$c);break;case'opening_balance':$this->openingApply($batch,$r,$actorId,$c);break;case'student_fee':$this->studentFee($batch,$r,$row,$actorId,$c);break;case'concession':$this->concession($batch,$r,$row,$actorId,$c);break;case'ledger_reference':break;}DB::table('tagore_fee_import_rows')->where('id',$r->id)->update(['status'=>'applied','updated_at'=>now()]);}
            DB::table('tagore_fee_import_batches')->where('id',$batchId)->update(['status'=>'applied','updated_at'=>now()]); return$c;
        });
    }

    private function sheetType(string $title):?string
    {
        $t=strtoupper(trim($title)); return match($t){
            'FEE STRUCTURE'=>'fee_structure','BUS FEE 26-27','BUS FEE 2026-27'=>'transport_route','OPENING','OPENING BALANCE','OPENING BALANCES'=>'opening_balance',
            'XII SCI FEE STRUCTURE','XII SCIENCE FEE STRUCTURE'=>'student_fee','FEE CONCESSION','FEE CONCESSIONS'=>'concession','STUDENTS'=>'students','ALL LEDGER'=>'ledger_reference',default=>null};
    }

    private function recognizedSheets($book):array{$out=[];foreach($book->getWorksheetIterator() as $s){if($this->sheetType($s->getTitle()))$out[]=$s->getTitle();}return array_values(array_unique($out));}

    private function sheetRows(Worksheet $sheet,string $type):array
    {
        $all=$sheet->toArray(null,true,true,true); if(!$all)return[];
        if($type==='transport_route') return $this->sideBySideRows($all);
        $header=$this->detectHeader($all,$type); if(!$header)return[]; [$headerRow,$map]=$header; $out=[];
        foreach($all as $n=>$vals){if($n<=$headerRow)continue;$row=$this->mapRow($map,$vals);if($this->rowHasData($row))$out[$n]=$row;}
        return$out;
    }

    private function sideBySideRows(array $all):array
    {
        $result=[];
        foreach($all as $n=>$vals){$cells=array_values($vals);$segments=[];$current=[];
            foreach($cells as $v){$text=trim((string)$v);if($text!==''||$current!==[])$current[]=$v;else if($current!==[]){$segments[]=$current;$current=[];}}
            if($current!==[])$segments[]=$current;
            if(count($segments)<=1){$header=$this->normalHeader($vals);if($header) {$result=array_replace($result,$this->rowsFromBlock($all,$n,$header));break;}continue;}
        }
        if($result)return$result;
        $header=$this->detectHeader($all,'transport_route');if(!$header)return[];return $this->rowsFromBlock($all,$header[0],$header[1]);
    }

    private function rowsFromBlock(array $all,int $headerRow,array $map):array{$out=[];foreach($all as $n=>$vals){if($n<=$headerRow)continue;$row=$this->mapRow($map,$vals);if($this->rowHasData($row))$out[$n]=$row;}return$out;}

    private function detectHeader(array $all,string $type):?array
    {
        foreach(array_slice($all,0,15,true) as $n=>$vals){$map=$this->normalHeader($vals);$labels=array_values($map);$hits=count(array_intersect($labels,$this->headerHints($type)));if($hits>=($type==='fee_structure'?1:2))return[$n,$map];}
        return null;
    }
    private function normalHeader(array $vals):array{$map=[];foreach($vals as $col=>$v){$label=strtoupper(trim((string)$v));if($label!=='')$map[$col]=$label;}return$map;}
    private function headerHints(string $type):array{return match($type){'fee_structure'=>['CLASS','STANDARD','CLASS NAME','STD','TOTAL','TOTAL FEE','ONE TIME','ONE TIME FEE'],'opening_balance'=>['STUDENT','STUDENT NAME','FEE AMOUNT','AMOUNT RECEIVED','BALANCE'],'student_fee'=>['STUDENT','STUDENT NAME','RECEIPT NO','RECEIPT NUMBER','TOTAL','AMOUNT','RECEIVED'],'concession'=>['STUDENT','STUDENT NAME','CONCESSION','DISCOUNT'],'ledger_reference'=>['STUDENT','STUDENT NAME','NAME','BALANCE','DUE','OUTSTANDING','AMOUNT'],'transport_route'=>['ROUTE','ROUTE NAME','BUS ROUTE','STOP','STOP NAME','FEE','AMOUNT'],default=>[]};}
    private function mapRow(array $map,array $vals):array{$row=[];foreach($map as $col=>$label)$row[$label]=$vals[$col]??null;return$row;}
    private function rowHasData(array $row):bool{return count(array_filter($row,fn($v)=>$v!==null&&trim((string)$v)!==''))>0;}

    private function feeStructure($batch,$r,$row,&$c):void{$class=trim((string)$this->v($row,['CLASS','STANDARD','CLASS NAME','STD']));if(!$class)return;$name='2026-27 '.$class;$s=DB::table('tagore_fee_structures')->where('institution_id',$batch->institution_id)->where('academic_year_id',$batch->academic_year_id)->whereRaw('lower(name)=?',[strtolower($name)])->first();$id=$s->id??DB::table('tagore_fee_structures')->insertGetId(['institution_id'=>$batch->institution_id,'academic_year_id'=>$batch->academic_year_id,'name'=>$name,'description'=>'Imported legacy FEE STRUCTURE','frequency'=>'annual','status'=>'active','created_at'=>now(),'updated_at'=>now()]);if(!$s)$c['fee_structures']++;foreach($row as $label=>$value){$label=trim((string)$label);$upper=strtoupper($label);if(!$label||in_array($upper,['CLASS','STANDARD','CLASS NAME','STD','TOTAL','TOTAL FEE'],true)||str_contains($upper,'ONE TIME'))continue;$amount=$this->money($value);if($amount<=0)continue;$code=substr(strtoupper(preg_replace('/[^A-Z0-9]+/i','_',$label)),0,60);DB::table('tagore_fee_components')->updateOrInsert(['fee_structure_id'=>$id,'code'=>$code],['name'=>$label,'category'=>'legacy','amount'=>$amount,'status'=>'active','created_at'=>now(),'updated_at'=>now()]);}$one=$this->money($this->v($row,['ONE TIME','ONE TIME FEE','ONE-TIME','ONE TIME AMOUNT']));if($one>0)DB::table('tagore_fee_components')->updateOrInsert(['fee_structure_id'=>$id,'code'=>'ONE_TIME_TOTAL'],['name'=>'One Time Fee','category'=>'one_time','amount'=>$one,'status'=>'active','created_at'=>now(),'updated_at'=>now()]);DB::table('tagore_fee_import_rows')->where('id',$r->id)->update(['target_type'=>'fee_structure','target_id'=>$id]);}
    private function transport($batch,$r,$row,&$c):void{$name=trim((string)$this->v($row,['ROUTE','ROUTE NAME','BUS ROUTE','STOP','STOP NAME','NAME']));if(!$name)return;$code=trim((string)$this->v($row,['ROUTE CODE','CODE']))?:strtoupper(substr(preg_replace('/[^A-Z0-9]+/i','_', $name),0,100));$fee=$this->money($this->v($row,['ANNUAL','ANNUAL FEE','BUS FEE','FEE','AMOUNT','TOTAL']));$x=DB::table('tagore_transport_routes')->where('institution_id',$batch->institution_id)->where('academic_year_id',$batch->academic_year_id)->where('code',$code)->first();$id=$x->id??DB::table('tagore_transport_routes')->insertGetId(['institution_id'=>$batch->institution_id,'academic_year_id'=>$batch->academic_year_id,'name'=>$name,'code'=>$code,'annual_fee'=>$fee,'status'=>'active','source_hash'=>$r->source_hash,'created_at'=>now(),'updated_at'=>now()]);if(!$x)$c['transport_routes']++;if($r->student_id){DB::table('tagore_transport_assignments')->updateOrInsert(['student_id'=>$r->student_id,'academic_year_id'=>$batch->academic_year_id],['route_id'=>$id,'institution_id'=>$batch->institution_id,'annual_fee'=>$fee,'status'=>'active','source_hash'=>$r->source_hash,'created_at'=>now(),'updated_at'=>now()]);$c['transport_assignments']++;}}
    private function openingApply($batch,$r,$actor,&$c):void{$a=round((float)$r->opening_balance,2);if(!$r->student_id||$a<=0)return;if(DB::table('tagore_fee_obligation_items')->where('fee_head','Opening Balance')->where('metadata_json','like','%'.$r->source_hash.'%')->exists())return;$id=DB::table('tagore_fee_obligations')->insertGetId(['student_id'=>$r->student_id,'institution_id'=>$batch->institution_id,'academic_year_id'=>$batch->academic_year_id,'fee_structure_id'=>null,'due_date'=>null,'gross_amount'=>$a,'discount_amount'=>0,'concession_amount'=>0,'net_amount'=>$a,'paid_amount'=>0,'outstanding_amount'=>$a,'status'=>'opening_balance','created_at'=>now(),'updated_at'=>now()]);DB::table('tagore_fee_obligation_items')->insert(['fee_obligation_id'=>$id,'fee_head'=>'Opening Balance','code'=>'OPENING_BALANCE','gross_amount'=>$a,'discount_amount'=>0,'concession_amount'=>0,'net_amount'=>$a,'paid_amount'=>0,'outstanding_amount'=>$a,'metadata_json'=>json_encode(['import_batch_id'=>$batch->id,'source_hash'=>$r->source_hash,'source_row'=>$r->row_number]),'created_at'=>now(),'updated_at'=>now()]);DB::table('tagore_financial_transactions')->insert(['institution_id'=>$batch->institution_id,'student_id'=>$r->student_id,'transaction_type'=>'OPENING_BALANCE','reference_type'=>'tagore_fee_import_batch','reference_id'=>$batch->id,'debit'=>$a,'credit'=>0,'balance_after'=>null,'description'=>'Legacy fee opening balance','transaction_date'=>now(),'created_by'=>$actor,'created_at'=>now(),'updated_at'=>now()]);$c['opening_balances']++;}
    private function studentFee($batch,$r,$row,$actor,&$c):void{$g=$this->money($this->v($row,['TOTAL','TOTAL FEE','GROSS','FEE AMOUNT','AMOUNT','PAYABLE']));$d=$this->money($this->v($row,['DISCOUNT','DISCOUNT AMOUNT']));$co=$this->money($this->v($row,['CONCESSION','CONCESSION AMOUNT']));$p=$this->money($this->v($row,['RECEIVED','RECEIVED AMOUNT','PAID','AMOUNT RECEIVED']));$net=max(0,round($g-$d-$co,2));if(!$r->student_id||($g<=0&&$p<=0))return;$exists=DB::table('tagore_fee_obligations')->where('student_id',$r->student_id)->where('academic_year_id',$batch->academic_year_id)->where('status','legacy_import')->where('gross_amount',$g)->where('paid_amount',$p)->exists();if($exists)return;$id=DB::table('tagore_fee_obligations')->insertGetId(['student_id'=>$r->student_id,'institution_id'=>$batch->institution_id,'academic_year_id'=>$batch->academic_year_id,'fee_structure_id'=>null,'due_date'=>$this->date($this->v($row,['DUE DATE','DATE'])),'gross_amount'=>$g,'discount_amount'=>$d,'concession_amount'=>$co,'net_amount'=>$net,'paid_amount'=>$p,'outstanding_amount'=>max(0,$net-$p),'status'=>$p>=$net&&$net>0?'paid':'legacy_import','created_at'=>now(),'updated_at'=>now()]);$item=DB::table('tagore_fee_obligation_items')->insertGetId(['fee_obligation_id'=>$id,'fee_head'=>'Legacy 2026-27 Fee','code'=>'LEGACY_2026_27','gross_amount'=>$g,'discount_amount'=>$d,'concession_amount'=>$co,'net_amount'=>$net,'paid_amount'=>$p,'outstanding_amount'=>max(0,$net-$p),'metadata_json'=>json_encode(['import_batch_id'=>$batch->id,'source_hash'=>$r->source_hash]),'created_at'=>now(),'updated_at'=>now()]);if($p>0){$payment=DB::table('tagore_payments')->insertGetId(['payment_order_id'=>null,'student_id'=>$r->student_id,'parent_user_id'=>null,'institution_id'=>$batch->institution_id,'amount'=>$p,'currency'=>'INR','gateway'=>null,'gateway_order_id'=>null,'gateway_payment_id'=>null,'status'=>'successful','paid_at'=>$this->date($this->v($row,['DATE','PAYMENT DATE','RECEIPT DATE']))?:now(),'receipt_no'=>$this->v($row,['RECEIPT NO','RECEIPT NUMBER','RECEIPT']),'payment_mode'=>'legacy','reference_number'=>$this->v($row,['RECEIPT NO','RECEIPT NUMBER','RECEIPT']),'notes'=>'Imported legacy fee payment','created_at'=>now(),'updated_at'=>now()]);DB::table('tagore_payment_allocations')->insert(['payment_id'=>$payment,'fee_obligation_id'=>$id,'fee_installment_id'=>null,'fee_obligation_item_id'=>$item,'amount'=>$p,'created_at'=>now(),'updated_at'=>now()]);$c['payments']++;}$c['student_fees']++;}
    private function concession($batch,$r,$row,$actor,&$c):void{$a=$this->money($this->v($row,['CONCESSION','CONCESSION AMOUNT','DISCOUNT','DISCOUNT AMOUNT']));if(!$r->student_id||$a<=0)return;DB::table('tagore_fee_concessions')->updateOrInsert(['student_id'=>$r->student_id,'institution_id'=>$batch->institution_id,'fee_obligation_id'=>null,'amount'=>$a],['type'=>'fixed','value'=>$a,'reason'=>'Legacy import: '.trim((string)$this->v($row,['REASON','REMARK','REMARKS','CONCESSION REASON'])),'approved_by'=>$actor,'approved_at'=>now(),'status'=>'approved','updated_at'=>now(),'created_at'=>now()]);$c['concessions']++;}

    private function matchStudent(int $institution,array $row):array{$school=DB::table('tagore_institutions')->where('id',$institution)->value('school_id');if(!$school)return[null,'Institution is not linked to a GegoK12 school.'];$key=$this->studentKey($row);if($key!==null&&ctype_digit($key)){ $mapped=DB::table('tagore_legacy_student_mappings')->where('institution_id',$institution)->where('source_system','legacy_erp')->where('source_key',$key)->value('student_id');if($mapped)return[(int)$mapped,null];return[null,'Legacy numeric identifier requires an explicit legacy-student mapping.'];}$name=trim((string)$this->v($row,['STUDENT','STUDENT NAME','NAME']));if($name==='')return[null,'Student identifier and name are missing.'];$m=DB::table('users')->where('school_id',$school)->where('usergroup_id',6)->whereRaw('lower(trim(name))=?',[strtolower($name)])->pluck('id');if($m->count()===1)return[(int)$m->first(),null];if($m->count()>1)return[null,'Multiple exact-name matches; manual mapping required.'];return[null,'Student could not be matched by stable ID or exact name.'];}
    private function studentKey(array $row):?string{$v=$this->v($row,['REG NO','REGISTRATION NO','REGISTRATION NUMBER','STUDENT ID','ADM NO','ADMISSION NO','ADMISSION NUMBER']);return$v===null?null:trim((string)$v);}
    private function nonStudentError(string $type,array $row):?string{if($type==='fee_structure'&&!$this->v($row,['CLASS','STANDARD','CLASS NAME','STD']))return'Class/standard is missing.';if($type==='transport_route'&&!$this->v($row,['ROUTE','ROUTE NAME','BUS ROUTE','STOP','STOP NAME','NAME']))return'Route/stop name is missing.';return null;}
    private function meaningful(string $type,array $row):bool{if($type==='ledger_reference')return$this->money($this->v($row,['BALANCE','DUE','OUTSTANDING','AMOUNT']))>0;if($type==='opening_balance')return$this->opening($row)>0;if($type==='fee_structure')return(bool)$this->v($row,['CLASS','STANDARD','CLASS NAME','STD']);if($type==='transport_route')return(bool)$this->v($row,['ROUTE','ROUTE NAME','BUS ROUTE','STOP','STOP NAME','NAME']);if($type==='concession')return$this->money($this->v($row,['CONCESSION','CONCESSION AMOUNT','DISCOUNT','DISCOUNT AMOUNT']))>0;return$this->money($this->v($row,['TOTAL','TOTAL FEE','GROSS','FEE AMOUNT','AMOUNT','PAYABLE','RECEIVED','RECEIVED AMOUNT','PAID']))>0;}
    private function opening(array $row):float{$b=$this->v($row,['BALANCE','FEE BALANCE','OPENING BALANCE']);if($b!==null)return$this->money($b);return max(0,$this->money($this->v($row,['FEE AMOUNT']))-$this->money($this->v($row,['AMOUNT RECEIVED','RECEIVED','PAID'])));}
    private function v(array $row,array $keys){foreach($keys as $k)if(array_key_exists($k,$row)&&trim((string)$row[$k])!=='')return$row[$k];return null;}
    private function money($v):float{return$v===null||$v===''?0:round((float)preg_replace('/[^0-9.\-]/','',(string)$v),2);}
    private function date($v):?string{if($v===null||$v==='')return null;if(is_numeric($v))return ExcelDate::excelToDateTimeObject($v)->format('Y-m-d');foreach(['d/m/Y','d-m-Y','Y-m-d','m/d/Y'] as$f){$d=\DateTime::createFromFormat($f,trim((string)$v));if($d)return$d->format('Y-m-d');}return null;}
}
