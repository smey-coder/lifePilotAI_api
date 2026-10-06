<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Artisan;

class SettingController extends Controller
{
    /**
     * Fetch all active platform configuration settings.
     */
    public function index(): JsonResponse
    {
        $settings = Cache::remember('platform_settings', 3600, function () {
            return [
                'app_name' => config('app.name', 'LifePilot AI'),
                'maintenance_mode' => app()->isDownForMaintenance(),
                'allow_registration' => true,
                'two_factor_auth' => false,
                'max_login_attempts' => 5,
                'session_timeout_mins' => 120,
                'email_notifications' => true,
                'system_alerts' => true,
                'log_retention_days' => 30,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $settings,
        ], 200);
    }

    /**
     * Update platform configuration settings and refresh cache.
     */
    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'app_name' => 'required|string|max:255',
            'maintenance_mode' => 'required|boolean',
            'allow_registration' => 'required|boolean',
            'two_factor_auth' => 'required|boolean',
            'max_login_attempts' => 'required|integer|min:1|max:20',
            'session_timeout_mins' => 'required|integer|min:15|max:1440',
            'email_notifications' => 'required|boolean',
            'system_alerts' => 'required|boolean',
            'log_retention_days' => 'required|integer|min:7|max:365',
        ]);

        // Toggle Laravel Maintenance Mode if changed
        if ($validated['maintenance_mode'] && !app()->isDownForMaintenance()) {
            Artisan::call('down', ['--secret' => 'lifepilot-bypass']);
        } elseif (!$validated['maintenance_mode'] && app()->isDownForMaintenance()) {
            Artisan::call('up');
        }

        // Cache the updated settings array
        Cache::put('platform_settings', $validated, 3600);

        return response()->json([
            'success' => true,
            'message' => 'System settings updated successfully.',
            'data' => $validated,
        ], 200);
    }

    /**
     * Clear application system caches.
     */
    public function clearCache(): JsonResponse
    {
        Cache::flush();
        Artisan::call('config:clear');
        Artisan::call('route:clear');

        return response()->json([
            'success' => true,
            'message' => 'System cache successfully flushed.',
        ], 200);
    }
}