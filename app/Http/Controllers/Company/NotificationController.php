<?php

namespace App\Http\Controllers\Company;

use App\Http\Controllers\Controller;
use App\Services\CompanyNotificationService;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function summary(Request $request)
    {
        return response()->json([
            'success' => true,
            'data' => CompanyNotificationService::summary($request->user()),
        ]);
    }

    public function index(Request $request)
    {
        $items = CompanyNotificationService::latest($request->user(), (int) $request->input('limit', 30), $request->boolean('unread_only', false));

        return response()->json([
            'success' => true,
            'count' => $items->count(),
            'data' => $items,
        ]);
    }

    public function markAllRead(Request $request)
    {
        $updated = CompanyNotificationService::markAllRead($request->user());

        if (!$request->expectsJson()) {
            return back();
        }

        return response()->json([
            'success' => true,
            'updated' => $updated,
        ]);
    }

    public function markModuleRead(Request $request)
    {
        $request->validate(['module' => 'required']);

        $updated = CompanyNotificationService::markModuleRead($request->user(), $request->input('module'));

        if (!$request->expectsJson()) {
            return back();
        }

        return response()->json([
            'success' => true,
            'updated' => $updated,
        ]);
    }
}
