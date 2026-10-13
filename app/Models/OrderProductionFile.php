<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderProductionFile extends Model
{
    protected $guarded = ['id'];
    public function order() { return $this->belongsTo(CustomerOrder::class, 'customer_order_id'); }
}
