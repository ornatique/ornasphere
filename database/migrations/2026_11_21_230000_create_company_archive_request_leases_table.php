<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('company_archive_request_leases',function (Blueprint $t) {
            $t->uuid('lease_id')->primary();
            $t->unsignedBigInteger('company_id')->index();
            $t->char('owner',64);
            $t->char('proof',64);
            $t->string('file_identity',96);
            $t->timestamp('created_at');
        });
    }

    public function down(): void
    {
        if (DB::table('company_archive_request_leases')->exists()) throw new RuntimeException('Drain every tracked request before removing request tracking.');
        Schema::dropIfExists('company_archive_request_leases');
    }
};
