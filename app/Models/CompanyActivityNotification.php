<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CompanyActivityNotification extends Model
{
    protected $fillable = [
        'company_id',
        'actor_user_id',
        'module',
        'action',
        'title',
        'message',
        'route_name',
        'route_params',
        'subject_type',
        'subject_id',
        'read_at',
    ];

    protected $casts = [
        'route_params' => 'array',
        'read_at' => 'datetime',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function actor()
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }
}
