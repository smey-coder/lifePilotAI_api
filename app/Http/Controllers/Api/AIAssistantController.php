<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Models\AiThread;
use App\Models\AiMessage;
use App\Services\AIService;
use Throwable;

class AIAssistantController extends Controller
{
    /**
     * Send a message to the selected AI provider.
     *
     * Supported providers: OpenAI, Gemini, Groq.
     */
    public function sendMessage(Request $request)
    {
        $validated = $request->validate([
            'message' => 'required|string|max:10000',
            'thread_id' => 'nullable|integer|exists:ai_threads,id',
            'provider' => 'nullable|string|in:openai,gemini,groq',
        ]);

        $user = $request->user();

        if (!$user) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthenticated.',
            ], 401);
        }

        $provider = $validated['provider'] ?? 'openai';
        $message = $validated['message'];

        // 1. Find an existing thread owned by the user,
        // or create a new thread.
        if (!empty($validated['thread_id'])) {
            $thread = AiThread::where('id', $validated['thread_id'])
                ->where('user_id', $user->id)
                ->firstOrFail();
        } else {
            $thread = AiThread::create([
                'user_id' => $user->id,
                'title' => mb_substr($message, 0, 30, 'UTF-8'),
            ]);
        }

        // 2. Prepare conversation history BEFORE saving
        // the new message, so the current message is added once.
        $history = AiMessage::where('ai_thread_id', $thread->id)
            ->latest('id')
            ->take(9)
            ->get()
            ->reverse()
            ->map(fn ($item) => [
                'role' => $item->role,
                'content' => $item->content,
            ])
            ->values()
            ->toArray();

        $systemPrompt = [
            'role' => 'system',
            'content' => 'You are Life Pilot AI, a helpful personal
assistant. Answer politely, clearly, and accurately. When the
user writes in Khmer, respond in Khmer. Otherwise, respond in
the language used by the user.',
        ];

        $fullMessages = array_merge(
            [$systemPrompt],
            $history,
            [[
                'role' => 'user',
                'content' => $message,
            ]]
        );

        // 3. Select the AI provider and model.
        $modelName = match ($provider) {
            'groq' => config(
                'services.groq.model',
                'openai/gpt-oss-20b'
            ),
            'gemini' => config(
                'services.gemini.model',
                'gemini-2.5-flash'
            ),
            default => config(
                'services.openai.model',
                'gpt-4o-mini'
            ),
        };

        try {
            // 4. Call the selected AI service.
            $aiContent = match ($provider) {
                'groq' => AIService::askGroq($fullMessages),
                'gemini' => AIService::askGemini($fullMessages),
                default => AIService::askOpenAI($fullMessages),
            };

            // Do not save a returned API error as a normal reply.
            if (
                !is_string($aiContent) ||
                trim($aiContent) === '' ||
                str_starts_with($aiContent, 'Groq Error:') ||
                str_starts_with($aiContent, 'OpenAI Error:') ||
                str_starts_with($aiContent, 'Gemini Error:')
            ) {
                Log::warning('Life Pilot AI provider returned an error.', [
                    'provider' => $provider,
                    'user_id' => $user->id,
                    'thread_id' => $thread->id,
                ]);

                return response()->json([
                    'status' => 'error',
                    'message' => is_string($aiContent)
                        && trim($aiContent) !== ''
                        ? $aiContent
                        : 'The AI provider returned an empty response.',
                    'thread_id' => $thread->id,
                    'provider_used' => $provider,
                ], 502, [], JSON_INVALID_UTF8_SUBSTITUTE);
            }

            // 5. Save the user message only after the AI call succeeds.
            AiMessage::create([
                'ai_thread_id' => $thread->id,
                'role' => 'user',
                'content' => $message,
                'provider' => $provider,
            ]);

            // 6. Save the successful assistant reply.
            $aiMessage = AiMessage::create([
                'ai_thread_id' => $thread->id,
                'role' => 'assistant',
                'content' => $aiContent,
                'provider' => $provider,
                'model' => $modelName,
            ]);

            // 7. Return the response.
            return response()->json([
                'status' => 'success',
                'thread_id' => $thread->id,
                'provider_used' => $provider,
                'model' => $modelName,
                'message' => $aiMessage,
            ], 200, [], JSON_INVALID_UTF8_SUBSTITUTE);

        } catch (Throwable $e) {
            Log::error('Life Pilot AI request failed.', [
                'provider' => $provider,
                'user_id' => $user->id,
                'thread_id' => $thread->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Unable to get an AI response. Please try again.',
                'thread_id' => $thread->id,
                'provider_used' => $provider,
            ], 502, [], JSON_INVALID_UTF8_SUBSTITUTE);
        }
    }

    /**
     * Get all chat messages for a specific thread
     */
    public function getMessages(Request $request, $threadId)
    {
        $user = $request->user();

        $thread = AiThread::where('id', $threadId)
            ->where('user_id', $user->id)
            ->with('messages')
            ->firstOrFail();

        return response()->json([
            'status' => 'success',
            'messages' => $thread->messages->map(function($msg) {
                return [
                    'id' => $msg->id,
                    'role' => $msg->role,
                    'content' => $msg->content,
                    'model' => $msg->model,
                    'created_at' => $msg->created_at,
                ];
            })
        ], 200, [], JSON_INVALID_UTF8_SUBSTITUTE);
    }

    /**
     * Get all chat threads belonging to the logged-in user.
     */
    public function getThreads(Request $request)
    {
        $threads = AiThread::where('user_id', $request->user()->id)
            ->latest()
            ->get();

        return response()->json([
            'status' => 'success',
            'threads' => $threads,
        ], 200, [], JSON_INVALID_UTF8_SUBSTITUTE);
    }

    /**
     * Delete specific AI chat thread and its messages
     */
    public function destroyThread($threadId, Request $request)
    {
        try {
            $thread = AiThread::where('id', $threadId)
                ->where('user_id', $request->user()->id)
                ->firstOrFail();

            // លុបសារទាំងអស់ក្នុង Thread នោះជាមុនសិន (បើគ្មាន Cascade on delete ក្នុង Database)
            $thread->messages()->delete();
            
            // លុប Thread ចោល
            $thread->delete();

            return response()->json([
                'status' => 'success',
                'message' => 'បានលុបប្រវត្តិសន្ទនាដោយជោគជ័យ'
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'រកមិនឃើញកិច្ចសន្ទនា ឬមានបញ្ហាក្នុងការលុប'
            ], 404);
        }
    }
}
