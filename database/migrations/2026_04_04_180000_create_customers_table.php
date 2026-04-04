<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->index();
            $table->unsignedBigInteger('legacy_user_id')->nullable()->unique();
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('mobile_no')->nullable();
            $table->text('address')->nullable();
            $table->string('city')->nullable();
            $table->string('area')->nullable();
            $table->string('landmark')->nullable();
            $table->string('pincode')->nullable();
            $table->string('contact_person1_name')->nullable();
            $table->string('contact_person1_phone')->nullable();
            $table->string('contact_person2_name')->nullable();
            $table->string('contact_person2_phone')->nullable();
            $table->string('gst_no')->nullable();
            $table->string('pan_no')->nullable();
            $table->string('aadhaar_no')->nullable();
            $table->date('birth_date')->nullable();
            $table->date('anniversary_date')->nullable();
            $table->string('reference')->nullable();
            $table->text('remarks')->nullable();
            $table->boolean('is_active')->default(1);
            $table->timestamps();
        });

        if (Schema::hasTable('users')) {
            $customerUsers = DB::table('users')
                ->whereRaw('LOWER(role) = ?', ['customer'])
                ->select([
                    'id',
                    'company_id',
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
                    'created_at',
                    'updated_at',
                ])
                ->get();

            foreach ($customerUsers as $u) {
                DB::table('customers')->insert([
                    // Keep same id so old sales/approvals customer_id mapping continues.
                    'id' => $u->id,
                    'company_id' => $u->company_id,
                    'legacy_user_id' => $u->id,
                    'name' => $u->name,
                    'email' => $u->email,
                    'mobile_no' => $u->mobile_no,
                    'address' => $u->address,
                    'city' => $u->city,
                    'area' => $u->area,
                    'landmark' => $u->landmark,
                    'pincode' => $u->pincode,
                    'contact_person1_name' => $u->contact_person1_name,
                    'contact_person1_phone' => $u->contact_person1_phone,
                    'contact_person2_name' => $u->contact_person2_name,
                    'contact_person2_phone' => $u->contact_person2_phone,
                    'gst_no' => $u->gst_no,
                    'pan_no' => $u->pan_no,
                    'aadhaar_no' => $u->aadhaar_no,
                    'birth_date' => $u->birth_date,
                    'anniversary_date' => $u->anniversary_date,
                    'reference' => $u->reference,
                    'remarks' => $u->remarks,
                    'is_active' => (int) ($u->is_active ?? 1),
                    'created_at' => $u->created_at ?? now(),
                    'updated_at' => $u->updated_at ?? now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};

