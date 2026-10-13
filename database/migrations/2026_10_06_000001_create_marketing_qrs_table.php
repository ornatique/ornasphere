<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('marketing_qrs', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('company_id')->index(); $t->unsignedBigInteger('created_by')->nullable();
            $t->uuid('token')->unique(); $t->string('title'); $t->string('type', 10); $t->unsignedSmallInteger('size')->default(250);
            $t->text('web_url')->nullable(); $t->text('android_url')->nullable(); $t->text('ios_url')->nullable();
            $t->string('logo_path')->nullable(); $t->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('marketing_qrs'); }
};
