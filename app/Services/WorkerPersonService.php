<?php

namespace App\Services;

use App\Models\CategoryPerson;
use App\Models\Customer;
use App\Models\JobWorker;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class WorkerPersonService
{
    public const WORKER_CATEGORY = 'Worker';

    public static function categoryPeopleForCompany(int $companyId): Collection
    {
        self::workerCategory($companyId);

        if (!Schema::hasTable('category_people')) {
            return collect();
        }

        return CategoryPerson::where('company_id', $companyId)
            ->orderBy('category_name')
            ->get();
    }

    public static function activeWorkers(int $companyId): Collection
    {
        self::syncExistingWorkersToPersons($companyId);

        if (!Schema::hasTable('job_workers')) {
            return collect();
        }

        return JobWorker::where('company_id', $companyId)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    public static function workerCategory(int $companyId): ?CategoryPerson
    {
        if (!Schema::hasTable('category_people')) {
            return null;
        }

        $category = CategoryPerson::where('company_id', $companyId)
            ->whereRaw('LOWER(TRIM(category_name)) = ?', [strtolower(self::WORKER_CATEGORY)])
            ->first();

        if ($category) {
            return $category;
        }

        return CategoryPerson::create([
            'company_id' => $companyId,
            'category_name' => self::WORKER_CATEGORY,
        ]);
    }

    public static function syncPersonToWorker(Customer $person): void
    {
        if (!Schema::hasTable('job_workers')) {
            return;
        }

        $person->loadMissing('categoryPerson');
        $worker = self::findLinkedWorker($person);
        $isWorker = self::isWorkerCategory(optional($person->categoryPerson)->category_name);

        if (!$isWorker || (int) $person->is_active !== 1) {
            if ($worker) {
                $worker->update(['is_active' => false]);
            }

            return;
        }

        $data = self::workerDataFromPerson($person);

        if ($worker) {
            $worker->update($data);
            return;
        }

        $name = strtolower(trim((string) $person->name));
        $worker = JobWorker::where('company_id', $person->company_id)
            ->whereRaw('LOWER(TRIM(name)) = ?', [$name])
            ->first();

        if ($worker) {
            $worker->update($data);
            return;
        }

        JobWorker::create($data);
    }

    public static function syncExistingWorkersToPersons(int $companyId): void
    {
        if (!Schema::hasTable('job_workers') || !Schema::hasTable('customers') || !Schema::hasTable('category_people')) {
            return;
        }

        $category = self::workerCategory($companyId);

        if (!$category) {
            return;
        }

        JobWorker::where('company_id', $companyId)
            ->orderBy('id')
            ->chunkById(100, function ($workers) use ($category) {
                foreach ($workers as $worker) {
                    $person = self::findPersonForWorker($worker);
                    $data = self::personDataFromWorker($worker, (int) $category->id, $person?->id);

                    if ($person) {
                        $person->update($data);
                    } else {
                        $person = Customer::create($data);
                    }

                    if (self::hasJobWorkerPersonColumn() && (int) $worker->person_id !== (int) $person->id) {
                        $worker->update(['person_id' => $person->id]);
                    }
                }
            }, 'id');
    }

    private static function findLinkedWorker(Customer $person): ?JobWorker
    {
        if (self::hasJobWorkerPersonColumn()) {
            $worker = JobWorker::where('company_id', $person->company_id)
                ->where('person_id', $person->id)
                ->first();

            if ($worker) {
                return $worker;
            }
        }

        return JobWorker::where('company_id', $person->company_id)
            ->whereRaw('LOWER(TRIM(name)) = ?', [strtolower(trim((string) $person->name))])
            ->first();
    }

    private static function findPersonForWorker(JobWorker $worker): ?Customer
    {
        if (self::hasJobWorkerPersonColumn() && $worker->person_id) {
            $person = Customer::where('company_id', $worker->company_id)
                ->where('id', $worker->person_id)
                ->first();

            if ($person) {
                return $person;
            }
        }

        return Customer::where('company_id', $worker->company_id)
            ->whereRaw('LOWER(TRIM(name)) = ?', [strtolower(trim((string) $worker->name))])
            ->first();
    }

    private static function workerDataFromPerson(Customer $person): array
    {
        $linkedWorkerId = self::hasJobWorkerPersonColumn()
            ? JobWorker::where('company_id', $person->company_id)->where('person_id', $person->id)->value('id')
            : null;

        $data = [
            'company_id' => $person->company_id,
            'name' => $person->name,
            'email' => self::usableJobWorkerEmail($person->email, $linkedWorkerId ? (int) $linkedWorkerId : null),
            'mobile_no' => $person->mobile_no,
            'address' => $person->address,
            'city' => $person->city,
            'area' => $person->area,
            'landmark' => $person->landmark,
            'pincode' => $person->pincode,
            'contact_person1_name' => $person->contact_person1_name,
            'contact_person1_phone' => $person->contact_person1_phone,
            'contact_person2_name' => $person->contact_person2_name,
            'contact_person2_phone' => $person->contact_person2_phone,
            'gst_no' => $person->gst_no,
            'pan_no' => $person->pan_no,
            'aadhaar_no' => $person->aadhaar_no,
            'birth_date' => $person->birth_date,
            'anniversary_date' => $person->anniversary_date,
            'reference' => $person->reference,
            'remarks' => $person->remarks,
            'is_active' => true,
        ];

        if (self::hasJobWorkerPersonColumn()) {
            $data['person_id'] = $person->id;
        }

        return $data;
    }

    private static function personDataFromWorker(JobWorker $worker, int $categoryId, ?int $ignoreCustomerId = null): array
    {
        $data = [
            'company_id' => $worker->company_id,
            'name' => $worker->name,
            'email' => self::usablePersonEmail($worker->email, $ignoreCustomerId),
            'mobile_no' => $worker->mobile_no,
            'address' => $worker->address,
            'city' => $worker->city,
            'area' => $worker->area,
            'landmark' => $worker->landmark,
            'pincode' => $worker->pincode,
            'contact_person1_name' => $worker->contact_person1_name,
            'contact_person1_phone' => $worker->contact_person1_phone,
            'contact_person2_name' => $worker->contact_person2_name,
            'contact_person2_phone' => $worker->contact_person2_phone,
            'gst_no' => $worker->gst_no,
            'pan_no' => $worker->pan_no,
            'aadhaar_no' => $worker->aadhaar_no,
            'birth_date' => $worker->birth_date,
            'anniversary_date' => $worker->anniversary_date,
            'reference' => $worker->reference,
            'remarks' => $worker->remarks,
            'is_active' => (bool) $worker->is_active,
        ];

        if (self::hasCustomerCategoryColumn()) {
            $data['category_person_id'] = $categoryId;
        }

        return $data;
    }

    private static function usablePersonEmail(?string $email, ?int $ignoreCustomerId = null): ?string
    {
        $email = strtolower(trim((string) $email));

        if ($email === '') {
            return null;
        }

        $exists = Customer::whereRaw('LOWER(TRIM(email)) = ?', [$email])
            ->when($ignoreCustomerId, fn ($query) => $query->where('id', '!=', $ignoreCustomerId))
            ->exists();

        if (!$exists && Schema::hasTable('users')) {
            $exists = User::whereRaw('LOWER(TRIM(email)) = ?', [$email])->exists();
        }

        return $exists ? null : $email;
    }

    private static function usableJobWorkerEmail(?string $email, ?int $ignoreWorkerId = null): ?string
    {
        $email = strtolower(trim((string) $email));

        if ($email === '') {
            return null;
        }

        $exists = JobWorker::whereRaw('LOWER(TRIM(email)) = ?', [$email])
            ->when($ignoreWorkerId, fn ($query) => $query->where('id', '!=', $ignoreWorkerId))
            ->exists();

        return $exists ? null : $email;
    }

    private static function isWorkerCategory(?string $categoryName): bool
    {
        return strtolower(trim((string) $categoryName)) === strtolower(self::WORKER_CATEGORY);
    }

    private static function hasCustomerCategoryColumn(): bool
    {
        return Schema::hasTable('customers') && Schema::hasColumn('customers', 'category_person_id');
    }

    private static function hasJobWorkerPersonColumn(): bool
    {
        return Schema::hasTable('job_workers') && Schema::hasColumn('job_workers', 'person_id');
    }
}
