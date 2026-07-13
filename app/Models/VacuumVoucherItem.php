<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VacuumVoucherItem extends Model
{
    protected $fillable = [
        'vacuum_voucher_id',
        'vacuum_buch_id',
        'buch_no',
        'gross_wt',
        'buch_wt',
        'net_wt',
        'silver_wt',
    ];

    protected $casts = [
        'vacuum_voucher_id' => 'integer',
        'vacuum_buch_id' => 'integer',
        'gross_wt' => 'decimal:3',
        'buch_wt' => 'decimal:3',
        'net_wt' => 'decimal:3',
        'silver_wt' => 'decimal:3',
    ];

    public function voucher()
    {
        return $this->belongsTo(VacuumVoucher::class, 'vacuum_voucher_id');
    }

    public function buch()
    {
        return $this->belongsTo(VacuumBuch::class, 'vacuum_buch_id');
    }
}
