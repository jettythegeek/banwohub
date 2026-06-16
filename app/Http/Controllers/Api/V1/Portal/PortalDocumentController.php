<?php

namespace App\Http\Controllers\Api\V1\Portal;

use App\Http\Controllers\Controller;
use App\Http\Concerns\ResolvesPortalClient;
use App\Http\Resources\PortalDocumentResource;
use App\Models\LegalDocument;
use App\Models\LegalMatter;
use App\Support\InAppNotifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PortalDocumentController extends Controller
{
    use ResolvesPortalClient;

    public function index(Request $request): AnonymousResourceCollection
    {
        $client = $this->portalClientFor($request->user());
        $scope = $request->string('scope', 'shared')->toString();

        $query = LegalDocument::query()
            ->with(['legalMatter:id,title,matter_number'])
            ->where('organization_id', $client->organization_id)
            ->whereNotIn('document_type', LegalDocument::ORGANIZATION_TYPES)
            ->whereHas('legalMatter', fn ($q) => $q->where('client_id', $client->id))
            ->when(
                $request->filled('legal_matter_id'),
                fn ($q) => $q->where('legal_matter_id', $request->integer('legal_matter_id')),
            );

        if ($scope === 'pending') {
            $query
                ->where('uploaded_by_client', true)
                ->whereNull('portal_reviewed_at');
        } else {
            $query->where('client_visible', true);
        }

        $documents = $query->latest()->paginate($request->integer('per_page', 15));

        return PortalDocumentResource::collection($documents);
    }

    public function store(Request $request, InAppNotifier $notifier): JsonResponse
    {
        $user = $request->user();
        $client = $this->portalClientFor($user);

        $data = $request->validate([
            'legal_matter_id' => ['required', 'integer'],
            'name' => ['nullable', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string'],
            'file' => ['required', 'file', 'max:20480'],
        ]);

        $matter = $this->assertPortalMatter($client, (int) $data['legal_matter_id']);
        $file = $request->file('file');
        $pathPrefix = "organizations/{$client->organization_id}/cases/{$matter->id}/portal-uploads";
        $path = $file->store($pathPrefix, 'local');
        $originalFilename = $file->getClientOriginalName();

        $document = LegalDocument::query()->create([
            'organization_id' => $client->organization_id,
            'legal_matter_id' => $matter->id,
            'uploaded_by' => $user->id,
            'document_type' => 'evidence',
            'name' => $data['name'] ?? pathinfo($originalFilename, PATHINFO_FILENAME),
            'category' => $data['category'] ?? 'client_upload',
            'description' => $data['description'] ?? null,
            'original_filename' => $originalFilename,
            'mime_type' => $file->getClientMimeType(),
            'size' => $file->getSize() ?: 0,
            'disk' => 'local',
            'path' => $path,
            'version' => 1,
            'client_visible' => false,
            'uploaded_by_client' => true,
        ]);

        activity('document')
            ->performedOn($document)
            ->causedBy($user)
            ->withProperties(['legal_matter_id' => $matter->id, 'portal_upload' => true])
            ->log('Client uploaded document via portal');

        $notifier->notifyPermission(
            $matter->organization,
            'documents.view',
            'portal_document_uploaded',
            'Client document uploaded',
            "{$document->name} — pending review on {$matter->title}",
            [
                'document_id' => $document->id,
                'legal_matter_id' => $matter->id,
                'client_id' => $client->id,
            ],
            $user
        );

        return (new PortalDocumentResource($document->load('legalMatter')))
            ->response()
            ->setStatusCode(201);
    }

    public function download(Request $request, LegalDocument $document): StreamedResponse
    {
        $client = $this->portalClientFor($request->user());

        $ownsMatter = $document->legalMatter?->client_id === $client->id;
        $canDownload = $ownsMatter && (
            $document->client_visible
            || ($document->uploaded_by_client && $document->uploaded_by === $request->user()->id)
        );

        abort_unless(
            $document->organization_id === $client->organization_id
            && LegalDocument::isCaseScopedType((string) $document->document_type)
            && $canDownload,
            404,
        );

        abort_unless(Storage::disk($document->disk)->exists($document->path), 404, 'Document file was not found.');

        return Storage::disk($document->disk)->download($document->path, $document->original_filename);
    }

    protected function assertPortalMatter(\App\Models\Client $client, int $matterId): LegalMatter
    {
        return LegalMatter::query()
            ->where('organization_id', $client->organization_id)
            ->where('client_id', $client->id)
            ->findOrFail($matterId);
    }
}
