<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Company\OrderManagementController as Orders;
use App\Models\{Company, Customer, Item, ItemSet, ManagedOrder};
use App\Services\CompanyModules;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;

class OrderManagementApiController extends Controller
{
    private function company(Request $request, array $actions): Company
    {
        $user = $request->user();
        abort_unless($user && $user->company_id, 403);
        // Match the web module: operate only on the company of the authenticated token.
        $request->validate(['company_id' => 'sometimes|required|integer', 'management_branches' => 'prohibited']);
        abort_if($request->has('company_id') && $request->integer('company_id') !== (int) $user->company_id, 403);
        $company = Company::findOrFail($user->company_id);
        abort_unless(CompanyModules::permissionAllowed($company, 'order-management'), 403, 'Order Management is not enabled for this company.');
        abort_unless($user->hasRole('company_admin') || $user->hasAnyPermission(array_map(fn ($action) => 'order-management-'.$action, $actions)), 403);
        return $company;
    }

    public function index(Request $request)
    {
        $company = $this->company($request, ['view']);
        $data = app(Orders::class)->listData($request, $company->slug);
        $orders = $data['orders'];
        $orders->withPath(url('/api/order-management'))->through(fn ($order) => $this->data($order));
        return response()->json(['success'=>true, 'data'=>$orders]);
    }

    public function options(Request $request)
    {
        $company = $this->company($request, ['view','create','edit']);
        return response()->json(['success'=>true,'data'=>[
            'customers'=>Customer::where('company_id',$company->id)->saleParties()->orderBy('name')->get(['id','name','email','mobile_no','is_active']),
            'items'=>Item::where('company_id',$company->id)->orderBy('item_name')->get(['id','item_name']),
            'new_customer_value'=>'other',
            'price_modes'=>\App\Services\WholesalePrice::enabled($company) ? ['sale','wholesale'] : ['sale'],
            'default_price_mode'=>'sale',
        ]]);
    }

    public function search(Request $request)
    {
        $company = $this->company($request, ['view','create','edit']);
        $response = app(Orders::class)->search($request, $company->slug);
        return response()->json(['success'=>true,'data'=>$response->getData(true)]);
    }

    public function show(Request $request, int $id)
    {
        $company = $this->company($request, ['view','edit']);
        $order = $this->order($company, $id);
        return response()->json(['success'=>true,'data'=>$this->data($order, true)]);
    }

    public function store(Request $request)
    {
        $company = $this->company($request, ['create']);
        $order = app(Orders::class)->persist($request, $company->slug);
        return response()->json(['success'=>true,'message'=>'Order saved successfully.','data'=>$this->data($this->order($company,$order->id),true)],201);
    }

    public function update(Request $request, int $id)
    {
        $company = $this->company($request, ['edit']);
        app(Orders::class)->persist($request, $company->slug, $id);
        return response()->json(['success'=>true,'message'=>'Order updated successfully.','data'=>$this->data($this->order($company,$id),true)]);
    }

    public function pdf(Request $request, int $id)
    {
        $company = $this->company($request, ['view']);
        return app(Orders::class)->pdf($request, $company->slug, $id);
    }

    private function order(Company $company, int $id): ManagedOrder
    {
        return ManagedOrder::where('company_id',$company->id)->with([
            'customer'=>fn ($q) => $q->where('company_id',$company->id),
            'items'=>fn ($q) => $q->where('company_id',$company->id)->orderBy('id'),
        ])->findOrFail($id);
    }

    private function data(ManagedOrder $order, bool $details = false): array
    {
        $data = ['id'=>$order->id,'number'=>$order->number,'company_id'=>$order->company_id,
            'price_mode'=>$order->price_mode ?? (\App\Services\WholesalePrice::enabled(Company::find($order->company_id)) ? 'wholesale' : 'sale'),
            'customer_id'=>$order->customer_id,'customer'=>$order->customer ? Arr::only($order->customer->toArray(),['id','name','email','mobile_no','city','address','pincode','is_active']) : null,
            'order_date'=>$order->order_date->format('Y-m-d'),'remarks'=>$order->remarks,
            'items_count'=>$order->items_count ?? $order->items->count(),
            'created_at'=>$order->created_at?->toISOString(),'updated_at'=>$order->updated_at?->toISOString(),
            'pdf_url'=>url('/api/order-management/'.$order->id.'/pdf')];
        if ($details) {
            $sets = ItemSet::where('company_id',$order->company_id)->whereIn('id',$order->items->pluck('item_set_id'))->get()->keyBy('id');
            $data['items'] = $order->items->map(function ($line) use ($sets) {
                $set = $sets->get($line->item_set_id);
                try { $imageUrl = app(\App\Services\ItemSetImageService::class)->imageUrl($set); }
                catch (\Throwable) { $imageUrl = null; }
                return array_replace(Arr::except($line->toArray(),['company_id','managed_order_id','created_at','updated_at']), [
                    'sale_price'=>$set ? (float) ($set->sale_price ?? 0) : $line->sale_price,
                    'billing_sale_price'=>$line->sale_price,
                    'available_quantity'=>(int) ($set?->available_quantity ?? 0), 'barcode'=>$set?->barcode,
                    'image_url'=>$imageUrl,
                    'wholesale_price'=>$set && \App\Services\WholesalePrice::enabled($set->pricingCompany) ? $set->wholesale_price : null,
                ]);
            });
            $data['totals'] = ['quantity'=>$order->items->sum('quantity'),
                'gross_weight'=>round($order->items->sum(fn ($line) => $line->gross_weight * $line->quantity),3),
                'other_weight'=>round($order->items->sum(fn ($line) => ($line->other_weight ?? max(0,$line->gross_weight - $line->net_weight)) * $line->quantity),3),
                'net_weight'=>round($order->items->sum(fn ($line) => $line->net_weight * $line->quantity),3),
                'amount'=>round($order->items->sum('total_amount'),2)];
        }
        return $data;
    }
}
