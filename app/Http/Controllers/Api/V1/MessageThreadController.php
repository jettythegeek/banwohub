<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Concerns\ResolvesOrganization;
use App\Http\Controllers\Api\V1\Concerns\ValidatesOrganizationAccess;
use App\Http\Controllers\Controller;
use App\Http\Resources\MessageResource;
use App\Http\Resources\MessageThreadResource;
use App\Events\MessageSent;
use App\Models\Message;
use App\Models\MessageThread;
use App\Services\CommunicationLogService;
use App\Services\MessageNotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

class MessageThreadController extends Controller
{
    use ResolvesOrganization;
    use ValidatesOrganizationAccess;

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', MessageThread::class);

        $user = $request->user();
        $organization = $this->organizationFor($user);

        $threads = MessageThread::query()
            ->with([
                'client:id,name,email',
                'legalMatter:id,title,matter_number',
                'latestMessage.sender:id,name,client_id',
            ])
            ->where('organization_id', $organization->id)
            ->when($request->filled('client_id'), fn ($q) => $q->where('client_id', $request->integer('client_id')))
            ->when($request->filled('legal_matter_id'), fn ($q) => $q->where('legal_matter_id', $request->integer('legal_matter_id')))
            ->when($request->boolean('unread_only'), function ($q) use ($user) {
                $q->whereHas('messages', fn ($mq) => $mq
                    ->where('sender_user_id', '!=', $user->id)
                    ->whereNull('read_at'));
            })
            ->orderByDesc('last_message_at')
            ->orderByDesc('id')
            ->paginate($request->integer('per_page', 25));

        return MessageThreadResource::collection($threads);
    }

    public function store(Request $request, MessageNotificationService $notifier, CommunicationLogService $commLogger): JsonResponse
    {
        $this->authorize('create', MessageThread::class);

        $user = $request->user();
        $organization = $this->organizationFor($user);

        $data = $request->validate([
            'client_id' => ['required', 'integer'],
            'legal_matter_id' => ['nullable', 'integer'],
            'subject' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:10000'],
            'attachments' => ['nullable', 'array'],
            'attachments.*.name' => ['required_with:attachments', 'string', 'max:255'],
            'attachments.*.url' => ['nullable', 'string', 'max:2048'],
        ]);

        $client = $this->clientForOrganization((int) $data['client_id'], $organization->id);

        if (! empty($data['legal_matter_id'])) {
            $matter = $this->legalMatterForOrganization((int) $data['legal_matter_id'], $organization->id);
            abort_unless($matter->client_id === $client->id, 422, 'Case does not belong to this client.');
        }

        $thread = DB::transaction(function () use ($data, $organization, $user, $client, $notifier, $commLogger) {
            $thread = MessageThread::query()->create([
                'organization_id' => $organization->id,
                'client_id' => $client->id,
                'legal_matter_id' => $data['legal_matter_id'] ?? null,
                'created_by' => $user->id,
                'subject' => $data['subject'],
                'last_message_at' => now(),
            ]);

            $message = Message::query()->create([
                'message_thread_id' => $thread->id,
                'sender_user_id' => $user->id,
                'body' => $data['body'],
                'attachments' => $data['attachments'] ?? null,
            ]);

            $notifier->notifyNewMessage($message, $user);
            $commLogger->logFromMessage($message, $thread, $user);
            broadcast(new MessageSent($message))->toOthers();

            return $thread;
        });

        return (new MessageThreadResource($thread->load(['client', 'legalMatter', 'messages.sender'])))
            ->response()
            ->setStatusCode(201);
    }

    public function show(MessageThread $messageThread): MessageThreadResource
    {
        $this->authorize('view', $messageThread);

        return new MessageThreadResource(
            $messageThread->load(['client', 'legalMatter', 'creator', 'messages.sender'])
        );
    }

    public function messages(Request $request, MessageThread $messageThread): AnonymousResourceCollection
    {
        $this->authorize('view', $messageThread);

        $messages = $messageThread->messages()
            ->with('sender:id,name,client_id')
            ->orderBy('created_at')
            ->paginate($request->integer('per_page', 50));

        return MessageResource::collection($messages);
    }

    public function sendMessage(Request $request, MessageThread $messageThread, MessageNotificationService $notifier, CommunicationLogService $commLogger): JsonResponse
    {
        $this->authorize('sendMessage', $messageThread);

        $data = $request->validate([
            'body' => ['required', 'string', 'max:10000'],
            'attachments' => ['nullable', 'array'],
            'attachments.*.name' => ['required_with:attachments', 'string', 'max:255'],
            'attachments.*.url' => ['nullable', 'string', 'max:2048'],
        ]);

        $message = DB::transaction(function () use ($messageThread, $request, $data, $notifier, $commLogger) {
            $message = Message::query()->create([
                'message_thread_id' => $messageThread->id,
                'sender_user_id' => $request->user()->id,
                'body' => $data['body'],
                'attachments' => $data['attachments'] ?? null,
            ]);

            $messageThread->update(['last_message_at' => now()]);
            $notifier->notifyNewMessage($message, $request->user());
            $commLogger->logFromMessage($message, $messageThread, $request->user());
            broadcast(new MessageSent($message))->toOthers();

            return $message;
        });

        return (new MessageResource($message->load('sender')))
            ->response()
            ->setStatusCode(201);
    }

    public function markRead(Request $request, MessageThread $messageThread): JsonResponse
    {
        $this->authorize('markRead', $messageThread);

        Message::query()
            ->where('message_thread_id', $messageThread->id)
            ->where('sender_user_id', '!=', $request->user()->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return response()->json(['marked' => true]);
    }
}
