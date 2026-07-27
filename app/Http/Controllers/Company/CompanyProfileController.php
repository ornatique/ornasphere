<?php

namespace App\Http\Controllers\Company;

use App\Http\Controllers\Controller;
use App\Models\Company;
use Illuminate\Http\Request;

class CompanyProfileController extends Controller
{
    public function show(Request $request, $slug)
    {
        $company = Company::whereSlug($slug)->firstOrFail();
        $user = $request->user();

        abort_if((int) $user->company_id !== (int) $company->id, 403);

        return view('company.profile.show', compact('company', 'user'));
    }
}
