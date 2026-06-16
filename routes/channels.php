<?php

use App\Models\MessageThread;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('message-thread.{threadId}', function (User $user, int $threadId): bool {
    $thread = MessageThread::query()->find($threadId);

    if (! $thread) {
        return false;
    }

    if ($user->client_id !== null) {
        return $thread->client_id === $user->client_id
            && $thread->organization_id === $user->organization_id;
    }

    if (! $user->can('messages.view')) {
        return false;
    }

    return $thread->organization_id === $user->organization_id;
});
