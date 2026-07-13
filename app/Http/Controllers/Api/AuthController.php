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
        $user = $request->user()->loadMissing('roles:id,name');
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
            'data' => $user,
            'role_names' => $user->roles->pluck('name')->values(),
            'permissions' => $expandedPermissions,
        ]);
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
            'role',
            'permission',
            'notification',
            'app-theme',
            'customer',
            'job-worker',
            'jobwork-issue',
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
            'casting-heating',
            'casting-metal-issue',
            'casting-release',
            'tree-cutting-issue',
            'tree-cutting-receive',
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
            'report-visiting-cards',
        ];
    }

    private function isDeprecatedPermission(string $permissionName): bool
    {
        return str_starts_with($permissionName, 'return-');
    }

    private function actionsForModule(string $module): array
    {
        return in_array($module, ['dashboard', 'notification'], true)
            ? ['view']
            : ['view', 'create', 'edit', 'delete', 'manage'];
    }
}
