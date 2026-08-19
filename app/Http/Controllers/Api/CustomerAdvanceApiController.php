<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CategoryPerson;
use App\Models\Company;
use App\Models\Customer;
use App\Models\CustomerAdvanceLedger;
use App\Models\CustomerAdvanceVoucher;
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

        $rows = $this->partyCustomersQuery((int) $company->id)
            ->orderBy('name')
            ->get(['id', 'name', 'city', 'mobile_no']);

        return response()->json([
            'success' => true,
            'message' => 'Active parties fetched successfully.',
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
            return response()->json(['success' => false, 'message' => 'party customer_id is required.'], 422);
        }

        $customer = $this->partyCustomersQuery((int) $company->id)
            ->find($customerId);
        if (!$customer) {
            return response()->json(['success' => false, 'message' => 'Party not found.'], 404);
        }

        $this->reconcileSaleSilverAdjustments($company->id, $customerId);
        $balance = $this->getCustomerBalance($company->id, $customerId);

        return response()->json([
            'success' => true,
            'message' => 'Party advance summary fetched successfully.',
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
            return response()->json(['success' => false, 'message' => 'party customer_id is required.'], 422);
        }

        $customer = $this->partyCustomersQuery((int) $company->id)
            ->find($customerId);
        if (!$customer) {
            return response()->json(['success' => false, 'message' => 'Party not found.'], 404);
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

    public function vouchers(Request $request)
    {
        $company = $this->resolveCompany($request);
        if (!$company) {
            return response()->json(['success' => false, 'message' => 'Company not found.'], 404);
        }

        $perPage = min(200, max(1, (int) $request->query('per_page', 25)));

        $query = CustomerAdvanceVoucher::with('customer')
            ->where('company_id', $company->id)
            ->when($request->filled('from_date'), function ($q) use ($request) {
                $q->whereDate('voucher_date', '>=', $request->query('from_date'));
            })
            ->when($request->filled('to_date'), function ($q) use ($request) {
                $q->whereDate('voucher_date', '<=', $request->query('to_date'));
            })
            ->when((int) $request->query('customer_id', 0) > 0, function ($q) use ($request) {
                $q->where('customer_id', (int) $request->query('customer_id'));
            })
            ->orderByDesc('voucher_date')
            ->orderByDesc('id');

        $rows = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'message' => 'Receive / Return / Purchase vouchers fetched successfully.',
            'count' => $rows->total(),
            'pagination' => [
                'current_page' => $rows->currentPage(),
                'last_page' => $rows->lastPage(),
                'per_page' => $rows->perPage(),
                'total' => $rows->total(),
            ],
            'filters' => [
                'from_date' => $request->query('from_date'),
                'to_date' => $request->query('to_date'),
                'customer_id' => (int) $request->query('customer_id', 0),
            ],
            'data' => collect($rows->items())->map(fn($voucher) => $this->voucherPayload($voucher))->values(),
        ]);
    }

    public function voucherShow(Request $request, int $id)
    {
        $company = $this->resolveCompany($request);
        if (!$company) {
            return response()->json(['success' => false, 'message' => 'Company not found.'], 404);
        }

        $voucher = CustomerAdvanceVoucher::with(['customer', 'items'])
            ->where('company_id', $company->id)
            ->find($id);

        if (!$voucher) {
            return response()->json(['success' => false, 'message' => 'Voucher not found.'], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Voucher fetched successfully.',
            'data' => $this->voucherPayload($voucher, true),
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
            'entry_type' => 'nullable|string|in:receive_amount,receive_metal,return_amount,convert_to_metal,convert_to_rupees,purchase_adjust_amount,purchase_adjust_metal',
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

        $customer = $this->partyCustomersQuery((int) $company->id)
            ->find((int) $request->customer_id);
        if (!$customer) {
            return response()->json(['success' => false, 'message' => 'Party not found.'], 404);
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
        if (!$hasAnyEntry && !in_array($entryType, ['receive_amount', 'receive_metal'], true)) {
            return response()->json(['success' => false, 'message' => 'First entry must be Receive Amount or Receive Metal.'], 422);
        }

        if ($entryType === 'receive_metal') {
            if (!$metalType) {
                return response()->json(['success' => false, 'message' => 'Metal type is required for receive metal.'], 422);
            }
            if ($fineWeight <= 0) {
                return response()->json(['success' => false, 'message' => 'Fine weight must be greater than zero.'], 422);
            }
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

        if ($entryType === 'receive_amount' && $amount <= 0) {
            return response()->json(['success' => false, 'message' => 'Amount must be greater than zero.'], 422);
        }

        if (in_array($entryType, ['return_amount', 'purchase_adjust_amount'], true) && $amount <= 0) {
            return response()->json(['success' => false, 'message' => 'Amount must be greater than zero.'], 422);
        }

        if ($entryType === 'purchase_adjust_metal') {
            if (!$metalType) {
                return response()->json(['success' => false, 'message' => 'Metal type is required for metal adjustment.'], 422);
            }
            if ($fineWeight <= 0) {
                return response()->json(['success' => false, 'message' => 'Fine weight must be greater than zero.'], 422);
            }
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
                $metalType = null;
                break;
            case 'receive_metal':
                $metalIn = round($fineWeight, 3);
                $amount = 0;
                break;
            case 'return_amount':
                $cashOut = $amount;
                $metalType = null;
                break;
            case 'purchase_adjust_amount':
                $cashOut = $amount;
                $metalType = null;
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

        [$entry, $voucher] = DB::transaction(function () use ($request, $company, $customer, $entryType, $cashIn, $cashOut, $metalType, $metalIn, $metalOut, $rateToStore, $amount) {
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

            $voucher = CustomerAdvanceVoucher::create([
                'company_id' => $company->id,
                'customer_id' => $customer->id,
                'ledger_id' => $entry->id,
                'voucher_no' => 'RP-TMP-' . uniqid(),
                'voucher_date' => $request->entry_date,
                'entry_type' => $entryType,
                'payment_mode' => $request->payment_mode,
                'amount' => $amount,
                'cash_in' => $cashIn,
                'cash_out' => $cashOut,
                'metal_type' => $metalType,
                'metal_in' => $metalIn,
                'metal_out' => $metalOut,
                'rate' => $rateToStore,
                'remarks' => $request->remarks,
                'created_by' => optional($request->user())->id,
            ]);

            $voucher->update([
                'voucher_no' => 'RP' . now()->format('y') . '-' . $voucher->id,
            ]);

            return [$entry, $voucher->fresh('customer')];
        });

        return response()->json([
            'success' => true,
            'message' => 'Receive / Return / Purchase voucher saved successfully.',
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
            'voucher' => $this->voucherPayload($voucher),
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
            return response()->json(['success' => false, 'message' => 'party customer_id is required.'], 422);
        }

        $customer = $this->partyCustomersQuery((int) $company->id)->find($customerId);
        if (!$customer) {
            return response()->json(['success' => false, 'message' => 'Party not found.'], 404);
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
            return response()->json(['success' => false, 'message' => 'party customer_id is required.'], 422);
        }

        $customer = $this->partyCustomersQuery((int) $company->id)
            ->find($customerId);
        if (!$customer) {
            return response()->json(['success' => false, 'message' => 'Party not found.'], 404);
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

    public function voucherPdfUrl(Request $request, int $id)
    {
        $company = $this->resolveCompany($request);
        if (!$company) {
            return response()->json(['success' => false, 'message' => 'Company not found.'], 404);
        }

        $voucher = CustomerAdvanceVoucher::where('company_id', $company->id)->find($id);
        if (!$voucher) {
            return response()->json(['success' => false, 'message' => 'Voucher not found.'], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Voucher PDF URL generated successfully.',
            'data' => [
                'url' => url('/api/sales/advance-ledger/vouchers/' . $voucher->id . '/pdf') . '?mode=inline',
                'download_url' => url('/api/sales/advance-ledger/vouchers/' . $voucher->id . '/pdf') . '?mode=download',
            ],
        ]);
    }

    public function voucherPdf(Request $request, int $id)
    {
        $company = $this->resolveCompany($request);
        if (!$company) {
            return response()->json(['success' => false, 'message' => 'Company not found.'], 404);
        }

        $voucher = CustomerAdvanceVoucher::with(['customer', 'items'])
            ->where('company_id', $company->id)
            ->find($id);

        if (!$voucher) {
            return response()->json(['success' => false, 'message' => 'Voucher not found.'], 404);
        }

        $pdf = Pdf::loadView('company.sales.pdf.advance_voucher', [
            'company' => $company,
            'voucher' => $voucher,
        ])->setPaper('a4', 'landscape');

        $fileName = 'receive-return-purchase-' . $voucher->voucher_no . '.pdf';
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

    private function partyCustomersQuery(int $companyId)
    {
        CategoryPerson::ensureCompanyDefaults($companyId);

        return Customer::query()
            ->where('company_id', $companyId)
            ->where('is_active', 1)
            ->whereHas('categoryPerson', function ($query) use ($companyId) {
                $query->where('company_id', $companyId)
                    ->whereIn(DB::raw('LOWER(TRIM(category_name))'), ['customer', 'party']);
            });
    }

    private function voucherPayload(CustomerAdvanceVoucher $voucher, bool $withItems = false): array
    {
        $payload = [
            'id' => (int) $voucher->id,
            'voucher_no' => (string) $voucher->voucher_no,
            'voucher_date' => optional($voucher->voucher_date)->format('Y-m-d'),
            'voucher_date_fmt' => optional($voucher->voucher_date)->format('d-m-Y'),
            'customer_id' => (int) $voucher->customer_id,
            'party_name' => (string) (optional($voucher->customer)->name ?? ''),
            'entry_type' => (string) $voucher->entry_type,
            'entry_type_label' => ucwords(str_replace('_', ' ', (string) $voucher->entry_type)),
            'payment_mode' => (string) ($voucher->payment_mode ?? ''),
            'payment_mode_label' => $voucher->payment_mode ? ucfirst((string) $voucher->payment_mode) : '-',
            'cash_in' => (float) ($voucher->cash_in ?? 0),
            'cash_out' => (float) ($voucher->cash_out ?? 0),
            'metal_type' => (string) ($voucher->metal_type ?? ''),
            'metal_type_label' => $voucher->metal_type ? ucfirst((string) $voucher->metal_type) : '-',
            'metal_in' => (float) ($voucher->metal_in ?? 0),
            'metal_out' => (float) ($voucher->metal_out ?? 0),
            'rate' => (float) ($voucher->rate ?? 0),
            'amount' => (float) ($voucher->amount ?? 0),
            'remarks' => (string) ($voucher->remarks ?? ''),
            'pdf_url' => url('/api/sales/advance-ledger/vouchers/' . $voucher->id . '/pdf') . '?mode=inline',
            'pdf_download_url' => url('/api/sales/advance-ledger/vouchers/' . $voucher->id . '/pdf') . '?mode=download',
            'created_at' => optional($voucher->created_at)->toDateTimeString(),
        ];

        if ($withItems) {
            $payload['items'] = $voucher->items->map(function ($item) {
                return [
                    'id' => (int) $item->id,
                    'itemset_id' => $item->itemset_id ? (int) $item->itemset_id : null,
                    'product_id' => $item->product_id ? (int) $item->product_id : null,
                    'label_code' => (string) ($item->label_code ?? ''),
                    'huid' => (string) ($item->huid ?? ''),
                    'item_name' => (string) ($item->item_name ?? ''),
                    'metal_type' => (string) ($item->metal_type ?? ''),
                    'gross_weight' => (float) ($item->gross_weight ?? 0),
                    'other_weight' => (float) ($item->other_weight ?? 0),
                    'net_weight' => (float) ($item->net_weight ?? 0),
                    'purity' => (float) ($item->purity ?? 0),
                    'waste_percent' => (float) ($item->waste_percent ?? 0),
                    'net_purity' => (float) ($item->net_purity ?? 0),
                    'fine_weight' => (float) ($item->fine_weight ?? 0),
                    'metal_rate' => (float) ($item->metal_rate ?? 0),
                    'apply_metal' => (bool) $item->apply_metal,
                    'metal_amount' => (float) ($item->metal_amount ?? 0),
                    'labour_rate' => (float) ($item->labour_rate ?? 0),
                    'apply_labour' => (bool) $item->apply_labour,
                    'labour_amount' => (float) ($item->labour_amount ?? 0),
                    'other_amount' => (float) ($item->other_amount ?? 0),
                    'total_amount' => (float) ($item->total_amount ?? 0),
                    'remarks' => (string) ($item->remarks ?? ''),
                ];
            })->values();
        }

        return $payload;
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
