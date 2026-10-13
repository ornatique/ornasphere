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
        if ($request->boolean('paginate')) {
            $request->validate(['before_id'=>'nullable|integer|min:1']);
            $items = CompanyNotificationService::latest($request->user(), 21, true, $request->integer('before_id') ?: null);
            $hasMore = $items->count() > 20;
            $items = $items->take(20)->values();
            return response()->json([
                'success'=>true, 'has_more'=>$hasMore, 'next_cursor'=>$items->last()?->id,
                'data'=>$items->map(function ($item) {
                    $url = '#';
                    if ($item->route_name && \Illuminate\Support\Facades\Route::has($item->route_name)) {
                        try {
                            $url = route($item->route_name, $item->route_params ?: ['slug'=>auth()->user()->company->slug]);
                            $url .= (str_contains($url, '?') ? '&' : '?').'mark_notifications_read=1';
                        } catch (\Throwable $e) { $url = '#'; }
                    }
                    return ['id'=>$item->id,'title'=>$item->title,'message'=>$item->message,'time'=>$item->created_at?->diffForHumans(),'url'=>$url];
                }),
            ]);
        }
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
