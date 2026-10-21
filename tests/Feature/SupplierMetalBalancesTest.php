<?php
namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Support\Facades\DB;
use App\Services\SupplierMetalBalances;

class SupplierMetalBalancesTest extends TestCase
{
    public function test_balances_are_supplier_and_company_scoped_and_preserve_negative_amounts(): void
    {
        DB::statement('CREATE TABLE companies (id integer primary key, name text)');
        DB::statement('CREATE TABLE category_people (id integer primary key, company_id integer, category_name text)');
        DB::statement('CREATE TABLE customers (id integer primary key, company_id integer, category_person_id integer, name text)');
        DB::statement('CREATE TABLE customer_advance_ledgers (company_id integer, customer_id integer, cash_in real, cash_out real, metal_type text, metal_in real, metal_out real)');
        foreach ([1,2] as $id) {
            DB::table('companies')->insert(['id'=>$id,'name'=>'Company '.$id]);
            DB::table('category_people')->insert(['id'=>$id,'company_id'=>$id,'category_name'=>'Supplier']);
            DB::table('customers')->insert(['id'=>$id,'company_id'=>$id,'category_person_id'=>$id,'name'=>'Supplier '.$id]);
            DB::table('customer_advance_ledgers')->insert(['company_id'=>$id,'customer_id'=>$id,'cash_in'=>0,'cash_out'=>100000,'metal_type'=>'silver','metal_in'=>455.726,'metal_out'=>0]);
        }
        DB::table('category_people')->insert(['id'=>3,'company_id'=>1,'category_name'=>'Customer']);
        DB::table('customers')->insert(['id'=>3,'company_id'=>1,'category_person_id'=>3,'name'=>'Customer']);
        DB::table('customer_advance_ledgers')->insert(['company_id'=>1,'customer_id'=>3,'metal_type'=>'silver','metal_in'=>999]);
        $rows = SupplierMetalBalances::rows([1]);
        $this->assertCount(1, $rows);
        $this->assertEquals(-100000, $rows->first()->cash_balance);
        $this->assertEquals(455.726, $rows->first()->silver_balance);
        $this->assertCount(2, SupplierMetalBalances::rows([1,2]));
        $this->assertCount(0, SupplierMetalBalances::rows([1], 2));
        $this->assertCount(0, SupplierMetalBalances::rows([1], 3));
    }
}
