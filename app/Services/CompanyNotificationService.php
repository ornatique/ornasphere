<?php

namespace App\Services;

use App\Models\CompanyActivityNotification;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class CompanyNotificationService
{
    public const MODULES = [
        'user' => 'Users',
        'customer' => 'Customers',
        'app_theme' => 'App Theme',
        'item' => 'Items',
        'label_config' => 'Label Config',
        'item_set' => 'Label Items',
        'other_charge' => 'Other Charges',
        'approval' => 'Approvals',
        'approval_return' => 'Approval Returns',
        'sale' => 'Sales',
        'sale_advance' => 'Receive / Return / Purchase',
        'job_worker' => 'Job Workers',
        'jobwork_issue' => 'Jobwork Issues',
        'production_cost' => 'Production Cost',
        'labour_formula' => 'Labour Formula',
        'production_step' => 'Production Step',
        'visiting_card' => 'Visiting Cards',
    ];

    public static function record(
        ?User $actor,
        string $module,
        string $action,
        string $title,
        ?string $message = null,
        ?string $routeName = null,
        array $routeParams = [],
        ?Model $subject = null
    ): void {
        if (!self::isReady() || !$actor || !$actor->company_id || $actor->hasRole('company_admin')) {
            return;
        }

        CompanyActivityNotification::create([
            'company_id' => $actor->company_id,
            'actor_user_id' => $actor->id,
            'module' => $module,
            'action' => $action,
            'title' => $title,
            'message' => $message,
            'route_name' => $routeName,
            'route_params' => $routeParams ?: null,
            'subject_type' => $subject ? $subject::class : null,
            'subject_id' => $subject?->getKey(),
        ]);
    }

    public static function summary(?User $user): array
    {
        if (!self::canRead($user)) {
            return [
                'total' => 0,
                'modules' => [],
                'labels' => self::MODULES,
                'latest' => collect(),
            ];
        }

        $moduleCounts = CompanyActivityNotification::query()
            ->where('company_id', $user->company_id)
            ->whereNull('read_at')
            ->selectRaw('module, COUNT(*) as total')
            ->groupBy('module')
            ->pluck('total', 'module')
            ->map(fn ($count) => (int) $count)
            ->all();

        $latest = self::latest($user, 20, true);

        return [
            'total' => array_sum($moduleCounts),
            'modules' => $moduleCounts,
            'labels' => self::MODULES,
            'latest' => $latest,
        ];
    }

    public static function latest(?User $user, int $limit = 20, bool $unreadOnly = true): Collection
    {
        if (!self::canRead($user)) {
            return collect();
        }

        return CompanyActivityNotification::with('actor:id,name')
            ->where('company_id', $user->company_id)
            ->when($unreadOnly, fn ($query) => $query->whereNull('read_at'))
            ->latest()
            ->limit(max(1, min($limit, 100)))
            ->get();
    }

    public static function markAllRead(?User $user): int
    {
        if (!self::canRead($user)) {
            return 0;
        }

        return CompanyActivityNotification::where('company_id', $user->company_id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);
    }

    public static function markModuleRead(?User $user, string|array|null $modules): int
    {
        if (!self::canRead($user) || empty($modules)) {
            return 0;
        }

        $modules = array_values(array_filter((array) $modules));
        if (!$modules) {
            return 0;
        }

        return CompanyActivityNotification::where('company_id', $user->company_id)
            ->whereIn('module', $modules)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);
    }

    public static function modulesForRoute(?string $routeName): array
    {
        $routeName = (string) $routeName;

        return match (true) {
            str_starts_with($routeName, 'company.customers.') => ['customer'],
            str_starts_with($routeName, 'company.users.') => ['user'],
            str_starts_with($routeName, 'company.app-themes.') => ['app_theme'],
            str_starts_with($routeName, 'company.items.') => ['item'],
            str_starts_with($routeName, 'company.label_config.') => ['label_config'],
            $routeName === 'company.list_itemset' || str_starts_with($routeName, 'company.item_sets.') => ['item_set'],
            str_starts_with($routeName, 'company.other-charge.') => ['other_charge'],
            str_starts_with($routeName, 'company.job-workers.') => ['job_worker'],
            str_starts_with($routeName, 'company.jobwork-issue.') => ['jobwork_issue'],
            str_starts_with($routeName, 'company.production-cost.') => ['production_cost'],
            str_starts_with($routeName, 'company.labour-formula.') => ['labour_formula'],
            str_starts_with($routeName, 'company.production-step.') => ['production_step'],
            $routeName === 'company.sales.customerAdvance' || str_starts_with($routeName, 'company.sales.advance.') => ['sale_advance'],
            str_starts_with($routeName, 'company.approval.return.') => ['approval_return'],
            str_starts_with($routeName, 'company.approval.') => ['approval'],
            str_starts_with($routeName, 'company.returns.') => ['approval_return'],
            str_starts_with($routeName, 'company.sales.') => ['sale'],
            str_starts_with($routeName, 'company.reports.visiting-cards.') => ['visiting_card'],
            default => [],
        };
    }

    public static function isReady(): bool
    {
        return Schema::hasTable('company_activity_notifications');
    }

    private static function canRead(?User $user): bool
    {
        return self::isReady()
            && $user
            && $user->company_id
            && ($user->hasRole('company_admin') || $user->can('notification-view'));
    }
}
