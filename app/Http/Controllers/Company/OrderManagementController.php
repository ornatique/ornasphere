<?php
namespace App\Http\Controllers\Company;

use App\Http\Controllers\Controller;
use App\Models\{CategoryPerson, Company, Customer, Item, ItemSet, ManagedOrder, User};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use App\Services\OrderManagementPricing as Pricing;

class OrderManagementController extends Controller
{
    private function company(Request $request, string $slug): Company
    {
        $company = Company::whereSlug($slug)->firstOrFail();
        abort_unless((int) $request->user()?->company_id === (int) $company->id, 403);
        return $company;
    }

    private function customers(int $companyId)
    {
        return Customer::where('company_id', $companyId)->where('is_active', true)->saleParties();
    }

    public function index(Request $request, string $slug)
    {
        $data = $this->listData($request, $slug);
        if ($request->expectsJson()) return response()->json(['html' => view('company.order_management.list', $data)->render()]);
        return view('company.order_management.index', $data);
    }

    public function listData(Request $request, string $slug): array
    {
        $company = $this->company($request, $slug);
        $filters = $request->validate(['search' => 'nullable|string|max:100',
            'per_page' => 'nullable|integer|min:1|max:100',
            'customer_id' => ['nullable', 'integer', Rule::exists('customers', 'id')->where('company_id', $company->id)],
            'from_date' => 'nullable|date_format:Y-m-d',
            'to_date' => ['nullable', 'date_format:Y-m-d', Rule::when($request->filled('from_date'), 'after_or_equal:from_date')],
        ]);
        $search = trim($filters['search'] ?? '');
        $customerId = $filters['customer_id'] ?? null;
        $fromDate = $filters['from_date'] ?? null;
        $toDate = $filters['to_date'] ?? null;
        $customers = Customer::where('company_id', $company->id)->saleParties()->orderBy('name')->get(['id', 'name']);
        $orders = ManagedOrder::where('company_id', $company->id)->with('customer')->withCount('items')
            ->when($customerId, fn ($q) => $q->where('customer_id', $customerId))
            ->when($fromDate, fn ($q) => $q->whereDate('order_date', '>=', $fromDate))
            ->when($toDate, fn ($q) => $q->whereDate('order_date', '<=', $toDate))
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($q) use ($search) {
                    $q->whereHas('customer', fn ($c) => $c->where('name', 'like', '%'.$search.'%'));
                    // Match the displayed number, including its prefix and zero padding, as the user types.
                    $numberExpression = $q->getModel()->getConnection()->getDriverName() === 'sqlite'
                        ? "('OM-' || printf('%06d', managed_orders.id))"
                        : "CONCAT('OM-', LPAD(CAST(managed_orders.id AS CHAR), GREATEST(6, CHAR_LENGTH(CAST(managed_orders.id AS CHAR))), '0'))";
                    $q->orWhereRaw('LOWER('.$numberExpression.') LIKE ?', ['%'.mb_strtolower($search).'%']);
                    $number = preg_replace('/^(?:OM[\s-]*)?0*/i', '', $search);
                    if (ctype_digit($number)) $q->orWhere('id', $number);
                });
            })->latest('id')->paginate($request->integer('per_page', 20))->withPath(route('company.order-management.index', $slug))->appends($request->only(['search', 'customer_id', 'from_date', 'to_date', 'per_page']));
        return compact('company', 'orders', 'search', 'customers', 'customerId', 'fromDate', 'toDate');
    }

    public function create(Request $request, string $slug)
    {
        return $this->form($request, $slug);
    }

    public function pdf(Request $request, string $slug, int $id)
    {
        $company = $this->company($request, $slug);
        $order = ManagedOrder::where('company_id', $company->id)->with([
            'customer' => fn ($q) => $q->where('company_id', $company->id),
            'items' => fn ($q) => $q->where('company_id', $company->id)->orderBy('id'),
        ])->findOrFail($id);
        $sets = ItemSet::where('company_id', $company->id)->whereIn('id', $order->items->pluck('item_set_id'))->get()->keyBy('id');
        $wholesaleEnabled = \App\Services\WholesalePrice::enabled($company);
        foreach ($order->items as $line) {
            $set = $sets->get($line->item_set_id);
            $line->setAttribute('display_wholesale_price', $wholesaleEnabled ? $set?->wholesale_price : null);
            $line->setAttribute('display_sale_price', $set ? (float) ($set->sale_price ?? 0) : $line->sale_price);
            $line->setAttribute('pdf_image', $set ? app(\App\Services\StockGalleryPdfImage::class)->dataUri($set) : null);
        }
        return \Barryvdh\DomPDF\Facade\Pdf::loadView('company.order_management.pdf', compact('company', 'order'))
            ->setPaper('a4', 'landscape')->setOption('isRemoteEnabled', false)
            ->stream('Order-'.$order->number.'.pdf', ['Attachment' => false]);
    }

    public function edit(Request $request, string $slug, int $id)
    {
        return $this->form($request, $slug, $id);
    }

    private function form(Request $request, string $slug, ?int $id = null)
    {
        $company = $this->company($request, $slug);
        $order = $id ? ManagedOrder::where('company_id', $company->id)->with('items')->findOrFail($id) : null;
        $wholesaleEnabled = \App\Services\WholesalePrice::enabled($company);
        $storedMode = $order?->price_mode ?? ($order && $wholesaleEnabled ? 'wholesale' : 'sale');
        $priceMode = old('price_mode', $storedMode);
        if (!$wholesaleEnabled && $priceMode === 'wholesale') $priceMode = 'sale';
        $customers = $this->customers($company->id)->orderBy('name')->get(['id', 'name']);
        // Keep an existing inactive customer's selection visible when editing.
        if ($order && !$customers->contains('id', $order->customer_id)) {
            $existing = Customer::where('company_id', $company->id)->find($order->customer_id);
            if ($existing) $customers->push($existing);
        }
        $items = Item::where('company_id', $company->id)->orderBy('item_name')->get(['id', 'item_name']);
        $selected = collect(old('items', $order?->items->toArray() ?? []));
        $sets = ItemSet::with('item')->where('company_id', $company->id)->whereIn('id', $selected->pluck('item_set_id'))->get()->keyBy('id');
        $rows = $selected->map(function ($row) use ($sets, $order, $priceMode, $storedMode) {
            $set = $sets->get($row['item_set_id'] ?? null);
            if (!$set) return null;
            $defaults = $this->payload($set);
            $saved = $order?->items->firstWhere('item_set_id', $set->id);
            return array_replace($defaults, array_filter(\Illuminate\Support\Arr::only($row, Pricing::SNAPSHOT), fn ($value) => $value !== null), [
                'quantity' => (int) ($row['quantity'] ?? 1), 'sale_price' => $defaults['sale_price'],
                'billing_sale_price' => $saved && $priceMode === $storedMode ? $saved->sale_price : Pricing::selectedPrice($defaults, $priceMode),
            ]);
        })->filter()->values();
        return view('company.order_management.form', compact('company', 'order', 'customers', 'items', 'rows', 'wholesaleEnabled', 'priceMode'));
    }

    private function payload(ItemSet $set): array
    {
        return ['item_set_id' => $set->id, 'item_name' => $set->item?->item_name ?? '',
            'huid' => $set->HUID, 'qr_code' => $set->qr_code, 'barcode' => $set->barcode,
            'gross_weight' => $set->gross_weight, 'net_weight' => $set->net_weight,
            'available_quantity' => (int) $set->available_quantity,
            'other_weight' => max(0, (float) $set->gross_weight - (float) $set->net_weight),
            'purity' => (float) ($set->item?->outward_purity ?? 0), 'waste_percent' => 0,
            'metal_rate' => (float) ($set->metal_rate ?? 0), 'labour_rate' => (float) ($set->sale_labour_rate ?? 0),
            'other_amount' => is_numeric($set->sale_other) ? (float) $set->sale_other : 0,
            'sale_price' => (float) ($set->sale_price ?? 0),
            'billing_sale_price' => (float) ($set->billing_sale_price ?? 0),
            'sale_unit_price' => round((float) ($set->sale_price ?? 0) / max(1, (int) $set->quantity), 2),
            'wholesale_unit_price' => \App\Services\WholesalePrice::enabled($set->pricingCompany) ? round((float) ($set->wholesale_price ?? 0) / max(1, (int) $set->quantity), 2) : 0,
            'wholesale_price' => \App\Services\WholesalePrice::enabled($set->pricingCompany) ? $set->wholesale_price : null,
            'image_url' => $this->imageUrl($set)];
    }

    private function imageUrl(ItemSet $set): ?string
    {
        try { return app(\App\Services\ItemSetImageService::class)->imageUrl($set); }
        catch (\Throwable) { return null; }
    }

    public function search(Request $request, string $slug)
    {
        $company = $this->company($request, $slug);
        $data = $request->validate(['keyword' => 'nullable|string|max:100', 'item_id' => 'nullable|integer|min:1',
            'exclude' => 'nullable|array|max:200', 'exclude.*' => 'required|integer|min:1']);
        $keyword = trim($data['keyword'] ?? '');
        $sets = ItemSet::with('item')->where('company_id', $company->id)->where('is_final', true)
            ->where('available_quantity', '>', 0)->whereHas('item', fn ($q) => $q->where('company_id', $company->id))
            ->whereNotIn('id', $data['exclude'] ?? [])
            ->when($data['item_id'] ?? null, fn ($q, $id) => $q->where('item_id', $id))
            ->when($keyword !== '', fn ($q) => $q->where(function ($q) use ($keyword) {
                $q->where('HUID', 'like', '%'.$keyword.'%')->orWhere('qr_code', 'like', '%'.$keyword.'%')
                    ->orWhere('barcode', 'like', '%'.$keyword.'%')
                    ->orWhereHas('item', fn ($i) => $i->where('item_name', 'like', '%'.$keyword.'%'));
            }))->when($keyword !== '', fn ($q) => $q->orderByRaw('CASE WHEN LOWER(HUID) = ? OR LOWER(qr_code) = ? OR LOWER(barcode) = ? THEN 0 ELSE 1 END', array_fill(0, 3, strtolower($keyword))))
            ->orderByDesc('id')->limit(50)->get();
        return response()->json($sets->map(fn ($set) => $this->payload($set)));
    }

    public function store(Request $request, string $slug, ?int $id = null)
    {
        $this->persist($request, $slug, $id);
        return redirect()->route('company.order-management.index', $slug)->with('success', $id ? 'Order updated successfully.' : 'Order saved successfully.');
    }

    public function persist(Request $request, string $slug, ?int $id = null): ManagedOrder
    {
        $company = $this->company($request, $slug);
        if ($id) ManagedOrder::where('company_id', $company->id)->findOrFail($id);
        $other = $request->input('customer_id') === 'other';
        if ($other) {
            $newCustomer = array_map(fn ($v) => is_string($v) ? trim($v) : $v, (array) $request->input('new_customer', []));
            if (is_string($newCustomer['email'] ?? null)) $newCustomer['email'] = strtolower($newCustomer['email']) ?: null;
            $request->merge(['new_customer' => $newCustomer]);
        }
        $data = $request->validate([
            'price_mode' => 'sometimes|required|in:sale,wholesale',
            'customer_id' => $other ? 'required|in:other' : ['required', 'integer', Rule::exists('customers', 'id')->where('company_id', $company->id)],
            'new_customer.name' => $other ? 'required|string|max:255' : 'exclude',
            'new_customer.email' => $other ? ['nullable', 'email', 'max:255', Rule::unique('customers', 'email')->where('company_id', $company->id)] : 'exclude',
            'new_customer.mobile_no' => $other ? 'nullable|digits:10' : 'exclude',
            'new_customer.address' => $other ? 'nullable|string|max:2000' : 'exclude',
            'new_customer.city' => $other ? 'nullable|string|max:191' : 'exclude',
            'new_customer.pincode' => $other ? 'nullable|digits:6' : 'exclude',
            'order_date' => 'required|date_format:Y-m-d', 'remarks' => 'nullable|string|max:5000',
            'items' => 'required|array|min:1|max:200', 'items.*.item_set_id' => 'required|integer|distinct',
            'items.*.quantity' => 'required|integer|min:1|max:1000000',
            'items.*.gross_weight' => 'sometimes|numeric|min:0|max:999999',
            'items.*.other_weight' => 'sometimes|numeric|min:0|lte:items.*.gross_weight',
            'items.*.purity' => 'sometimes|numeric|min:0|max:100',
            'items.*.waste_percent' => 'sometimes|numeric|min:0|max:100',
            'items.*.metal_rate' => 'sometimes|numeric|min:0|max:999999999',
            'items.*.labour_rate' => 'sometimes|numeric|min:0|max:999999999',
            'items.*.other_amount' => 'sometimes|numeric|min:0|max:999999999',
            'items.*.remarks' => 'nullable|string|max:1000',
        ]);
        return DB::transaction(function () use ($data, $company, $id, $other) {
            if ($other && !empty($data['new_customer']['email'])) {
                $email = $data['new_customer']['email'];
                if (Customer::where('company_id', $company->id)->whereRaw('TRIM(LOWER(email)) = ?', [$email])->exists()
                    || User::where('company_id', $company->id)->whereRaw('TRIM(LOWER(email)) = ?', [$email])->exists()) {
                    throw ValidationException::withMessages(['new_customer.email' => 'This email is already used for another person.']);
                }
            }
            // Lock stock only to validate a consistent snapshot; orders do not reserve or sell stock.
            $sets = ItemSet::with('item')->where('company_id', $company->id)
                ->whereIn('id', array_column($data['items'], 'item_set_id'))->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            foreach ($data['items'] as $i => $line) {
                $set = $sets->get($line['item_set_id']);
                if (!$set || !$set->is_final || (int) $set->item?->company_id !== (int) $company->id) {
                    throw ValidationException::withMessages(["items.$i.quantity" => 'Select a valid final item from this company.']);
                }
            }
            $order = $id ? ManagedOrder::where('company_id', $company->id)->lockForUpdate()->findOrFail($id) : new ManagedOrder(['company_id' => $company->id]);
            $priceMode = $data['price_mode'] ?? $order->price_mode ?? ($id && \App\Services\WholesalePrice::enabled($company) ? 'wholesale' : 'sale');
            if ($priceMode === 'wholesale' && !\App\Services\WholesalePrice::enabled($company)) {
                throw ValidationException::withMessages(['price_mode'=>'Wholesale Price is not enabled for this company.']);
            }
            if ($other) {
                $category = CategoryPerson::where('company_id', $company->id)->whereRaw('LOWER(TRIM(category_name)) = ?', ['customer'])->first();
                $category ??= CategoryPerson::create(['company_id' => $company->id, 'category_name' => 'Customer', 'is_system_default' => true]);
                $customer = Customer::create($data['new_customer'] + ['company_id' => $company->id, 'category_person_id' => $category->id, 'is_active' => true]);
            } else {
                $customer = $this->customers($company->id)->whereKey($data['customer_id'])->first();
                if (!$customer && $id && (int) $order->customer_id === (int) $data['customer_id']) $customer = Customer::where('company_id', $company->id)->find($data['customer_id']);
                if (!$customer) throw ValidationException::withMessages(['customer_id' => 'Select an active customer.']);
            }
            $order->fill(['customer_id' => $customer->id, 'order_date' => $data['order_date'], 'remarks' => $data['remarks'] ?? null, 'price_mode' => $priceMode])->save();
            $order->items()->delete();
            foreach ($data['items'] as $line) {
                $set = $sets[$line['item_set_id']];
                $order->items()->create(['company_id' => $company->id, 'item_set_id' => $set->id,
                    'item_name' => $set->item->item_name, 'huid' => $set->HUID, 'qr_code' => $set->qr_code,
                    'quantity' => $line['quantity']] + Pricing::calculate($line, $this->payload($set), $priceMode));
            }
            return $order;
        });
    }
}
