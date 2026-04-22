<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class JobworkIssueItem extends Model
{
    protected $fillable = [
        'jobwork_issue_id',
        'item_id',
        'other_charge_id',
        'gross_wt',
        'other_wt',
        'other_amt',
        'purity',
        'net_purity',
        'net_wt',
        'fine_wt',
        'qty_pcs',
        'remarks',
        'total_amt',
    ];

    protected $casts = [
        'jobwork_issue_id' => 'integer',
        'item_id' => 'integer',
        'other_charge_id' => 'integer',
        'gross_wt' => 'float',
        'other_wt' => 'float',
        'other_amt' => 'float',
        'purity' => 'float',
        'net_purity' => 'float',
        'net_wt' => 'float',
        'fine_wt' => 'float',
        'qty_pcs' => 'integer',
        'total_amt' => 'float',
    ];

    public function jobworkIssue()
    {
        return $this->belongsTo(JobworkIssue::class);
    }

    public function item()
    {
        return $this->belongsTo(Item::class);
    }

    public function otherCharge()
    {
        return $this->belongsTo(OtherCharge::class);
    }
}
