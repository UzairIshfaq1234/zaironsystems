<?php

namespace App\Notifications;

use App\Models\Employee;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Invites an employee to the portal (first password) or resets it. Link valid for 72 hours. */
class EmployeePasswordReset extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public string $token, public bool $invite = false) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(Employee $notifiable): MailMessage
    {
        $url = url('/portal/reset-password?'.http_build_query(['token' => $this->token, 'email' => $notifiable->email]));

        return (new MailMessage)
            ->subject($this->invite ? 'Your ZAIRON Systems employee portal' : 'Reset your ZAIRON Systems portal password')
            ->greeting("Hello {$notifiable->name},")
            ->line($this->invite
                ? 'You now have access to the ZAIRON Systems employee portal: your attendance, shift, leave, salary slips and performance reviews.'
                : 'Use the button below to choose a new portal password.')
            ->action('Set my password', $url)
            ->line('This link expires in 72 hours. If you did not expect this email, ignore it.');
    }
}
