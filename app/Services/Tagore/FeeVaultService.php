<?php

namespace App\Services\Tagore;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class FeeVaultService
{
    public function closeYear(int $academicYearId, int $institutionId, int $userId): array
    {
        $existing = DB::table('tagore_fee_year_closures')->where('academic_year_id',$academicYearId)->where('institution_id',$institutionId)->first();
        if ($existing) return (array)$existing;

        $payload = [
            'academic_year_id'=>$academicYearId,
            'institution_id'=>$institutionId,
            'closed_at'=>now()->toIso8601String(),
            'students'=>DB::table('tagore_fee_obligations')->where('academic_year_id',$academicYearId)->where('institution_id',$institutionId)->get()->map(fn($r)=>(array)$r)->all(),
            'items'=>DB::table('tagore_fee_obligation_items as i')->join('tagore_fee_obligations as o','o.id','=','i.fee_obligation_id')->where('o.academic_year_id',$academicYearId)->where('o.institution_id',$institutionId)->get()->map(fn($r)=>(array)$r)->all(),
            'installments'=>DB::table('tagore_fee_installments as i')->join('tagore_fee_obligations as o','o.id','=','i.fee_obligation_id')->where('o.academic_year_id',$academicYearId)->where('o.institution_id',$institutionId)->get()->map(fn($r)=>(array)$r)->all(),
            'payments'=>DB::table('tagore_payments')->where('institution_id',$institutionId)->whereBetween('paid_at',[$academicYearId ? '2000-01-01' : now()->toDateString(), now()])->get()->map(fn($r)=>(array)$r)->all(),
            'allocations'=>DB::table('tagore_payment_allocations as a')->join('tagore_payments as p','p.id','=','a.payment_id')->where('p.institution_id',$institutionId)->get()->map(fn($r)=>(array)$r)->all(),
            'transactions'=>DB::table('tagore_financial_transactions')->where('institution_id',$institutionId)->get()->map(fn($r)=>(array)$r)->all(),
        ];

        $bytes=json_encode($payload, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
        $relative='tagore-fee-vault/'.$institutionId.'/'.$academicYearId.'/archive-'.now()->format('YmdHis').'.json';
        Storage::disk('local')->put($relative,$bytes);
        $sha=hash('sha256',$bytes);

        $id=DB::table('tagore_fee_year_closures')->insertGetId([
            'academic_year_id'=>$academicYearId,'institution_id'=>$institutionId,'closed_at'=>now(),'closed_by'=>$userId,
            'archive_path'=>$relative,'archive_sha256'=>$sha,'student_count'=>count($payload['students']),
            'payment_count'=>count($payload['payments']),'transaction_count'=>count($payload['transactions']),
            'status'=>'archived','created_at'=>now(),'updated_at'=>now(),
        ]);
        DB::table('tagore_audit_events')->insert([
            'user_id'=>$userId,'institution_id'=>$institutionId,'action'=>'FEE_YEAR_ARCHIVED','entity_type'=>'tagore_fee_year_closures','entity_id'=>$id,
            'new_values_json'=>json_encode(['academic_year_id'=>$academicYearId,'archive_sha256'=>$sha]),'created_at'=>now(),'updated_at'=>now(),
        ]);
        return (array)DB::table('tagore_fee_year_closures')->find($id);
    }

    public function studentArchives(int $studentId): array
    {
        $rows=DB::table('tagore_fee_year_closures as c')
            ->join('tagore_fee_obligations as o',function($j){$j->on('o.academic_year_id','=','c.academic_year_id')->on('o.institution_id','=','c.institution_id');})
            ->where('o.student_id',$studentId)->select('c.*')->distinct()->orderByDesc('c.closed_at')->get();
        $out=[];
        foreach($rows as $row){
            if(!Storage::disk('local')->exists($row->archive_path)) continue;
            $data=json_decode(Storage::disk('local')->get($row->archive_path),true);
            $out[]=['closure'=>(array)$row,'records'=>array_values(array_filter($data['students']??[],fn($x)=>(int)($x['student_id']??0)===$studentId)),'payments'=>array_values(array_filter($data['payments']??[],fn($x)=>(int)($x['student_id']??0)===$studentId)),'transactions'=>array_values(array_filter($data['transactions']??[],fn($x)=>(int)($x['student_id']??0)===$studentId))];
        }
        return $out;
    }
}
