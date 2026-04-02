<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ApprovalItem extends Model
{
    use HasFactory;

    protected $table = 'approval_items';

    protected $fillable = [
        'approval_id',
        'itemset_id',
        'item_id',
        'huid',
        'qr_code',
        'gross_weight',
        'other_weight',
        'net_weight',
        'purity',
        'waste_percent',
        'net_purity',
        'total_fine_weight',
        'metal_rate',
        'metal_amount',
        'labour_rate',
        'labour_amount',
        'other_amount',
        'total_amount',
        'status'
    ];

    protected $casts = [
        'gross_weight' => 'decimal:3',
        'other_weight' => 'decimal:3',
        'net_weight'   => 'decimal:3',
        'purity' => 'decimal:3',
        'waste_percent' => 'decimal:3',
        'net_purity' => 'decimal:3',
        'total_fine_weight' => 'decimal:3',
        'metal_rate' => 'decimal:2',
        'metal_amount' => 'decimal:2',
        'labour_rate' => 'decimal:2',
        'labour_amount' => 'decimal:2',
        'other_amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
    ];

    /*
    |--------------------------------------------------------------------------
    | RELATIONSHIPS
    |--------------------------------------------------------------------------
    */

    // Parent Approval
    public function approval()
    {
        return $this->belongsTo(ApprovalHeader::class, 'approval_id');
    }

    // Item master
    public function item()
    {
        return $this->belongsTo(Item::class, 'item_id');
    }  
    public function itemSet()
    {
        return $this->belongsTo(ItemSet::class, 'itemset_id');
    }

    // Backward compatibility for old wrongly saved rows.
    public function legacyItemSet()
    {
        return $this->belongsTo(ItemSet::class, 'item_id');
    }

    
    /*
    |--------------------------------------------------------------------------
    | SCOPES
    |--------------------------------------------------------------------------
    */

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeSold($query)
    {
        return $query->where('status', 'sold');
    }

    public function scopeReturned($query)
    {
        return $query->where('status', 'returned');
    }

    /*
    |--------------------------------------------------------------------------
    | ACCESSORS
    |--------------------------------------------------------------------------
    */

    public function getStatusBadgeAttribute()
    {
        if ($this->status == 'pending') {
            return '<span class="badge bg-warning">Pending</span>';
        } elseif ($this->status == 'sold') {
            return '<span class="badge bg-success">Sold</span>';
        } else {
            return '<span class="badge bg-danger">Returned</span>';
        }
    }
}
