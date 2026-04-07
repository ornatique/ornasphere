<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Company;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class CompanyUserController extends Controller
{
    // ✅ List Company Users
    public function index(Request $request)
    {
        $user = $request->user(); // logged in user
    
        $companyId = $user->company_id;
        $company = Company::find($companyId);
    
        $users = User::where('company_id', $companyId)
            ->latest()
            ->get();
    
        return response()->json([
            'success' => true,
            'data' => $users,
            'seat' => [
                'max_users' => (int) optional($company)->max_users,
                'current_users' => (int) $users->count(),
            ],
        ]);
    }

    // ✅ Create User For Company
public function store(Request $request)
{
    $authUser = $request->user(); // logged in user
    $companyId = $authUser->company_id;
    $company = Company::find($companyId);
    $selectedRole = strtolower((string) $request->role);

    if ($selectedRole === 'customer') {
        return response()->json([
            'success' => false,
            'message' => 'Customer role is not allowed in User API. Use customer APIs instead.'
        ], 422);
    }

    $currentUserCount = User::where('company_id', $companyId)->count();
    $maxUsers = (int) optional($company)->max_users;
    if ($maxUsers > 0 && $currentUserCount >= $maxUsers) {
        return response()->json([
            'success' => false,
            'message' => 'User limit reached for your plan. Please buy more user seats.',
            'seat' => [
                'max_users' => $maxUsers,
                'current_users' => (int) $currentUserCount,
            ],
        ], 422);
    }

    // 🔹 Employee Limit Check
    if ($request->role == 'Employee') {

        $employeeCount = User::where('company_id', $companyId)
            ->where('role', 'Employee')
            ->count();

        if ($employeeCount >= 2) {
            return response()->json([
                'success' => false,
                'message' => 'Employee limit reached.'
            ], 422);
        }
    }

    // 🔹 Validation
    $validated = $request->validate([
        'name'  => 'required|string',
        'email' => 'required|email|unique:users,email',
        'role'  => 'required',
        //'profile_image' => 'nullable|image|max:2048',
    ]);

    // 🔹 Upload Image (If Provided)
    $imagePath = null;

    if ($request->hasFile('profile_image')) {
        $imagePath = $request->file('profile_image')
            ->store('profile_images', 'public');
    }

    // 🔹 Create User
    $user = User::create([
        'company_id' => $companyId,
        'name' => $validated['name'],
        'email' => $validated['email'],
        // Requested behavior: default password = same email id.
        'password' => Hash::make($validated['email']),
        'role' => $validated['role'],
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

    // 🔹 Assign Role (Spatie)
    $user->assignRole($validated['role']);

    return response()->json([
        'success' => true,
        'message' => 'User created successfully',
        'default_password' => $validated['email'],
        'data' => $user
    ], 201);
}

    // ✅ Update Company User


public function update(Request $request, $id)
    {
        $authUser = $request->user();
        $companyId = $authUser->company_id;

        // 🔐 Company wise security
        $user = User::where('company_id', $companyId)
                    ->where('id', $id)
                    ->first();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found.'
            ], 404);
        }

        // ✅ Validation
    $validated = $request->validate([
        'name'  => 'required|string|max:255',
        'email' => [
            'required',
            'email',
                Rule::unique('users')->ignore($user->id),
            ],
            'role'  => 'required|string',
            //'profile_image' => 'nullable|image|max:2048',
        ]);

        if (strtolower((string) $validated['role']) === 'customer') {
            return response()->json([
                'success' => false,
                'message' => 'Customer role is not allowed in User API. Use customer APIs instead.'
            ], 422);
        }

        // 🖼 Update Image
        if ($request->hasFile('profile_image')) {

            // delete old image
            if ($user->profile_image) {
                Storage::disk('public')->delete($user->profile_image);
            }

            $imagePath = $request->file('profile_image')
                                 ->store('profile_images', 'public');

            $user->profile_image = $imagePath;
        }

        // 🔄 Update other fields
        $user->update([
            'name'  => $validated['name'],
            'email' => $validated['email'],
            'role'  => $validated['role'],

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

        // 🔁 Sync Spatie Role
        $user->syncRoles([$validated['role']]);

        return response()->json([
            'success' => true,
            'message' => 'User updated successfully',
            'updated_user_id' => $user->id,
            'data' => $user
        ], 200);
    }

    // ✅ Delete Company User
    public function reset2fa(Request $request, $id)
    {
        $authUser = $request->user();
        $companyId = $authUser->company_id;

        $user = User::where('company_id', $companyId)
            ->where('id', $id)
            ->first();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found.'
            ], 404);
        }

        $user->forceFill([
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_enabled' => false,
        ])->save();

        return response()->json([
            'success' => true,
            'message' => 'User 2FA reset successfully.',
            'user_id' => $user->id,
        ], 200);
    }

    // ✅ Delete Company User
    public function destroy(Request $request, $id)
    {
    $authUser = $request->user();
    $companyId = $authUser->company_id;

    // 🔐 Company-wise security
    $user = User::where('company_id', $companyId)
                ->where('id', $id)
                ->first();

    if (!$user) {
        return response()->json([
            'success' => false,
            'message' => 'User not found.'
        ], 404);
    }

    // ❌ Prevent self delete (Optional but Recommended)
    if ($authUser->id == $user->id) {
        return response()->json([
            'success' => false,
            'message' => 'You cannot delete your own account.'
        ], 403);
    }

    // 🖼 Delete profile image if exists
    if ($user->profile_image) {
        Storage::disk('public')->delete($user->profile_image);
    }

    // 🔁 Remove roles (Spatie)
    $user->syncRoles([]);

    // 🗑 Delete user
    $user->delete();

    return response()->json([
        'success' => true,
        'message' => 'User deleted successfully',
        'deleted_user_id' => $id
    ], 200);
}
}
