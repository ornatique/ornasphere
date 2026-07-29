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
        'receive_net_wt',
        'receive_fine_wt',
        'receive_qty_pcs',
        'loss_wt',
        'remarks',
    ];

    protected $casts = [
        'jobwork_receive_id' => 'integer',
        'jobwork_issue_item_id' => 'integer',
        'item_id' => 'integer',
        'receive_gross_wt' => 'float',
        'other_wt' => 'float',
        'other_amt' => 'float',
        'receive_net_wt' => 'float',
        'receive_fine_wt' => 'float',
        'receive_qty_pcs' => 'integer',
        'loss_wt' => 'float',
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
