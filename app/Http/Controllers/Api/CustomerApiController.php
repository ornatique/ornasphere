<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CustomerApiController extends Controller
{
    public function index(Request $request)
    {
        $companyId = $request->user()->company_id;

        $customers = Customer::where('company_id', $companyId)
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

        $customer = Customer::where('company_id', $companyId)
            ->where('id', $id)
            ->first();

        if (!$customer) {
            return response()->json([
                'success' => false,
                'message' => 'Customer not found.'
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

        $validated = $this->validatePayload($request, $companyId);

        $customer = Customer::create(array_merge($validated, [
            'company_id' => $companyId,
            'is_active' => $request->boolean('is_active', true) ? 1 : 0,
        ]));

        return response()->json([
            'success' => true,
            'message' => 'Customer created successfully.',
            'data' => $customer,
        ], 201);
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
                'message' => 'Customer not found.'
            ], 404);
        }

        $validated = $this->validatePayload($request, $companyId, $customer->id);

        $customer->update(array_merge($validated, [
            'is_active' => $request->boolean('is_active', (bool) $customer->is_active) ? 1 : 0,
        ]));

        return response()->json([
            'success' => true,
            'message' => 'Customer updated successfully.',
            'data' => $customer,
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
                'message' => 'Customer not found.'
            ], 404);
        }

        $customer->delete();

        return response()->json([
            'success' => true,
            'message' => 'Customer deleted successfully.',
        ]);
    }

    private function validatePayload(Request $request, int $companyId, ?int $customerId = null): array
    {
        return $request->validate([
            'name' => 'required|string|max:255',
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
        ]);
    }
}

