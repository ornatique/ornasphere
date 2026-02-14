<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Company extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'email',
        'max_users',
        'plan',
        'address_1',
        'address_2',
        'city',
        'state',
        'postcode',
        'country',
        'status'
    ];

    public function users()
    {
        return $this->hasMany(User::class);
    }
}
