<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CastingReleaseItem extends Model
{
    public function scopeForVoucherHistory($query, int $companyId, int $voucherId, array $itemIds)
    {
        return $query->where('company_id', $companyId)->where('vacuum_voucher_id', $voucherId)
            ->when($itemIds !== [], fn ($q) => $q->where(fn ($rows) =>
                $rows->where('is_custom', true)->orWhereIn('vacuum_voucher_item_id', $itemIds)
            ));
    }

    protected $fillable = [
        'company_id',
        'vacuum_voucher_id',
        'vacuum_voucher_item_id',
        'custom_buch_no',
        'is_custom',
        'custom_type',
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
        'is_custom' => 'boolean',
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
