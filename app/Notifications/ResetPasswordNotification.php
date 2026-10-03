<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ResetPasswordNotification extends Notification
{
    use Queueable;

    public $token;

    public function __construct($token)
    {
        $this->token = $token;
    }

    public function via($notifiable)
    {
        return ['mail'];
    }

    public function toMail($notifiable)
    {
        // យក FRONTEND_URL ពី .env (http://localhost:5173) ភ្ជាប់ទៅកាន់ React ResetPassword Page
        $frontendUrl = config('app.frontend_url', env('FRONTEND_URL', 'http://localhost:5173'));
        $resetUrl = $frontendUrl . '/reset-password?token=' . $this->token . '&email=' . urlencode($notifiable->email);

        return (new MailMessage)
            ->subject('LifePilot AI - កំណត់ពាក្យសម្ងាត់ឡើងវិញ')
            ->line('អ្នកបានស្នើសុំកំណត់ពាក្យសម្ងាត់ឡើងវិញសម្រាប់គណនី LifePilot AI របស់អ្នក។')
            ->action('កំណត់ពាក្យសម្ងាត់ឡើងវិញ', $resetUrl)
            ->line('ប្រសិនបើអ្នកមិនបានស្នើសុំទេ សូមរំលងអុីមែលនេះ។');
    }
}