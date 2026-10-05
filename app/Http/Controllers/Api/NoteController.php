<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Note;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class NoteController extends Controller
{
    /**
     * Display a listing of user notes (Supports Search, Category Filter, Pin Sorting & Pagination)
     */
    public function index(Request $request)
    {
        try {
            $user = $request->user();
            $search = $request->input('search');
            $category = $request->input('category');

            $query = Note::where('user_id', $user->id)
                ->when($search, function ($q) use ($search) {
                    $q->where(function ($sub) use ($search) {
                        $sub->where('title', 'LIKE', "%{$search}%")
                            ->orWhere('content', 'LIKE', "%{$search}%")
                            ->orWhere('category', 'LIKE', "%{$search}%");
                    });
                })
                ->when($category, function ($q) use ($category) {
                    $q->where('category', $category);
                })
                ->orderBy('is_pinned', 'desc') // Pin លើគេជានិច្ច
                ->orderBy('updated_at', 'desc');

            if ($request->boolean('all')) {
                $notes = $query->get();
            } else {
                $perPage = $request->input('per_page', 9);
                $notes = $query->paginate($perPage);
            }

            return response()->json([
                'success' => true,
                'message' => 'Notes retrieved successfully',
                'data' => $notes
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve notes',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store a newly created note.
     */
    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'title' => 'required|string|max:255',
                'content' => 'nullable|string',
                'category' => 'nullable|string|max:100',
                'tags' => 'nullable|array',
                'tags.*' => 'string|max:50',
                'is_pinned' => 'nullable|boolean',
            ]);

            $note = Note::create([
                'user_id' => $request->user()->id,
                'title' => $validated['title'],
                'content' => $validated['content'] ?? null,
                'category' => $validated['category'] ?? 'General',
                'tags' => $validated['tags'] ?? [],
                'is_pinned' => $validated['is_pinned'] ?? false,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Note created successfully',
                'data' => $note
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
                'message' => 'Create note failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified note.
     */
    public function show(Request $request, $id)
    {
        try {
            $note = Note::where('user_id', $request->user()->id)->find($id);

            if (!$note) {
                return response()->json([
                    'success' => false,
                    'message' => 'Note not found'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $note
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Get note failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update the specified note.
     */
    public function update(Request $request, $id)
    {
        try {
            $note = Note::where('user_id', $request->user()->id)->find($id);

            if (!$note) {
                return response()->json([
                    'success' => false,
                    'message' => 'Note not found'
                ], 404);
            }

            $validated = $request->validate([
                'title' => 'sometimes|required|string|max:255',
                'content' => 'nullable|string',
                'category' => 'nullable|string|max:100',
                'tags' => 'nullable|array',
                'tags.*' => 'string|max:50',
                'is_pinned' => 'nullable|boolean',
            ]);

            $note->update([
                'title' => $validated['title'] ?? $note->title,
                'content' => array_key_exists('content', $validated) ? $validated['content'] : $note->content,
                'category' => $validated['category'] ?? $note->category,
                'tags' => $validated['tags'] ?? $note->tags,
                'is_pinned' => isset($validated['is_pinned']) ? $validated['is_pinned'] : $note->is_pinned,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Note updated successfully',
                'data' => $note
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
                'message' => 'Update note failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Toggle Pin status quickly
     */
    public function togglePin(Request $request, $id)
    {
        try {
            $note = Note::where('user_id', $request->user()->id)->find($id);

            if (!$note) {
                return response()->json([
                    'success' => false,
                    'message' => 'Note not found'
                ], 404);
            }

            $note->update(['is_pinned' => !$note->is_pinned]);

            return response()->json([
                'success' => true,
                'message' => 'Pin status updated',
                'data' => $note
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Toggle pin failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified note.
     */
    public function destroy(Request $request, $id)
    {
        try {
            $note = Note::where('user_id', $request->user()->id)->find($id);

            if (!$note) {
                return response()->json([
                    'success' => false,
                    'message' => 'Note not found'
                ], 404);
            }

            $note->delete();

            return response()->json([
                'success' => true,
                'message' => 'Note deleted successfully'
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Delete note failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}