<?php

namespace App\Http\Controllers\Company;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Customer;
use App\Models\CustomerAdvanceLedger;
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

        $customers = Customer::where('company_id', $company->id)
            ->where('is_active', 1)
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
                'message' => 'Customer is required.',
            ], 422);
        }

        $customer = Customer::where('company_id', $company->id)
            ->where('is_active', 1)
            ->find($customerId);
        if (!$customer) {
            return response()->json([
                'success' => false,
                'message' => 'Customer not found.',
            ], 404);
        }

        $this->reconcileSaleSilverAdjustments($company->id, $customerId);

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
        ]);

        $customer = Customer::where('company_id', $company->id)
            ->where('is_active', 1)
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

        CustomerAdvanceLedger::create([
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

        return redirect()
            ->route('company.sales.advance.index', ['slug' => $company->slug])
            ->with('selected_customer_id', $customer->id)
            ->with('success', 'Advance ledger entry saved successfully.');
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
            return back()->with('error', 'Please select customer first.');
        }

        $customer = Customer::where('company_id', $company->id)
            ->where('is_active', 1)
            ->findOrFail($customerId);

        $this->reconcileSaleSilverAdjustments($company->id, $customerId);

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
