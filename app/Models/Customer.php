<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Customer extends Model
{
    protected $fillable = [
        'company_id',
        'category_person_id',
        'legacy_user_id',
        'name',
        'email',
        'mobile_no',
        'address',
        'city',
        'area',
        'landmark',
        'pincode',
        'contact_person1_name',
        'contact_person1_phone',
        'contact_person2_name',
        'contact_person2_phone',
        'gst_no',
        'pan_no',
        'aadhaar_no',
        'birth_date',
        'anniversary_date',
        'reference',
        'remarks',
        'is_active',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function categoryPerson()
    {
        return $this->belongsTo(CategoryPerson::class, 'category_person_id');
    }

    public function jobWorker()
    {
        return $this->hasOne(JobWorker::class, 'person_id');
    }

    public function advanceLedgers()
    {
        return $this->hasMany(CustomerAdvanceLedger::class);
    }
}
