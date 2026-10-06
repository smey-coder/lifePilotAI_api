<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Habit;
use App\Models\Task;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Carbon\Carbon;

class UserDashboardController extends Controller
{
    /**
     * Fetch user-specific dashboard data, daily habit completions, and active streaks.
     */
    public function index(Request $request): JsonResponse
    {
        $userId = $request->user()->id;
        $today = Carbon::today()->toDateString();

        // Fetch user's habits along with today's completion status from habit_logs
        $habits = Habit::where('user_id', $userId)
            ->withExists(['logs as is_completed_today' => function ($query) use ($today) {
                $query->whereDate('completed_date', $today);
            }])
            ->get();

        $totalHabits = $habits->count();
        $completedHabitsCount = $habits->where('is_completed_today', true)->count();
        $completionPercentage = $totalHabits > 0 ? round(($completedHabitsCount / $totalHabits) * 100) : 0;

        // Calculate max streak or overall active streak across habits
        $maxStreak = $habits->max('streak_count') ?? 0;

        // Fetch pending tasks due today
        $pendingTasksCount = Task::where('user_id', $userId)
            ->whereDate('due_date', $today)
            ->where('status', '!=', 'completed')
            ->count();

        // Format habit items for frontend dashboard display
        $formattedHabits = $habits->map(function ($habit) {
            return [
                'id' => $habit->id,
                'title' => $habit->title,
                'frequency' => $habit->frequency,
                'streak_count' => $habit->streak_count,
                'completed' => (bool) $habit->is_completed_today,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => [
                'user' => [
                    'id' => $request->user()->id,
                    'name' => $request->user()->name,
                ],
                'metrics' => [
                    'habit_completion_rate' => $completionPercentage,
                    'completed_habits' => $completedHabitsCount,
                    'total_habits' => $totalHabits,
                    'streak_days' => $maxStreak,
                    'pending_tasks_today' => $pendingTasksCount,
                ],
                'habits' => $formattedHabits,
                'ai_suggestion' => "You have completed {$completedHabitsCount} of {$totalHabits} habits today! Keep up your {$maxStreak}-day streak.",
            ],
        ], 200);
    }
}