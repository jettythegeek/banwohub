<?php

namespace App\Services;

use App\Models\CommunicationLog;
use App\Models\Message;
use App\Models\MessageThread;
use App\Models\User;

class CommunicationLogService
{
    public function logFromMessage(Message $message, MessageThread $thread, User $sender): CommunicationLog
    {
        return CommunicationLog::query()->create([
            'organization_id' => $thread->organization_id,
            'client_id' => $thread->client_id,
            'legal_matter_id' => $thread->legal_matter_id,
            'message_thread_id' => $thread->id,
            'channel' => 'in_app',
            'subject' => $thread->subject,
            'body' => $message->body,
            'logged_by_user_id' => $sender->id,
            'occurred_at' => $message->created_at ?? now(),
        ]);
    }
}
