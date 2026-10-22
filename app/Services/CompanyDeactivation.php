<?php
namespace App\Services;

use App\Models\{Company, User};
use Illuminate\Support\Str;

class CompanyDeactivation
{
    public static function activateUsers(Company $company): void
    {
        $ids = [$company->id];
        if (!$company->parent_company_id && CompanyBranches::ready()) {
            $branches = Company::where('parent_company_id', $company->id);
            $ids = array_merge($ids, (clone $branches)->pluck('id')->all());
            $branches->update(['status' => true]);
        }
        // Do not restore revoked sessions, tokens or cleared 2FA secrets.
        User::whereIn('company_id', $ids)->update(['is_active' => true]);
    }

    public static function resetUsers(Company $company): void
    {
        $ids = [$company->id];
        if (!$company->parent_company_id && CompanyBranches::ready()) {
            $ids = array_merge($ids, Company::where('parent_company_id', $company->id)->pluck('id')->all());
        }
        foreach (User::whereIn('company_id', $ids)->get() as $user) {
            $user->forceFill([
                'two_factor_secret' => null,
                'two_factor_enabled' => false,
                'two_factor_recovery_codes' => null,
                'active_web_session_id' => (string) Str::uuid(),
                'active_api_token_id' => null,
                'remember_token' => null,
            ])->save();
            InactiveTokenResponse::revoke($user);
        }
    }
}
