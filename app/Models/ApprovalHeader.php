<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ApprovalHeader extends Model
{
    use HasFactory;

    protected $table = 'approval_headers';

    protected $fillable = [
        'company_id',
        'customer_id',
        'approval_no',
        'approval_date',
        'status',
        'employee_id',
        'modified_count',
    ];

    protected $casts = [
        'approval_date' => 'date',
        'modified_count' => 'integer',
    ];

    /*
    |--------------------------------------------------------------------------
    | RELATIONSHIPS
    |--------------------------------------------------------------------------
    */

    // Company
    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    // Customer
    public function customer()
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    // Approval Items
    public function items()
    {
        return $this->hasMany(ApprovalItem::class, 'approval_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'employee_id');
    }
    
    /*
    |--------------------------------------------------------------------------
    | SCOPES
    |--------------------------------------------------------------------------
    */

    public function scopeOpen($query)
    {
        return $query->where('status', 'open');
    }

    public function scopeClosed($query)
    {
        return $query->where('status', 'closed');
    }

    public function scopePartial($query)
    {
        return $query->where('status', 'partial');
    }
    
    /*
    |--------------------------------------------------------------------------
    | ACCESSORS
    |--------------------------------------------------------------------------
    */

    public function getStatusBadgeAttribute()
    {
        if ($this->status == 'open') {
            return '<span class="badge bg-warning">Open</span>';
        } elseif ($this->status == 'partial') {
            return '<span class="badge bg-info">Partial</span>';
        } else {
            return '<span class="badge bg-success">Closed</span>';
        }
    }
}
