<?php

namespace App\Services\Tagore;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InventoryService
{
    public function moveStock(int $institutionId, int $itemId, float $quantity, string $type, int $userId, ?string $notes=null): void
    {
        if ($quantity <= 0 || !in_array($type,['in','out','adjust'],true)) {
            throw ValidationException::withMessages(['stock'=>'Invalid stock movement.']);
        }

        DB::transaction(function () use ($institutionId,$itemId,$quantity,$type,$userId,$notes) {
            $item=DB::table('tagore_inventory_items')->where('id',$itemId)->where('institution_id',$institutionId)->lockForUpdate()->first();
            if (!$item) throw ValidationException::withMessages(['item'=>'Inventory item not found.']);
            $new=(float)$item->quantity;
            if ($type==='in') $new += $quantity;
            elseif ($type==='out') $new -= $quantity;
            else $new = $quantity;
            if ($new < 0) throw ValidationException::withMessages(['stock'=>'Insufficient stock.']);

            DB::table('tagore_inventory_items')->where('id',$itemId)->update(['quantity'=>round($new,3),'updated_at'=>now()]);
            DB::table('tagore_inventory_movements')->insert([
                'institution_id'=>$institutionId,'inventory_item_id'=>$itemId,'movement_type'=>$type,
                'quantity'=>$quantity,'unit_cost'=>(float)$item->unit_cost,'performed_by'=>$userId,'notes'=>$notes,
                'created_at'=>now(),'updated_at'=>now(),
            ]);
        });
    }

    public function approveExpense(int $expenseId,int $institutionId,int $userId,string $status,?string $notes=null): void
    {
        if (!in_array($status,['approved','rejected'],true)) throw ValidationException::withMessages(['status'=>'Invalid expense decision.']);
        $expense=DB::table('tagore_expense_claims')->where('id',$expenseId)->where('institution_id',$institutionId)->lockForUpdate()->first();
        if (!$expense || $expense->status!=='pending') throw ValidationException::withMessages(['expense'=>'Only pending expenses can be decided.']);
        DB::table('tagore_expense_claims')->where('id',$expenseId)->update(['status'=>$status,'approved_by'=>$userId,'approval_notes'=>$notes,'approved_at'=>now(),'updated_at'=>now()]);
    }

    public function receivePurchaseOrder(int $poId,int $institutionId,int $userId): void
    {
        DB::transaction(function () use($poId,$institutionId,$userId) {
            $po=DB::table('tagore_inventory_purchase_orders')->where('id',$poId)->where('institution_id',$institutionId)->lockForUpdate()->first();
            if (!$po || !in_array($po->status,['approved','partial'],true)) throw ValidationException::withMessages(['purchase_order'=>'Purchase order is not ready for receipt.']);
            $items=DB::table('tagore_inventory_purchase_order_items')->where('purchase_order_id',$poId)->lockForUpdate()->get();
            foreach($items as $line){
                $remaining=max(0,(float)$line->ordered_quantity-(float)$line->received_quantity);
                if($remaining<=0) continue;
                $inventory=DB::table('tagore_inventory_items')->where('id',$line->inventory_item_id)->where('institution_id',$institutionId)->lockForUpdate()->first();
                if(!$inventory) throw ValidationException::withMessages(['purchase_order'=>'An inventory item is missing.']);
                DB::table('tagore_inventory_items')->where('id',$inventory->id)->update([
                    'quantity'=>round((float)$inventory->quantity+$remaining,3),
                    'unit_cost'=>(float)$line->unit_cost,'updated_at'=>now()
                ]);
                DB::table('tagore_inventory_movements')->insert([
                    'institution_id'=>$institutionId,'inventory_item_id'=>$inventory->id,'movement_type'=>'in',
                    'quantity'=>$remaining,'unit_cost'=>(float)$line->unit_cost,'reference_type'=>'purchase_order',
                    'reference_id'=>$poId,'performed_by'=>$userId,'notes'=>'Purchase order receipt',
                    'created_at'=>now(),'updated_at'=>now()
                ]);
                DB::table('tagore_inventory_purchase_order_items')->where('id',$line->id)->update(['received_quantity'=>(float)$line->ordered_quantity,'updated_at'=>now()]);
            }
            DB::table('tagore_inventory_purchase_orders')->where('id',$poId)->update(['status'=>'received','received_at'=>now(),'updated_at'=>now()]);
        });
    }
}
