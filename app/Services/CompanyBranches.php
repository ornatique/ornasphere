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

    public static function managementSelection(\Illuminate\Http\Request $request, Company $current)
    {
        if (!$request->has('management_branches')) return collect([$current])->keyBy('id');
        $validated = $request->validate([
            'management_branches' => 'required|array|min:1',
            'management_branches.*' => 'required|integer|distinct',
        ]);
        $user = $request->user();
        abort_unless($user, 403);
        $allowed = self::visibleCompanies($user)->filter(fn ($company) => self::isActive($company))->keyBy('id');
        $ids = array_map('intval', $validated['management_branches']);
        foreach ($ids as $id) abort_unless($allowed->has($id), 403, 'Company or branch access denied.');
        return $allowed->only($ids)->keyBy('id');
    }

    public static function apiManagementSelection(\Illuminate\Http\Request $request)
    {
        $request->validate(['company_id' => 'sometimes|required|integer']);
        abort_unless($request->user()?->company, 403);
        if ($request->has('company_id') && $request->has('management_branches')) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'company_id' => 'Send company_id or management_branches, not both.',
            ]);
        }
        $selection = clone $request;
        if ($request->has('company_id')) $selection->merge(['management_branches' => [$request->integer('company_id')]]);
        return self::managementSelection($selection, $request->user()->company);
    }

    public static function apiManagementCompanyId(\Illuminate\Http\Request $request): int
    {
        $companies = self::apiManagementSelection($request);
        if ($companies->count() !== 1) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'company_id' => 'Select exactly one company for this action.',
            ]);
        }
        return (int) $companies->first()->id;
    }

    public static function visibleCompanies(User $user)
    {
        return Company::where(function ($query) use ($user) {
            $query->where('id', $user->company_id);
            if (self::canManage($user)) $query->orWhere('parent_company_id', $user->company_id);
        })->orderBy('name')->get();
    }
}
