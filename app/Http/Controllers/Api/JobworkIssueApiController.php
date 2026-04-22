<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\JobworkIssue;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class JobworkIssueApiController extends Controller
{
    public function index(Request $request)
    {
        $companyId = (int) $request->user()->company_id;

        $rows = JobworkIssue::query()
            ->where('company_id', $companyId)
            ->with(['jobWorker:id,name', 'productionStep:id,name'])
            ->with('items.item:id,item_name')
            ->withSum('items as gross_wt_sum', 'gross_wt')
            ->withSum('items as net_wt_sum', 'net_wt')
            ->withSum('items as fine_wt_sum', 'fine_wt')
            ->withSum('items as total_amt_sum', 'total_amt')
            ->when($request->filled('from_date'), fn($q) => $q->whereDate('jobwork_date', '>=', $request->from_date))
            ->when($request->filled('to_date'), fn($q) => $q->whereDate('jobwork_date', '<=', $request->to_date))
            ->latest('jobwork_date')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $rows,
        ]);
    }

    public function show(Request $request, $id)
    {
        $companyId = (int) $request->user()->company_id;
        $row = JobworkIssue::where('company_id', $companyId)
            ->with(['jobWorker:id,name', 'productionStep:id,name'])
            ->with('items.item:id,item_name')
            ->find($id);

        if (!$row) {
            return response()->json(['success' => false, 'message' => 'Jobwork Issue not found.'], 404);
        }

        return response()->json(['success' => true, 'data' => $row]);
    }

    public function store(Request $request)
    {
        $companyId = (int) $request->user()->company_id;
        $validated = $this->validatePayload($request, $companyId);

        $row = DB::transaction(function () use ($validated, $companyId, $request) {
            $issue = JobworkIssue::create([
                'company_id' => $companyId,
                'voucher_no' => $this->generateVoucherNo($companyId, $validated['jobwork_date']),
                'jobwork_date' => $validated['jobwork_date'],
                'job_worker_id' => $validated['job_worker_id'],
                'production_step_id' => $validated['production_step_id'],
                'remarks' => $validated['remarks'] ?? null,
                'created_by' => $request->user()->id,
                'updated_by' => $request->user()->id,
                'modified_count' => 0,
            ]);

            foreach ($validated['items'] as $item) {
                $issue->items()->create([
                    'item_id' => $item['item_id'],
                    'other_charge_id' => $item['other_charge_id'] ?? null,
                    'gross_wt' => (float) ($item['gross_wt'] ?? 0),
                    'other_wt' => (float) ($item['other_wt'] ?? 0),
                    'other_amt' => (float) ($item['other_amt'] ?? 0),
                    'purity' => (float) ($item['purity'] ?? 0),
                    'net_purity' => (float) ($item['net_purity'] ?? 0),
                    'net_wt' => (float) ($item['net_wt'] ?? 0),
                    'fine_wt' => (float) ($item['fine_wt'] ?? 0),
                    'qty_pcs' => (int) ($item['qty_pcs'] ?? 0),
                    'remarks' => $item['remarks'] ?? null,
                    'total_amt' => (float) ($item['total_amt'] ?? 0),
                ]);
            }

            return $issue->load('items.item:id,item_name', 'jobWorker:id,name', 'productionStep:id,name');
        });

        return response()->json(['success' => true, 'message' => 'Jobwork Issue created successfully.', 'data' => $row], 201);
    }

    public function update(Request $request, $id)
    {
        $companyId = (int) $request->user()->company_id;
        $issue = JobworkIssue::where('company_id', $companyId)->find($id);

        if (!$issue) {
            return response()->json(['success' => false, 'message' => 'Jobwork Issue not found.'], 404);
        }

        $validated = $this->validatePayload($request, $companyId, (int) $issue->id);

        $row = DB::transaction(function () use ($issue, $validated, $request) {
            $issue->update([
                'jobwork_date' => $validated['jobwork_date'],
                'job_worker_id' => $validated['job_worker_id'],
                'production_step_id' => $validated['production_step_id'],
                'remarks' => $validated['remarks'] ?? null,
                'updated_by' => $request->user()->id,
                'modified_count' => ((int) $issue->modified_count) + 1,
            ]);

            $issue->items()->delete();
            foreach ($validated['items'] as $item) {
                $issue->items()->create([
                    'item_id' => $item['item_id'],
                    'other_charge_id' => $item['other_charge_id'] ?? null,
                    'gross_wt' => (float) ($item['gross_wt'] ?? 0),
                    'other_wt' => (float) ($item['other_wt'] ?? 0),
                    'other_amt' => (float) ($item['other_amt'] ?? 0),
                    'purity' => (float) ($item['purity'] ?? 0),
                    'net_purity' => (float) ($item['net_purity'] ?? 0),
                    'net_wt' => (float) ($item['net_wt'] ?? 0),
                    'fine_wt' => (float) ($item['fine_wt'] ?? 0),
                    'qty_pcs' => (int) ($item['qty_pcs'] ?? 0),
                    'remarks' => $item['remarks'] ?? null,
                    'total_amt' => (float) ($item['total_amt'] ?? 0),
                ]);
            }

            return $issue->load('items.item:id,item_name', 'jobWorker:id,name', 'productionStep:id,name');
        });

        return response()->json(['success' => true, 'message' => 'Jobwork Issue updated successfully.', 'data' => $row]);
    }

    public function destroy(Request $request, $id)
    {
        $companyId = (int) $request->user()->company_id;
        $issue = JobworkIssue::where('company_id', $companyId)->find($id);

        if (!$issue) {
            return response()->json(['success' => false, 'message' => 'Jobwork Issue not found.'], 404);
        }

        $issue->delete();
        return response()->json(['success' => true, 'message' => 'Jobwork Issue deleted successfully.']);
    }

    private function validatePayload(Request $request, int $companyId, ?int $id = null): array
    {
        return $request->validate([
            'jobwork_date' => ['required', 'date'],
            'job_worker_id' => [
                'required',
                'integer',
                Rule::exists('job_workers', 'id')->where(fn($q) => $q->where('company_id', $companyId)),
            ],
            'production_step_id' => [
                'required',
                'integer',
                Rule::exists('production_steps', 'id')->where(fn($q) => $q->where('company_id', $companyId)),
            ],
            'remarks' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.item_id' => [
                'required',
                'integer',
                Rule::exists('items', 'id')->where(fn($q) => $q->where('company_id', $companyId)),
            ],
            'items.*.other_charge_id' => [
                'nullable',
                'integer',
                Rule::exists('other_charges', 'id')->where(fn($q) => $q->where('company_id', $companyId)),
            ],
            'items.*.gross_wt' => ['nullable', 'numeric'],
            'items.*.other_wt' => ['nullable', 'numeric'],
            'items.*.other_amt' => ['nullable', 'numeric'],
            'items.*.purity' => ['nullable', 'numeric'],
            'items.*.net_purity' => ['nullable', 'numeric'],
            'items.*.net_wt' => ['nullable', 'numeric'],
            'items.*.fine_wt' => ['nullable', 'numeric'],
            'items.*.qty_pcs' => ['nullable', 'integer'],
            'items.*.remarks' => ['nullable', 'string'],
            'items.*.total_amt' => ['nullable', 'numeric'],
        ]);
    }

    private function generateVoucherNo(int $companyId, string $date): string
    {
        $yearShort = Carbon::parse($date)->format('y');
        $prefix = 'JI' . $yearShort . '-';

        $last = JobworkIssue::where('company_id', $companyId)
            ->where('voucher_no', 'like', $prefix . '%')
            ->orderByDesc('id')
            ->value('voucher_no');

        $next = 1;
        if ($last && str_contains($last, '-')) {
            $parts = explode('-', $last);
            $lastNo = (int) end($parts);
            $next = $lastNo + 1;
        }

        return $prefix . $next;
    }
}
