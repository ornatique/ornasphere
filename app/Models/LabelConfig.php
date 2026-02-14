<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LabelConfig extends Model
{
    protected $fillable = [
        'company_id',
        'item_id',
        'prefix',
        'numeric_length',
        'last_no',
        'reuse',
        'random',
        'min_no',
        'max_no',
        'from_date',
        'to_date'
    ];

    public function item()
    {
        return $this->belongsTo(Item::class);
    }
}
