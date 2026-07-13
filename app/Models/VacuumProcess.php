<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VacuumProcess extends Model
{
    protected $fillable = [
        'company_id',
        'created_by',
        'updated_by',
        'modified_count',
        'name',
    ];

    protected $casts = [
        'company_id' => 'integer',
        'created_by' => 'integer',
        'updated_by' => 'integer',
        'modified_count' => 'integer',
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
