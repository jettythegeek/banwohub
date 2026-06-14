<?php

namespace App\Notifications;

use App\Models\Organization;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ClientPortalWelcomeNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Organization $organization,
        public string $plainPassword,
        public bool $isPasswordReset = false,
    ) {
    }

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        if (! $notifiable->email) {
            return [];
        }

        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $firmName = $this->organization->legal_name ?: $this->organization->name;
        $loginUrl = rtrim(config('app.frontend_url'), '/').'/portal/login';

        $subject = $this->isPasswordReset
            ? "{$firmName} — Your client portal password has been reset"
            : "{$firmName} — Welcome to your client portal";

        $message = (new MailMessage)
            ->subject($subject)
            ->greeting("Hello {$notifiable->name},")
            ->line($this->isPasswordReset
                ? 'Your client portal password has been reset. Use the credentials below to sign in.'
                : 'Your firm has set up a client portal account for you. Use the credentials below to sign in.');

        $message->line("Login email: {$notifiable->email}");

        if ($this->isPasswordReset || $this->plainPassword !== '') {
            $message->line("Password: {$this->plainPassword}");
        }

        return $message
            ->action('Sign in to the portal', $loginUrl)
            ->line('If you did not expect this email, please contact your legal team.');
    }
}
