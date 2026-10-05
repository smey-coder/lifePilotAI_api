<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Task;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class TaskController extends Controller
{
    /**
     * Display a listing of tasks (Supports Search, Status/Priority Filter & Pagination)
     */
    public function index(Request $request)
    {
        try {
            $user = $request->user();
            $search = $request->input('search');
            $status = $request->input('status');
            $priority = $request->input('priority');

            // ទាញយកតែ Task របស់ User ដែលកំពុង Login ស្វ័យប្រវត្តិ
            $query = Task::where('user_id', $user->id)
                ->when($search, function ($q) use ($search) {
                    $q->where(function ($sub) use ($search) {
                        $sub->where('title', 'LIKE', "%{$search}%")
                            ->orWhere('description', 'LIKE', "%{$search}%");
                    });
                })
                ->when($status, function ($q) use ($status) {
                    $q->where('status', $status);
                })
                ->when($priority, function ($q) use ($priority) {
                    $q->where('priority', $priority);
                })
                ->orderBy('due_date', 'asc')
                ->orderBy('id', 'desc');

            if ($request->boolean('all')) {
                $tasks = $query->get();
            } else {
                $perPage = $request->input('per_page', 10);
                $tasks = $query->paginate($perPage);
            }

            return response()->json([
                'success' => true,
                'message' => 'Tasks retrieved successfully',
                'data' => $tasks
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve tasks',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store a newly created task in storage.
     */
    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'title' => 'required|string|max:255',
                'description' => 'nullable|string',
                'status' => 'nullable|in:todo,in_progress,done',
                'priority' => 'nullable|in:low,medium,high,urgent',
                'due_date' => 'nullable|date',
            ]);

            $task = Task::create([
                'user_id' => $request->user()->id,
                'title' => $validated['title'],
                'description' => $validated['description'] ?? null,
                'status' => $validated['status'] ?? 'todo',
                'priority' => $validated['priority'] ?? 'medium',
                'due_date' => $validated['due_date'] ?? null,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Task created successfully',
                'data' => $task
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
                'message' => 'Create task failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified task.
     */
    public function show(Request $request, $id)
    {
        try {
            $task = Task::where('user_id', $request->user()->id)->find($id);

            if (!$task) {
                return response()->json([
                    'success' => false,
                    'message' => 'Task not found'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $task
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Get task failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update the specified task.
     */
    public function update(Request $request, $id)
    {
        try {
            $task = Task::where('user_id', $request->user()->id)->find($id);

            if (!$task) {
                return response()->json([
                    'success' => false,
                    'message' => 'Task not found'
                ], 404);
            }

            $validated = $request->validate([
                'title' => 'sometimes|required|string|max:255',
                'description' => 'nullable|string',
                'status' => 'nullable|in:todo,in_progress,done',
                'priority' => 'nullable|in:low,medium,high,urgent',
                'due_date' => 'nullable|date',
            ]);

            $task->update(array_filter([
                'title' => $validated['title'] ?? $task->title,
                'description' => array_key_exists('description', $validated) ? $validated['description'] : $task->description,
                'status' => $validated['status'] ?? $task->status,
                'priority' => $validated['priority'] ?? $task->priority,
                'due_date' => array_key_exists('due_date', $validated) ? $validated['due_date'] : $task->due_date,
            ], fn ($val) => $val !== null));

            return response()->json([
                'success' => true,
                'message' => 'Task updated successfully',
                'data' => $task
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
                'message' => 'Update task failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update task status only (Quick Drag-and-drop / Status Switch)
     */
    public function updateStatus(Request $request, $id)
    {
        try {
            $task = Task::where('user_id', $request->user()->id)->find($id);

            if (!$task) {
                return response()->json([
                    'success' => false,
                    'message' => 'Task not found'
                ], 404);
            }

            $validated = $request->validate([
                'status' => 'required|in:todo,in_progress,done'
            ]);

            $task->update(['status' => $validated['status']]);

            return response()->json([
                'success' => true,
                'message' => 'Task status updated successfully',
                'data' => $task
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Update task status failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified task from storage.
     */
    public function destroy(Request $request, $id)
    {
        try {
            $task = Task::where('user_id', $request->user()->id)->find($id);

            if (!$task) {
                return response()->json([
                    'success' => false,
                    'message' => 'Task not found'
                ], 404);
            }

            $task->delete();

            return response()->json([
                'success' => true,
                'message' => 'Task deleted successfully'
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Delete task failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}