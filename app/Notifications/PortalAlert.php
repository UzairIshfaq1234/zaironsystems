<?php

namespace App\Notifications;

use App\Models\Employee;
use App\Models\User;
use App\Support\Team;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Notification as Notifications;

/**
 * An in-app alert (the bell), stored in the `notifications` table. Team members see it in the admin;
 * employees see it in the portal and also get an email. `kind` picks the icon and colour.
 * `url` is a route inside the app the alert belongs to (the admin for team members, the portal for employees).
 */
class PortalAlert extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $kind,
        public string $title,
        public string $body,
        public string $url,
    ) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return $notifiable instanceof Employee ? ['database', 'mail'] : ['database'];
    }

    /** @return array{kind: string, title: string, body: string, url: string} */
    public function toArray(object $notifiable): array
    {
        return ['kind' => $this->kind, 'title' => $this->title, 'body' => $this->body, 'url' => $this->url];
    }

    /** Email for employees only: a short message with a button to the portal. */
    public function toMail(Employee $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject($this->title)
            ->greeting("Hello {$notifiable->name},")
            ->line($this->body)
            ->action('Open the employee portal', url('/portal'.$this->url));
    }

    /** Alerts every active admin who holds `$permission`, except the person who caused it. */
    public static function toTeam(string $permission, self $alert, ?User $except = null): void
    {
        $users = Team::usersWith($permission)->reject(fn (User $user) => $user->is($except));
        Notifications::send($users, $alert);
    }
}
