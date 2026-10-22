<?php
namespace App\Http\Controllers\Company;

use App\Http\Controllers\Controller;
use App\Models\{Company, Customer, CustomerAdvanceLedger, CustomerAdvanceVoucher};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Validation\ValidationException;

class BullionController extends Controller
{
    private function customers(int $companyId)
    {
        return Customer::where('company_id', $companyId)
            ->whereHas('categoryPerson', fn ($query) => $query->where('company_id', $companyId)
                ->whereRaw('LOWER(TRIM(category_name)) IN (?)', ['bullion']));
    }

    private function company(Request $request, string $slug): Company
    {
        $company = \App\Services\CompanyBranches::visibleCompanies($request->user())->firstWhere('slug', $slug);
        abort_unless($company && \App\Services\CompanyBranches::isActive($company), 403);
        return $company;
    }

    public function index(Request $request, string $slug)
    {
        $company = $this->company($request, $slug);
        $companies = \App\Services\CompanyBranches::managementSelection($request, $company);
        $customers = $companies->flatMap(fn ($branch) => $this->customers($branch->id)->get(['id','name','company_id']))->sortBy('name');
        $selected = null;
        if ($request->filled('customer_id')) {
            $key = $request->query('customer_id');
            abort_unless(is_string($key), 404);
            // Upgrade existing bookmarked links only after checking ownership/category.
            if (ctype_digit($key)) {
                $customer = $customers->firstWhere('id', (int) $key) ?? abort(404);
                return redirect()->route('company.bullion.index', ['slug'=>$slug, 'customer_id'=>Crypt::encryptString((string)$customer->id), 'management_branches'=>$companies->keys()->all(), 'format'=>$request->query('format') === 'pdf' ? 'pdf' : null]);
            }
            try {
                $id = Crypt::decryptString($key);
            } catch (\Illuminate\Contracts\Encryption\DecryptException $exception) {
                abort(404, 'Invalid bullion selection.');
            }
            abort_unless(ctype_digit($id), 404);
            $selected = $customers->firstWhere('id', (int) $id) ?? abort(404);
        }
        $ledgerCompany = $selected ? $companies[$selected->company_id] : $company;
        $entries = CustomerAdvanceLedger::where('company_id', $ledgerCompany->id)->where('customer_id', $selected?->id ?? 0)->orderBy('entry_date')->orderBy('id')->get();
        $balance = ['cash'=>0, 'gold'=>0, 'silver'=>0];
        foreach ($entries as $entry) {
            $balance['cash'] += $entry->cash_in - $entry->cash_out;
            if (isset($balance[$entry->metal_type])) $balance[$entry->metal_type] += $entry->metal_in - $entry->metal_out;
            $entry->running = $balance;
        }
        if ($request->query('format') === 'pdf' || $request->routeIs('company.bullion.pdf')) {
            abort_unless($selected, 422, 'Select a bullion to download the ledger.');
            $company = $ledgerCompany;
            return \Barryvdh\DomPDF\Facade\Pdf::loadView('company.bullion.pdf', compact('company','selected','entries','balance'))
                ->setPaper('a4', 'landscape')
                ->download('bullion-ledger-'.$selected->id.'-'.now()->format('Ymd').'.pdf');
        }
        return view('company.bullion.index', compact('company','customers','selected','entries','balance','companies','ledgerCompany'));
    }

    public function store(Request $request, string $slug)
    {
        $company = $this->company($request, $slug);
        if (!\App\Services\BranchReadOnly::canWrite($request->user(), $company->id)) abort(403, \App\Services\BranchReadOnly::MESSAGE);
        $data = $request->validate([
            'customer_id'=>'required|integer', 'entry_date'=>'required|date',
            'entry_type'=>'required|in:receive_amount,receive_metal,return_amount,return_metal,convert_to_metal,convert_to_rupees',
            'metal_type'=>'nullable|in:gold,silver', 'amount'=>'nullable|numeric|min:0',
            'net_weight'=>'nullable|numeric|min:0', 'purity'=>'nullable|numeric|gt:0|max:100',
            'rate'=>'nullable|numeric|min:0', 'rate_confirmed'=>'accepted',
            'payment_mode'=>'nullable|string|max:30', 'remarks'=>'nullable|string|max:120',
        ]);
        DB::transaction(function () use ($company, $data, $request) {
            $this->customers($company->id)->whereKey($data['customer_id'])->lockForUpdate()->firstOrFail();
            $type = $data['entry_type'];
            $cash = round($data['amount'] ?? 0, 2);
            $fine = round(($data['net_weight'] ?? 0) * ($data['purity'] ?? 100) / 100, 3);
            $rate = round((float) ($data['rate'] ?? 0), 2);
            $metal = $data['metal_type'] ?? null;
            $ci = $co = $mi = $mo = 0;
            $fail = fn ($message) => throw ValidationException::withMessages(['transaction'=>$message]);
            if (!in_array($type, ['receive_amount','return_amount']) && !$metal) $fail('Select gold or silver.');
            if ($type === 'receive_metal' && $rate <= 0) $fail('Enter the purchase rate in INR per gram of pure metal.');
            if (in_array($type, ['convert_to_metal','convert_to_rupees']) && $rate <= 0) $fail('Enter a confirmed rate in INR per gram of pure metal.');
            switch ($type) {
                case 'receive_amount': $ci=$cash; $metal=null; break;
                case 'return_amount': $co=$cash; $metal=null; break;
                case 'receive_metal': $mi=$fine; break;
                case 'return_metal': $mo=$fine; break;
                case 'convert_to_metal': $co=$cash; $mi=round($cash/$rate,3); break;
                case 'convert_to_rupees': $mo=$fine; $ci=round($fine*$rate,2); break;
            }
            if (($ci+$co+$mi+$mo)<=0 || (str_starts_with($type,'convert_') && min($ci+$co,$mi+$mo)<=0)) $fail('Enter a positive transaction amount or weight.');
            $ledger = CustomerAdvanceLedger::where('company_id',$company->id)->where('customer_id',$data['customer_id']);
            $cashBalance = (clone $ledger)->sum(DB::raw('cash_in - cash_out'));
            $metalBalance = (clone $ledger)->where('metal_type',$metal)->sum(DB::raw('metal_in - metal_out'));
            // Conversions may create a debit balance; physical returns still require funds.
            if (!in_array($type, ['convert_to_metal', 'convert_to_rupees']) && (($co > 0 && $co > round($cashBalance,2)) || ($mo > 0 && $mo > round($metalBalance,3)))) $fail('Insufficient bullion cash or fine-metal balance.');
            $cashOnly = in_array($type, ['receive_amount','return_amount']);
            $remarks = 'Bullion: '.($data['remarks'] ?? '');
            if (in_array($type, ['receive_metal','return_metal','convert_to_rupees'])) $remarks .= ' | Net '.($data['net_weight'] ?? 0).'g; purity '.($data['purity'] ?? 100).'%';
            if (str_starts_with($type,'convert_')) $remarks .= '; rate manually confirmed';
            if ($type === 'receive_metal') $remarks .= '; metal value INR '.number_format($fine*$rate,2,'.','');
            $values = ['company_id'=>$company->id,'customer_id'=>$data['customer_id'],'entry_type'=>$type,
                'cash_in'=>$ci,'cash_out'=>$co,'metal_in'=>$mi,'metal_out'=>$mo,'metal_type'=>$metal,
                'rate'=>str_starts_with($type,'convert_') || $type === 'receive_metal' ? $rate : 0,'payment_mode'=>$cashOnly ? ($data['payment_mode'] ?? null) : null,
                'remarks'=>$remarks,'created_by'=>$request->user()->id];
            $entry = CustomerAdvanceLedger::create($values + ['entry_date'=>$data['entry_date']]);
            CustomerAdvanceVoucher::create($values + ['ledger_id'=>$entry->id,'voucher_date'=>$data['entry_date'],
                'voucher_no'=>\App\Services\VoucherSeries::next($company->id,'RP'),'amount'=>$ci+$co]);
        });
        return redirect()->route('company.bullion.index', ['slug'=>$slug,'customer_id'=>Crypt::encryptString((string)$data['customer_id'])])->with('success','Bullion entry saved in the bullion ledger.');
    }
}
