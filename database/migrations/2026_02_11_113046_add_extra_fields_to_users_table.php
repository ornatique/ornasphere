<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::table('users', function (Blueprint $table) {

            $table->string('role')->nullable()->after('company_id');
            $table->boolean('is_active')->default(0)->after('email');

            $table->string('person_code')->nullable();
            $table->string('city')->nullable();
            $table->text('address')->nullable();
            $table->string('area')->nullable();
            $table->string('landmark')->nullable();
            $table->string('pincode')->nullable();

            $table->string('mobile_no')->nullable();
            $table->string('phone_no')->nullable();

            $table->string('contact_person1_name')->nullable();
            $table->string('contact_person1_phone')->nullable();
            $table->string('contact_person2_name')->nullable();
            $table->string('contact_person2_phone')->nullable();

            $table->string('gst_no')->nullable();
            $table->string('pan_no')->nullable();
            $table->string('aadhaar_no')->nullable();
            $table->string('hallmark_license_no')->nullable();

            $table->date('birth_date')->nullable();
            $table->date('anniversary_date')->nullable();

            $table->string('reference')->nullable();
            $table->text('remarks')->nullable();
        });
    }


    /**
     * Reverse the migrations.
     */
    public function down()
    {
        Schema::table('users', function (Blueprint $table) {

            $table->dropColumn([
                'role',
                'profile_image',
                'person_code',
                'city',
                'address',
                'area',
                'landmark',
                'pincode',
                'mobile_no',
                'phone_no',
                'contact_person1_name',
                'contact_person1_phone',
                'contact_person2_name',
                'contact_person2_phone',
                'gst_no',
                'pan_no',
                'aadhaar_no',
                'hallmark_license_no',
                'birth_date',
                'anniversary_date',
                'reference',
                'remarks',
                'is_active',
            ]);
        });
    }
};
