<?php 

use App\Models\AuditLog;

function auditLog($action, $module, $desc = null)
{
    AuditLog::create([
        'super_admin_id' => auth('superadmin')->id(),
        'company_id' => session('company_id'),
        'user_id' => auth()->id(),
        'action' => $action,
        'module' => $module,
        'description' => $desc,
        'ip' => request()->ip(),
        'user_agent' => request()->userAgent(),
    ]);
}
