<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ManagedOrder extends Model
{
    protected $guarded = ['id'];
    protected $casts = ['order_date' => 'date'];
    public function customer() { return $this->belongsTo(Customer::class); }
    public function items() { return $this->hasMany(ManagedOrderItem::class); }
    public function getNumberAttribute(): string { return 'OM-'.str_pad((string) $this->id, 6, '0', STR_PAD_LEFT); }
}
