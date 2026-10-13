<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CustomerOrderLine extends Model
{
    protected $guarded = ['id'];
    public function order() { return $this->belongsTo(CustomerOrder::class, 'customer_order_id'); }
    public function designs() { return $this->hasMany(CadDesign::class); }
    public function getAcceptedAttribute(): int { return $this->designs->flatMap->batches->where('status', 'ready')->sum('accepted_pieces'); }
    public function getProgressAttribute(): string
    {
        if ($this->accepted >= $this->pieces) return 'ready_for_casting';
        if ($this->designs->flatMap->batches->isNotEmpty()) return 'in_rpt';
        return $this->designs->isNotEmpty() ? 'in_design' : 'confirmed';
    }
}
