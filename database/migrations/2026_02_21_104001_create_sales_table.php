<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('sales', function (Blueprint $table)
        {
            $table->id();

            $table->unsignedBigInteger('company_id');
            $table->unsignedBigInteger('customer_id');

            $table->string('voucher_series');
            $table->string('voucher_no')->unique();

            $table->date('sale_date');

            $table->string('invoice_type')->nullable();

            $table->unsignedBigInteger('approval_person_id')->nullable();
            $table->unsignedBigInteger('employee_id')->nullable();

            $table->decimal('total_amount', 12, 2)->default(0);
            $table->decimal('received_amount', 12, 2)->default(0);
            $table->decimal('paid_amount', 12, 2)->default(0);
            $table->decimal('net_total', 12, 2)->default(0);

            $table->text('remarks')->nullable();

            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('sales');
    }
};
