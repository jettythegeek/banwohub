<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Concerns\ResolvesOrganization;
use App\Http\Controllers\Controller;
use App\Models\CaseNote;
use App\Models\Client;
use App\Models\LegalDocument;
use App\Models\LegalMatter;
use App\Models\Message;
use App\Models\MessageThread;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class SearchController extends Controller
{
    use ResolvesOrganization;

    public function __invoke(Request $request): JsonResponse
    {
        $query = trim((string) $request->query('q', ''));
        abort_if(mb_strlen($query) < 2, 422, 'Search query must be at least 2 characters.');

        $organization = $this->organizationFor($request->user());
        $like = '%'.addcslashes($query, '%_\\').'%';

        $cases = LegalMatter::query()
            ->where('organization_id', $organization->id)
            ->where(function ($builder) use ($like) {
                $builder->where('title', 'like', $like)
                    ->orWhere('matter_number', 'like', $like)
                    ->orWhere('practice_area', 'like', $like);
            })
            ->orderByDesc('updated_at')
            ->limit(10)
            ->get(['id', 'title', 'matter_number', 'status'])
            ->map(fn (LegalMatter $matter) => [
                'type' => 'case',
                'id' => $matter->id,
                'title' => $matter->title,
                'subtitle' => $matter->matter_number,
                'status' => $matter->status,
                'url' => "/cases/{$matter->id}",
            ]);

        $clients = Client::query()
            ->where('organization_id', $organization->id)
            ->where(function ($builder) use ($like) {
                $builder->where('name', 'like', $like)
                    ->orWhere('email', 'like', $like)
                    ->orWhere('phone', 'like', $like);
            })
            ->orderByDesc('updated_at')
            ->limit(10)
            ->get(['id', 'name', 'email', 'status'])
            ->map(fn (Client $client) => [
                'type' => 'client',
                'id' => $client->id,
                'title' => $client->name,
                'subtitle' => $client->email,
                'status' => $client->status,
                'url' => "/clients/{$client->id}",
            ]);

        $documents = LegalDocument::query()
            ->where('organization_id', $organization->id)
            ->where(function ($builder) use ($like) {
                $builder->where('name', 'like', $like)
                    ->orWhere('original_filename', 'like', $like)
                    ->orWhere('content_html', 'like', $like);
            })
            ->orderByDesc('updated_at')
            ->limit(10)
            ->get(['id', 'name', 'original_filename', 'legal_matter_id', 'document_type', 'content_html'])
            ->map(function (LegalDocument $document) use ($query) {
                $snippet = $this->snippetFromHtml($document->content_html, $query);

                return [
                    'type' => 'document',
                    'id' => $document->id,
                    'title' => $document->name,
                    'subtitle' => $snippet ?: $document->original_filename,
                    'legal_matter_id' => $document->legal_matter_id,
                    'document_type' => $document->document_type,
                    'url' => $document->legal_matter_id
                        ? "/cases/{$document->legal_matter_id}/documents"
                        : '/cases',
                ];
            });

        $notes = CaseNote::query()
            ->where('organization_id', $organization->id)
            ->where(function ($builder) use ($like) {
                $builder->where('title', 'like', $like)
                    ->orWhere('body', 'like', $like);
            })
            ->orderByDesc('updated_at')
            ->limit(10)
            ->get(['id', 'title', 'body', 'legal_matter_id', 'note_type', 'visibility'])
            ->map(function (CaseNote $note) use ($query) {
                return [
                    'type' => 'note',
                    'id' => $note->id,
                    'title' => $note->title ?: 'Untitled note',
                    'subtitle' => $this->snippetFromText($note->body, $query),
                    'legal_matter_id' => $note->legal_matter_id,
                    'note_type' => $note->note_type,
                    'visibility' => $note->visibility,
                    'url' => $note->legal_matter_id
                        ? "/cases/{$note->legal_matter_id}/notes"
                        : '/cases',
                ];
            });

        $messages = Message::query()
            ->select(['messages.id', 'messages.body', 'messages.message_thread_id', 'messages.created_at'])
            ->join('message_threads', 'message_threads.id', '=', 'messages.message_thread_id')
            ->where('message_threads.organization_id', $organization->id)
            ->where('messages.body', 'like', $like)
            ->orderByDesc('messages.created_at')
            ->limit(10)
            ->get()
            ->map(function (Message $message) use ($query) {
                $thread = MessageThread::query()
                    ->with(['client:id,name', 'legalMatter:id,title'])
                    ->find($message->message_thread_id);

                $clientName = $thread?->client?->name;
                $matterTitle = $thread?->legalMatter?->title;
                $context = collect([$clientName, $matterTitle])->filter()->implode(' · ');

                return [
                    'type' => 'message',
                    'id' => $message->id,
                    'title' => $thread?->subject ?: ($clientName ? "Message with {$clientName}" : 'Message thread'),
                    'subtitle' => $this->snippetFromText($message->body, $query) ?: $context,
                    'message_thread_id' => $message->message_thread_id,
                    'client_id' => $thread?->client_id,
                    'legal_matter_id' => $thread?->legal_matter_id,
                    'url' => $thread
                        ? '/messages?thread='.$thread->id
                        : '/messages',
                ];
            });

        $sections = [
            ['key' => 'cases', 'label' => 'Cases', 'count' => $cases->count()],
            ['key' => 'clients', 'label' => 'Clients', 'count' => $clients->count()],
            ['key' => 'documents', 'label' => 'Documents', 'count' => $documents->count()],
            ['key' => 'notes', 'label' => 'Notes', 'count' => $notes->count()],
            ['key' => 'messages', 'label' => 'Messages', 'count' => $messages->count()],
        ];

        return response()->json([
            'query' => $query,
            'results' => [
                'cases' => $cases,
                'clients' => $clients,
                'documents' => $documents,
                'notes' => $notes,
                'messages' => $messages,
            ],
            'sections' => $sections,
            'total' => $cases->count() + $clients->count() + $documents->count()
                + $notes->count() + $messages->count(),
        ]);
    }

    protected function snippetFromHtml(?string $html, string $query): ?string
    {
        return $this->snippetFromText(strip_tags((string) $html), $query);
    }

    protected function snippetFromText(?string $text, string $query): ?string
    {
        $plain = trim(preg_replace('/\s+/', ' ', (string) $text) ?? '');
        if ($plain === '') {
            return null;
        }

        $lowerPlain = Str::lower($plain);
        $lowerQuery = Str::lower($query);
        $position = mb_strpos($lowerPlain, $lowerQuery);

        if ($position === false) {
            return Str::limit($plain, 120);
        }

        $start = max(0, $position - 40);
        $excerpt = mb_substr($plain, $start, 120);

        return ($start > 0 ? '…' : '').trim($excerpt).(mb_strlen($plain) > $start + 120 ? '…' : '');
    }
}
