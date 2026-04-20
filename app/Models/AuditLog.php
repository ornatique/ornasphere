<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class AuditLog extends Model
{
    protected $fillable = [
        'super_admin_id',
        'company_id',
        'user_id',
        'action',
        'module',
        'description',
        'ip',
        'user_agent',
    ];

    public static function logEvent(
        string $action,
        string $module,
        ?string $description = null,
        array $context = []
    ): void {
        try {
            $request = request();

            $superAdminId = $context['super_admin_id']
                ?? optional(Auth::guard('superadmin')->user())->id;

            $companyId = $context['company_id']
                ?? optional(Auth::user())->company_id;

            $userId = $context['user_id']
                ?? optional(Auth::user())->id;

            static::create([
                'super_admin_id' => $superAdminId,
                'company_id' => $companyId,
                'user_id' => $userId,
                'action' => $action,
                'module' => $module,
                'description' => $description,
                'ip' => $context['ip'] ?? optional($request)->ip(),
                'user_agent' => $context['user_agent'] ?? optional($request)->userAgent(),
            ]);
        } catch (\Throwable $e) {
            // Never break business flow because of audit logging failure.
        }
    }
}
