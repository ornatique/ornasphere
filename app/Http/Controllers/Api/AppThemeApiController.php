<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CompanyAppTheme;
use Illuminate\Http\Request;

class AppThemeApiController extends Controller
{
    public function active(Request $request)
    {
        $companyId = (int) optional($request->user())->company_id;

        $theme = CompanyAppTheme::where('company_id', $companyId)
            ->where('is_active', true)
            ->latest()
            ->first();

        return response()->json([
            'success' => true,
            'message' => 'App theme fetched successfully.',
            'data' => $theme ? $theme->toAppPayload() : CompanyAppTheme::defaultPayload(),
        ]);
    }
}
