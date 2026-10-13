<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CustomerOrder extends Model
{
    protected $guarded = ['id'];
    public function customer() { return $this->belongsTo(Customer::class); }
    public function lines() { return $this->hasMany(CustomerOrderLine::class); }
    public function vouchers() { return $this->belongsToMany(CustomerAdvanceVoucher::class, 'customer_order_vouchers', 'customer_order_id', 'voucher_id')->withTimestamps(); }
    public function getProgressAttribute(): string
    {
        if (in_array($this->status, ['draft', 'on_hold', 'cancelled'])) return $this->status;
        $stages = $this->lines->map(fn ($line) => $line->progress);
        if ($stages->isNotEmpty() && $stages->every(fn ($stage) => $stage === 'ready_for_casting')) return 'ready_for_casting';
        if ($stages->contains('in_rpt') || $stages->contains('ready_for_casting')) return 'in_rpt';
        return $stages->contains('in_design') ? 'in_design' : 'confirmed';
    }
}
