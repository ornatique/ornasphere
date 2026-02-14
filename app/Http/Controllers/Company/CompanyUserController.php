<?php

namespace App\Http\Controllers\Company;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Company;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Yajra\DataTables\Facades\DataTables;
use Illuminate\Support\Facades\Crypt;

class CompanyUserController extends Controller
{
    public function index(Request $request, $slug)
    {
        $company = Company::whereSlug($slug)->firstOrFail();

        if ($request->ajax()) {

            $users = User::where('company_id', $company->id)
                ->with('roles')
                ->select('users.*');

            return DataTables::of($users)

                ->addIndexColumn()

                ->addColumn('role', function ($user) {
                    return $user->roles->pluck('name')->implode(', ');
                })

                ->addColumn('status', function ($user) {
                    return $user->is_active
                        ? '<span class="badge bg-success">Active</span>'
                        : '<span class="badge bg-danger">Inactive</span>';
                })

                ->addColumn('action', function ($user) use ($company) {

                    $encryptedId = Crypt::encryptString($user->id);

                    $editUrl = route('company.users.edit', [$company->slug, $encryptedId]);
                    $toggleUrl = route('company.users.toggle', [$company->slug, $encryptedId]);

                    $statusBtnClass = $user->is_active ? 'btn-success' : 'btn-danger';
                    $statusText = $user->is_active ? 'Active' : 'Inactive';

                    return '
                            <a href="' . $editUrl . '" class="btn btn-sm btn-primary">Edit</a>

                            <button type="button"
                                class="btn btn-sm ' . $statusBtnClass . ' toggle-status-btn"
                                data-url="' . $toggleUrl . '">
                                ' . $statusText . '
                            </button>
                        ';
                })

                ->rawColumns(['status', 'action'])
                ->make(true);
        }

        return view('company.users.index', compact('company'));
    }


    public function create($slug)
    {
        $company = Company::whereSlug($slug)->firstOrFail();

        $roles = Role::where('company_id', $company->id)->get();

        return view('company.users.create', compact('company', 'roles'));
    }


    public function store(Request $request, $slug)
    {
        $company = Company::whereSlug($slug)->firstOrFail();

        // Employee Limit Check
        if ($request->role == 'Employee') {
            $employeeCount = User::where('company_id', $company->id)
                ->where('role', 'Employee')
                ->count();

            if ($employeeCount >= 2) {
                return back()->with('error', 'Employee limit reached.');
            }
        }

        $request->validate([
            'name' => 'required|string',
            'email' => 'required|email|unique:users,email',
            'role' => 'required',
            // 'profile_image' => 'nullable|image|max:2048',
        ]);

        $imagePath = null;
        if ($request->hasFile('profile_image')) {
            $imagePath = $request->file('profile_image')
                ->store('profile_images', 'public');
        }

        $user = User::create([
            'company_id' => $company->id,
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make('User@123'),
            'role' => $request->role,
            'profile_image' => $imagePath,

            'person_code' => $request->person_code,
            'city' => $request->city,
            'address' => $request->address,
            'area' => $request->area,
            'landmark' => $request->landmark,
            'pincode' => $request->pincode,
            'mobile_no' => $request->mobile_no,
            'phone_no' => $request->phone_no,
            'contact_person1_name' => $request->contact_person1_name,
            'contact_person1_phone' => $request->contact_person1_phone,
            'contact_person2_name' => $request->contact_person2_name,
            'contact_person2_phone' => $request->contact_person2_phone,
            'gst_no' => $request->gst_no,
            'pan_no' => $request->pan_no,
            'aadhaar_no' => $request->aadhaar_no,
            'hallmark_license_no' => $request->hallmark_license_no,
            'birth_date' => $request->birth_date,
            'anniversary_date' => $request->anniversary_date,
            'reference' => $request->reference,
            'remarks' => $request->remarks,
        ]);

        $user->assignRole($request->role);

        return redirect()
            ->route('company.users.index', $company->slug)
            ->with('success', 'User created successfully');
    }

    public function edit($slug, $encryptedId)
    {
        $company = Company::whereSlug($slug)->firstOrFail();

        $userId = Crypt::decryptString($encryptedId);

        $user = User::where('id', $userId)
            ->where('company_id', $company->id)
            ->firstOrFail();

        $roles = Role::where('company_id', $company->id)->get();

        return view('company.users.edit', compact('company', 'user', 'roles'));
    }

    public function update(Request $request, $slug, $encryptedId)
    {
        $company = Company::whereSlug($slug)->firstOrFail();

        $userId = Crypt::decryptString($encryptedId);

        $user = User::where('id', $userId)
            ->where('company_id', $company->id)
            ->firstOrFail();

        $request->validate([
            'name' => 'required|string|max:255',
            'role' => 'required',
            'profile_image' => 'nullable|image|max:2048',
        ]);

        $newRole = $request->role;
        $currentRole = $user->getRoleNames()->first();

        // ✅ Employee Limit Check During Edit
        if ($newRole === 'Employee' && $currentRole !== 'Employee') {

            $employeeCount = User::role('Employee')
                ->where('company_id', $company->id)
                ->count();

            if ($employeeCount >= 2) {
                return back()->withErrors([
                    'role' => 'Employee limit reached. Please upgrade your plan.'
                ])->withInput();
            }
        }

        // Update profile image
        if ($request->hasFile('profile_image')) {

            if ($user->profile_image) {
                Storage::disk('public')->delete($user->profile_image);
            }

            $user->profile_image = $request->file('profile_image')
                ->store('profile_images', 'public');
        }

        // Update fields
        $user->update([
            'name' => $request->name,
            'profile_image' => $user->profile_image,
            'person_code' => $request->person_code,
            'city' => $request->city,
            'area' => $request->area,
            'landmark' => $request->landmark,
            'pincode' => $request->pincode,
            'mobile_no' => $request->mobile_no,
            'phone_no' => $request->phone_no,
            'contact_person1_name' => $request->contact_person1_name,
            'contact_person1_phone' => $request->contact_person1_phone,
            'contact_person2_name' => $request->contact_person2_name,
            'contact_person2_phone' => $request->contact_person2_phone,
            'gst_no' => $request->gst_no,
            'pan_no' => $request->pan_no,
            'aadhaar_no' => $request->aadhaar_no,
            'hallmark_license_no' => $request->hallmark_license_no,
            'birth_date' => $request->birth_date,
            'anniversary_date' => $request->anniversary_date,
            'reference' => $request->reference,
            'address' => $request->address,
        ]);

        $user->syncRoles([$newRole]);

        return redirect()
            ->route('company.users.index', $company->slug)
            ->with('success', 'User updated successfully');
    }



    public function destroy($slug, User $user)
    {
        $company = Company::whereSlug($slug)->firstOrFail();

        abort_if($user->company_id !== $company->id, 403);

        if ($user->profile_image) {
            Storage::disk('public')->delete($user->profile_image);
        }

        $user->delete();

        return back()->with('success', 'User deleted');
    }

    public function toggle($slug, $encryptedId)
    {
        $company = Company::where('slug', $slug)->firstOrFail();

        $userId = Crypt::decryptString($encryptedId);

        $user = User::where('id', $userId)
            ->where('company_id', $company->id)
            ->firstOrFail();

        // Prevent self deactivation
        if ($user->id === auth()->id()) {
            return response()->json([
                'status' => false,
                'message' => 'You cannot deactivate yourself.'
            ], 403);
        }

        // Toggle status
        $user->is_active = $user->is_active ? 0 : 1;
        $user->save();

        return response()->json([
            'status' => true,
            'new_status' => $user->is_active,
            'message' => $user->is_active
                ? 'User activated successfully.'
                : 'User deactivated successfully.'
        ]);
    }


    public function checkEmployeeLimit($slug)
    {
        $company = Company::whereSlug($slug)->firstOrFail();

        $employeeCount = User::where('company_id', $company->id)
            ->role('Employee') // Spatie way
            ->count();

        return response()->json([
            'limit_reached' => $employeeCount >= 2
        ]);
    }
}
