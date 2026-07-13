<?php

namespace App\Http\Controllers\Company;

use App\Http\Controllers\Controller;
use App\Models\ApprovalHeader;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Sale;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Database\QueryException;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;
use Yajra\DataTables\Facades\DataTables;

class CustomerController extends Controller
{
    public function index(Request $request, $slug)
    {
        $company = Company::whereSlug($slug)->firstOrFail();

        if ($request->ajax()) {
            $customers = Customer::where('company_id', $company->id)
                ->select('customers.*');

            return DataTables::of($customers)
                ->addIndexColumn()
                ->addColumn('status', function ($customer) {
                    return (int) $customer->is_active === 1
                        ? '<span class="badge bg-success">Active</span>'
                        : '<span class="badge bg-danger">Inactive</span>';
                })
                ->addColumn('action', function ($customer) use ($company) {
                    $encryptedId = Crypt::encryptString($customer->id);
                    $editUrl = route('company.customers.edit', [$company->slug, $encryptedId]);
                    $deleteUrl = route('company.customers.delete', [$company->slug, $encryptedId]);

                    return '<a href="' . $editUrl . '" class="btn btn-sm btn-primary">Edit</a>
                            <button type="button" class="btn btn-sm btn-danger deleteBtn" data-url="' . e($deleteUrl) . '">Delete</button>';
                })
                ->rawColumns(['status', 'action'])
                ->make(true);
        }

        return view('company.customers.index', compact('company'));
    }

    public function create($slug)
    {
        $company = Company::whereSlug($slug)->firstOrFail();
        return view('company.customers.create', compact('company'));
    }

    public function store(Request $request, $slug)
    {
        $company = Company::whereSlug($slug)->firstOrFail();

        $this->normalizeCustomerRequest($request);
        $validated = $this->validateCustomer($request, $company->id);
        $this->ensureEmailIsUnique($validated['email'] ?? null);

        try {
            Customer::create(array_merge($validated, [
                'company_id' => $company->id,
                'is_active' => $request->boolean('is_active', true),
            ]));
        } catch (Throwable $e) {
            $this->throwDuplicateEmailValidation($e);
            throw $e;
        }

        return redirect()
            ->route('company.customers.index', $company->slug)
            ->with('success', 'Customer created successfully');
    }

    public function edit($slug, $encryptedId)
    {
        $company = Company::whereSlug($slug)->firstOrFail();
        $customerId = Crypt::decryptString($encryptedId);

        $customer = Customer::where('company_id', $company->id)
            ->where('id', $customerId)
            ->firstOrFail();

        return view('company.customers.edit', compact('company', 'customer'));
    }

    public function update(Request $request, $slug, $encryptedId)
    {
        $company = Company::whereSlug($slug)->firstOrFail();
        $customerId = Crypt::decryptString($encryptedId);

        $customer = Customer::where('company_id', $company->id)
            ->where('id', $customerId)
            ->firstOrFail();

        $this->normalizeCustomerRequest($request);
        $validated = $this->validateCustomer($request, $company->id, $customer->id);
        $this->ensureEmailIsUnique($validated['email'] ?? null, (int) $customer->id, $customer->legacy_user_id ? (int) $customer->legacy_user_id : null);

        try {
            $customer->update(array_merge($validated, [
                'is_active' => $request->boolean('is_active', false),
            ]));
        } catch (Throwable $e) {
            $this->throwDuplicateEmailValidation($e);
            throw $e;
        }

        return redirect()
            ->route('company.customers.index', $company->slug)
            ->with('success', 'Customer updated successfully');
    }

    public function destroy(Request $request, $slug, $encryptedId)
    {
        $company = Company::whereSlug($slug)->firstOrFail();
        $customerId = Crypt::decryptString($encryptedId);

        $customer = Customer::where('company_id', $company->id)
            ->where('id', $customerId)
            ->firstOrFail();

        if ((int) $customer->is_active === 0) {
            $message = 'Customer is already inactive.';
        } else {
            $customer->update(['is_active' => 0]);
            $message = $this->isCustomerUsed($company->id, (int) $customer->id)
                ? 'Customer is used in transactions, so deleted not allowed. Customer set to inactive.'
                : 'Customer set to inactive successfully.';
        }

        if ($request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => $message,
            ]);
        }

        return redirect()
            ->route('company.customers.index', $company->slug)
            ->with('success', $message);
    }

    public function exportExcel($slug): StreamedResponse
    {
        $company = Company::whereSlug($slug)->firstOrFail();
        $rows = Customer::where('company_id', $company->id)->orderBy('name')->get();

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Name', 'Email', 'Mobile', 'City', 'Area', 'Landmark', 'Created Date', 'Status']);
            foreach ($rows as $r) {
                fputcsv($out, [
                    $r->name,
                    $r->email,
                    $r->mobile_no,
                    $r->city,
                    $r->area,
                    $r->landmark,
                    optional($r->created_at)?->format('d-m-Y h:i A'),
                    (int) $r->is_active === 1 ? 'Active' : 'Inactive',
                ]);
            }
            fclose($out);
        }, 'customers_report.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function exportPdf($slug)
    {
        $company = Company::whereSlug($slug)->firstOrFail();
        $rows = Customer::where('company_id', $company->id)->orderBy('name')->get();

        return Pdf::loadView('company.customers.pdf.index', compact('company', 'rows'))
            ->setPaper('a4', 'portrait')
            ->download('customers_report.pdf');
    }

    private function validateCustomer(Request $request, int $companyId, ?int $customerId = null): array
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
        ], [
            'email.unique' => 'This email id is already used for another customer.',
            'email.email' => 'Please enter a valid email id.',
        ]);
    }

    private function normalizeCustomerRequest(Request $request): void
    {
        $input = [];

        foreach ([
            'name',
            'email',
            'mobile_no',
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
        ] as $field) {
            if ($request->has($field)) {
                $input[$field] = trim((string) $request->input($field));
            }
        }

        if (array_key_exists('email', $input) && $input['email'] !== '') {
            $input['email'] = strtolower($input['email']);
        }

        $request->merge($input);
    }

    private function throwDuplicateEmailValidation(Throwable $e): void
    {
        $message = (string) $e->getMessage();
        $normalizedMessage = strtolower($message);

        if (
            str_contains($normalizedMessage, 'duplicate')
            || str_contains($normalizedMessage, '1062')
            || ((string) $e->getCode() === '23000' && (str_contains($normalizedMessage, 'email') || str_contains($normalizedMessage, 'customers') || str_contains($normalizedMessage, 'users')))
        ) {
            throw ValidationException::withMessages([
                'email' => 'This email id is already used for another customer.',
            ]);
        }
    }

    private function ensureEmailIsUnique(?string $email, ?int $ignoreCustomerId = null, ?int $ignoreUserId = null): void
    {
        $email = strtolower(trim((string) $email));

        if ($email === '') {
            return;
        }

        $exists = Customer::query()
            ->whereRaw('TRIM(LOWER(email)) = ?', [$email])
            ->when($ignoreCustomerId, fn($query) => $query->where('id', '!=', $ignoreCustomerId))
            ->exists();

        if (!$exists) {
            $exists = User::query()
                ->whereRaw('TRIM(LOWER(email)) = ?', [$email])
                ->when($ignoreUserId, fn($query) => $query->where('id', '!=', $ignoreUserId))
                ->exists();
        }

        if ($exists) {
            throw ValidationException::withMessages([
                'email' => 'This email id is already used for another customer.',
            ]);
        }
    }

    private function isCustomerUsed(int $companyId, int $customerId): bool
    {
        return Sale::where('company_id', $companyId)->where('customer_id', $customerId)->exists()
            || ApprovalHeader::where('company_id', $companyId)->where('customer_id', $customerId)->exists();
    }
}
