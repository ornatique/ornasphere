<?php
namespace App\Services;

use App\Models\{Company, User};
use Illuminate\Support\Facades\Schema;

class CompanyBranches
{
    public const LIMIT = 2;
    public const LIMIT_MESSAGE = 'Branch limit reached. You can create up to 2 branches. Please contact administration for additional branches.';
    public static function isActive(?Company $company): bool
    {
        if (!$company || (int) $company->status !== 1) return false;
        return !$company->parent_company_id || Company::whereKey($company->parent_company_id)->where('status', 1)->exists();
    }
    public static function ready(): bool { return Schema::hasColumn('companies', 'parent_company_id'); }

    public static function canManage(User $user): bool
    {
        return self::ready() && $user->hasRole('company_admin') && $user->company && !$user->company->parent_company_id;
    }

    public static function visibleCompanies(User $user)
    {
        return Company::where(function ($query) use ($user) {
            $query->where('id', $user->company_id);
            if (self::canManage($user)) $query->orWhere('parent_company_id', $user->company_id);
        })->orderBy('name')->get();
    }
}
