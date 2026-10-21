<?php

namespace App\Services;

use App\Models\Company;
use Illuminate\Http\Request;

/** Read-only report scope. Never changes the user's transaction workspace. */
class ReportBranches
{
    /** Display the same authorized company selection used by the report query. */
    public static function heading($company, ?Request $request = null): string
    {
        $request ??= request();
        if (!$company instanceof Company || !$request->user()) {
            return $company->name ?? $company->company_name ?? 'Company';
        }

        $ids = self::ids($company, $request);
        return Company::whereIn('id', $ids)->orderBy('name')->get(['name', 'parent_company_id'])
            ->map(fn ($selected) => $selected->name.(!$selected->parent_company_id ? ' - Company Admin' : ''))
            ->implode(' / ');
    }

    public static function name($id): string
    {
        $request = request();
        if (!$request->attributes->has('report_branch_names')) {
            $user = $request->user();
            $names = $user ? CompanyBranches::visibleCompanies($user)->pluck('name', 'id')->all() : [];
            $request->attributes->set('report_branch_names', $names);
        }
        return $request->attributes->get('report_branch_names')[(int) $id] ?? '';
    }
    public static function ids(Company $company, ?Request $request = null): array
    {
        $request ??= request();
        $user = $request->user();
        abort_unless($user && (int) $user->company_id === (int) $company->id, 403);
        // An omitted selection always means the authenticated workspace, even
        // when a caller supplies a company model with only selected attributes.
        if (!$request->exists('report_branches')) {
            return [(int) $company->id];
        }
        $selection = $request->input('report_branches', $company->slug);
        abort_unless(is_string($selection), 422, 'Invalid branch selection.');
        $cacheKey = 'report_scope_'.$user->id.'_'.$company->id.'_'.hash('sha256', $selection);
        if ($request->attributes->has($cacheKey)) return $request->attributes->get($cacheKey);
        $slugs = array_values(array_unique(array_filter(explode(',', $selection))));
        abort_if(!$slugs, 422, 'Select at least one branch.');
        if (!CompanyBranches::canManage($user)) {
            abort_unless($slugs === [$company->slug], 403);
            return [(int) $company->id];
        }
        $allowed = CompanyBranches::visibleCompanies($user);
        abort_if($slugs !== ['all'] && array_diff($slugs, $allowed->pluck('slug')->all()), 403);
        $ids = ($slugs === ['all'] ? $allowed : $allowed->whereIn('slug', $slugs))->pluck('id')->map(fn ($id) => (int) $id)->all();
        $request->attributes->set($cacheKey, $ids);
        return $ids;
    }
}
