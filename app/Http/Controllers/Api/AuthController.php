<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use App\Models\Permission;
use App\Models\User;
use PragmaRX\Google2FA\Google2FA;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
            'company_id' => 'nullable|integer',
            'company_slug' => 'nullable|string|max:255',
        ]);

        $users = User::with('company')
            ->where('email', $request->email)
            ->when($request->filled('company_id'), function ($query) use ($request) {
                $query->where('company_id', (int) $request->input('company_id'));
            })
            ->when($request->filled('company_slug'), function ($query) use ($request) {
                $query->whereHas('company', function ($companyQuery) use ($request) {
                    $companyQuery->where('slug', (string) $request->input('company_slug'));
                });
            })
            ->get();

        $matchedUsers = $users
            ->filter(fn($candidate) => Hash::check($request->password, $candidate->password))
            ->values();

        if (
            !$request->filled('company_id')
            && !$request->filled('company_slug')
            && $matchedUsers->count() > 1
        ) {
            return response()->json([
                'success' => false,
                'code' => 'COMPANY_SELECTION_REQUIRED',
                'message' => 'Multiple companies use these credentials. Please select a company and log in again.',
                'companies' => $matchedUsers
                    ->filter(fn($candidate) => $candidate->company)
                    ->map(fn($candidate) => [
                        'id' => (int) $candidate->company->id,
                        'name' => $candidate->company->name,
                        'slug' => $candidate->company->slug,
                    ])
                    ->values(),
            ], 422);
        }

        $user = $matchedUsers->first();

        if (!$user) {
            return response()->json(['message' => 'Invalid credentials'], 401);
        }

        if (!$user->company || $user->company->status != 1) {
            return response()->json(['message' => 'Company inactive'], 403);
        }

        if ($user->is_active != 1) {
            return response()->json(['message' => 'User inactive'], 403);
        }

        return response()->json([
            'otp_required' => true,
            'user_id' => $user->id,
            'company' => [
                'id' => (int) $user->company->id,
                'name' => $user->company->name,
                'slug' => $user->company->slug,
            ],
        ]);
    }

    public function verifyOtp(Request $request)
    {
        $request->validate([
            'user_id' => 'required',
            'otp' => 'required'
        ]);

        $user = User::find($request->user_id);

        if (!$user) {
            return response()->json(['message' => 'User not found'], 404);
        }

        $google2fa = new Google2FA();

        $valid = $google2fa->verifyKey(
            $user->two_factor_secret,
            $request->otp
        );

        if (!$valid) {
            return response()->json(['message' => 'Invalid OTP'], 401);
        }

        $token = $user->createToken('api-token')->plainTextToken;

        return response()->json([
            'message' => 'Login successful',
            'token' => $token,
            'company' => [
                'id' => (int) optional($user->company)->id,
                'name' => optional($user->company)->name,
                'slug' => optional($user->company)->slug,
            ],
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logged out']);
    }

    public function me(Request $request)
    {
        $user = $request->user()->loadMissing(['roles:id,name', 'company']);
        $basePermissions = $this->basePermissionsForUser($user);
        $expandedPermissions = $basePermissions->flatMap(function ($permissionName) {
            $name = (string) $permissionName;

            if (!str_ends_with($name, '-manage')) {
                return [$name];
            }

            $module = substr($name, 0, -strlen('-manage'));

            return [
                $name,
                $module . '-view',
                $module . '-create',
                $module . '-edit',
                $module . '-delete',
            ];
        })->unique()->values();
        $expandedPermissions = $expandedPermissions
            ->reject(fn ($permissionName) => $this->isDeprecatedPermission((string) $permissionName))
            ->values();

        $profile = $this->profilePayload($user, $expandedPermissions);

        return response()->json([
            'success' => true,
            'data' => $user->append('profile_image_url'),
            'profile' => $profile,
            'company' => $profile['company'],
            'role_names' => $user->roles->pluck('name')->values(),
            'permissions' => $expandedPermissions,
        ]);
    }

    public function profile(Request $request)
    {
        $user = $request->user()->loadMissing(['roles:id,name', 'company']);
        $basePermissions = $this->basePermissionsForUser($user);
        $expandedPermissions = $basePermissions->flatMap(function ($permissionName) {
            $name = (string) $permissionName;

            if (!str_ends_with($name, '-manage')) {
                return [$name];
            }

            $module = substr($name, 0, -strlen('-manage'));

            return [
                $name,
                $module . '-view',
                $module . '-create',
                $module . '-edit',
                $module . '-delete',
            ];
        })->unique()->values();
        $expandedPermissions = $expandedPermissions
            ->reject(fn ($permissionName) => $this->isDeprecatedPermission((string) $permissionName))
            ->values();

        return response()->json([
            'success' => true,
            'data' => $this->profilePayload($user, $expandedPermissions),
        ]);
    }

    private function profilePayload(User $user, $permissions): array
    {
        $company = $user->company;

        return [
            'id' => (int) $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role,
            'role_names' => $user->roles->pluck('name')->values(),
            'permissions' => $permissions,
            'is_active' => (bool) $user->is_active,
            'status' => $user->is_active ? 'Active' : 'Inactive',
            'profile_image' => $user->profile_image,
            'profile_image_url' => $this->safeProfileImageUrl($user),
            'avatar_initials' => $this->initials($user->name),
            'person_code' => $user->person_code,
            'mobile_no' => $user->mobile_no,
            'phone_no' => $user->phone_no,
            'city' => $user->city,
            'address' => $user->address,
            'area' => $user->area,
            'landmark' => $user->landmark,
            'pincode' => $user->pincode,
            'contact_person1_name' => $user->contact_person1_name,
            'contact_person1_phone' => $user->contact_person1_phone,
            'contact_person2_name' => $user->contact_person2_name,
            'contact_person2_phone' => $user->contact_person2_phone,
            'gst_no' => $user->gst_no,
            'pan_no' => $user->pan_no,
            'aadhaar_no' => $user->aadhaar_no,
            'hallmark_license_no' => $user->hallmark_license_no,
            'birth_date' => $this->formatDate($user->birth_date),
            'anniversary_date' => $this->formatDate($user->anniversary_date),
            'reference' => $user->reference,
            'remarks' => $user->remarks,
            'created_at' => optional($user->created_at)->toDateTimeString(),
            'created_at_view' => $this->formatDateTime($user->created_at),
            'updated_at' => optional($user->updated_at)->toDateTimeString(),
            'updated_at_view' => $this->formatDateTime($user->updated_at),
            'company' => $company ? [
                'id' => (int) $company->id,
                'name' => $company->name,
                'slug' => $company->slug,
                'email' => $company->email,
                'company_logo' => $company->company_logo,
                'company_logo_url' => $this->companyLogoUrl($company->company_logo),
                'max_users' => $company->max_users,
                'plan' => $company->plan,
                'address_1' => $company->address_1,
                'address_2' => $company->address_2,
                'city' => $company->city,
                'state' => $company->state,
                'postcode' => $company->postcode,
                'country' => $company->country,
                'status' => $company->status == 1 ? 'Active' : 'Inactive',
            ] : null,
        ];
    }

    private function safeProfileImageUrl(User $user): ?string
    {
        $rawPath = trim((string) ($user->profile_image ?? ''));
        if ($rawPath === '') {
            return null;
        }

        $path = ltrim($rawPath, '/');
        if (str_starts_with($path, 'public/')) {
            $path = substr($path, 7);
        }

        if (file_exists(public_path($path))) {
            return asset('public/' . $path);
        }

        if (file_exists(storage_path('app/public/' . $path))) {
            return asset('storage/' . $path);
        }

        return null;
    }

    private function companyLogoUrl(?string $rawPath): ?string
    {
        $rawPath = trim((string) $rawPath);
        if ($rawPath === '') {
            return null;
        }

        $path = ltrim($rawPath, '/');
        if (str_starts_with($path, 'public/')) {
            $path = substr($path, 7);
        }

        if (file_exists(public_path($path))) {
            return asset('public/' . $path);
        }

        if (file_exists(storage_path('app/public/' . $path))) {
            return asset('storage/' . $path);
        }

        return null;
    }

    private function initials(?string $name): string
    {
        $parts = preg_split('/\s+/', trim((string) $name));
        $parts = array_values(array_filter($parts));

        if (empty($parts)) {
            return 'U';
        }

        $first = strtoupper(substr($parts[0], 0, 1));
        $second = isset($parts[1]) ? strtoupper(substr($parts[1], 0, 1)) : '';

        return $first . $second;
    }

    private function formatDate($value): ?string
    {
        if (empty($value)) {
            return null;
        }

        return date('d-m-Y', strtotime((string) $value));
    }

    private function formatDateTime($value): ?string
    {
        if (empty($value)) {
            return null;
        }

        return date('d-m-Y / h:i A', strtotime((string) $value));
    }

    private function basePermissionsForUser(User $user)
    {
        if ($user->hasRole('company_admin')) {
            $this->ensureWebPermissions();

            return collect($this->defaultPermissionModules())
                ->flatMap(function ($module) {
                    return collect($this->actionsForModule($module))->map(fn ($action) => "{$module}-{$action}");
                })
                ->values();
        }

        return $user->getAllPermissions()->pluck('name')->values();
    }

    private function ensureWebPermissions(): void
    {
        foreach ($this->defaultPermissionModules() as $module) {
            foreach ($this->actionsForModule($module) as $action) {
                $permission = Permission::firstOrCreate([
                    'name' => "{$module}-{$action}",
                    'guard_name' => 'web',
                ]);

                if ($permission->company_id !== null) {
                    $permission->company_id = null;
                    $permission->save();
                }
            }
        }
    }

    private function defaultPermissionModules(): array
    {
        return [
            'dashboard',
            'user',
            'category-person',
            'role',
            'permission',
            'notification',
            'app-theme',
            'person',
            'job-worker',
            'jobwork-issue',
            'jobwork-receive',
            'item',
            'item-set',
            'label-config',
            'label-print',
            'other-charge',
            'production-cost',
            'labour-formula',
            'production-step',
            'vacuum-buch',
            'vacuum-process',
            'vacuum-voucher',
            'vacuum-live-dashboard',
            'casting-heating',
            'casting-metal-issue',
            'casting-release',
            'tree-cutting-office',
            'tree-cutting-issue',
            'tree-cutting-receive',
            'casting-sorting',
            'voucher-history',
            'sale',
            'sale-advance',
            'approval',
            'approval-return',
            'report-sales-summary',
            'report-purchase-receiver-summary',
            'report-stock-position',
            'report-approval-outstanding',
            'report-outstanding-amount',
            'report-barcode-history',
            'report-worker-loss',
            'report-visiting-cards',
        ];
    }

    private function isDeprecatedPermission(string $permissionName): bool
    {
        return str_starts_with($permissionName, 'return-')
            || str_starts_with($permissionName, 'customer-');
    }

    private function actionsForModule(string $module): array
    {
        return in_array($module, ['dashboard', 'notification', 'vacuum-live-dashboard'], true)
            ? ['view']
            : ['view', 'create', 'edit', 'delete', 'manage'];
    }
}
