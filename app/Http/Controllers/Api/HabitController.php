<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Habit;
use App\Models\HabitLog;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class HabitController extends Controller
{
    /**
     * Get all habits with recent logs for the authenticated user.
     */
    public function index(Request $request): JsonResponse
    {
        $habits = Habit::with(['logs' => function ($query) {
            $query->where('completed_date', '>=', Carbon::now()->subDays(30));
        }])
        ->where('user_id', $request->user()->id)
        ->latest()
        ->get();

        return response()->json($habits);
    }

    /**
     * Create a new habit.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'frequency' => 'nullable|string|max:50',
        ]);

        $habit = Habit::create([
            'user_id' => $request->user()->id,
            'title' => $validated['title'],
            'frequency' => $validated['frequency'] ?? 'daily',
            'streak_count' => 0,
        ]);

        return response()->json($habit->load('logs'), 201);
    }

    /**
     * Display a specific habit.
     */
    public function show(Request $request, $id): JsonResponse
    {
        $habit = Habit::with('logs')->where('id', $id)->firstOrFail();

        if ((string)$habit->user_id !== (string)$request->user()->id) {
            return response()->json(['message' => 'Unauthorized access'], 403);
        }

        return response()->json($habit);
    }

    /**
     * Update an existing habit.
     */
    public function update(Request $request, $id): JsonResponse
    {
        $habit = Habit::where('id', $id)->firstOrFail();

        if ((string)$habit->user_id !== (string)$request->user()->id) {
            return response()->json(['message' => 'Unauthorized access'], 403);
        }

        $validated = $request->validate([
            'title' => 'sometimes|string|max:255',
            'frequency' => 'sometimes|string|max:50',
        ]);

        $habit->update($validated);

        return response()->json($habit->load('logs'));
    }

    /**
     * Toggle habit mark complete for a specific date & recalculate streak.
     */
    public function toggleLog(Request $request, $id): JsonResponse
    {
        $habit = Habit::where('id', $id)->firstOrFail();

        if ((string)$habit->user_id !== (string)$request->user()->id) {
            return response()->json(['message' => 'Unauthorized access'], 403);
        }

        $date = $request->input('completed_date', Carbon::today()->toDateString());

        DB::transaction(function () use ($habit, $date) {
            $existingLog = HabitLog::where('habit_id', $habit->id)
                ->where('completed_date', $date)
                ->first();

            if ($existingLog) {
                $existingLog->delete();
            } else {
                HabitLog::create([
                    'habit_id' => $habit->id,
                    'completed_date' => $date,
                ]);
            }

            // Recalculate streak_count
            $this->recalculateStreak($habit);
        });

        return response()->json($habit->fresh(['logs']));
    }

    /**
     * Delete a habit.
     */
    public function destroy(Request $request, $id): JsonResponse
    {
        $habit = Habit::where('id', $id)->firstOrFail();

        if ((string)$habit->user_id !== (string)$request->user()->id) {
            return response()->json(['message' => 'Unauthorized access'], 403);
        }

        $habit->delete();

        return response()->json(['message' => 'Habit deleted successfully']);
    }

    /**
     * Helper to recalculate consecutive streak days.
     */
    private function recalculateStreak(Habit $habit): void
    {
        $logs = HabitLog::where('habit_id', $habit->id)
            ->orderBy('completed_date', 'desc')
            ->pluck('completed_date')
            ->map(fn($d) => Carbon::parse($d)->toDateString())
            ->toArray();

        if (empty($logs)) {
            $habit->update(['streak_count' => 0]);
            return;
        }

        $streak = 0;
        $checkDate = Carbon::today();

        // If today isn't completed yet, check starting from yesterday
        if (!in_array($checkDate->toDateString(), $logs)) {
            $checkDate->subDay();
        }

        while (in_array($checkDate->toDateString(), $logs)) {
            $streak++;
            $checkDate->subDay();
        }

        $habit->update(['streak_count' => $streak]);
    }
}