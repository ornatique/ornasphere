<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Yajra\DataTables\Facades\DataTables;
use App\Mail\CompanyLoginMail;
use Illuminate\Support\Facades\DB;


class CompanyController extends Controller
{
    /*
    ======================================================
    LIST PAGE + DATATABLE
    ======================================================
    */
    public function index(Request $request)
    {
        if ($request->ajax()) {

            $companies = Company::query()->withCount('users')
                ->with(['users' => function ($query) {
                    $query->select('id', 'company_id', 'password_set_url')
                        ->whereHas('roles', function ($q) {
                            $q->where('name', 'company_admin');
                        });
                }]);

            return DataTables::of($companies)

                ->addIndexColumn()
                ->addColumn('password_set_url', function ($row) {

                    $adminUser = $row->users->first();

                    if ($adminUser && $adminUser->password_set_url) {

                        $url = $adminUser->password_set_url;

                        return '
                            <div class="d-flex gap-1">
                                
                                <button class="btn btn-sm btn-info copyBtn"
                                        data-url="' . $url . '">
                                    Copy
                                </button>

                            </div>
                        ';
                    }

                    return '<span class="text-danger">Not Available</span>';
                })

                ->filterColumn('users_count', function ($query, $keyword) {
                    $query->whereHas('users', function ($q) use ($keyword) {
                        $q->where('name', 'like', "%{$keyword}%");
                    });
                })

                ->addColumn('status', function ($row) {
                    $checked = $row->status ? 'checked' : '';

                    return '
                        <div class="form-check form-switch" style="margin-left:40px;">
                            <input class="form-check-input toggleStatus"
                                type="checkbox"
                                data-id="' . $row->id . '"
                                ' . $checked . '>
                        </div>
                    ';
                })
                ->addColumn('action', function ($row) {
                    return '
                        <a href="' . route('superadmin.companies.edit', $row->id) . '"
                        class="btn btn-sm btn-primary">Edit</a>

                        
                        <form method="POST"
                            action="' . route('superadmin.companies.reset-2fa', $row->id) . '"
                            style="display:inline"
                            onsubmit="return confirm(\'Reset 2FA for this company?\')">
                            ' . csrf_field() . '
                            <button class="btn btn-sm btn-secondary">
                                Reset 2FA
                            </button>
                        </form>
                        <button data-id="' . $row->id . '"
                                class="btn btn-sm btn-danger deleteBtn">
                            Delete
                        </button>
                    ';
                })



                ->rawColumns(['password_set_url', 'action', 'status'])
                ->make(true);
        }

        return view('superadmin.auth.company.index');
    }

    public function create()
    {
        return view('superadmin.auth.company.create');
    }
    /*
    ======================================================
    STORE COMPANY
    ======================================================
    */

    public function store(Request $request)
    {
        $request->validate([
            'name'      => 'required|unique:companies,name',
            'email'     => 'required|email|unique:users,email',
            'max_users' => 'required|integer|min:1',
        ]);

        DB::beginTransaction();

        try {
            /*
        -------------------------
        Create Company
        -------------------------
        */
            $slug = Str::slug($request->name) . '-' . rand(100, 999);

            $company = Company::create([
                'name'       => $request->name,
                'slug'       => $slug,
                'email'      => $request->email,
                'max_users'  => $request->max_users,

                // Address fields
                'address_1'  => $request->address_1,
                'address_2'  => $request->address_2,
                'city'       => $request->city,
                'state'      => $request->state,
                'postcode'   => $request->postcode,
                'country'    => $request->country,

                // default inactive
                'status'     => 0,
            ]);

            /*
        -------------------------
        Create Default Admin User
        -------------------------
        */
            $tempPassword = Str::password(10);

            $user = User::create([
                'name'             => $company->name . ' Admin',
                'email'            => $request->email,
                'password'         => Hash::make($tempPassword),
                'company_id'       => $company->id,
                'role'             =>'admin',
                'password_changed' => false,
            ]);

            // Assign role
            $user->assignRole('company_admin');

            /*
        -------------------------
        Create Password Set Token
        -------------------------
        */

            $token = Str::uuid()->toString();

            // Generate full URL
            $setPasswordUrl = url('/set-password/' . $token);

            // Save full URL in users table
            $user->password_set_url = $setPasswordUrl;
            $user->save();


            DB::table('password_set_tokens')->insert([
                'user_id'    => $user->id,
                'token'      => $token,
                'expires_at' => now()->addHours(24),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            /*
        -------------------------
        Send Email (Queued)
        -------------------------
        */
            Mail::to($user->email)
                ->queue(new CompanyLoginMail($company, $user, $token));

            DB::commit();

            return redirect()
                ->route('superadmin.companies.index')
                ->with('success', 'Company created. Login email sent successfully.');
        } catch (\Exception $e) {
            DB::rollBack();

            return back()->withErrors([
                'error' => 'Something went wrong. Please try again.',
            ]);
        }
    }



    /*
    ======================================================
    EDIT COMPANY
    ======================================================
    */
    public function edit(Company $company)
    {
        return view('superadmin.auth.company.edit', compact('company'));
    }


    /*
    ======================================================
    UPDATE COMPANY
    ======================================================
    */

    public function update(Request $request, Company $company)
    {
        $request->validate([
            'name'       => 'required|string|max:255|unique:companies,name,' . $company->id,
            'email'      => 'required|email|max:255',
            'max_users'  => 'required|integer|min:1',
        ]);

        $company->update([
            'name'       => $request->name,
            'email'      => $request->email,
            'max_users'  => $request->max_users,

            'address_1'  => $request->address_1,
            'address_2'  => $request->address_2,
            'city'       => $request->city,
            'state'      => $request->state,
            'postcode'   => $request->postcode,
            'country'    => $request->country,
        ]);

        return redirect()
            ->route('superadmin.companies.index')
            ->with('success', 'Company updated successfully');
    }



    /*
    ======================================================
    DELETE COMPANY
    ======================================================
    */
    public function destroy(Company $company)
    {
        $company->delete(); // cascade deletes users automatically

        return redirect()
            ->route('superadmin.companies.index')
            ->with('success', 'Company deleted successfully');
    }

    public function toggleStatus(Company $company)
    {
        // Toggle company status
        $newStatus = !$company->status;

        $company->update([
            'status' => $newStatus
        ]);

        // Update Admin user of that company
        User::where('company_id', $company->id)
            ->where('role', 'Admin') // adjust if using role column
            ->update([
                'is_active' => $newStatus
            ]);

        return response()->json([
            'success' => true,
            'status'  => $newStatus
        ]);
    }

    public function resendLogin(Company $company)
    {
        $user = $company->users()->first();

        if (!$user) {
            return back()->with('error', 'No admin user found.');
        }

        $password = Str::password(10);

        $user->update([
            'password' => Hash::make($password),
        ]);

        Mail::to($user->email)
            ->queue(new CompanyLoginMail($company, $user->email, $password));

        return back()->with('success', 'Login credentials resent successfully.');
    }

    public function reset2fa(Company $company)
    {
        // Reset 2FA for all users of this company
        $company->users()->update([
            'two_factor_secret' => null,
            'two_factor_enabled' => false,
            'two_factor_recovery_codes' => null,
        ]);

        return redirect()
            ->back()
            ->with('success', '2FA has been reset for this company.');
    }
}
