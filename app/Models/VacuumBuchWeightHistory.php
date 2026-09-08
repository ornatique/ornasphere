<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VacuumBuchWeightHistory extends Model
{
    protected $fillable = [
        'vacuum_buch_id',
        'company_id',
        'changed_by',
        'old_weight',
        'new_weight',
        'changed_at',
    ];

    protected $casts = [
        'vacuum_buch_id' => 'integer',
        'company_id' => 'integer',
        'changed_by' => 'integer',
        'old_weight' => 'decimal:3',
        'new_weight' => 'decimal:3',
        'changed_at' => 'datetime',
    ];

    public function buch()
    {
        return $this->belongsTo(VacuumBuch::class, 'vacuum_buch_id');
    }

    public function changedByUser()
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
