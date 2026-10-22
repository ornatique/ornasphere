<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\{AuditLog, Company, User};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class BranchManagementController extends Controller
{
    private function checkBranch(Company $company, Company $branch): void
    {
        abort_unless(!$company->parent_company_id && ((int) $branch->id === (int) $company->id || (int) $branch->parent_company_id === (int) $company->id), 404);
    }

    private function revokeAccess(User $user, ?string $reason = null): void
    {
        $user->forceFill([
            'active_web_session_id' => (string) Str::uuid(),
            'active_api_token_id' => null,
            'remember_token' => null,
        ])->save();
        if ($reason) {
            \App\Services\InactiveTokenResponse::revoke($user, $reason);
        } else {
            $user->tokens()->delete();
        }
    }

    public function show(Company $company, Company $branch)
    {
        $this->checkBranch($company, $branch);
        $users = $branch->users()->with('roles')->orderBy('name')->paginate(20);
        $locations = Company::whereKey($company->id)->orWhere('parent_company_id', $company->id)->orderBy('name')->get();
        return view('superadmin.auth.company.branch-details', compact('company', 'branch', 'users', 'locations'));
    }

    public function status(Request $request, Company $company, Company $branch)
    {
        $this->checkBranch($company, $branch);
        $data = $request->validate(['status' => 'required|boolean']);
        DB::transaction(function () use ($branch, $data) {
            $branch->update(['status' => (bool) $data['status']]);
            if (!$data['status']) {
                $companyIds = [$branch->id];
                if (!$branch->parent_company_id) {
                    $companyIds = array_merge($companyIds, Company::where('parent_company_id', $branch->id)->pluck('id')->all());
                }
                foreach (User::whereIn('company_id', $companyIds)->get() as $user) {
                    $this->revokeAccess($user, 'COMPANY_INACTIVE');
                }
            }
            AuditLog::logEvent('branch_status', 'company', 'Branch status set to '.(int) $data['status'], ['company_id' => $branch->id]);
        });
        return back()->with('success', 'Company status updated. Activation enables all related users; deactivation resets their 2FA.');
    }

    public function updateUser(Request $request, Company $company, Company $branch, User $user)
    {
        $this->checkBranch($company, $branch);
        abort_unless((int) $user->company_id === (int) $branch->id, 404);
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'is_active' => 'required|boolean',
        ]);
        DB::transaction(function () use ($user, $data, $branch) {
            $user = User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            $emailChanged = $user->email !== $data['email'];
            $oldEmail = $user->email;
            $user->fill($data);
            if ($emailChanged) {
                $user->email_verified_at = null;
            }
            if (!$data['is_active']) {
                $user->forceFill([
                    'two_factor_secret' => null,
                    'two_factor_enabled' => false,
                    'two_factor_recovery_codes' => null,
                ]);
            }
            $user->save();
            if ($emailChanged || !$data['is_active']) {
                $this->revokeAccess($user, !$data['is_active'] ? 'USER_INACTIVE' : null);
            }
            AuditLog::logEvent('branch_user_update', 'company', 'Updated branch user '.$user->id.'; email '.$oldEmail.' -> '.$user->email.'; active '.(int) $user->is_active,
                ['company_id' => $branch->id, 'user_id' => $user->id]);
        });
        return back()->with('success', 'User updated. Deactivated users have their 2FA reset and must enrol again after reactivation.');
    }
}
