<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\Log;
use App\Models\Reminder;
use App\Notifications\ReminderNotification;
use Carbon\Carbon;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// រត់រៀងរាល់នាទីដើម្បីឆែក Reminder
Schedule::call(function () {
    // ស្វែងរក Reminder ណាដែលជិតដល់ម៉ោង/ដល់ម៉ោង ហើយមិនទាន់បានផ្ញើ (status pending)
    $reminders = Reminder::where('status', 'pending')
        ->where('remind_at', '<=', Carbon::now())
        ->get();

    foreach ($reminders as $reminder) {
        // ផ្ញើសារទៅកាន់ User តាម Telegram / Email
        $reminder->user->notify(new ReminderNotification($reminder));

        // ប្តូរ Status ទៅជា sent ដើម្បីកុំឱ្យវាផ្ញើសារជាន់គ្នា
        $reminder->update(['status' => 'sent']);
    }
})->everyMinute();