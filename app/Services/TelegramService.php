<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TelegramService
{
    public static function sendMessage(string $chatId, string $message): bool
    {
        $token = env('TELEGRAM_BOT_TOKEN');

        if (!$token) {
            Log::error("Telegram Service Error: TELEGRAM_BOT_TOKEN missing in .env");
            return false;
        }

        $url = "https://api.telegram.org/bot{$token}/sendMessage";

        try {
            // withoutVerifying() ការពារបញ្ហា cURL error 60 (SSL certificate issue លើ Localhost)
            $response = Http::withoutVerifying()->post($url, [
                'chat_id' => $chatId,
                'text' => $message,
                'parse_mode' => 'HTML',
            ]);

            if ($response->successful()) {
                return true;
            }

            Log::error("Telegram API Error Response: " . $response->body());
            return false;
        } catch (\Exception $e) {
            Log::error("Telegram Notification Exception: " . $e->getMessage());
            return false;
        }
    }
}