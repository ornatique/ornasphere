<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureCompanyIsActive;
use App\Models\CategoryPerson;
use App\Models\Company;
use App\Models\JobWorker;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ApiCustomerWorkerSyncTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->createTables();
        (require database_path('migrations/2026_05_30_120000_create_customer_advance_ledgers_table.php'))->up();
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('customer_advance_ledgers');
        Schema::dropIfExists('job_workers');
        Schema::dropIfExists('customers');
        Schema::dropIfExists('category_people');
        Schema::dropIfExists('users');
        Schema::dropIfExists('companies');

        parent::tearDown();
    }

    public function test_api_created_worker_person_is_synced_to_job_workers(): void
    {
        $company = Company::create([
            'name' => 'Test Company',
            'slug' => 'test-company',
            'email' => 'company@example.com',
        ]);

        $user = User::factory()->create([
            'company_id' => $company->id,
            'is_active' => 1,
        ]);

        $workerCategory = CategoryPerson::create([
            'company_id' => $company->id,
            'category_name' => CategoryPerson::DEFAULT_WORKER,
            'is_system_default' => true,
        ]);

        Sanctum::actingAs($user);

        $this->withoutMiddleware(EnsureCompanyIsActive::class)
            ->postJson('/api/customers', [
                'name' => 'API Worker',
                'category_person_id' => $workerCategory->id,
                'email' => 'api-worker@example.com',
                'mobile_no' => '9876543210',
                'city' => 'Ahmedabad',
                'is_active' => 1,
                'opening' => ['date'=>'2026-10-01', 'cash'=>50000, 'cash_side'=>'cr', 'silver'=>500, 'silver_side'=>'dr', 'gold'=>0, 'gold_side'=>'dr', 'other'=>0, 'other_side'=>'dr'],
            ])
            ->assertOk()
            ->assertJsonPath('data.opening.cash', 50000)
            ->assertJsonPath('data.opening.silver', 500)
            ->assertJsonPath('success', true);

        $worker = JobWorker::where('company_id', $company->id)
            ->where('name', 'API Worker')
            ->first();

        $this->assertNotNull($worker);
        $this->assertSame('api-worker@example.com', $worker->email);
        $this->assertSame('9876543210', $worker->mobile_no);
        $this->assertTrue((bool) $worker->is_active);
    }

    private function createTables(): void
    {
        foreach (['job_workers', 'customers', 'category_people', 'users', 'companies'] as $table) {
            Schema::dropIfExists($table);
        }

        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug');
            $table->string('email')->nullable();
            $table->timestamps();
        });

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->nullable();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password')->nullable();
            $table->rememberToken();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('category_people', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id');
            $table->string('category_name');
            $table->boolean('is_system_default')->default(false);
            $table->timestamps();
        });

        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id');
            $table->unsignedBigInteger('category_person_id')->nullable();
            $table->unsignedBigInteger('legacy_user_id')->nullable();
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('mobile_no', 20)->nullable();
            $table->text('address')->nullable();
            $table->string('city', 191)->nullable();
            $table->string('area', 191)->nullable();
            $table->string('landmark', 191)->nullable();
            $table->string('pincode', 20)->nullable();
            $table->string('contact_person1_name', 191)->nullable();
            $table->string('contact_person1_phone', 20)->nullable();
            $table->string('contact_person2_name', 191)->nullable();
            $table->string('contact_person2_phone', 20)->nullable();
            $table->string('gst_no', 191)->nullable();
            $table->string('pan_no', 191)->nullable();
            $table->string('aadhaar_no', 191)->nullable();
            $table->date('birth_date')->nullable();
            $table->date('anniversary_date')->nullable();
            $table->string('reference', 191)->nullable();
            $table->text('remarks')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('job_workers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id');
            $table->unsignedBigInteger('person_id')->nullable();
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('mobile_no', 20)->nullable();
            $table->text('address')->nullable();
            $table->string('city', 191)->nullable();
            $table->string('area', 191)->nullable();
            $table->string('landmark', 191)->nullable();
            $table->string('pincode', 20)->nullable();
            $table->string('contact_person1_name', 191)->nullable();
            $table->string('contact_person1_phone', 20)->nullable();
            $table->string('contact_person2_name', 191)->nullable();
            $table->string('contact_person2_phone', 20)->nullable();
            $table->string('gst_no', 191)->nullable();
            $table->string('pan_no', 191)->nullable();
            $table->string('aadhaar_no', 191)->nullable();
            $table->date('birth_date')->nullable();
            $table->date('anniversary_date')->nullable();
            $table->string('reference', 191)->nullable();
            $table->text('remarks')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }
}
