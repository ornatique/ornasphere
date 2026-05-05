<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('visiting_cards', function (Blueprint $table) {
            $table->json('mobile_numbers')->nullable()->after('mobile_no');
        });
    }

    public function down(): void
    {
        Schema::table('visiting_cards', function (Blueprint $table) {
            $table->dropColumn('mobile_numbers');
        });
    }
};
