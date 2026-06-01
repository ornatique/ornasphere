<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Customer;
use App\Models\CustomerAdvanceLedger;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class CustomerAdvanceApiController extends Controller
{
    public function customers(Request $request)
    {
        $company = $this->resolveCompany($request);
        if (!$company) {
            return response()->json(['success' => false, 'message' => 'Company not found.'], 404);
        }

        $rows = Customer::query()
            ->where('company_id', $company->id)
            ->where('is_active', 1)
            ->orderBy('name')
            ->get(['id', 'name', 'city', 'mobile_no']);

        return response()->json([
            'success' => true,
            'message' => 'Active customers fetched successfully.',
            'count' => $rows->count(),
            'data' => $rows,
        ]);
    }

    public function summary(Request $request)
    {
        $company = $this->resolveCompany($request);
        if (!$company) {
            return response()->json(['success' => false, 'message' => 'Company not found.'], 404);
        }

        $customerId = (int) $request->query('customer_id', 0);
        if ($customerId <= 0) {
            return response()->json(['success' => false, 'message' => 'customer_id is required.'], 422);
        }

        $customer = Customer::query()
            ->where('company_id', $company->id)
            ->where('is_active', 1)
            ->find($customerId);
        if (!$customer) {
            return response()->json(['success' => false, 'message' => 'Customer not found.'], 404);
        }

        $this->reconcileSaleSilverAdjustments($company->id, $customerId);
        $balance = $this->getCustomerBalance($company->id, $customerId);

        return response()->json([
            'success' => true,
            'message' => 'Customer advance summary fetched successfully.',
            'data' => [
                'customer' => [
                    'id' => (int) $customer->id,
                    'name' => (string) $customer->name,
                    'city' => (string) ($customer->city ?? ''),
                ],
                'balance' => $balance,
            ],
        ]);
    }

    public function entries(Request $request)
    {
        $company = $this->resolveCompany($request);
        if (!$company) {
            return response()->json(['success' => false, 'message' => 'Company not found.'], 404);
        }

        $customerId = (int) $request->query('customer_id', 0);
        if ($customerId <= 0) {
            return response()->json(['success' => false, 'message' => 'customer_id is required.'], 422);
        }

        $customer = Customer::query()
            ->where('company_id', $company->id)
            ->where('is_active', 1)
            ->find($customerId);
        if (!$customer) {
            return response()->json(['success' => false, 'message' => 'Customer not found.'], 404);
        }

        $this->reconcileSaleSilverAdjustments($company->id, $customerId);

        $perPage = min(200, max(1, (int) $request->query('per_page', 50)));
        $rows = CustomerAdvanceLedger::query()
            ->where('company_id', $company->id)
            ->where('customer_id', $customerId)
            ->orderByDesc('entry_date')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($perPage);

        $data = collect($rows->items())->map(function ($row) {
            return [
                'id' => (int) $row->id,
                'entry_date' => optional($row->entry_date)->format('Y-m-d'),
                'entry_date_fmt' => optional($row->entry_date)->format('d-m-Y'),
                'entry_datetime_fmt' => optional($row->created_at)->format('d-m-Y h:i A'),
                'entry_type' => (string) $row->entry_type,
                'payment_mode' => (string) ($row->payment_mode ?? ''),
                'cash_in' => (float) ($row->cash_in ?? 0),
                'cash_out' => (float) ($row->cash_out ?? 0),
                'metal_type' => (string) ($row->metal_type ?? ''),
                'metal_in' => (float) ($row->metal_in ?? 0),
                'metal_out' => (float) ($row->metal_out ?? 0),
                'rate' => (float) ($row->rate ?? 0),
                'remarks' => (string) ($row->remarks ?? ''),
                'created_at' => optional($row->created_at)->toDateTimeString(),
            ];
        })->values();

        return response()->json([
            'success' => true,
            'message' => 'Advance ledger entries fetched successfully.',
            'count' => $rows->total(),
            'pagination' => [
                'current_page' => $rows->currentPage(),
                'last_page' => $rows->lastPage(),
                'per_page' => $rows->perPage(),
                'total' => $rows->total(),
            ],
            'data' => $data,
            'balance' => $this->getCustomerBalance($company->id, $customerId),
        ]);
    }

    public function store(Request $request)
    {
        $company = $this->resolveCompany($request);
        if (!$company) {
            return response()->json(['success' => false, 'message' => 'Company not found.'], 404);
        }

        $validator = Validator::make($request->all(), [
            'entry_date' => 'required|date',
            'customer_id' => 'required|integer',
            'entry_type' => 'nullable|string|in:receive_amount,return_amount,convert_to_metal,convert_to_rupees,purchase_adjust_amount,purchase_adjust_metal',
            'payment_mode' => 'nullable|string|max:30',
            'amount' => 'nullable|numeric|min:0',
            'metal_type' => 'nullable|string|in:gold,silver,other',
            'rate' => 'nullable|numeric|min:0',
            'fine_weight' => 'nullable|numeric|min:0',
            'remarks' => 'nullable|string|max:1000',
            'reference_type' => 'nullable|string|max:50',
            'reference_id' => 'nullable|integer|min:1',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $customer = Customer::query()
            ->where('company_id', $company->id)
            ->where('is_active', 1)
            ->find((int) $request->customer_id);
        if (!$customer) {
            return response()->json(['success' => false, 'message' => 'Customer not found.'], 404);
        }

        $entryType = (string) $request->input('entry_type', 'receive_amount');
        $amount = (float) $request->input('amount', 0);
        $rate = (float) $request->input('rate', 0);
        $metalType = $request->input('metal_type');
        $fineWeight = (float) $request->input('fine_weight', 0);

        $hasAnyEntry = CustomerAdvanceLedger::query()
            ->where('company_id', $company->id)
            ->where('customer_id', (int) $customer->id)
            ->exists();
        if (!$hasAnyEntry && $entryType !== 'receive_amount') {
            return response()->json(['success' => false, 'message' => 'First entry must be Receive Amount.'], 422);
        }

        if ($entryType === 'convert_to_metal') {
            if (!$metalType) {
                return response()->json(['success' => false, 'message' => 'Metal type is required for conversion.'], 422);
            }
            if ($amount <= 0 || $rate <= 0) {
                return response()->json(['success' => false, 'message' => 'Amount and rate must be greater than zero.'], 422);
            }
            $fineWeight = $amount / $rate;
            $balance = $this->getCustomerBalance($company->id, (int) $customer->id);
            if ((float) ($balance['cash_balance'] ?? 0) + 0.000001 < $amount) {
                return response()->json(['success' => false, 'message' => 'Amount exceeds available cash advance.'], 422);
            }
        }

        if ($entryType === 'convert_to_rupees') {
            if (!$metalType) {
                return response()->json(['success' => false, 'message' => 'Metal type is required for conversion.'], 422);
            }
            if ($fineWeight <= 0 || $rate <= 0) {
                return response()->json(['success' => false, 'message' => 'Fine weight and rate must be greater than zero.'], 422);
            }
            $amount = $fineWeight * $rate;
            $balance = $this->getCustomerBalance($company->id, (int) $customer->id);
            $availableMetal = (float) data_get($balance, 'metal_balance.' . $metalType, 0);
            if ($availableMetal + 0.000001 < $fineWeight) {
                return response()->json(['success' => false, 'message' => 'Fine weight exceeds available metal balance.'], 422);
            }
        }

        $cashIn = 0.0;
        $cashOut = 0.0;
        $metalIn = 0.0;
        $metalOut = 0.0;
        $rateToStore = 0.0;

        switch ($entryType) {
            case 'receive_amount':
                $cashIn = $amount;
                break;
            case 'return_amount':
                $cashOut = $amount;
                break;
            case 'purchase_adjust_amount':
                $cashOut = $amount;
                break;
            case 'purchase_adjust_metal':
                $metalOut = $fineWeight;
                break;
            case 'convert_to_metal':
                $cashOut = $amount;
                $metalIn = $fineWeight;
                $rateToStore = $rate;
                break;
            case 'convert_to_rupees':
                $metalOut = $fineWeight;
                $cashIn = $amount;
                $rateToStore = $rate;
                break;
        }

        $entry = CustomerAdvanceLedger::create([
            'company_id' => $company->id,
            'customer_id' => $customer->id,
            'entry_date' => $request->entry_date,
            'entry_type' => $entryType,
            'payment_mode' => $request->payment_mode,
            'cash_in' => $cashIn,
            'cash_out' => $cashOut,
            'metal_type' => $metalType,
            'metal_in' => $metalIn,
            'metal_out' => $metalOut,
            'rate' => $rateToStore,
            'remarks' => $request->remarks,
            'reference_type' => $request->reference_type,
            'reference_id' => $request->reference_id,
            'created_by' => optional($request->user())->id,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Advance ledger entry saved successfully.',
            'data' => [
                'id' => (int) $entry->id,
                'customer_id' => (int) $entry->customer_id,
                'entry_type' => (string) $entry->entry_type,
                'entry_date' => optional($entry->entry_date)->format('Y-m-d'),
                'cash_in' => (float) ($entry->cash_in ?? 0),
                'cash_out' => (float) ($entry->cash_out ?? 0),
                'metal_type' => (string) ($entry->metal_type ?? ''),
                'metal_in' => (float) ($entry->metal_in ?? 0),
                'metal_out' => (float) ($entry->metal_out ?? 0),
                'rate' => (float) ($entry->rate ?? 0),
                'remarks' => (string) ($entry->remarks ?? ''),
            ],
            'balance' => $this->getCustomerBalance($company->id, (int) $entry->customer_id),
        ]);
    }

    public function pdfUrl(Request $request)
    {
        // If direct=1, return actual PDF response from the same endpoint
        if ((int) $request->query('direct', 0) === 1) {
            return $this->pdf($request);
        }

        $company = $this->resolveCompany($request);
        if (!$company) {
            return response()->json(['success' => false, 'message' => 'Company not found.'], 404);
        }

        $customerId = (int) $request->query('customer_id', 0);
        if ($customerId <= 0) {
            return response()->json(['success' => false, 'message' => 'customer_id is required.'], 422);
        }

        $url = route('company.sales.advance.pdf', ['slug' => $company->slug]) . '?customer_key=' . urlencode(\Illuminate\Support\Facades\Crypt::encryptString((string) $customerId));

        return response()->json([
            'success' => true,
            'message' => 'PDF URL generated successfully.',
            'data' => [
                'url' => $url,
                'api_pdf_url' => url('/api/sales/advance-ledger/pdf') . '?customer_id=' . $customerId . '&mode=inline',
            ],
        ]);
    }

    public function pdf(Request $request)
    {
        $company = $this->resolveCompany($request);
        if (!$company) {
            return response()->json(['success' => false, 'message' => 'Company not found.'], 404);
        }

        $customerId = (int) $request->query('customer_id', 0);
        if ($customerId <= 0) {
            return response()->json(['success' => false, 'message' => 'customer_id is required.'], 422);
        }

        $customer = Customer::query()
            ->where('company_id', $company->id)
            ->where('is_active', 1)
            ->find($customerId);
        if (!$customer) {
            return response()->json(['success' => false, 'message' => 'Customer not found.'], 404);
        }

        $this->reconcileSaleSilverAdjustments($company->id, $customerId);

        $rows = CustomerAdvanceLedger::query()
            ->with('customer')
            ->where('company_id', $company->id)
            ->where('customer_id', $customerId)
            ->orderByDesc('entry_date')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get();

        $balance = $this->getCustomerBalance($company->id, $customerId);

        $pdf = Pdf::loadView('company.sales.pdf.advance_ledger_history', [
            'company' => $company,
            'customer' => $customer,
            'rows' => $rows,
            'balance' => $balance,
        ])->setPaper('a4', 'landscape');

        $fileName = 'advance-ledger-history-' . $customer->id . '.pdf';
        $mode = strtolower((string) $request->query('mode', 'inline'));
        if ($mode === 'download') {
            return $pdf->download($fileName);
        }
        return $pdf->stream($fileName);
    }

    private function resolveCompany(Request $request): ?Company
    {
        $user = $request->user();
        if (!$user || empty($user->company_id)) {
            return null;
        }

        return Company::find((int) $user->company_id);
    }

    private function getCustomerBalance(int $companyId, ?int $customerId): array
    {
        if (!$customerId) {
            return [
                'cash_balance' => 0.0,
                'metal_balance' => ['gold' => 0.0, 'silver' => 0.0, 'other' => 0.0],
            ];
        }

        $cash = DB::table('customer_advance_ledgers')
            ->where('company_id', $companyId)
            ->where('customer_id', $customerId)
            ->selectRaw('COALESCE(SUM(cash_in),0) - COALESCE(SUM(cash_out),0) as cash_balance')
            ->value('cash_balance');

        $metalRows = DB::table('customer_advance_ledgers')
            ->where('company_id', $companyId)
            ->where('customer_id', $customerId)
            ->whereNotNull('metal_type')
            ->selectRaw('metal_type, COALESCE(SUM(metal_in),0) - COALESCE(SUM(metal_out),0) as bal')
            ->groupBy('metal_type')
            ->pluck('bal', 'metal_type');

        return [
            'cash_balance' => (float) $cash,
            'metal_balance' => [
                'gold' => (float) ($metalRows['gold'] ?? 0),
                'silver' => (float) ($metalRows['silver'] ?? 0),
                'other' => (float) ($metalRows['other'] ?? 0),
            ],
        ];
    }

    private function reconcileSaleSilverAdjustments(int $companyId, int $customerId): void
    {
        $rows = CustomerAdvanceLedger::query()
            ->where('company_id', $companyId)
            ->where('customer_id', $customerId)
            ->where('entry_type', 'purchase_adjust_metal')
            ->where('metal_type', 'silver')
            ->where('reference_type', 'sale')
            ->whereNotNull('reference_id')
            ->get();

        foreach ($rows as $row) {
            $fine = DB::table('sale_items')
                ->where('sale_id', (int) $row->reference_id)
                ->sum('fine_weight');
            $fine = (float) $fine;
            if (abs((float) $row->metal_out - $fine) > 0.000001) {
                $row->metal_out = $fine;
                $row->save();
            }
        }
    }
}
