<?php

namespace App\Http\Controllers\Company;

use App\Http\Controllers\Controller;
use App\Models\CategoryPerson;
use App\Models\Company;
use App\Models\Customer;
use App\Models\CustomerAdvanceLedger;
use App\Models\CustomerAdvanceVoucher;
use App\Models\SaleItem;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

class CustomerAdvanceController extends Controller
{
    public function index(Request $request, $slug)
    {
        $company = Company::where('slug', $slug)->firstOrFail();

        $customers = $this->partyCustomersQuery($company->id)
            ->orderBy('name')
            ->get(['id', 'name']);

        $vouchersQuery = CustomerAdvanceVoucher::with('customer')
            ->where('company_id', $company->id)
            ->latest('voucher_date')
            ->latest('id');

        if ($request->filled('from_date')) {
            $vouchersQuery->whereDate('voucher_date', '>=', $request->date('from_date'));
        }

        if ($request->filled('to_date')) {
            $vouchersQuery->whereDate('voucher_date', '<=', $request->date('to_date'));
        }

        if ((int) $request->input('customer_id', 0) > 0) {
            $vouchersQuery->where('customer_id', (int) $request->input('customer_id'));
        }

        $vouchers = $vouchersQuery->paginate(25)->withQueryString();

        return view('company.sales.advance_index', compact('company', 'customers', 'vouchers'));
    }

    public function create(Request $request, $slug)
    {
        $company = Company::where('slug', $slug)->firstOrFail();

        $customers = $this->partyCustomersQuery($company->id)
            ->orderBy('name')
            ->get(['id', 'name', 'city', 'mobile_no']);

        $selectedCustomerId = (int) session('selected_customer_id', 0);
        $balance = $this->getCustomerBalance($company->id, null);
        $rows = collect();
        $customerHasEntries = false;

        return view('company.sales.advance_ledger', compact('company', 'customers', 'rows', 'selectedCustomerId', 'balance', 'customerHasEntries'));
    }

    public function data(Request $request, $slug)
    {
        $company = Company::where('slug', $slug)->firstOrFail();
        $customerId = (int) $request->query('customer_id', 0);

        if ($customerId <= 0) {
            return response()->json([
                'success' => false,
                'message' => 'Party is required.',
            ], 422);
        }

        $customer = $this->partyCustomersQuery($company->id)
            ->find($customerId);
        if (!$customer) {
            return response()->json([
                'success' => false,
                'message' => 'Party not found.',
            ], 404);
        }

        $this->reconcileSaleSilverAdjustments($company->id, $customerId);
        $this->reconcileCustomerItemReceiveMetalLedgers($company->id, $customerId);

        $balance = $this->getCustomerBalance($company->id, $customerId);
        $rows = CustomerAdvanceLedger::with('customer')
            ->where('company_id', $company->id)
            ->where('customer_id', $customerId)
            ->latest('entry_date')
            ->latest('id')
            ->limit(200)
            ->get();

        $tbody = view('company.sales.partials.advance_ledger_rows', compact('rows'))->render();

        return response()->json([
            'success' => true,
            'balance' => $balance,
            'rows_html' => $tbody,
            'row_count' => $rows->count(),
            'customer_id' => $customerId,
            'customer_key' => Crypt::encryptString((string) $customerId),
        ]);
    }

    public function store(Request $request, $slug)
    {
        $company = Company::where('slug', $slug)->firstOrFail();

        $request->validate([
            'entry_date' => 'required|date',
            'customer_id' => 'required|integer',
            'entry_type' => 'nullable|string|in:receive_amount,return_amount,convert_to_metal,convert_to_rupees,purchase_adjust_amount,purchase_adjust_metal',
            'payment_mode' => 'nullable|string|max:30',
            'amount' => 'nullable|numeric|min:0',
            'metal_type' => 'nullable|string|in:gold,silver,other',
            'rate' => 'nullable|numeric|min:0',
            'fine_weight' => 'nullable|numeric|min:0',
            'remarks' => 'nullable|string|max:255',
            'advance_items_payload' => 'nullable|string',
        ]);

        $customer = $this->partyCustomersQuery($company->id)
            ->findOrFail((int) $request->customer_id);

        $entryType = (string) $request->input('entry_type', 'receive_amount');
        $amount = (float) $request->input('amount', 0);
        $rate = (float) $request->input('rate', 0);
        $metalType = $request->input('metal_type');
        $fineWeight = (float) $request->input('fine_weight', 0);

        $hasAnyEntry = CustomerAdvanceLedger::where('company_id', $company->id)
            ->where('customer_id', (int) $customer->id)
            ->exists();
        if (!$hasAnyEntry && $entryType !== 'receive_amount') {
            return back()->with('error', 'First entry must be Receive Amount.');
        }

        $cashIn = 0.0;
        $cashOut = 0.0;
        $metalIn = 0.0;
        $metalOut = 0.0;

        if ($entryType === 'receive_amount') {
            if ($amount <= 0) {
                return back()->with('error', 'Amount must be greater than 0.');
            }
            $cashIn = $amount;
            $metalType = null;
            $rate = 0;
            $fineWeight = 0;
        } elseif ($entryType === 'return_amount' || $entryType === 'purchase_adjust_amount') {
            if ($amount <= 0) {
                return back()->with('error', 'Amount must be greater than 0.');
            }
            $cashOut = $amount;
            $metalType = null;
            $rate = 0;
            $fineWeight = 0;
        } elseif ($entryType === 'convert_to_metal') {
            if ($amount <= 0 || $rate <= 0 || empty($metalType)) {
                return back()->with('error', 'Amount, rate and metal type are required for conversion.');
            }
            $balance = $this->getCustomerBalance($company->id, (int) $customer->id);
            $availableCash = round((float) ($balance['cash_balance'] ?? 0), 2);
            if (round($amount, 2) > $availableCash) {
                return back()->with('error', 'Convert amount cannot be greater than available cash advance balance.');
            }
            $cashOut = $amount;
            $metalIn = round($amount / $rate, 3);
            $fineWeight = 0;
            $request->merge(['payment_mode' => null]);
        } elseif ($entryType === 'convert_to_rupees') {
            if ($amount <= 0 || $rate <= 0 || empty($metalType)) {
                return back()->with('error', 'Fine weight, rate and metal type are required for conversion.');
            }
            $balance = $this->getCustomerBalance($company->id, (int) $customer->id);
            $metalBalance = (float) data_get($balance, 'metal_balance.' . $metalType, 0);
            if ($amount > $metalBalance) {
                return back()->with('error', 'Convert fine weight cannot be greater than available metal balance.');
            }
            $metalOut = round($amount, 3);
            $cashIn = round($amount * $rate, 2);
            $fineWeight = 0;
            $request->merge(['payment_mode' => null]);
        } elseif ($entryType === 'purchase_adjust_metal') {
            if ($fineWeight <= 0 || empty($metalType)) {
                return back()->with('error', 'Fine weight and metal type are required for metal adjustment.');
            }
            $metalOut = $fineWeight;
            $amount = 0;
            $rate = 0;
        }

        $balance = $this->getCustomerBalance($company->id, (int) $customer->id);
        if ($cashOut > 0 && $cashOut > (float) ($balance['cash_balance'] ?? 0)) {
            return back()->with('error', 'Insufficient cash advance balance.');
        }
        if ($metalOut > 0) {
            $metalBalance = (float) data_get($balance, 'metal_balance.' . $metalType, 0);
            if ($metalOut > $metalBalance) {
                return back()->with('error', 'Insufficient metal advance balance for selected metal.');
            }
        }

        $items = $this->decodeAdvanceItems((string) $request->input('advance_items_payload', '[]'));

        DB::transaction(function () use ($request, $company, $customer, $entryType, $amount, $cashIn, $cashOut, $metalType, $metalIn, $metalOut, $rate, $items) {
            $ledger = CustomerAdvanceLedger::create([
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
                'rate' => $rate,
                'reference_type' => $entryType === 'purchase_adjust_amount' || $entryType === 'purchase_adjust_metal' ? 'sale' : null,
                'remarks' => $request->remarks,
                'created_by' => optional($request->user())->id,
            ]);

            $voucher = CustomerAdvanceVoucher::create([
                'company_id' => $company->id,
                'customer_id' => $customer->id,
                'ledger_id' => $ledger->id,
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
                'rate' => $rate,
                'remarks' => $request->remarks,
                'created_by' => optional($request->user())->id,
            ]);

            $voucher->update([
                'voucher_no' => 'RP' . now()->format('y') . '-' . $voucher->id,
            ]);

            foreach ($items as $itemRow) {
                $voucher->items()->create($itemRow);
            }

            $this->createItemReceiveMetalLedgers($request, $company, $customer, $voucher, $items);
        });

        return redirect()
            ->route('company.sales.advance.index', ['slug' => $company->slug])
            ->with('selected_customer_id', $customer->id)
            ->with('success', 'Advance ledger entry saved successfully.');
    }

    public function storeItems(Request $request, $slug)
    {
        $company = Company::where('slug', $slug)->firstOrFail();

        $request->validate([
            'entry_date' => 'required|date',
            'customer_id' => 'required|integer',
            'remarks' => 'nullable|string|max:255',
            'advance_items_payload' => 'required|string',
        ]);

        $customer = $this->partyCustomersQuery($company->id)
            ->findOrFail((int) $request->customer_id);

        $items = $this->decodeAdvanceItems((string) $request->input('advance_items_payload', '[]'));
        if (empty($items)) {
            return back()
                ->with('selected_customer_id', $customer->id)
                ->with('error', 'Please select at least one item before saving item details.');
        }

        DB::transaction(function () use ($request, $company, $customer, $items) {
            $voucher = CustomerAdvanceVoucher::create([
                'company_id' => $company->id,
                'customer_id' => $customer->id,
                'ledger_id' => null,
                'voucher_no' => 'RP-TMP-' . uniqid(),
                'voucher_date' => $request->entry_date,
                'entry_type' => 'item_receive',
                'payment_mode' => null,
                'amount' => collect($items)->sum('total_amount'),
                'cash_in' => 0,
                'cash_out' => 0,
                'metal_type' => null,
                'metal_in' => 0,
                'metal_out' => 0,
                'rate' => 0,
                'remarks' => $request->remarks,
                'created_by' => optional($request->user())->id,
            ]);

            $voucher->update([
                'voucher_no' => 'RP' . now()->format('y') . '-' . $voucher->id,
            ]);

            foreach ($items as $itemRow) {
                $voucher->items()->create($itemRow);
            }

            $firstLedgerId = $this->createItemReceiveMetalLedgers($request, $company, $customer, $voucher, $items);
            if ($firstLedgerId && !$voucher->ledger_id) {
                $voucher->update(['ledger_id' => $firstLedgerId]);
            }
        });

        return redirect()
            ->route('company.sales.advance.index', ['slug' => $company->slug])
            ->with('selected_customer_id', $customer->id)
            ->with('success', 'Item details saved successfully.');
    }

    public function voucherPdf($slug, $encryptedId)
    {
        $company = Company::where('slug', $slug)->firstOrFail();

        try {
            $voucherId = (int) Crypt::decryptString($encryptedId);
        } catch (\Throwable $e) {
            abort(404);
        }

        $voucher = CustomerAdvanceVoucher::with(['customer', 'items'])
            ->where('company_id', $company->id)
            ->findOrFail($voucherId);

        $pdf = Pdf::loadView('company.sales.pdf.advance_voucher', [
            'company' => $company,
            'voucher' => $voucher,
        ])->setPaper('a4', 'landscape');

        return $pdf->stream('receive-return-purchase-' . $voucher->voucher_no . '.pdf');
    }

    public function exportPdf(Request $request, $slug)
    {
        $company = Company::where('slug', $slug)->firstOrFail();
        $customerKey = (string) $request->query('customer_key', '');
        $customerId = 0;
        if ($customerKey !== '') {
            try {
                $customerId = (int) Crypt::decryptString($customerKey);
            } catch (\Throwable $e) {
                $customerId = 0;
            }
        }
        if ($customerId <= 0) {
            return back()->with('error', 'Please select party first.');
        }

        $customer = $this->partyCustomersQuery($company->id)
            ->findOrFail($customerId);

        $this->reconcileSaleSilverAdjustments($company->id, $customerId);
        $this->reconcileCustomerItemReceiveMetalLedgers($company->id, $customerId);

        $rows = CustomerAdvanceLedger::with('customer')
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

        return $pdf->stream('advance-ledger-history-' . $customer->id . '.pdf');
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

    private function decodeAdvanceItems(string $payload): array
    {
        $rows = json_decode($payload, true);
        if (!is_array($rows)) {
            return [];
        }

        $items = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $isItemOnly = filter_var($row['is_item_only'] ?? false, FILTER_VALIDATE_BOOLEAN)
                || (string) ($row['source'] ?? '') === 'item';

            $item = [
                'itemset_id' => $isItemOnly ? null : $this->nullableInt($row['itemset_id'] ?? $row['id'] ?? null),
                'product_id' => $this->nullableInt($row['product_id'] ?? $row['item_id'] ?? ($isItemOnly ? ($row['id'] ?? null) : null)),
                'label_code' => $this->nullableString($row['code'] ?? $row['label_code'] ?? null),
                'huid' => $this->nullableString($row['huid'] ?? null),
                'item_name' => $this->nullableString($row['name'] ?? $row['item_name'] ?? null),
                'metal_type' => $this->normalizeLedgerMetalType($row['metal_type'] ?? $row['metal'] ?? null),
                'gross_weight' => $this->decimalValue($row['gross_weight'] ?? 0, 3),
                'other_weight' => $this->decimalValue($row['other_weight'] ?? 0, 3),
                'net_weight' => $this->decimalValue($row['net_weight'] ?? 0, 3),
                'purity' => $this->decimalValue($row['purity'] ?? 0, 3),
                'waste_percent' => $this->decimalValue($row['waste_percent'] ?? 0, 3),
                'net_purity' => $this->decimalValue($row['net_purity'] ?? 0, 3),
                'fine_weight' => $this->decimalValue($row['fine_weight'] ?? 0, 3),
                'metal_rate' => $this->decimalValue($row['metal_rate'] ?? 0, 2),
                'apply_metal' => filter_var($row['apply_metal'] ?? true, FILTER_VALIDATE_BOOLEAN),
                'metal_amount' => $this->decimalValue($row['metal_amount'] ?? 0, 2),
                'labour_rate' => $this->decimalValue($row['labour_rate'] ?? 0, 2),
                'apply_labour' => filter_var($row['apply_labour'] ?? true, FILTER_VALIDATE_BOOLEAN),
                'labour_amount' => $this->decimalValue($row['labour_amount'] ?? 0, 2),
                'other_amount' => $this->decimalValue($row['other_amount'] ?? 0, 2),
                'total_amount' => $this->decimalValue($row['total_amount'] ?? 0, 2),
                'remarks' => $this->nullableString($row['remarks'] ?? null),
            ];

            $hasIdentity = $item['itemset_id'] || $item['product_id'] || $item['label_code'] || $item['item_name'];
            $hasValue = abs($item['gross_weight']) > 0 || abs($item['net_weight']) > 0 || abs($item['fine_weight']) > 0 || abs($item['total_amount']) > 0;
            if ($hasIdentity || $hasValue) {
                $items[] = $item;
            }
        }

        return $items;
    }

    private function createItemReceiveMetalLedgers(Request $request, Company $company, Customer $customer, CustomerAdvanceVoucher $voucher, array $items): ?int
    {
        $firstLedgerId = null;

        collect($items)
            ->groupBy(fn($item) => $item['metal_type'] ?: 'other')
            ->each(function ($metalItems, $metalType) use ($request, $company, $customer, $voucher, &$firstLedgerId) {
                $fineWeight = round((float) $metalItems->sum('fine_weight'), 3);
                if ($fineWeight <= 0) {
                    return;
                }

                $ledger = CustomerAdvanceLedger::create([
                    'company_id' => $company->id,
                    'customer_id' => $customer->id,
                    'entry_date' => $request->entry_date,
                    'entry_type' => 'item_receive',
                    'payment_mode' => null,
                    'cash_in' => 0,
                    'cash_out' => 0,
                    'metal_type' => $metalType,
                    'metal_in' => $fineWeight,
                    'metal_out' => 0,
                    'rate' => 0,
                    'reference_type' => 'customer_advance_voucher',
                    'reference_id' => $voucher->id,
                    'remarks' => 'Item receive fine metal from ' . $voucher->voucher_no,
                    'created_by' => optional($request->user())->id,
                ]);

                $firstLedgerId ??= $ledger->id;
            });

        return $firstLedgerId;
    }

    private function decimalValue($value, int $precision): float
    {
        return round((float) ($value ?? 0), $precision);
    }

    private function nullableInt($value): ?int
    {
        $value = (int) ($value ?? 0);
        return $value > 0 ? $value : null;
    }

    private function nullableString($value): ?string
    {
        $value = trim((string) ($value ?? ''));
        return $value !== '' ? mb_substr($value, 0, 255) : null;
    }

    private function normalizeLedgerMetalType($value): string
    {
        $value = strtolower(trim((string) ($value ?? '')));
        if ($value === 'gold' || str_contains($value, 'gold')) {
            return 'gold';
        }
        if ($value === 'silver' || str_contains($value, 'silver')) {
            return 'silver';
        }

        return 'other';
    }

    private function reconcileCustomerItemReceiveMetalLedgers(int $companyId, int $customerId): void
    {
        $vouchers = CustomerAdvanceVoucher::query()
            ->where('company_id', $companyId)
            ->where('customer_id', $customerId)
            ->whereHas('items')
            ->whereNotExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('customer_advance_ledgers as cal')
                    ->whereColumn('cal.reference_id', 'customer_advance_vouchers.id')
                    ->where('cal.reference_type', 'customer_advance_voucher')
                    ->where('cal.entry_type', 'item_receive');
            })
            ->get();

        foreach ($vouchers as $voucher) {
            $itemRows = DB::table('customer_advance_voucher_items as cavi')
                ->leftJoin('items', 'items.id', '=', 'cavi.product_id')
                ->where('cavi.voucher_id', $voucher->id)
                ->select([
                    'cavi.fine_weight',
                    'cavi.metal_type',
                    'items.metal as item_metal',
                ])
                ->get();

            $groupedFine = [];
            foreach ($itemRows as $row) {
                $metalType = $this->normalizeLedgerMetalType($row->metal_type ?: $row->item_metal);
                $groupedFine[$metalType] = ($groupedFine[$metalType] ?? 0) + (float) ($row->fine_weight ?? 0);
            }

            foreach ($groupedFine as $metalType => $fineWeight) {
                $fineWeight = round((float) $fineWeight, 3);
                if ($fineWeight <= 0) {
                    continue;
                }

                CustomerAdvanceLedger::create([
                    'company_id' => $companyId,
                    'customer_id' => $customerId,
                    'entry_date' => $voucher->voucher_date,
                    'entry_type' => 'item_receive',
                    'payment_mode' => null,
                    'cash_in' => 0,
                    'cash_out' => 0,
                    'metal_type' => $metalType,
                    'metal_in' => $fineWeight,
                    'metal_out' => 0,
                    'rate' => 0,
                    'reference_type' => 'customer_advance_voucher',
                    'reference_id' => $voucher->id,
                    'remarks' => 'Item receive fine metal from ' . $voucher->voucher_no,
                    'created_by' => null,
                    'created_at' => $voucher->created_at,
                    'updated_at' => now(),
                ]);
            }
        }
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
            $fineRequired = (float) SaleItem::query()
                ->where('sale_id', (int) $row->reference_id)
                ->sum('fine_weight');

            if ($fineRequired > 0) {
                $newOut = round($fineRequired, 3);
                if ((float) $row->metal_out !== $newOut || (string) ($row->remarks ?? '') !== 'Auto silver adjusted from sale fine weight') {
                    $row->metal_out = $newOut;
                    $row->remarks = 'Auto silver adjusted from sale fine weight';
                    $row->save();
                }
            }
        }
    }
}
