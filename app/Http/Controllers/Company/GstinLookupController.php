<?php

namespace App\Http\Controllers\Company;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Customer;
use App\Services\GstinLookup;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class GstinLookupController extends Controller
{
    public function search(Request $request, string $slug)
    {
        $company = Company::whereSlug($slug)->firstOrFail();
        abort_unless((int) $request->user()?->company_id === (int) $company->id, 403);
        if ($request->isMethod('get')) {
            return view('company.customers.gst_search', compact('company'));
        }
        $validated = $request->validate(['gstin' => 'required|string|max:15']);
        try {
            $data = app(GstinLookup::class)->lookup($validated['gstin']);
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $exception) {
            // Configuration/provider failures are expected lookup responses, not application crashes.
            return response()->json(['message' => $exception->getMessage()], $exception->getStatusCode());
        }
        $existing = Customer::where('company_id', $company->id)->where('gst_no', $data['gst_no'])->exists();
        $token = (string) Str::uuid();
        $request->session()->put('gst_customer_prefill', ['token' => $token, 'company_id' => $company->id,
            'expires_at' => now()->addMinutes(30)->timestamp, 'data' => $data]);
        return response()->json(['data' => $data, 'existing' => $existing,
            'create_url' => route('company.customers.create', ['slug' => $slug, 'gst_prefill' => $token])]);
    }
}
