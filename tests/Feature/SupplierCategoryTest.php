<?php

namespace Tests\Feature;

use App\Models\CategoryPerson;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SupplierCategoryTest extends TestCase
{
    public function test_supplier_is_backfilled_reused_and_created_for_new_companies(): void
    {
        DB::statement('CREATE TABLE companies (id integer primary key)');
        DB::statement('CREATE TABLE category_people (id integer primary key, company_id integer, category_name text, is_system_default integer, created_at text, updated_at text)');
        DB::table('companies')->insert([['id'=>1], ['id'=>2]]);
        DB::table('category_people')->insert(['id'=>10,'company_id'=>1,'category_name'=>' supplier ','is_system_default'=>0]);
        $migration = require database_path('migrations/2026_10_16_000001_add_default_supplier_category.php');
        $migration->up();
        $migration->up();
        $this->assertEquals(2, CategoryPerson::count());
        $this->assertSame('Supplier', CategoryPerson::findOrFail(10)->category_name);
        $this->assertTrue(CategoryPerson::findOrFail(10)->isSystemDefault());
        CategoryPerson::ensureCompanyDefaults(3);
        CategoryPerson::ensureCompanyDefaults(3);
        $this->assertEquals(3, CategoryPerson::where('company_id',3)->count());
        $this->assertTrue(CategoryPerson::where('company_id',3)->where('category_name','Supplier')->firstOrFail()->isSystemDefault());
        $migration->down();
        $this->assertNotNull(CategoryPerson::find(10));
    }
}
