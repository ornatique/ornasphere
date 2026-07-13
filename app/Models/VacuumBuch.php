<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VacuumBuch extends Model
{
    protected $table = 'vacuum_buchs';

    protected $fillable = [
        'company_id',
        'created_by',
        'updated_by',
        'modified_count',
        'buch_no',
        'size_inch',
        'weight',
    ];

    protected $casts = [
        'company_id' => 'integer',
        'created_by' => 'integer',
        'updated_by' => 'integer',
        'modified_count' => 'integer',
        'size_inch' => 'decimal:2',
        'weight' => 'decimal:3',
    ];

    public function createdByUser()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedByUser()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
