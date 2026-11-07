<?php

namespace App\Notifications;

use App\Support\Seo;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Branded password-reset email pointing at the admin portal. */
class ResetPasswordNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private string $token) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $minutes = config('auth.passwords.users.expire');

        return (new MailMessage)
            ->subject('Reset your ZAIRON Systems admin password')
            ->view('mail.action', [
                'heading' => 'Reset your password',
                'lines' => [
                    "Hi {$notifiable->name},",
                    'We received a request to reset the password for your ZAIRON Systems admin account.',
                ],
                'button' => 'Choose a new password',
                'url' => Seo::url('/admin/reset-password?'.http_build_query(['token' => $this->token, 'email' => $notifiable->email])),
                'outro' => [
                    "This link expires in {$minutes} minutes.",
                    'If you did not ask for this, you can ignore this email. Your password will not change.',
                ],
            ]);
    }
}
