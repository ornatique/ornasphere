<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CadDesign extends Model
{
    protected $guarded = ['id'];
    public function line() { return $this->belongsTo(CustomerOrderLine::class, 'customer_order_line_id'); }
    public function designer() { return $this->belongsTo(User::class, 'designer_id'); }
    public function batches() { return $this->hasMany(RptBatch::class); }
}
