<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Goal;
use App\Models\GoalMilestone;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class GoalController extends Controller
{
    /**
     * Get all goals with milestones for the authenticated user.
     */
    public function index(Request $request): JsonResponse
    {
        $goals = Goal::with('milestones')
            ->where('user_id', $request->user()->id)
            ->latest()
            ->get();

        return response()->json($goals);
    }

    /**
     * Store a new goal and its initial milestones.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'target_date' => 'nullable|date',
            'status' => 'nullable|in:on_track,behind,completed',
            'milestones' => 'nullable|array',
            'milestones.*' => 'required|string|max:255',
        ]);

        $goal = DB::transaction(function () use ($request, $validated) {
            $goal = Goal::create([
                'user_id' => $request->user()->id,
                'title' => $validated['title'],
                'description' => $validated['description'] ?? null,
                'target_date' => $validated['target_date'] ?? null,
                'status' => $validated['status'] ?? 'on_track',
                'progress_percentage' => 0,
            ]);

            if (!empty($validated['milestones'])) {
                foreach ($validated['milestones'] as $milestoneTitle) {
                    $goal->milestones()->create(['title' => $milestoneTitle]);
                }
                $goal->recalculateProgress();
            }

            return $goal;
        });

        return response()->json($goal->load('milestones'), 201);
    }

    /**
     * Display a specific goal.
     */
    public function show(Request $request, $id): JsonResponse
    {
        $goal = Goal::with('milestones')->where('id', $id)->firstOrFail();

        // Safe User Ownership Check (Loosely typed check for ID)
        if ((string)$goal->user_id !== (string)$request->user()->id) {
            return response()->json(['message' => 'Unauthorized access'], 403);
        }

        return response()->json($goal);
    }

    /**
     * Update goal details.
     */
    public function update(Request $request, $id): JsonResponse
    {
        $goal = Goal::where('id', $id)->firstOrFail();

        // Safe User Ownership Check
        if ((string)$goal->user_id !== (string)$request->user()->id) {
            return response()->json(['message' => 'Unauthorized access'], 403);
        }

        $validated = $request->validate([
            'title' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'target_date' => 'nullable|date',
            'status' => 'sometimes|in:on_track,behind,completed',
            'progress_percentage' => 'sometimes|integer|min:0|max:100',
            'milestones' => 'nullable|array',
            'milestones.*' => 'required|string|max:255',
        ]);

        DB::transaction(function () use ($goal, $validated) {
            $goal->update([
                'title' => $validated['title'] ?? $goal->title,
                'description' => $validated['description'] ?? $goal->description,
                'target_date' => $validated['target_date'] ?? $goal->target_date,
                'status' => $validated['status'] ?? $goal->status,
                'progress_percentage' => $validated['progress_percentage'] ?? $goal->progress_percentage,
            ]);

            // Synchronize milestones if passed
            if (isset($validated['milestones'])) {
                $goal->milestones()->delete();
                foreach ($validated['milestones'] as $milestoneTitle) {
                    $goal->milestones()->create(['title' => $milestoneTitle]);
                }
                $goal->recalculateProgress();
            }
        });

        return response()->json($goal->load('milestones'));
    }

    /**
     * Toggle milestone completion and update progress percentage.
     */
    public function toggleMilestone(Request $request, $id): JsonResponse
    {
        $milestone = GoalMilestone::where('id', $id)->firstOrFail();
        $goal = $milestone->goal;

        if (!$goal || (string)$goal->user_id !== (string)$request->user()->id) {
            return response()->json(['message' => 'Unauthorized access'], 403);
        }

        $milestone->update([
            'is_completed' => !$milestone->is_completed,
        ]);

        $goal->recalculateProgress();

        return response()->json($goal->load('milestones'));
    }

    /**
     * Delete a goal.
     */
    public function destroy(Request $request, $id): JsonResponse
    {
        $goal = Goal::where('id', $id)->firstOrFail();

        if ((string)$goal->user_id !== (string)$request->user()->id) {
            return response()->json(['message' => 'Unauthorized access'], 403);
        }

        $goal->delete();

        return response()->json(['message' => 'Goal deleted successfully']);
    }
}