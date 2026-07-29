<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CategoryPerson extends Model
{
    protected $fillable = [
        'company_id',
        'category_name',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }
}
