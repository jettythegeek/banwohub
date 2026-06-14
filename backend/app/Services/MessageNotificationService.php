<?php

namespace App\Services;

use App\Models\Client;
use App\Models\Message;
use App\Models\MessageThread;
use App\Models\User;
use App\Notifications\NewMessageNotification;
use App\Support\InAppNotifier;
use Illuminate\Support\Facades\Notification;

class MessageNotificationService
{
    public function __construct(private InAppNotifier $notifier)
    {
    }

    public function notifyNewMessage(Message $message, User $sender): void
    {
        $thread = $message->thread()->with(['client', 'legalMatter'])->first();
        if (! $thread) {
            return;
        }

        $preview = mb_strlen($message->body) > 120
            ? mb_substr($message->body, 0, 117) . '…'
            : $message->body;

        $data = [
            'message_thread_id' => $thread->id,
            'message_id' => $message->id,
            'legal_matter_id' => $thread->legal_matter_id,
            'client_id' => $thread->client_id,
        ];

        if ($sender->client_id !== null) {
            $this->notifier->notifyPermission(
                $thread->organization,
                'messages.view',
                'message_received',
                'New client message',
                "{$thread->subject}: {$preview}",
                $data,
                $sender
            );
            $this->notifyStaffByEmail($thread, $message, $sender);

            return;
        }

        $this->notifyPortalUsers($thread->client, 'portal_message_received', 'New message from your legal team', "{$thread->subject}: {$preview}", $data, $sender);
        $this->notifyPortalUsersByEmail($thread->client, $message, $thread, $sender);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function notifyPortalUsers(Client $client, string $type, string $title, ?string $body, array $data, User $actor): void
    {
        $client->portalUsers()
            ->where('is_active', true)
            ->get()
            ->each(fn (User $user) => $this->notifier->notifyUser($user, $type, $title, $body, $data, $actor));
    }

    protected function notifyStaffByEmail(MessageThread $thread, Message $message, User $sender): void
    {
        $recipients = User::query()
            ->where('organization_id', $thread->organization_id)
            ->where('is_active', true)
            ->whereNotNull('email')
            ->where('id', '!=', $sender->id)
            ->whereHas('roles.permissions', fn ($q) => $q->where('name', 'messages.view'))
            ->get();

        if ($recipients->isEmpty()) {
            return;
        }

        Notification::send(
            $recipients,
            new NewMessageNotification($message, $thread, $sender->name, forPortalUser: false)
        );
    }

    protected function notifyPortalUsersByEmail(Client $client, Message $message, MessageThread $thread, User $sender): void
    {
        $recipients = $client->portalUsers()
            ->where('is_active', true)
            ->whereNotNull('email')
            ->get();

        if ($recipients->isEmpty()) {
            return;
        }

        Notification::send(
            $recipients,
            new NewMessageNotification($message, $thread, $sender->name, forPortalUser: true)
        );
    }
}
