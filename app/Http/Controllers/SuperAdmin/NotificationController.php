<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\SuperAdminNotification;
use App\Services\SuperAdminNotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

class NotificationController extends Controller
{
    public function index(Request $request)
    {
        $notifications = SuperAdminNotificationService::history((int) $request->input('per_page', 20));

        return view('superadmin.auth.notifications.index', compact('notifications'));
    }

    public function open(SuperAdminNotification $notification)
    {
        SuperAdminNotificationService::markRead($notification);

        // Older notifications target the company edit form, which is intentionally blocked
        // while archive/restore is active. Send these clicks to the read-only details page.
        if ($notification->route_name === 'superadmin.companies.edit' && !empty($notification->route_params['company'])) {
            return redirect()->route('superadmin.companies.show', ['company' => $notification->route_params['company']]);
        }

        if (!empty($notification->route_name) && Route::has($notification->route_name)) {
            try {
                return redirect()->to(route($notification->route_name, $notification->route_params ?: []));
            } catch (\Throwable $e) {
                return redirect()->route('superadmin.notifications.index');
            }
        }

        return redirect()->route('superadmin.notifications.index');
    }

    public function markAllRead(Request $request)
    {
        $updated = SuperAdminNotificationService::markAllRead();

        if (!$request->expectsJson()) {
            return back();
        }

        return response()->json([
            'success' => true,
            'updated' => $updated,
        ]);
    }
}
