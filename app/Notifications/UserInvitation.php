<?php

namespace App\Notifications;

use App\Support\Seo;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Invitation to the admin portal with a link to set a password. */
class UserInvitation extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private string $token, private string $invitedBy) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $hours = intdiv(config('auth.passwords.invites.expire'), 60);

        return (new MailMessage)
            ->subject("You're invited to the ZAIRON Systems admin portal")
            ->view('mail.action', [
                'heading' => 'Welcome to ZAIRON Systems',
                'lines' => [
                    "Hi {$notifiable->name},",
                    "{$this->invitedBy} has invited you to the ZAIRON Systems admin portal. Set a password to activate your account.",
                ],
                'button' => 'Set your password',
                'url' => Seo::url('/admin/reset-password?'.http_build_query(['token' => $this->token, 'email' => $notifiable->email, 'invite' => 1])),
                'outro' => ["This invitation expires in {$hours} hours. Ask an administrator to resend it if it runs out."],
            ]);
    }
}
