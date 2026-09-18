<?php

namespace App\Services;

use App\Models\Company;
use App\Models\SuperAdminNotification;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class SuperAdminNotificationService
{
    public static function record(
        ?Company $company,
        ?User $actor,
        string $module,
        string $action,
        string $title,
        ?string $message = null,
        ?string $routeName = null,
        array $routeParams = [],
        ?Model $subject = null
    ): void {
        if (!self::isReady()) {
            return;
        }

        $subjectType = $subject ? $subject::class : null;
        $subjectId = $subject?->getKey();

        if (self::hasRecentDuplicate($company?->id, $module, $action, $subjectType, $subjectId, $message)) {
            return;
        }

        SuperAdminNotification::create([
            'company_id' => $company?->id,
            'actor_user_id' => $actor?->id,
            'module' => $module,
            'action' => $action,
            'title' => $title,
            'message' => $message,
            'route_name' => $routeName,
            'route_params' => $routeParams ?: null,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
        ]);
    }

    public static function summary(): array
    {
        if (!self::isReady()) {
            return [
                'total' => 0,
                'latest' => collect(),
            ];
        }

        return [
            'total' => SuperAdminNotification::whereNull('read_at')->count(),
            'latest' => self::latest(20, true),
        ];
    }

    public static function latest(int $limit = 20, bool $unreadOnly = true): Collection
    {
        if (!self::isReady()) {
            return collect();
        }

        return SuperAdminNotification::with(['company:id,name,slug', 'actor:id,name'])
            ->when($unreadOnly, fn ($query) => $query->whereNull('read_at'))
            ->latest()
            ->limit(max(1, min($limit, 100)))
            ->get();
    }

    public static function history(int $perPage = 20): LengthAwarePaginator
    {
        return SuperAdminNotification::with(['company:id,name,slug', 'actor:id,name'])
            ->latest()
            ->paginate(max(1, min($perPage, 100)));
    }

    public static function markRead(SuperAdminNotification $notification): void
    {
        if (!$notification->read_at) {
            $notification->forceFill(['read_at' => now()])->save();
        }
    }

    public static function markAllRead(): int
    {
        if (!self::isReady()) {
            return 0;
        }

        return SuperAdminNotification::whereNull('read_at')->update(['read_at' => now()]);
    }

    private static function hasRecentDuplicate(?int $companyId, string $module, string $action, ?string $subjectType, mixed $subjectId, ?string $message): bool
    {
        if (!$subjectType || !$subjectId) {
            return false;
        }

        return SuperAdminNotification::query()
            ->where('company_id', $companyId)
            ->where('module', $module)
            ->where('action', $action)
            ->where('subject_type', $subjectType)
            ->where('subject_id', $subjectId)
            ->where('message', $message)
            ->where('created_at', '>=', now()->subMinutes(10))
            ->exists();
    }

    public static function isReady(): bool
    {
        return Schema::hasTable('super_admin_notifications');
    }
}
