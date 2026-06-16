<?php

namespace App\Http\Controllers\Api\V1\Portal;

use App\Http\Concerns\ResolvesPortalClient;
use App\Http\Controllers\Controller;
use App\Http\Resources\MessageResource;
use App\Http\Resources\MessageThreadResource;
use App\Events\MessageSent;
use App\Models\LegalMatter;
use App\Models\Message;
use App\Models\MessageThread;
use App\Services\CommunicationLogService;
use App\Services\MessageNotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

class PortalMessageController extends Controller
{
    use ResolvesPortalClient;

    public function index(Request $request): AnonymousResourceCollection
    {
        $client = $this->portalClientFor($request->user());

        $threads = MessageThread::query()
            ->with([
                'legalMatter:id,title,matter_number',
                'latestMessage.sender:id,name,client_id',
            ])
            ->where('organization_id', $client->organization_id)
            ->where('client_id', $client->id)
            ->when($request->filled('legal_matter_id'), function ($q) use ($request, $client) {
                $matterId = $request->integer('legal_matter_id');
                $this->assertPortalMatter($client, $matterId);
                $q->where('legal_matter_id', $matterId);
            })
            ->orderByDesc('last_message_at')
            ->orderByDesc('id')
            ->paginate($request->integer('per_page', 25));

        return MessageThreadResource::collection($threads);
    }

    public function store(Request $request, MessageNotificationService $notifier, CommunicationLogService $commLogger): JsonResponse
    {
        $user = $request->user();
        $client = $this->portalClientFor($user);

        $data = $request->validate([
            'legal_matter_id' => ['nullable', 'integer'],
            'subject' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:10000'],
            'attachments' => ['nullable', 'array'],
            'attachments.*.name' => ['required_with:attachments', 'string', 'max:255'],
            'attachments.*.url' => ['nullable', 'string', 'max:2048'],
        ]);

        if (! empty($data['legal_matter_id'])) {
            $this->assertPortalMatter($client, (int) $data['legal_matter_id']);
        }

        $thread = DB::transaction(function () use ($data, $client, $user, $notifier, $commLogger) {
            $thread = MessageThread::query()->create([
                'organization_id' => $client->organization_id,
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

        return (new MessageThreadResource($thread->load(['legalMatter', 'messages.sender'])))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Request $request, MessageThread $messageThread): MessageThreadResource
    {
        $client = $this->portalClientFor($request->user());
        abort_unless(
            $messageThread->organization_id === $client->organization_id
            && $messageThread->client_id === $client->id,
            404
        );

        return new MessageThreadResource(
            $messageThread->load(['legalMatter', 'messages.sender'])
        );
    }

    public function sendMessage(Request $request, MessageThread $messageThread, MessageNotificationService $notifier, CommunicationLogService $commLogger): JsonResponse
    {
        $user = $request->user();
        $client = $this->portalClientFor($user);
        abort_unless(
            $messageThread->organization_id === $client->organization_id
            && $messageThread->client_id === $client->id,
            404
        );

        $data = $request->validate([
            'body' => ['required', 'string', 'max:10000'],
            'attachments' => ['nullable', 'array'],
            'attachments.*.name' => ['required_with:attachments', 'string', 'max:255'],
            'attachments.*.url' => ['nullable', 'string', 'max:2048'],
        ]);

        $message = DB::transaction(function () use ($messageThread, $user, $data, $notifier, $commLogger) {
            $message = Message::query()->create([
                'message_thread_id' => $messageThread->id,
                'sender_user_id' => $user->id,
                'body' => $data['body'],
                'attachments' => $data['attachments'] ?? null,
            ]);

            $messageThread->update(['last_message_at' => now()]);
            $notifier->notifyNewMessage($message, $user);
            $commLogger->logFromMessage($message, $messageThread, $user);
            broadcast(new MessageSent($message))->toOthers();

            return $message;
        });

        return (new MessageResource($message->load('sender')))
            ->response()
            ->setStatusCode(201);
    }

    public function markRead(Request $request, MessageThread $messageThread): JsonResponse
    {
        $client = $this->portalClientFor($request->user());
        abort_unless(
            $messageThread->organization_id === $client->organization_id
            && $messageThread->client_id === $client->id,
            404
        );

        Message::query()
            ->where('message_thread_id', $messageThread->id)
            ->where('sender_user_id', '!=', $request->user()->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return response()->json(['marked' => true]);
    }

    protected function assertPortalMatter(\App\Models\Client $client, int $matterId): LegalMatter
    {
        return LegalMatter::query()
            ->where('organization_id', $client->organization_id)
            ->where('client_id', $client->id)
            ->findOrFail($matterId);
    }
}
