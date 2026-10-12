<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class AIService
{
    private static function cleanUtf8(?string $text): string
    {
        if (!$text) return '';

        $clean = iconv('UTF-8', 'UTF-8//IGNORE', $text);
        if ($clean === false) {
            $clean = mb_convert_encoding($text, 'UTF-8', 'UTF-8');
        }

        return $clean;
    }

    /**
     * Call OpenAI API (GPT-4o-mini)
     */
    public static function askOpenAI(array $messages)
    {
        $apiKey = config('services.openai.key') ?: env('OPENAI_API_KEY');

        $response = Http::withoutVerifying()
            ->withHeaders([
                'Authorization' => 'Bearer ' . $apiKey,
                'Content-Type' => 'application/json; charset=utf-8',
            ])->post('https://api.openai.com/v1/chat/completions', [
                'model' => 'gpt-4o-mini',
                'messages' => $messages,
            ]);

        $rawBody = self::cleanUtf8($response->body());
        $data = json_decode($rawBody, true);

        if (isset($data['error']['message'])) {
            return 'OpenAI Error: ' . $data['error']['message'];
        }

        return self::cleanUtf8($data['choices'][0]['message']['content'] ?? 'OpenAI API Error');
    }

   
    /**
     * Call Groq AI API (GPT-OSS 20B)
     */
    public static function askGroq(array $messages)
    {
        $apiKey = config('services.groq.key');

        if (empty($apiKey)) {
            return 'Groq Error: API key is missing.';
        }

        try {
            $response = Http::timeout(60)
                ->withToken($apiKey)
                ->acceptJson()
                ->post('https://api.groq.com/openai/v1/chat/completions', [
                    'model' => config(
                        'services.groq.model',
                        'openai/gpt-oss-20b'
                    ),
                    'messages' => $messages,
                    'temperature' => 0.7,
                ]);

            $data = $response->json();

            if ($response->failed()) {
                return 'Groq Error: ' . (
                    $data['error']['message']
                    ?? 'HTTP ' . $response->status()
                );
            }

            $content = $data['choices'][0]['message']['content'] ?? null;

            if (!is_string($content)) {
                return 'Groq Error: No response content received.';
            }

            return self::cleanUtf8($content);

        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            return 'Groq Error: Connection failed. Please try again.';
        } catch (\Throwable $e) {
            report($e);

            return 'Groq Error: An unexpected error occurred.';
        }
    }


    /**
     * Call Google Gemini API (gemini-3.8-flash)
     */
    public static function askGemini(array $messages)
    {
        $apiKey = config('services.gemini.key') ?: env('GEMINI_API_KEY');

        $contents = [];
        $systemInstruction = null;

        foreach ($messages as $msg) {
            if ($msg['role'] === 'system') {
                $systemInstruction = [
                    'parts' => [['text' => self::cleanUtf8($msg['content'])]]
                ];
                continue;
            }

            $role = ($msg['role'] === 'assistant' || $msg['role'] === 'model') ? 'model' : 'user';

            $contents[] = [
                'role' => $role,
                'parts' => [['text' => self::cleanUtf8($msg['content'])]]
            ];
        }

        // Endpoint ផ្លូវការសម្រាប់ gemini-3.8-flash
        $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-3.8-flash:generateContent?key={$apiKey}";

        $payload = ['contents' => $contents];
        if ($systemInstruction) {
            $payload['system_instruction'] = $systemInstruction;
        }

        $response = Http::withoutVerifying()
            ->withHeaders([
                'Content-Type' => 'application/json; charset=utf-8',
            ])->post($url, $payload);

        $rawBody = self::cleanUtf8($response->body());
        $data = json_decode($rawBody, true);

        if (isset($data['error']['message'])) {
            return 'Gemini Error: ' . $data['error']['message'];
        }

        return self::cleanUtf8($data['candidates'][0]['content']['parts'][0]['text'] ?? 'Gemini API Error');
    }
}