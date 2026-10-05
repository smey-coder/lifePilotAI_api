<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Reminder;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ReminderController extends Controller
{
    /**
     * Display a listing of reminders with search & filtering
     */
    public function index(Request $request)
    {
        try {
            $user = $request->user();
            $search = $request->input('search');
            $channel = $request->input('channel');
            $frequency = $request->input('frequency');

            $query = Reminder::where('user_id', $user->id)
                ->when($search, function ($q) use ($search) {
                    $q->where('title', 'LIKE', "%{$search}%");
                })
                ->when($channel, function ($q) use ($channel) {
                    $q->where('channel', $channel);
                })
                ->when($frequency, function ($q) use ($frequency) {
                    $q->where('frequency', $frequency);
                })
                ->orderBy('remind_at', 'asc')
                ->orderBy('id', 'desc');

            if ($request->boolean('all')) {
                $reminders = $query->get();
            } else {
                $perPage = $request->input('per_page', 10);
                $reminders = $query->paginate($perPage);
            }

            return response()->json([
                'success' => true,
                'message' => 'Reminders retrieved successfully',
                'data' => $reminders
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve reminders',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store a newly created reminder.
     */
    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'title' => 'required|string|max:255',
                'remind_at' => 'required|date',
                'frequency' => 'nullable|in:once,daily,weekly,monthly',
                'channel' => 'nullable|in:browser,email,telegram',
            ]);

            $reminder = Reminder::create([
                'user_id' => $request->user()->id,
                'title' => $validated['title'],
                'remind_at' => $validated['remind_at'],
                'frequency' => $validated['frequency'] ?? 'once',
                'channel' => $validated['channel'] ?? 'browser',
                'is_triggered' => false,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Reminder created successfully',
                'data' => $reminder
            ], 201);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Create reminder failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified reminder.
     */
    public function show(Request $request, $id)
    {
        try {
            $reminder = Reminder::where('user_id', $request->user()->id)->find($id);

            if (!$reminder) {
                return response()->json([
                    'success' => false,
                    'message' => 'Reminder not found'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $reminder
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Get reminder failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update the specified reminder.
     */
    public function update(Request $request, $id)
    {
        try {
            $reminder = Reminder::where('user_id', $request->user()->id)->find($id);

            if (!$reminder) {
                return response()->json([
                    'success' => false,
                    'message' => 'Reminder not found'
                ], 404);
            }

            $validated = $request->validate([
                'title' => 'sometimes|required|string|max:255',
                'remind_at' => 'sometimes|required|date',
                'frequency' => 'nullable|in:once,daily,weekly,monthly',
                'channel' => 'nullable|in:browser,email,telegram',
                'is_triggered' => 'nullable|boolean',
            ]);

            $reminder->update([
                'title' => $validated['title'] ?? $reminder->title,
                'remind_at' => $validated['remind_at'] ?? $reminder->remind_at,
                'frequency' => $validated['frequency'] ?? $reminder->frequency,
                'channel' => $validated['channel'] ?? $reminder->channel,
                'is_triggered' => isset($validated['is_triggered']) ? $validated['is_triggered'] : $reminder->is_triggered,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Reminder updated successfully',
                'data' => $reminder
            ], 200);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Update reminder failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Toggle Triggered Status
     */
    public function toggleTriggered(Request $request, $id)
    {
        try {
            $reminder = Reminder::where('user_id', $request->user()->id)->find($id);

            if (!$reminder) {
                return response()->json([
                    'success' => false,
                    'message' => 'Reminder not found'
                ], 404);
            }

            $reminder->update(['is_triggered' => !$reminder->is_triggered]);

            return response()->json([
                'success' => true,
                'message' => 'Reminder status toggled successfully',
                'data' => $reminder
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Toggle reminder failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified reminder.
     */
    public function destroy(Request $request, $id)
    {
        try {
            $reminder = Reminder::where('user_id', $request->user()->id)->find($id);

            if (!$reminder) {
                return response()->json([
                    'success' => false,
                    'message' => 'Reminder not found'
                ], 404);
            }

            $reminder->delete();

            return response()->json([
                'success' => true,
                'message' => 'Reminder deleted successfully'
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Delete reminder failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}