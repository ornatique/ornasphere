<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Company;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class CompanyUserController extends Controller
{
    private function storeProfileImageToUploads(Request $request): ?string
    {
        if (!$request->hasFile('profile_image')) {
            return null;
        }

        $file = $request->file('profile_image');
        $dir = public_path('uploads/profile_images');

        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }

        $name = Str::uuid()->toString() . '.' . $file->getClientOriginalExtension();
        $file->move($dir, $name);

        return 'uploads/profile_images/' . $name;
    }

    private function deleteProfileImageIfExists(?string $path): void
    {
        if (!$path) {
            return;
        }

        $normalized = ltrim($path, '/');
        if (str_starts_with($normalized, 'public/')) {
            $normalized = substr($normalized, 7);
        }

        $publicFile = public_path($normalized);
        if (file_exists($publicFile)) {
            @unlink($publicFile);
            return;
        }

        Storage::disk('public')->delete($path);
    }

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
        'profile_image' => 'nullable|image|max:2048',
    ]);

    // 🔹 Upload Image (If Provided)
    $imagePath = $this->storeProfileImageToUploads($request);

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
        'data' => $user->append('profile_image_url')
    ], 200);
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
            'profile_image' => 'nullable|image|max:2048',
        ]);

        if (strtolower((string) $validated['role']) === 'customer') {
            return response()->json([
                'success' => false,
                'message' => 'Customer role is not allowed in User API. Use customer APIs instead.'
            ], 422);
        }

        // 🖼 Update Image
        if ($request->hasFile('profile_image')) {
            $this->deleteProfileImageIfExists($user->profile_image);
            $user->profile_image = $this->storeProfileImageToUploads($request);
        }

        // 🔄 Update other fields
        $user->update([
            'name'  => $validated['name'],
            'email' => $validated['email'],
            'role'  => $validated['role'],
            'profile_image' => $user->profile_image,

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
            'data' => $user->fresh()->append('profile_image_url')
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
    public function toggleStatus(Request $request, $id)
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

        if ((int) $authUser->id === (int) $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'You cannot change your own account status.'
            ], 403);
        }

        if ($request->has('is_active')) {
            $newStatus = $request->boolean('is_active') ? 1 : 0;
        } elseif ($request->filled('status')) {
            $status = strtolower((string) $request->status);
            $newStatus = in_array($status, ['1', 'active', 'true', 'yes'], true) ? 1 : 0;
        } else {
            $newStatus = (int) $user->is_active === 1 ? 0 : 1;
        }

        $user->forceFill(['is_active' => $newStatus])->save();

        return response()->json([
            'success' => true,
            'message' => $newStatus ? 'User activated successfully.' : 'User deactivated successfully.',
            'user_id' => (int) $user->id,
            'is_active' => (int) $user->is_active,
            'status' => $newStatus ? 'active' : 'inactive',
            'data' => $user->fresh(),
        ], 200);
    }
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
    $this->deleteProfileImageIfExists($user->profile_image);

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

