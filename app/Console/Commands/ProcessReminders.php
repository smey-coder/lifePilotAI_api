<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Reminder;
use App\Mail\ReminderEmail;
use App\Services\TelegramService;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class ProcessReminders extends Command
{
    protected $signature = 'reminders:process';
    protected $description = 'Process and send pending email and telegram reminders';

    public function handle()
    {
        $now = Carbon::now();
        $this->info("Current Application Time: " . $now->toDateTimeString());

        // ទាញយក Reminder ទាំង Email និង Telegram ដែលដល់ម៉ោងរំលឹក (remind_at <= NOW)
        $reminders = Reminder::with('user')
            ->where('is_triggered', false)
            ->where('remind_at', '<=', $now)
            ->get();

        if ($reminders->isEmpty()) {
            $this->warn('No pending reminders match the criteria (remind_at <= NOW).');
            return 0;
        }

        $emailCount = 0;
        $telegramCount = 0;

        foreach ($reminders as $reminder) {
            $user = $reminder->user;

            // 1. ដំណើរការផ្ញើ Email (ប្រើ try-catch ដើម្បីការពារកុំឱ្យស្ទះដំណើរការ)
            if ($reminder->channel === 'email') {
                $userEmail = $user ? $user->email : null;
                if ($userEmail) {
                    try {
                        Mail::to($userEmail)->send(new ReminderEmail($reminder));
                        $emailCount++;
                        $this->info("Sent EMAIL to: {$userEmail} (ID: {$reminder->id})");
                    } catch (\Exception $e) {
                        Log::error("Failed to send EMAIL for Reminder ID {$reminder->id}: " . $e->getMessage());
                        $this->error("Failed to send EMAIL (ID: {$reminder->id}). Check logs.");
                    }
                }
            }

            // 2. ដំណើរការផ្ញើ Telegram
            if ($reminder->channel === 'telegram') {
                $chatId = ($user && $user->telegram_chat_id) 
                    ? $user->telegram_chat_id 
                    : (config('services.telegram.default_chat_id') ?? env('TELEGRAM_DEFAULT_CHAT_ID'));

                if ($chatId) {
                    $formattedDate = Carbon::parse($reminder->remind_at)->format('Y-m-d h:i A');
                    $msg = "🔔 <b>ការរំលឹកពី LifePilot AI</b>\n\n"
                         . "📌 <b>ចំណងជើង:</b> {$reminder->title}\n"
                         . "⏰ <b>ម៉ោងរំលឹក:</b> {$formattedDate}\n"
                         . "🔄 <b>ការសារឡើងវិញ:</b> " . ucfirst($reminder->frequency);

                    $sent = TelegramService::sendMessage($chatId, $msg);
                    
                    if ($sent) {
                        $telegramCount++;
                        $this->info("Sent TELEGRAM to Chat ID: {$chatId} (ID: {$reminder->id})");
                    } else {
                        $this->error("Failed to send TELEGRAM (ID: {$reminder->id})");
                    }
                } else {
                    $this->error("Skipped Telegram Reminder ID: {$reminder->id} - Missing Chat ID.");
                }
            }

            // 3. អាប់ដេត Status ឬគណនាថ្ងៃស្អែកបើជា Repeat
            if ($reminder->frequency === 'once') {
                $reminder->update(['is_triggered' => true]);
            } else {
                $nextDate = match ($reminder->frequency) {
                    'daily' => Carbon::parse($reminder->remind_at)->addDay(),
                    'weekly' => Carbon::parse($reminder->remind_at)->addWeek(),
                    'monthly' => Carbon::parse($reminder->remind_at)->addMonth(),
                    default => $now,
                };

                $reminder->update([
                    'remind_at' => $nextDate,
                    'is_triggered' => false,
                ]);
            }
        }

        $this->info("Processed: {$emailCount} Email(s), {$telegramCount} Telegram Message(s).");
        return 0;
    }
}