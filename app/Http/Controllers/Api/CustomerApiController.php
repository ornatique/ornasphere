<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ApprovalHeader;
use App\Models\Customer;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CustomerApiController extends Controller
{
    public function index(Request $request)
    {
        $companyId = $request->user()->company_id;

        $customers = Customer::with('categoryPerson:id,category_name')
            ->where('company_id', $companyId)
            ->when($request->filled('search'), function ($q) use ($request) {
                $search = trim((string) $request->search);
                $q->where(function ($q2) use ($search) {
                    $q2->where('name', 'like', "%{$search}%")
                        ->orWhere('mobile_no', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('city', 'like', "%{$search}%");
                });
            })
            ->when($request->filled('is_active'), function ($q) use ($request) {
                $q->where('is_active', (int) $request->is_active);
            })
            ->latest()
            ->get();

        return response()->json([
            'success' => true,
            'data' => $customers,
        ]);
    }

    public function show(Request $request, $id)
    {
        $companyId = $request->user()->company_id;

        $customer = Customer::with('categoryPerson:id,category_name')
            ->where('company_id', $companyId)
            ->where('id', $id)
            ->first();

        if (!$customer) {
            return response()->json([
                'success' => false,
                'message' => 'Person not found.'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $customer,
        ]);
    }

    public function store(Request $request)
    {
        $companyId = $request->user()->company_id;

        $this->normalizePayload($request);
        $validated = $this->validatePayload($request, $companyId);
        $this->ensureEmailIsUnique($validated['email'] ?? null, (int) $companyId);

        $customer = Customer::create(array_merge($validated, [
            'company_id' => $companyId,
            'is_active' => $request->boolean('is_active', true) ? 1 : 0,
        ]));

        return response()->json([
            'success' => true,
            'message' => 'Person created successfully.',
            'data' => $customer->load('categoryPerson:id,category_name'),
        ], 200);
    }

    public function update(Request $request, $id)
    {
        $companyId = $request->user()->company_id;

        $customer = Customer::where('company_id', $companyId)
            ->where('id', $id)
            ->first();

        if (!$customer) {
            return response()->json([
                'success' => false,
                'message' => 'Person not found.'
            ], 404);
        }

        $this->normalizePayload($request);
        $validated = $this->validatePayload($request, $companyId, $customer->id);
        $this->ensureEmailIsUnique(
            $validated['email'] ?? null,
            (int) $companyId,
            (int) $customer->id,
            $customer->legacy_user_id ? (int) $customer->legacy_user_id : null
        );

        $customer->update(array_merge($validated, [
            'is_active' => $request->boolean('is_active', (bool) $customer->is_active) ? 1 : 0,
        ]));

        return response()->json([
            'success' => true,
            'message' => 'Person updated successfully.',
            'data' => $customer->load('categoryPerson:id,category_name'),
        ]);
    }

    public function destroy(Request $request, $id)
    {
        $companyId = $request->user()->company_id;
        $customer = Customer::where('company_id', $companyId)
            ->where('id', $id)
            ->first();

        if (!$customer) {
            return response()->json([
                'success' => false,
                'message' => 'Person not found.'
            ], 404);
        }

        if ((int) $customer->is_active === 0) {
            $message = 'Person is already inactive.';
        } else {
            $customer->update(['is_active' => 0]);
            $message = $this->isCustomerUsed((int) $companyId, (int) $customer->id)
                ? 'Person is used in transactions, so deleted not allowed. Person set to inactive.'
                : 'Person set to inactive successfully.';
        }

        return response()->json([
            'success' => true,
            'message' => $message,
        ]);
    }

    private function validatePayload(Request $request, int $companyId, ?int $customerId = null): array
    {
        $nameRule = $customerId ? 'sometimes' : 'required';

        return $request->validate([
            'name' => [$nameRule, 'string', 'max:255'],
            'category_person_id' => [
                'nullable',
                Rule::exists('category_people', 'id')->where(fn ($q) => $q->where('company_id', $companyId)),
            ],
            'email' => [
                'nullable',
                'email',
                'max:255',
                Rule::unique('customers', 'email')
                    ->where(fn($q) => $q->where('company_id', $companyId))
                    ->ignore($customerId),
            ],
            'mobile_no' => 'nullable|string|max:20',
            'address' => 'nullable|string',
            'city' => 'nullable|string|max:191',
            'area' => 'nullable|string|max:191',
            'landmark' => 'nullable|string|max:191',
            'pincode' => 'nullable|string|max:20',
            'contact_person1_name' => 'nullable|string|max:191',
            'contact_person1_phone' => 'nullable|string|max:20',
            'contact_person2_name' => 'nullable|string|max:191',
            'contact_person2_phone' => 'nullable|string|max:20',
            'gst_no' => 'nullable|string|max:191',
            'pan_no' => 'nullable|string|max:191',
            'aadhaar_no' => 'nullable|string|max:191',
            'birth_date' => 'nullable|date',
            'anniversary_date' => 'nullable|date',
            'reference' => 'nullable|string|max:191',
            'remarks' => 'nullable|string',
        ], [
            'email.email' => 'Please enter a valid email id.',
            'email.unique' => 'This email id is already used for another person.',
            'category_person_id.exists' => 'Please select a valid category person.',
        ]);
    }

    private function normalizePayload(Request $request): void
    {
        foreach ([
            'name',
            'email',
            'mobile_no',
            'address',
            'city',
            'area',
            'landmark',
            'pincode',
            'contact_person1_name',
            'contact_person1_phone',
            'contact_person2_name',
            'contact_person2_phone',
            'gst_no',
            'pan_no',
            'aadhaar_no',
            'reference',
            'remarks',
        ] as $field) {
            if ($request->has($field) && is_string($request->input($field))) {
                $request->merge([$field => trim($request->input($field))]);
            }
        }

        if ($request->filled('email')) {
            $request->merge(['email' => strtolower((string) $request->input('email'))]);
        }
    }

    private function ensureEmailIsUnique(?string $email, int $companyId, ?int $ignoreCustomerId = null, ?int $ignoreUserId = null): void
    {
        $email = strtolower(trim((string) $email));

        if ($email === '') {
            return;
        }

        $exists = Customer::query()
            ->where('company_id', $companyId)
            ->whereRaw('TRIM(LOWER(email)) = ?', [$email])
            ->when($ignoreCustomerId, fn ($query) => $query->where('id', '!=', $ignoreCustomerId))
            ->exists();

        if (!$exists) {
            $exists = User::query()
                ->where('company_id', $companyId)
                ->whereRaw('TRIM(LOWER(email)) = ?', [$email])
                ->when($ignoreUserId, fn ($query) => $query->where('id', '!=', $ignoreUserId))
                ->exists();
        }

        if ($exists) {
            throw ValidationException::withMessages([
                'email' => ['This email id is already used for another person.'],
            ]);
        }
    }

    private function isCustomerUsed(int $companyId, int $customerId): bool
    {
        return Sale::where('company_id', $companyId)->where('customer_id', $customerId)->exists()
            || ApprovalHeader::where('company_id', $companyId)->where('customer_id', $customerId)->exists();
    }
}
