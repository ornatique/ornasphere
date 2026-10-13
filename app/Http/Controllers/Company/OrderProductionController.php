<?php
namespace App\Http\Controllers\Company;

use App\Http\Controllers\Controller;
use App\Models\{Company, Customer, Item, User, CustomerOrder, CustomerOrderLine, CadDesign, RptBatch, OrderProductionFile, CustomerAdvanceVoucher};
use App\Support\OrderProduction as Flow;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB, Storage};
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class OrderProductionController extends Controller
{
    private function company(Request $request, string $slug): Company
    {
        $company = Company::whereSlug($slug)->firstOrFail();
        abort_unless((int) $request->user()->company_id === (int) $company->id, 403);
        return $company;
    }
    private function order(Company $company, int $id): CustomerOrder
    {
        return CustomerOrder::where('company_id', $company->id)->findOrFail($id);
    }
    private function scoped(string $table, int $companyId)
    {
        return Rule::exists($table, 'id')->where('company_id', $companyId);
    }
    private function customers(int $companyId)
    {
        return Customer::where('company_id', $companyId)->where('is_active', 1)->whereHas('categoryPerson',
            fn ($q) => $q->where('company_id', $companyId)->whereIn(DB::raw('LOWER(TRIM(category_name))'), ['customer', 'party']));
    }
    private function done(Company $company, CustomerOrder $order)
    {
        return redirect()->route('company.orders.show', [$company->slug, $order->id])->with('success', 'Saved successfully.');
    }
    private function locked(CustomerOrder $order, callable $operation)
    {
        return DB::transaction(function () use ($order, $operation) {
            $fresh = CustomerOrder::lockForUpdate()->findOrFail($order->id);
            return $operation($fresh);
        });
    }

    public function index(Request $request, string $slug)
    {
        $company = $this->company($request, $slug);
        $query = CustomerOrder::with(['customer', 'lines.designs.batches'])->where('company_id', $company->id);
        if ($request->filled('search')) $query->where(function ($q) use ($request) {
            $search = '%'.mb_substr($request->string('search'), 0, 100).'%';
            $q->where('number', 'like', $search)->orWhereHas('customer', fn ($c) => $c->where('name', 'like', $search));
        });
        $orders = $query->latest()->paginate(20)->withQueryString();
        return view('company.orders.index', compact('company', 'orders'));
    }
    public function create(Request $request, string $slug)
    {
        $company = $this->company($request, $slug);
        $customers = $this->customers($company->id)->orderBy('name')->get();
        $items = Item::where('company_id', $company->id)->orderBy('item_name')->get();
        return view('company.orders.create', compact('company', 'customers', 'items'));
    }
    public function edit(Request $request, string $slug, int $id)
    {
        $company = $this->company($request, $slug);
        $order = $this->order($company, $id)->load('lines');
        Flow::check($order->status === 'draft', 'Only draft orders can be edited.');
        $customers = $this->customers($company->id)->orderBy('name')->get();
        $items = Item::where('company_id', $company->id)->orderBy('item_name')->get();
        return view('company.orders.create', compact('company', 'order', 'customers', 'items'));
    }
    public function store(Request $request, string $slug, ?int $id = null)
    {
        $company = $this->company($request, $slug);
        $data = $request->validate([
            'customer_id' => ['required', 'integer', $this->scoped('customers', $company->id)],
            'order_date' => 'required|date', 'delivery_date' => 'nullable|date|after_or_equal:order_date',
            'customer_reference' => 'nullable|string|max:255', 'remarks' => 'nullable|string|max:5000',
            'lines' => 'required|array|min:1|max:100',
            'lines.*.id' => 'nullable|integer|distinct',
            'lines.*.item_id' => ['required', 'integer', $this->scoped('items', $company->id)],
            'lines.*.metal' => 'required|in:gold,silver,other', 'lines.*.purity' => 'required|numeric|gt:0|max:100',
            'lines.*.pieces' => 'required|integer|min:1|max:1000000',
            'lines.*.expected_weight' => 'required|numeric|gt:0|max:999999999',
            'lines.*.size' => 'nullable|string|max:255', 'lines.*.instructions' => 'nullable|string|max:5000',
        ]);
        Flow::check($this->customers($company->id)->whereKey($data['customer_id'])->exists(), 'Select an active customer or party.');
        $order = DB::transaction(function () use ($data, $company, $request, $id) {
            $lines = $data['lines']; unset($data['lines']);
            if ($id) {
                $order = CustomerOrder::where('company_id', $company->id)->lockForUpdate()->findOrFail($id);
                Flow::check($order->status === 'draft', 'Only draft orders can be edited.');
                Flow::check((int) $order->customer_id === (int) $data['customer_id'] || !$order->vouchers()->exists(), 'Customer cannot change after a voucher has been linked.');
                $order->update($data);
            } else {
                $order = CustomerOrder::create($data + ['company_id' => $company->id, 'created_by' => $request->user()->id,
                    'number' => 'ORD-'.Str::uuid(), 'status' => 'draft']);
                $order->update(['number' => 'ORD-'.str_pad((string) $order->id, 6, '0', STR_PAD_LEFT)]);
            }
            $keep = [];
            foreach ($lines as $line) {
                $lineId = $line['id'] ?? null;
                $line = \Illuminate\Support\Arr::only($line, ['item_id', 'metal', 'purity', 'pieces', 'expected_weight', 'size', 'instructions']);
                $line['item_name'] = Item::findOrFail($line['item_id'])->item_name;
                if ($lineId) {
                    $record = $order->lines()->findOrFail($lineId); $record->update($line);
                } else $record = $order->lines()->create($line);
                $keep[] = $record->id;
            }
            $removed = $order->lines()->whereNotIn('id', $keep)->pluck('id');
            Flow::check(!OrderProductionFile::whereIn('customer_order_line_id', $removed)->exists(), 'An item with reference attachments cannot be removed.');
            $order->lines()->whereIn('id', $removed)->delete();
            Flow::event($order, $request->user()->id, $id ? 'Draft order updated' : 'Order created');
            return $order;
        });
        return $this->done($company, $order);
    }
    public function show(Request $request, string $slug, int $id)
    {
        $company = $this->company($request, $slug);
        $order = $this->order($company, $id)->load(['customer', 'lines.designs.batches', 'lines.designs.designer', 'vouchers']);
        $users = User::where('company_id', $company->id)->orderBy('name')->get(['id', 'name']);
        $files = OrderProductionFile::where('customer_order_id', $order->id)->get();
        $events = DB::table('order_production_events')->where('customer_order_id', $id)->latest('id')->get();
        $unlinkedVouchers = CustomerAdvanceVoucher::where('company_id', $company->id)
            ->where('customer_id', $order->customer_id)
            ->whereNotIn('id', DB::table('customer_order_vouchers')->select('voucher_id'))
            ->latest('id')->get();
        return view('company.orders.show', compact('company', 'order', 'users', 'files', 'events', 'unlinkedVouchers'));
    }
    public function linkVoucher(Request $request, string $slug, int $id)
    {
        $company = $this->company($request, $slug);
        $order = $this->order($company, $id);
        $request->validate(['voucher_id' => 'required|integer|min:1']);
        $voucher = CustomerAdvanceVoucher::where('company_id', $company->id)
            ->where('customer_id', $order->customer_id)->findOrFail($request->integer('voucher_id'));
        Flow::linkVoucher($order->id, $voucher, $request->user()->id);
        return redirect()->route('company.orders.show', [$slug, $id])
            ->with('success', 'Existing voucher linked. No additional payment or ledger entry was created.');
    }
    public function status(Request $request, string $slug, int $id)
    {
        $company = $this->company($request, $slug); $order = $this->order($company, $id);
        $data = $request->validate(['status' => 'required|in:confirmed,on_hold,cancelled']);
        $this->locked($order, function ($order) use ($data, $request) {
            Flow::check($order->status !== 'cancelled', 'A cancelled order cannot be reopened.');
            Flow::check(!($order->status === 'draft' && $data['status'] === 'on_hold'), 'Confirm the draft before placing it on hold.');
            if ($data['status'] === 'cancelled') {
                Flow::check(!RptBatch::whereHas('design.line', fn ($q) => $q->where('customer_order_id', $order->id))->exists(),
                    'Production has started. Put this order on hold for review instead of cancelling it.');
            }
            $order->update($data); Flow::event($order, $request->user()->id, 'Order status: '.$data['status']);
        });
        return $this->done($company, $order);
    }
    public function cadIndex(Request $request, string $slug)
    {
        $company = $this->company($request, $slug);
        $records = CadDesign::with('line.order.customer', 'designer')->whereHas('line.order', fn ($q) => $q->where('company_id', $company->id))->latest()->paginate(30);
        $kind = 'cad'; return view('company.orders.production_index', compact('company', 'records', 'kind'));
    }
    public function cadStore(Request $request, string $slug, int $lineId)
    {
        $company = $this->company($request, $slug);
        $line = CustomerOrderLine::whereHas('order', fn ($q) => $q->where('company_id', $company->id))->findOrFail($lineId);
        $data = $request->validate(['designer_id' => ['required', 'integer', $this->scoped('users', $company->id)], 'due_date' => 'nullable|date']);
        $design = $this->locked($line->order, function ($order) use ($line, $data, $request) {
            Flow::active($order);
            Flow::check(!$line->designs()->whereIn('status', ['assigned', 'designing', 'submitted'])->exists(), 'Complete or request revision on the current CAD before starting another revision.');
            $revision = (int) $line->designs()->max('revision') + 1;
            $design = $line->designs()->create($data + ['revision' => $revision, 'number' => 'CAD-'.$line->id.'-R'.$revision]);
            Flow::event($order, $request->user()->id, $design->number.' assigned'); return $design;
        });
        return redirect()->route('company.cad.show', [$slug, $design->id]);
    }
    public function cadShow(Request $request, string $slug, int $id)
    {
        $company = $this->company($request, $slug);
        $design = CadDesign::with('line.order.customer', 'batches')->whereHas('line.order', fn ($q) => $q->where('company_id', $company->id))->findOrFail($id);
        $order = $design->line->order;
        $users = User::where('company_id', $company->id)->orderBy('name')->get(['id', 'name']);
        $files = OrderProductionFile::where('cad_design_id', $id)->get();
        return view('company.orders.cad', compact('company', 'design', 'order', 'users', 'files'));
    }
    public function cadUpdate(Request $request, string $slug, int $id)
    {
        $company = $this->company($request, $slug);
        $design = CadDesign::whereHas('line.order', fn ($q) => $q->where('company_id', $company->id))->findOrFail($id);
        $data = $request->validate(['status' => 'required|in:designing,submitted,revision_requested,approved',
            'weight_per_piece' => 'nullable|numeric|gt:0|max:999999999', 'notes' => 'nullable|string|max:5000',
            'approved_by' => 'nullable|string|max:255', 'approval_remarks' => 'nullable|string|max:5000']);
        $this->locked($design->line->order, function ($order) use ($design, $data, $request) {
            Flow::active($order); $design->refresh();
            $allowed = ['assigned' => ['designing'], 'designing' => ['designing', 'submitted'], 'submitted' => ['approved', 'revision_requested']];
            Flow::check(in_array($data['status'], $allowed[$design->status] ?? []), 'Invalid CAD transition. Approved and revision-requested records are preserved; create a new revision.');
            if ($data['status'] === 'submitted') {
                Flow::check(!empty($data['weight_per_piece']), 'Enter estimated finished weight per piece before submitting.');
                Flow::check(OrderProductionFile::where('cad_design_id', $design->id)->where('kind', 'print')->exists(), 'Upload a print file before submitting.');
            }
            if ($design->status === 'submitted') {
                // Review cannot silently modify the design that was submitted.
                unset($data['weight_per_piece'], $data['notes']);
                Flow::check(!empty($data['approval_remarks']), 'Enter approval or revision remarks.');
            }
            if ($data['status'] === 'approved') {
                Flow::check(!empty($data['approved_by']), 'Record the customer or person who approved this design.');
                $data['approved_at'] = now(); $data['approval_recorded_by'] = $request->user()->id;
            } else unset($data['approved_by']);
            $design->update($data);
            Flow::event($order, $request->user()->id, $design->number.': '.$design->status);
        });
        return redirect()->route('company.cad.show', [$slug, $id])->with('success', 'CAD updated.');
    }
    public function rptIndex(Request $request, string $slug)
    {
        $company = $this->company($request, $slug);
        $records = RptBatch::with('design.line.order.customer', 'operator')->whereHas('design.line.order', fn ($q) => $q->where('company_id', $company->id))->latest()->paginate(30);
        $kind = 'rpt'; return view('company.orders.production_index', compact('company', 'records', 'kind'));
    }
    public function rptStore(Request $request, string $slug, int $designId)
    {
        $company = $this->company($request, $slug);
        $design = CadDesign::whereHas('line.order', fn ($q) => $q->where('company_id', $company->id))->findOrFail($designId);
        $data = $request->validate(['operator_id' => ['required', 'integer', $this->scoped('users', $company->id)],
            'printer' => 'required|string|max:255', 'material' => 'required|string|max:255', 'planned_pieces' => 'required|integer|min:1|max:1000000']);
        $batch = $this->locked($design->line->order, function ($order) use ($design, $data, $request) {
            Flow::active($order); $design->refresh();
            Flow::check($design->status === 'approved', 'Only approved CAD revisions can be printed.');
            Flow::check((int) $design->revision === (int) $design->line->designs()->max('revision'), 'Use the latest approved CAD revision; an updated design is underway.');
            $batches = RptBatch::whereHas('design', fn ($q) => $q->where('customer_order_line_id', $design->customer_order_line_id))->get();
            $reserved = $batches->sum(fn ($b) => $b->status === 'ready' ? $b->accepted_pieces : $b->planned_pieces);
            Flow::check($data['planned_pieces'] <= $design->line->pieces - $reserved, 'Planned pieces exceed the unreserved order quantity. Complete quality checks on existing batches before planning reprints.');
            $batch = $design->batches()->create($data + ['number' => 'RPT-'.Str::uuid()]);
            $batch->update(['number' => 'RPT-'.str_pad((string) $batch->id, 6, '0', STR_PAD_LEFT)]);
            Flow::event($order, $request->user()->id, $batch->number.' planned from '.$design->number); return $batch;
        });
        return redirect()->route('company.rpt.show', [$slug, $batch->id]);
    }
    public function rptShow(Request $request, string $slug, int $id)
    {
        $company = $this->company($request, $slug);
        $batch = RptBatch::with('design.line.order.customer')->whereHas('design.line.order', fn ($q) => $q->where('company_id', $company->id))->findOrFail($id);
        $order = $batch->design->line->order;
        $files = OrderProductionFile::where('rpt_batch_id', $id)->get();
        return view('company.orders.rpt', compact('company', 'batch', 'order', 'files'));
    }
    public function rptUpdate(Request $request, string $slug, int $id)
    {
        $company = $this->company($request, $slug);
        $batch = RptBatch::whereHas('design.line.order', fn ($q) => $q->where('company_id', $company->id))->findOrFail($id);
        $data = $request->validate(['status' => 'required|in:printing,printed,quality_check,ready',
            'printed_pieces' => 'required|integer|min:0|max:1000000', 'accepted_pieces' => 'required|integer|min:0|max:1000000',
            'rejected_pieces' => 'required|integer|min:0|max:1000000', 'pattern_weight' => 'required|numeric|min:0|max:999999999',
            'started_on' => 'nullable|date', 'completed_on' => 'nullable|date|after_or_equal:started_on',
            'rejection_reason' => 'nullable|string|max:5000', 'inspection_notes' => 'nullable|string|max:5000']);
        $this->locked($batch->design->line->order, function ($order) use ($batch, $data, $request) {
            Flow::active($order); $batch->refresh();
            $allowed = ['planned' => ['printing'], 'printing' => ['printing', 'printed'], 'printed' => ['quality_check'], 'quality_check' => ['quality_check', 'ready']];
            Flow::check(in_array($data['status'], $allowed[$batch->status] ?? []), 'Invalid RPT transition. Completed quality checks cannot be overwritten.');
            Flow::check(!empty($data['started_on']), 'Enter the printing start date.');
            Flow::check($data['printed_pieces'] <= $batch->planned_pieces, 'Printed quantity cannot exceed planned quantity.');
            Flow::check($data['accepted_pieces'] + $data['rejected_pieces'] <= $data['printed_pieces'], 'Accepted plus rejected cannot exceed printed quantity.');
            if ($data['status'] === 'ready') {
                Flow::check(!empty($data['completed_on']), 'Enter the completion date.');
                Flow::check($data['accepted_pieces'] + $data['rejected_pieces'] === (int) $data['printed_pieces'], 'Inspect all printed pieces before completing quality checks.');
                Flow::check(!$data['rejected_pieces'] || !empty($data['rejection_reason']), 'Enter the rejection reason.');
            }
            $batch->update($data); Flow::event($order, $request->user()->id, $batch->number.': '.$batch->status);
        });
        return redirect()->route('company.rpt.show', [$slug, $id])->with('success', 'RPT batch updated.');
    }
    public function upload(Request $request, string $slug, int $id)
    {
        $company = $this->company($request, $slug); $order = $this->order($company, $id);
        $data = $request->validate(['kind' => 'required|in:reference,source,print,preview,inspection',
            'line_id' => 'nullable|integer', 'cad_id' => 'nullable|integer', 'rpt_id' => 'nullable|integer',
            'file' => 'required|file|max:51200']);
        $file = $request->file('file'); $extension = strtolower($file->getClientOriginalExtension());
        $module = match ($data['kind']) { 'reference' => 'orders', 'inspection' => 'rpt', default => 'cad' };
        abort_unless($request->routeIs('company.'.$module.'.files.update'), 403);
        $extensions = match ($data['kind']) { 'source' => ['3dm','stl','obj','step','stp','iges','igs','zip'], 'print' => ['stl','obj','3mf'], default => ['jpg','jpeg','png','webp'] };
        Flow::check(in_array($extension, $extensions), 'Unsupported file type for this attachment.');
        if (in_array($data['kind'], ['reference', 'preview', 'inspection'])) $request->validate(['file' => 'image|mimes:jpg,jpeg,png,webp|max:12288']);
        $path = null;
        try {
            $this->locked($order, function ($order) use ($data, $file, $extension, &$path, $request) {
                Flow::check(!in_array($order->status, ['cancelled', 'on_hold']), 'Cannot upload while the order is cancelled or on hold.');
                $attributes = ['customer_order_id' => $order->id, 'kind' => $data['kind']];
                if ($data['kind'] === 'reference') {
                    $line = $order->lines()->findOrFail($data['line_id'] ?? 0);
                    $attributes['customer_order_line_id'] = $line->id;
                } elseif ($data['kind'] === 'inspection') {
                    $batch = RptBatch::whereHas('design.line', fn ($q) => $q->where('customer_order_id', $order->id))->findOrFail($data['rpt_id'] ?? 0);
                    Flow::check($batch->status !== 'ready', 'Completed inspections are preserved.');
                    $attributes['rpt_batch_id'] = $batch->id;
                } else {
                    $design = CadDesign::whereHas('line', fn ($q) => $q->where('customer_order_id', $order->id))->findOrFail($data['cad_id'] ?? 0);
                    Flow::check(in_array($design->status, ['assigned', 'designing']), 'Submitted CAD attachments are locked. Create a new revision for changes.');
                    $attributes['cad_design_id'] = $design->id;
                }
                $path = $file->storeAs('order-production/'.$order->company_id, Str::uuid().'.'.$extension, 'local');
                Flow::check((bool) $path, 'File could not be stored.');
                OrderProductionFile::create($attributes + ['path' => $path, 'name' => mb_substr(basename($file->getClientOriginalName()), 0, 240)]);
                Flow::event($order, $request->user()->id, 'Uploaded '.$data['kind'].' attachment');
            });
        } catch (\Throwable $e) { if ($path) Storage::disk('local')->delete($path); throw $e; }
        return back()->with('success', 'File uploaded.');
    }
    public function download(Request $request, string $slug, int $id)
    {
        $company = $this->company($request, $slug);
        $file = OrderProductionFile::whereHas('order', fn ($q) => $q->where('company_id', $company->id))->findOrFail($id);
        abort_unless(Storage::disk('local')->exists($file->path), 404);
        return Storage::disk('local')->download($file->path, $file->name, ['X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'private, no-store']);
    }
}
