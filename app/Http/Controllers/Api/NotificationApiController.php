<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\CompanyNotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

class NotificationApiController extends Controller
{
    public function summary(Request $request)
    {
        $summary = CompanyNotificationService::summary($request->user());

        return response()->json([
            'success' => true,
            'data' => [
                'total' => $summary['total'],
                'modules' => $summary['modules'],
                'labels' => $summary['labels'],
            ],
        ]);
    }

    public function index(Request $request)
    {
        $unreadOnly = $request->boolean('unread_only', false);
        $items = CompanyNotificationService::latest($request->user(), (int) $request->input('limit', 30), $unreadOnly)
            ->map(fn ($row) => $this->format($row))
            ->values();

        return response()->json([
            'success' => true,
            'count' => $items->count(),
            'data' => $items,
        ]);
    }

    public function markAllRead(Request $request)
    {
        $count = CompanyNotificationService::markAllRead($request->user());

        return response()->json([
            'success' => true,
            'updated' => $count,
        ]);
    }

    public function markModuleRead(Request $request)
    {
        $request->validate([
            'module' => 'required',
        ]);

        $count = CompanyNotificationService::markModuleRead($request->user(), $request->input('module'));

        return response()->json([
            'success' => true,
            'updated' => $count,
        ]);
    }

    private function format($row): array
    {
        return [
            'id' => $row->id,
            'module' => $row->module,
            'action' => $row->action,
            'title' => $row->title,
            'message' => $row->message,
            'actor' => optional($row->actor)->name,
            'route_name' => $row->route_name,
            'route_params' => $row->route_params,
            'url' => $this->notificationUrl($row),
            'redirect_url' => $this->notificationUrl($row),
            'subject_type' => $row->subject_type,
            'subject_id' => $row->subject_id,
            'is_read' => $row->read_at !== null,
            'read_at' => optional($row->read_at)->toDateTimeString(),
            'created_at' => optional($row->created_at)->toDateTimeString(),
        ];
    }

    private function notificationUrl($row): ?string
    {
        if (empty($row->route_name) || !Route::has($row->route_name)) {
            return null;
        }

        try {
            $url = route($row->route_name, $row->route_params ?: []);
        } catch (\Throwable $e) {
            return null;
        }

        return $url . (Str::contains($url, '?') ? '&' : '?') . 'mark_notifications_read=1';
    }
}
