<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController; 
use App\Http\Controllers\Api\GoogleAuthController;
use App\Http\Controllers\Api\PermissionController;
use App\Http\Controllers\Api\RoleController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\TaskController;
use App\Http\Controllers\Api\NoteController;
use App\Http\Controllers\Api\ReminderController;
use App\Http\Controllers\Api\GoalController;
use App\Http\Controllers\Api\HabitController;
use App\Http\Controllers\Api\UserDashboardController;
use App\Http\Controllers\Api\AdminDashboardController;
use App\Http\Controllers\Api\SettingController;
use App\Http\Controllers\Api\UserSettingsController;    
use App\Http\Controllers\Api\ProfileController;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// Public routes
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
Route::post('/reset-password', [AuthController::class, 'resetPassword']);

Route::get('/auth/google/redirect', [GoogleAuthController::class, 'redirect']);
Route::get('/auth/google/callback', [GoogleAuthController::class, 'callback']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/dashboard', [AuthController::class, 'dashboard']);
    Route::post('/logout', [AuthController::class, 'logout']);

    //Permission routes
    Route::get('/permissions', [PermissionController::class, 'index']);
    Route::get('/permissions/{id}', [PermissionController::class, 'show']);
    Route::post('/permissions', [PermissionController::class, 'store']);
    Route::put('/permissions/{id}', [PermissionController::class, 'update']);
    Route::delete('/delete/permissions/{id}', [PermissionController::class, 'destroy']);

    //Role routes
    Route::get('/roles', [RoleController::class, 'index']);
    Route::get('/roles/{id}', [RoleController::class, 'show']);
    Route::post('/roles', [RoleController::class, 'store']);
    Route::put('/roles/{id}', [RoleController::class, 'update']);
    Route::delete('/roles/{id}', [RoleController::class, 'destroy']);

    //User routes
    Route::get('/users', [UserController::class, 'index']);
    Route::get('/users/{id}', [UserController::class, 'show']);
    Route::post('/users', [UserController::class, 'store']);
    Route::put('/users/{id}', [UserController::class, 'update']);
    Route::delete('/users/{id}', [UserController::class, 'destroy']);

    // Task Management API Routes
    Route::get('/tasks', [TaskController::class, 'index']);
    Route::post('/tasks', [TaskController::class, 'store']);
    Route::get('/tasks/{id}', [TaskController::class, 'show']);
    Route::put('/tasks/{id}', [TaskController::class, 'update']);
    Route::patch('/tasks/{id}/status', [TaskController::class, 'updateStatus']); // Quick status toggle
    Route::delete('/tasks/{id}', [TaskController::class, 'destroy']);

    // Note Management Routes
    Route::get('/notes', [NoteController::class, 'index']);
    Route::post('/notes', [NoteController::class, 'store']);
    Route::get('/notes/{id}', [NoteController::class, 'show']);
    Route::put('/notes/{id}', [NoteController::class, 'update']);
    Route::patch('/notes/{id}/pin', [NoteController::class, 'togglePin']);
    Route::delete('/notes/{id}', [NoteController::class, 'destroy']);

    // Intelligent Reminders Routes
    Route::get('/reminders', [ReminderController::class, 'index']);
    Route::post('/reminders', [ReminderController::class, 'store']);
    Route::get('/reminders/{id}', [ReminderController::class, 'show']);
    Route::put('/reminders/{id}', [ReminderController::class, 'update']);
    Route::patch('/reminders/{id}/toggle', [ReminderController::class, 'toggleTriggered']);
    Route::delete('/reminders/{id}', [ReminderController::class, 'destroy']);
    Route::get('/run-scheduler', function () {
        try {
            // ១. លុប cache:clear ចេញ ដើម្បីការពារ Permission Denied Exception លើ Render
            // ២. រត់ Artisan Command ដោយផ្ទាល់
            $exitCode = Artisan::call('reminders:process');
            $output = Artisan::output();

            return response()->json([
                'status' => 'success',
                'exit_code' => $exitCode,
                'output' => trim($output)
            ], 200);

        } catch (\Throwable $e) {
            // កត់ត្រាចូល Render Logs
            Log::error("Scheduler Error: " . $e->getMessage());

            // បង្វិល HTTP Status 200 មកវិញ ដើម្បីកុំឱ្យ Cron-job.org ចាប់បាន Error 500
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ], 200);
        }
    });

    // Goal Management Routes
    Route::get('/goals', [GoalController::class, 'index']);
    Route::post('/goals', [GoalController::class, 'store']);
    Route::get('/goals/{id}', [GoalController::class, 'show']);
    Route::put('/goals/{id}', [GoalController::class, 'update']);
    Route::delete('/goals/{id}', [GoalController::class, 'destroy']);
    Route::patch('milestones/{milestone}/toggle', [GoalController::class, 'toggleMilestone']);

    //Habit Management Routes
    Route::get('/habits', [HabitController::class, 'index']);
    Route::post('/habits', [HabitController::class, 'store']);
    Route::get('/habits/{id}', [HabitController::class, 'show']);
    Route::put('/habits/{id}', [HabitController::class, 'update']);
    Route::post('/habits/{id}/toggle', [HabitController::class, 'toggleLog']);
    Route::delete('/habits/{id}', [HabitController::class, 'destroy']);

    // Standard User Dashboard API
    Route::get('/dashboard/user', [UserDashboardController::class, 'index']);

    // Admin Dashboard API (Protected by 'role:admin' or 'permission:view-admin-dashboard')
    Route::middleware(['role:Admin'])->group(function () {
        Route::get('/dashboard/admin', [AdminDashboardController::class, 'index']);
    });

    // 2. Admin Only (Global Platform Settings)
    Route::middleware(['auth:sanctum', 'role:Admin'])->prefix('admin')->group(function () {
        Route::get('/settings', [SettingController::class, 'index']);
        Route::put('/settings', [SettingController::class, 'update']);
        Route::post('/settings/clear-cache', [SettingController::class, 'clearCache']);
    });

    // 1. All Authenticated Users (User Settings)
    Route::middleware(['auth:sanctum'])->prefix('user')->group(function () {
        Route::get('/settings', [UserSettingsController::class, 'show']);
        Route::put('/profile', [UserSettingsController::class, 'updateProfile']);
        Route::put('/password', [UserSettingsController::class, 'updatePassword']);
    });

    // User Profile API
    Route::get('/profile', [ProfileController::class, 'show']);
    Route::put('/profile', [ProfileController::class, 'update']);
});