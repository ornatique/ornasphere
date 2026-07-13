<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CastingReleaseItem extends Model
{
    protected $fillable = [
        'company_id',
        'vacuum_voucher_id',
        'vacuum_voucher_item_id',
        'release_tree_wt',
        'release_tree_bhuko',
        'loss',
        'released_by',
        'released_at',
    ];

    protected $casts = [
        'company_id' => 'integer',
        'vacuum_voucher_id' => 'integer',
        'vacuum_voucher_item_id' => 'integer',
        'release_tree_wt' => 'decimal:3',
        'release_tree_bhuko' => 'decimal:3',
        'loss' => 'decimal:3',
        'released_by' => 'integer',
        'released_at' => 'datetime',
    ];

    public function voucher()
    {
        return $this->belongsTo(VacuumVoucher::class, 'vacuum_voucher_id');
    }

    public function voucherItem()
    {
        return $this->belongsTo(VacuumVoucherItem::class, 'vacuum_voucher_item_id');
    }

    public function releasedByUser()
    {
        return $this->belongsTo(User::class, 'released_by');
    }
}
