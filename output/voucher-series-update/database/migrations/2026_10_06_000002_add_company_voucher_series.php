<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('companies', fn(Blueprint $t) => $t->string('voucher_prefix', 20)->nullable());
        Schema::create('company_voucher_counters', function(Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('company_id'); $t->string('module',10);
            $t->unsignedBigInteger('last_number')->default(0);
            $t->unique(['company_id','module']);
        });
        foreach (['sales'=>'voucher_no','approval_headers'=>'approval_no','customer_advance_vouchers'=>'voucher_no'] as $table=>$column) {
            // Older schemas enforce global uniqueness; company prefixes may legitimately match.
            foreach (Schema::getIndexes($table) as $index) {
                if ($index['unique'] && $index['columns'] === [$column]) {
                    Schema::table($table, fn(Blueprint $t) => $t->dropUnique($index['name']));
                }
            }
            $exists=collect(Schema::getIndexes($table))->contains(fn($i)=>$i['unique'] && $i['columns']===['company_id',$column]);
            if (!$exists) Schema::table($table, fn(Blueprint $t)=>$t->unique(['company_id',$column]));
        }
    }

    public function down(): void
    {
        // Keep company-scoped indexes: different companies may now have identical numbers.
        Schema::dropIfExists('company_voucher_counters');
        Schema::table('companies', fn(Blueprint $t)=>$t->dropColumn('voucher_prefix'));
    }
};
