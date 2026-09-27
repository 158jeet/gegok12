<?php

namespace App\Services\Tagore;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class FeeEditorService
{
    public function bulkAdjust(array $filters, float $minPercent, float $maxPercent, int $userId): int
    {
        if ($minPercent < -100 || $maxPercent > 100 || $minPercent > $maxPercent) {
            throw ValidationException::withMessages(['percentage'=>'Percentage range must be between -100% and 100%, with minimum no greater than maximum.']);
        }

        return DB::transaction(function () use ($filters,$minPercent,$maxPercent,$userId) {
            $query=DB::table('tagore_fee_obligations')
                ->where('is_opening_balance',false);
            foreach (['institution_id','academic_year_id','fee_structure_id','student_id'] as $field) {
                if (isset($filters[$field]) && $filters[$field] !== '') $query->where($field,(int)$filters[$field]);
            }
            $rows=$query->lockForUpdate()->get();
            $count=0;

            foreach($rows as $row){
                $percentage=$minPercent === $maxPercent ? $minPercent : (random_int((int)round($minPercent*100),(int)round($maxPercent*100))/100);
                $factor=1+($percentage/100);
                $gross=round((float)$row->gross_amount*$factor,2);
                $discount=round((float)$row->discount_amount*$factor,2);
                $concession=round((float)$row->concession_amount*$factor,2);
                $net=max(0,round($gross-$discount-$concession,2));
                $paid=(float)$row->paid_amount;
                $outstanding=max(0,round($net-$paid,2));
                $status=$outstanding<=0.009?'paid':($paid>0?'partial':'pending');

                DB::table('tagore_fee_obligations')->where('id',$row->id)->update([
                    'gross_amount'=>$gross,'discount_amount'=>$discount,'concession_amount'=>$concession,
                    'net_amount'=>$net,'outstanding_amount'=>$outstanding,'status'=>$status,'updated_at'=>now(),
                ]);

                DB::table('tagore_fee_editor_events')->insert([
                    'institution_id'=>$row->institution_id,'student_id'=>$row->student_id,'fee_obligation_id'=>$row->id,
                    'change_type'=>'BULK_PERCENTAGE_ADJUSTMENT','percentage'=>$percentage,
                    'old_amount'=>$row->net_amount,'new_amount'=>$net,
                    'old_values_json'=>json_encode(['gross'=>$row->gross_amount,'discount'=>$row->discount_amount,'concession'=>$row->concession_amount,'net'=>$row->net_amount]),
                    'new_values_json'=>json_encode(['gross'=>$gross,'discount'=>$discount,'concession'=>$concession,'net'=>$net]),
                    'edited_by'=>$userId,'created_at'=>now(),'updated_at'=>now(),
                ]);
                $count++;
            }
            return $count;
        });
    }
}
