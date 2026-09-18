<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class JobworkReceiveItem extends Model
{
    protected $fillable = [
        'jobwork_receive_id',
        'jobwork_issue_item_id',
        'item_id',
        'receive_gross_wt',
        'other_wt',
        'other_amt',
        'other_charge_details',
        'purity',
        'waste_percent',
        'net_purity',
        'receive_net_wt',
        'receive_fine_wt',
        'metal_rate',
        'metal_amount',
        'labour_rate',
        'labour_amount',
        'receive_qty_pcs',
        'loss_wt',
        'remarks',
        'total_amount',
    ];

    protected $casts = [
        'jobwork_receive_id' => 'integer',
        'jobwork_issue_item_id' => 'integer',
        'item_id' => 'integer',
        'receive_gross_wt' => 'float',
        'other_wt' => 'float',
        'other_amt' => 'float',
        'purity' => 'float',
        'waste_percent' => 'float',
        'net_purity' => 'float',
        'receive_net_wt' => 'float',
        'receive_fine_wt' => 'float',
        'metal_rate' => 'float',
        'metal_amount' => 'float',
        'labour_rate' => 'float',
        'labour_amount' => 'float',
        'receive_qty_pcs' => 'integer',
        'loss_wt' => 'float',
        'total_amount' => 'float',
    ];

    public function jobworkReceive()
    {
        return $this->belongsTo(JobworkReceive::class);
    }

    public function jobworkIssueItem()
    {
        return $this->belongsTo(JobworkIssueItem::class);
    }

    public function item()
    {
        return $this->belongsTo(Item::class);
    }
}
