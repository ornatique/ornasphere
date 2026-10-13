<?php
namespace App\Services;

use App\Models\Customer;
use App\Models\CustomerAdvanceLedger;
use Illuminate\Http\Request;

class PersonOpeningBalance
{
    public function cashBalance(int $companyId, int $personId, ?string $date = null): float
    {
        if ($companyId <= 0 || $personId <= 0) return 0.0;
        return (float) CustomerAdvanceLedger::where('company_id', $companyId)
            ->where('customer_id', $personId)->where('reference_type', 'person_opening')
            ->where('reference_id', $personId)
            ->when($date, fn ($query) => $query->whereDate('entry_date', '<=', $date))
            ->selectRaw('COALESCE(SUM(cash_in - cash_out), 0) as balance')->value('balance');
    }

    public function validate(Request $request): ?array
    {
        if (!$request->has('opening')) return null;
        $rules = ['opening'=>'required|array', 'opening.date'=>'required|date_format:Y-m-d'];
        foreach (['cash','gold','silver','other'] as $asset) {
            $rules["opening.$asset"] = 'required|numeric|min:0|max:999999999';
            $rules["opening.{$asset}_side"] = 'required|in:dr,cr';
        }
        return $request->validate($rules)['opening'];
    }

    public function read(Customer $person): array
    {
        $result = ['date'=>null];
        foreach (['cash','gold','silver','other'] as $asset) {
            $result[$asset] = 0;
            $result[$asset.'_side'] = 'dr';
        }
        $rows = $this->query($person)->get();
        foreach ($rows as $row) {
            $result['date'] = $row->entry_date->format('Y-m-d');
            $asset = $row->metal_type ?: 'cash';
            $signed = $asset === 'cash' ? (float)$row->cash_out - (float)$row->cash_in : (float)$row->metal_out - (float)$row->metal_in;
            $result[$asset] = abs($signed);
            $result[$asset.'_side'] = $signed < 0 ? 'cr' : 'dr';
        }
        return $result;
    }

    public function save(Customer $person, ?array $opening, ?int $userId): void
    {
        if ($opening === null) return;
        // Called within the person save transaction; lock prevents duplicate openings.
        Customer::where('company_id', $person->company_id)->whereKey($person->id)->lockForUpdate()->firstOrFail();
        $this->query($person)->delete();
        foreach (['cash','gold','silver','other'] as $asset) {
            $value = round((float)$opening[$asset], $asset === 'cash' ? 2 : 3);
            if ($value == 0) continue;
            $credit = $opening[$asset.'_side'] === 'cr';
            CustomerAdvanceLedger::create([
                'company_id'=>$person->company_id, 'customer_id'=>$person->id,
                'entry_date'=>$opening['date'], 'entry_type'=>'opening_balance',
                'reference_type'=>'person_opening', 'reference_id'=>$person->id,
                'cash_in'=>$asset === 'cash' && $credit ? $value : 0,
                'cash_out'=>$asset === 'cash' && !$credit ? $value : 0,
                'metal_type'=>$asset === 'cash' ? null : $asset,
                'metal_in'=>$asset !== 'cash' && $credit ? $value : 0,
                'metal_out'=>$asset !== 'cash' && !$credit ? $value : 0,
                'rate'=>0, 'remarks'=>'Opening balance', 'created_by'=>$userId,
            ]);
        }
    }

    private function query(Customer $person)
    {
        return CustomerAdvanceLedger::where('company_id',$person->company_id)->where('customer_id',$person->id)
            ->where('reference_type','person_opening')->where('reference_id',$person->id);
    }
}
