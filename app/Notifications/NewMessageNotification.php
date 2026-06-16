<?php

namespace App\Notifications;

use App\Models\Message;
use App\Models\MessageThread;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class NewMessageNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Message $message,
        public MessageThread $thread,
        public string $senderName,
        public bool $forPortalUser = false,
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
        $preview = mb_strlen($this->message->body) > 200
            ? mb_substr($this->message->body, 0, 197) . '…'
            : $this->message->body;

        $subject = $this->forPortalUser
            ? 'New message from your legal team'
            : 'New client message';

        $greeting = $this->forPortalUser
            ? 'You have a new message from Banwolaw.'
            : 'A client sent a new message.';

        $actionUrl = $this->forPortalUser
            ? rtrim(config('app.portal_frontend_url', config('app.url')), '/') . '/portal/messages?thread=' . $this->thread->id
            : rtrim(config('app.url'), '/') . '/messages?thread=' . $this->thread->id;

        return (new MailMessage)
            ->subject("{$subject}: {$this->thread->subject}")
            ->greeting($greeting)
            ->line("From: {$this->senderName}")
            ->line("Subject: {$this->thread->subject}")
            ->line($preview)
            ->action('View message', $actionUrl);
    }
}
