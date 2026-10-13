<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RptBatch extends Model
{
    protected $guarded = ['id'];
    public function design() { return $this->belongsTo(CadDesign::class, 'cad_design_id'); }
    public function operator() { return $this->belongsTo(User::class, 'operator_id'); }
}
