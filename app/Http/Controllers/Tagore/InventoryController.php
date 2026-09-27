<?php

namespace App\Http\Controllers\Tagore;

use App\Http\Controllers\Controller;
use App\Services\Tagore\InventoryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class InventoryController extends Controller
{
    public function index(Request $request): View
    {
        [$roles,$ids]=$this->context($request);
        abort_unless($roles->contains('OWNER') || $roles->intersect(['PRINCIPAL','ACCOUNTS'])->isNotEmpty(),403);
        $institutions=DB::table('tagore_institutions')->whereIn('id',$ids)->where('status','active')->orderBy('display_name')->get(['id','display_name']);
        $vendors=DB::table('tagore_inventory_vendors')->whereIn('institution_id',$ids)->where('status','active')->orderBy('name')->get();
        $items=DB::table('tagore_inventory_items')->whereIn('institution_id',$ids)->orderBy('name')->limit(500)->get();
        $orders=DB::table('tagore_inventory_purchase_orders')->whereIn('institution_id',$ids)->orderByDesc('id')->limit(100)->get();
        $expenses=DB::table('tagore_expense_claims')->whereIn('institution_id',$ids)->orderByDesc('id')->limit(100)->get();
        $assets=DB::table('tagore_assets')->whereIn('institution_id',$ids)->orderByDesc('id')->limit(100)->get();
        return view('tagore.inventory.index',compact('institutions','vendors','items','orders','expenses','assets'));
    }

    public function vendor(Request $request)
    {
        [$roles,$ids]=$this->context($request); $this->authorize($roles);
        $d=$request->validate(['institution_id'=>'required|integer','name'=>'required|string|max:190','phone'=>'nullable|string|max:30','email'=>'nullable|email|max:190','address'=>'nullable|string|max:2000','gstin'=>'nullable|string|max:30']);
        abort_unless(in_array((int)$d['institution_id'],$ids,true),403);
        DB::table('tagore_inventory_vendors')->insert($d+['status'=>'active','created_at'=>now(),'updated_at'=>now()]);
        return back()->with('success','Vendor added.');
    }

    public function item(Request $request)
    {
        [$roles,$ids]=$this->context($request); $this->authorize($roles);
        $d=$request->validate(['institution_id'=>'required|integer','name'=>'required|string|max:190','sku'=>'nullable|string|max:100','category'=>'nullable|string|max:100','unit'=>'nullable|string|max:30','reorder_level'=>'numeric|min:0','unit_cost'=>'numeric|min:0','vendor_id'=>'nullable|integer']);
        abort_unless(in_array((int)$d['institution_id'],$ids,true),403);
        DB::table('tagore_inventory_items')->insert($d+['quantity'=>0,'status'=>'active','created_at'=>now(),'updated_at'=>now()]);
        return back()->with('success','Inventory item added.');
    }

    public function movement(Request $request,InventoryService $service)
    {
        [$roles,$ids]=$this->context($request); $this->authorize($roles);
        $d=$request->validate(['institution_id'=>'required|integer','item_id'=>'required|integer','quantity'=>'required|numeric|min:0.001','type'=>'required|in:in,out,adjust','notes'=>'nullable|string|max:2000']);
        abort_unless(in_array((int)$d['institution_id'],$ids,true),403);
        $service->moveStock((int)$d['institution_id'],(int)$d['item_id'],(float)$d['quantity'],$d['type'],(int)$request->user()->id,$d['notes']??null);
        return back()->with('success','Stock movement recorded.');
    }

    public function purchaseOrder(Request $request)
    {
        [$roles,$ids]=$this->context($request); $this->authorize($roles);
        $d=$request->validate(['institution_id'=>'required|integer','vendor_id'=>'required|integer','items_json'=>'required|json']);
        abort_unless(in_array((int)$d['institution_id'],$ids,true),403);
        $items=json_decode($d['items_json'],true); abort_unless(is_array($items) && $items,422);
        $total=0;
        foreach($items as $line){
            $qty=(float)($line['quantity']??0); $cost=(float)($line['unit_cost']??0); $itemId=(int)($line['item_id']??0);
            abort_if($qty<=0 || $cost<0 || $itemId<=0,422,'Invalid purchase order line.');
            $valid=DB::table('tagore_inventory_items')->where('id',$itemId)->where('institution_id',$d['institution_id'])->exists();
            abort_unless($valid,422,'Purchase order item is outside the institution.');
            $total += $qty*$cost;
        }
        $poId=DB::transaction(function() use($d,$items,$total,$request){
            $poId=DB::table('tagore_inventory_purchase_orders')->insertGetId([
                'institution_id'=>$d['institution_id'],'vendor_id'=>$d['vendor_id'],
                'po_no'=>'PO-'.$d['institution_id'].'-'.now()->format('YmdHis').'-'.random_int(100,999),
                'order_date'=>today(),'total_amount'=>round($total,2),'status'=>'draft',
                'created_at'=>now(),'updated_at'=>now()
            ]);
            foreach($items as $line){
                DB::table('tagore_inventory_purchase_order_items')->insert([
                    'purchase_order_id'=>$poId,'inventory_item_id'=>(int)$line['item_id'],
                    'ordered_quantity'=>(float)$line['quantity'],'received_quantity'=>0,
                    'unit_cost'=>(float)$line['unit_cost'],'line_total'=>round((float)$line['quantity']*(float)$line['unit_cost'],2),
                    'created_at'=>now(),'updated_at'=>now()
                ]);
            }
            return $poId;
        });
        return back()->with('success','Purchase order created (#'.$poId.').');
    }

    public function approvePurchaseOrder(Request $request,int $id)
    {
        [$roles,$ids]=$this->context($request); abort_unless($roles->contains('OWNER') || $roles->intersect(['PRINCIPAL','ACCOUNTS'])->isNotEmpty(),403);
        $po=DB::table('tagore_inventory_purchase_orders')->where('id',$id)->whereIn('institution_id',$ids)->first(); abort_unless($po,404);
        abort_unless($po->status==='draft',422,'Only draft purchase orders can be approved.');
        DB::table('tagore_inventory_purchase_orders')->where('id',$id)->update(['status'=>'approved','approved_by'=>$request->user()->id,'approved_at'=>now(),'updated_at'=>now()]);
        return back()->with('success','Purchase order approved.');
    }

    public function receivePurchaseOrder(Request $request,int $id,InventoryService $service)
    {
        [$roles,$ids]=$this->context($request); abort_unless($roles->contains('OWNER') || $roles->intersect(['PRINCIPAL','ACCOUNTS'])->isNotEmpty(),403);
        $po=DB::table('tagore_inventory_purchase_orders')->where('id',$id)->whereIn('institution_id',$ids)->first(); abort_unless($po,404);
        $service->receivePurchaseOrder($id,(int)$po->institution_id,(int)$request->user()->id);
        return back()->with('success','Purchase order received and stock updated.');
    }

    public function expense(Request $request)
    {
        [$roles,$ids]=$this->context($request); $this->authorize($roles);
        $d=$request->validate(['institution_id'=>'required|integer','expense_date'=>'required|date','department'=>'nullable|string|max:100','category'=>'required|string|max:100','description'=>'required|string|max:2000','amount'=>'required|numeric|min:0.01','vendor'=>'nullable|string|max:190']);
        abort_unless(in_array((int)$d['institution_id'],$ids,true),403);
        DB::table('tagore_expense_claims')->insert($d+['submitted_by'=>$request->user()->id,'status'=>'pending','created_at'=>now(),'updated_at'=>now()]);
        return back()->with('success','Expense submitted for approval.');
    }

    public function decideExpense(Request $request,int $id,InventoryService $service)
    {
        [$roles,$ids]=$this->context($request); abort_unless($roles->contains('OWNER') || $roles->intersect(['PRINCIPAL','ACCOUNTS'])->isNotEmpty(),403);
        $expense=DB::table('tagore_expense_claims')->where('id',$id)->whereIn('institution_id',$ids)->first(); abort_unless($expense,404);
        $d=$request->validate(['status'=>'required|in:approved,rejected','notes'=>'nullable|string|max:2000']);
        $service->approveExpense($id,(int)$expense->institution_id,(int)$request->user()->id,$d['status'],$d['notes']??null);
        return back()->with('success','Expense decision saved.');
    }

    private function authorize($roles): void { abort_unless($roles->contains('OWNER') || $roles->intersect(['PRINCIPAL','ACCOUNTS'])->isNotEmpty(),403); }

    private function context(Request $request): array
    {
        $userId=(int)$request->user()->id;
        $roles=DB::table('tagore_user_roles as ur')->join('tagore_roles as r','r.id','=','ur.role_id')->where('ur.user_id',$userId)->where('ur.status','active')->pluck('r.code')->unique()->values();
        $ids=$roles->contains('OWNER') ? DB::table('tagore_institutions')->where('status','active')->pluck('id')->map(fn($id)=>(int)$id)->all() : DB::table('tagore_user_roles')->where('user_id',$userId)->where('status','active')->whereNotNull('institution_id')->pluck('institution_id')->map(fn($id)=>(int)$id)->unique()->values()->all();
        return [$roles,$ids];
    }
}
