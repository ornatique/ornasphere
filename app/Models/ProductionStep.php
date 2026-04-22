<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductionStep extends Model
{
    protected $fillable = [
        'company_id',
        'created_by',
        'updated_by',
        'modified_count',
        'name',
        'labour_formula_id',
        'receivable_loss',
        'auto_create_cost',
        'production_cost_id',
        'remarks',
        'status',
    ];

    protected $casts = [
        'created_by' => 'integer',
        'updated_by' => 'integer',
        'modified_count' => 'integer',
        'labour_formula_id' => 'integer',
        'receivable_loss' => 'boolean',
        'auto_create_cost' => 'boolean',
        'production_cost_id' => 'integer',
        'status' => 'boolean',
    ];

    public function labourFormula()
    {
        return $this->belongsTo(LabourFormula::class);
    }

    public function productionCost()
    {
        return $this->belongsTo(ProductionCost::class);
    }

    public function createdByUser()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedByUser()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function users()
    {
        return $this->belongsToMany(User::class, 'production_step_user')
            ->withTimestamps();
    }
}
