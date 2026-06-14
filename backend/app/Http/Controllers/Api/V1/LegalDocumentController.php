<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Concerns\ResolvesOrganization;
use App\Http\Controllers\Api\V1\Concerns\ValidatesOrganizationAccess;
use App\Http\Controllers\Controller;
use App\Http\Resources\DocumentVersionResource;
use App\Http\Resources\LegalDocumentResource;
use App\Models\DocumentFolder;
use App\Models\DocumentVersion;
use App\Models\LegalDocument;
use App\Models\LegalMatter;
use App\Services\ApprovalWorkflowService;
use App\Services\DocumentMergeService;
use App\Services\DocumentVersionService;
use App\Services\OnlyOfficeConfigService;
use App\Support\InAppNotifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LegalDocumentController extends Controller
{
    use ResolvesOrganization;
    use ValidatesOrganizationAccess;

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', LegalDocument::class);

        $organization = $this->organizationFor($request->user());

        $documents = LegalDocument::query()
            ->with(['legalMatter:id,title,matter_number', 'uploader:id,name', 'parentTemplate:id,name', 'aiApprover:id,name', 'documentFolder:id,name', 'checkedOutBy:id,name'])
            ->where('organization_id', $organization->id)
            ->when($request->filled('legal_matter_id'), fn ($q) => $q->where('legal_matter_id', $request->integer('legal_matter_id')))
            ->when($request->filled('document_type'), fn ($q) => $q->where('document_type', $request->string('document_type')))
            ->when($request->filled('category'), fn ($q) => $q->where('category', $request->string('category')))
            ->when($request->filled('document_folder_id'), fn ($q) => $q->where('document_folder_id', $request->integer('document_folder_id')))
            ->when($request->boolean('portal_pending'), function ($q) {
                $q->where('uploaded_by_client', true)->whereNull('portal_reviewed_at');
            })
            ->latest()
            ->paginate($request->integer('per_page', 15));

        return LegalDocumentResource::collection($documents)->additional([
            'document_types' => LegalDocument::CASE_DOCUMENT_TYPES,
        ]);
    }

    public function store(Request $request, InAppNotifier $notifier, DocumentVersionService $versionService): JsonResponse
    {
        $this->authorize('create', LegalDocument::class);

        $organization = $this->organizationFor($request->user());
        $data = $request->validate([
            'legal_matter_id' => ['nullable', 'integer', 'exists:legal_matters,id'],
            'document_type' => ['nullable', 'string', Rule::in(LegalDocument::TYPES)],
            'name' => ['nullable', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string'],
            'content_html' => ['nullable', 'string'],
            'parent_template_id' => ['nullable', 'integer', 'exists:legal_documents,id'],
            'file' => ['nullable', 'file', 'max:20480'],
        ]);

        abort_unless($request->hasFile('file') || ! empty($data['content_html']), 422, 'Provide a file or HTML content.');

        $documentType = $data['document_type'] ?? 'pleading';
        $matter = null;
        if (LegalDocument::isCaseScopedType($documentType)) {
            abort_unless(isset($data['legal_matter_id']), 422, 'Case documents require legal_matter_id.');
            $matter = $this->legalMatterForOrganization((int) $data['legal_matter_id'], $organization->id);
        }

        $path = '';
        $originalFilename = ($data['name'] ?? 'document').'.html';
        $mimeType = 'text/html';
        $size = 0;

        if ($request->hasFile('file')) {
            $file = $request->file('file');
            $pathPrefix = $documentType === 'organization_template'
                ? "organizations/{$organization->id}/templates"
                : "organizations/{$organization->id}/cases/{$matter?->id}/documents";
            $path = $file->store($pathPrefix, 'local');
            $originalFilename = $file->getClientOriginalName();
            $mimeType = $file->getClientMimeType();
            $size = $file->getSize() ?: 0;
        } elseif (! empty($data['content_html'])) {
            $pathPrefix = $documentType === 'organization_template'
                ? "organizations/{$organization->id}/templates"
                : "organizations/{$organization->id}/cases/{$matter?->id}/documents";
            $filename = uniqid('doc_', true).'.html';
            $path = "{$pathPrefix}/{$filename}";
            Storage::disk('local')->put($path, $data['content_html']);
            $size = strlen($data['content_html']);
        }

        $document = LegalDocument::query()->create([
            'organization_id' => $organization->id,
            'legal_matter_id' => $matter?->id ?? ($data['legal_matter_id'] ?? null),
            'uploaded_by' => $request->user()->id,
            'document_type' => $documentType,
            'name' => $data['name'] ?? pathinfo($originalFilename, PATHINFO_FILENAME),
            'category' => $data['category'] ?? null,
            'description' => $data['description'] ?? null,
            'content_html' => $data['content_html'] ?? null,
            'parent_template_id' => $data['parent_template_id'] ?? null,
            'original_filename' => $originalFilename,
            'mime_type' => $mimeType,
            'size' => $size,
            'disk' => 'local',
            'path' => $path,
            'version' => 1,
        ]);

        activity('document')
            ->performedOn($document)
            ->causedBy($request->user())
            ->withProperties(['legal_matter_id' => $document->legal_matter_id])
            ->log('Document uploaded');

        if ($document->content_html) {
            $versionService->recordVersion($document, $document->content_html, $request->user(), 'Initial version', 'human');
        }

        if ($document->legal_matter_id) {
            $notifier->notifyPermission(
                $organization,
                'documents.view',
                'document_uploaded',
                'Document uploaded',
                $document->name,
                ['document_id' => $document->id, 'legal_matter_id' => $document->legal_matter_id],
                $request->user()
            );
        }

        return (new LegalDocumentResource($document->load(['legalMatter', 'uploader', 'parentTemplate'])))
            ->response()
            ->setStatusCode(201);
    }

    public function generateDraft(Request $request, DocumentMergeService $mergeService, DocumentVersionService $versionService): JsonResponse
    {
        $this->authorize('create', LegalDocument::class);

        $organization = $this->organizationFor($request->user());
        $data = $request->validate([
            'template_id' => ['required', 'integer', 'exists:legal_documents,id'],
            'legal_matter_id' => ['required', 'integer', 'exists:legal_matters,id'],
            'name' => ['nullable', 'string', 'max:255'],
        ]);

        $template = LegalDocument::query()
            ->where('organization_id', $organization->id)
            ->where('document_type', 'organization_template')
            ->findOrFail($data['template_id']);

        $matter = $this->legalMatterForOrganization((int) $data['legal_matter_id'], $organization->id);
        $matter->load('client');

        $content = $mergeService->mergeTemplate($mergeService->templateContent($template), $matter);
        $filename = uniqid('draft_', true).'.html';
        $path = "organizations/{$organization->id}/cases/{$matter->id}/documents/{$filename}";
        Storage::disk('local')->put($path, $content);

        $document = LegalDocument::query()->create([
            'organization_id' => $organization->id,
            'legal_matter_id' => $matter->id,
            'uploaded_by' => $request->user()->id,
            'document_type' => 'case_document',
            'name' => $data['name'] ?? $template->name.' draft',
            'category' => 'template_draft',
            'description' => 'Generated from template '.$template->name,
            'content_html' => $content,
            'parent_template_id' => $template->id,
            'original_filename' => ($data['name'] ?? $template->name).'.html',
            'mime_type' => 'text/html',
            'size' => strlen($content),
            'disk' => 'local',
            'path' => $path,
            'version' => 1,
        ]);

        $versionService->recordVersion($document, $content, $request->user(), 'Generated from template', 'system');

        activity('document')
            ->performedOn($document)
            ->causedBy($request->user())
            ->withProperties(['legal_matter_id' => $matter->id, 'template_id' => $template->id])
            ->log('Document draft generated from template');

        return (new LegalDocumentResource($document->load(['legalMatter', 'uploader', 'parentTemplate', 'aiApprover'])))
            ->response()
            ->setStatusCode(201);
    }

    public function mergeFieldsCatalog(DocumentMergeService $mergeService): JsonResponse
    {
        $this->authorize('viewAny', LegalDocument::class);

        return response()->json([
            'fields' => $mergeService->fieldCatalog(),
        ]);
    }

    public function saveAiDraft(Request $request, DocumentVersionService $versionService): JsonResponse
    {
        $this->authorize('create', LegalDocument::class);

        $organization = $this->organizationFor($request->user());
        $data = $request->validate([
            'legal_matter_id' => ['required', 'integer', 'exists:legal_matters,id'],
            'content_html' => ['required', 'string'],
            'name' => ['nullable', 'string', 'max:255'],
            'template_id' => ['nullable', 'integer', 'exists:legal_documents,id'],
            'ai_governance_log_id' => ['nullable', 'integer', 'exists:ai_governance_logs,id'],
        ]);

        $matter = $this->legalMatterForOrganization((int) $data['legal_matter_id'], $organization->id);

        $templateId = null;
        $templateName = 'AI draft';
        if (! empty($data['template_id'])) {
            $template = LegalDocument::query()
                ->where('organization_id', $organization->id)
                ->where('document_type', 'organization_template')
                ->findOrFail($data['template_id']);
            $templateId = $template->id;
            $templateName = $template->name;
        }

        $content = $data['content_html'];
        $filename = uniqid('ai_draft_', true).'.html';
        $path = "organizations/{$organization->id}/cases/{$matter->id}/documents/{$filename}";
        Storage::disk('local')->put($path, $content);

        $document = LegalDocument::query()->create([
            'organization_id' => $organization->id,
            'legal_matter_id' => $matter->id,
            'uploaded_by' => $request->user()->id,
            'document_type' => 'case_document',
            'name' => $data['name'] ?? $templateName.' (AI)',
            'category' => 'ai_draft',
            'description' => 'AI-assisted draft requiring lawyer review',
            'content_html' => $content,
            'parent_template_id' => $templateId,
            'original_filename' => ($data['name'] ?? $templateName).'.html',
            'mime_type' => 'text/html',
            'size' => strlen($content),
            'disk' => 'local',
            'path' => $path,
            'version' => 1,
            'ai_generated' => true,
            'ai_review_status' => 'generated',
            'ai_governance_log_id' => $data['ai_governance_log_id'] ?? null,
        ]);

        $versionService->recordVersion($document, $content, $request->user(), 'AI draft saved', 'ai');

        activity('document')
            ->performedOn($document)
            ->causedBy($request->user())
            ->withProperties([
                'legal_matter_id' => $matter->id,
                'ai_generated' => true,
                'ai_review_status' => 'generated',
            ])
            ->log('AI draft saved');

        return (new LegalDocumentResource($document->load(['legalMatter', 'uploader', 'parentTemplate', 'aiApprover'])))
            ->response()
            ->setStatusCode(201);
    }

    public function updateAiReview(Request $request, LegalDocument $document): LegalDocumentResource
    {
        $this->authorize('update', $document);

        abort_unless($document->ai_generated, 422, 'This document was not AI-generated.');

        $data = $request->validate([
            'ai_review_status' => ['required', 'string', Rule::in(LegalDocument::AI_REVIEW_STATUSES)],
        ]);

        $newStatus = $data['ai_review_status'];

        if ($newStatus === 'finalized') {
            abort_unless($document->isAiFinalizable(), 422, 'Lawyer approval is required before finalizing AI documents.');
        }

        if ($newStatus === 'approved') {
            abort_unless(
                $request->user()->hasAnyRole(['Lawyer', 'Partner', 'Firm Admin']),
                403,
                'Only a lawyer may approve AI-generated documents.',
            );
        }

        abort_unless(
            $document->canTransitionAiReviewTo($newStatus),
            422,
            'Invalid AI review status transition.',
        );

        $updates = ['ai_review_status' => $newStatus];

        if ($newStatus === 'approved') {
            $updates['ai_approved_by'] = $request->user()->id;
            $updates['ai_approved_at'] = now();
        }

        $document->update($updates);

        activity('document')
            ->performedOn($document)
            ->causedBy($request->user())
            ->withProperties([
                'legal_matter_id' => $document->legal_matter_id,
                'ai_review_status' => $newStatus,
            ])
            ->log('AI document review status updated');

        return new LegalDocumentResource($document->fresh()->load(['legalMatter', 'uploader', 'parentTemplate', 'aiApprover']));
    }

    public function show(LegalDocument $document): LegalDocumentResource
    {
        $this->authorize('view', $document);

        return new LegalDocumentResource($document->load(['legalMatter', 'uploader', 'parentTemplate', 'documentFolder', 'checkedOutBy']));
    }

    public function checkout(Request $request, LegalDocument $document): LegalDocumentResource
    {
        $this->authorize('update', $document);

        if ($document->isCheckedOut() && ! $document->isCheckedOutBy($request->user())) {
            abort(422, 'Document is checked out by another user.');
        }

        if (! $document->isCheckedOut()) {
            $document->update([
                'checked_out_by' => $request->user()->id,
                'checked_out_at' => now(),
            ]);

            activity('document')
                ->performedOn($document)
                ->causedBy($request->user())
                ->withProperties(['legal_matter_id' => $document->legal_matter_id])
                ->log('Document checked out');
        }

        return new LegalDocumentResource($document->fresh()->load(['legalMatter', 'uploader', 'parentTemplate', 'documentFolder', 'checkedOutBy']));
    }

    public function checkin(Request $request, LegalDocument $document): LegalDocumentResource
    {
        $this->authorize('update', $document);

        abort_unless($document->isCheckedOut(), 422, 'Document is not checked out.');

        abort_unless(
            $document->isCheckedOutBy($request->user()) || $request->user()->hasAnyRole(['Firm Admin', 'System Admin', 'Partner']),
            403,
            'Only the user who checked out the document may check it in.',
        );

        $document->update([
            'checked_out_by' => null,
            'checked_out_at' => null,
        ]);

        activity('document')
            ->performedOn($document)
            ->causedBy($request->user())
            ->withProperties(['legal_matter_id' => $document->legal_matter_id])
            ->log('Document checked in');

        return new LegalDocumentResource($document->fresh()->load(['legalMatter', 'uploader', 'parentTemplate', 'documentFolder', 'checkedOutBy']));
    }

    public function update(
        Request $request,
        LegalDocument $document,
        ApprovalWorkflowService $workflow,
        DocumentVersionService $versionService,
    ): LegalDocumentResource {
        $this->authorize('update', $document);

        $organization = $this->organizationFor($request->user());

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string'],
            'content_html' => ['nullable', 'string'],
            'change_summary' => ['nullable', 'string', 'max:500'],
            'client_visible' => ['sometimes', 'boolean'],
            'requires_approval' => ['sometimes', 'boolean'],
            'document_folder_id' => ['nullable', 'integer', 'exists:document_folders,id'],
        ]);

        if (array_key_exists('document_folder_id', $data) && $data['document_folder_id'] !== null) {
            $folder = DocumentFolder::query()
                ->where('organization_id', $organization->id)
                ->findOrFail($data['document_folder_id']);
            abort_unless(
                $folder->legal_matter_id === $document->legal_matter_id,
                422,
                'Folder must belong to the same case as the document.',
            );
        }

        if (array_key_exists('client_visible', $data) && $data['client_visible']) {
            $workflow->assertSendAllowed($document);
        }

        $changeSummary = $data['change_summary'] ?? null;
        unset($data['change_summary']);

        if (isset($data['content_html'])) {
            if ($document->path) {
                Storage::disk($document->disk)->put($document->path, $data['content_html']);
            }
            $data['size'] = strlen($data['content_html']);
            $data['version'] = ($document->version ?? 1) + 1;

            if ($document->ai_generated && $document->ai_review_status !== 'finalized') {
                if ($document->ai_review_status === 'approved') {
                    $data['ai_approved_by'] = null;
                    $data['ai_approved_at'] = null;
                }
                $data['ai_review_status'] = 'edited';
            }
        }

        if ($document->uploaded_by_client && $document->portal_reviewed_at === null
            && array_key_exists('client_visible', $data)) {
            $data['portal_reviewed_at'] = now();
        }

        $document->update($data);

        if (isset($data['content_html'])) {
            $versionService->recordVersion(
                $document->fresh(),
                $data['content_html'],
                $request->user(),
                $changeSummary ?: 'Content updated',
                'human',
            );
        }

        if (array_key_exists('client_visible', $data) && $data['client_visible']) {
            $workflow->markFinalized($document);
        }

        activity('document')
            ->performedOn($document)
            ->causedBy($request->user())
            ->withProperties(['legal_matter_id' => $document->legal_matter_id, 'version' => $document->version])
            ->log('Document updated');

        return new LegalDocumentResource($document->fresh()->load(['legalMatter', 'uploader', 'parentTemplate', 'aiApprover']));
    }

    public function versions(Request $request, LegalDocument $document): AnonymousResourceCollection
    {
        $this->authorize('view', $document);

        $versions = $document->versions()
            ->with('author:id,name')
            ->orderByDesc('version_number')
            ->get();

        return DocumentVersionResource::collection($versions);
    }

    public function showVersion(LegalDocument $document, DocumentVersion $documentVersion): DocumentVersionResource
    {
        $this->authorize('view', $document);

        abort_unless($documentVersion->document_id === $document->id, 404);

        return new DocumentVersionResource($documentVersion->load('author:id,name'));
    }

    public function compareVersions(Request $request, LegalDocument $document): JsonResponse
    {
        $this->authorize('view', $document);

        $data = $request->validate([
            'from_version' => ['required', 'integer', 'min:1'],
            'to_version' => ['required', 'integer', 'min:1'],
        ]);

        $from = $document->versions()
            ->where('version_number', $data['from_version'])
            ->firstOrFail();
        $to = $document->versions()
            ->where('version_number', $data['to_version'])
            ->firstOrFail();

        return response()->json([
            'from' => (new DocumentVersionResource($from->load('author:id,name')))->resolve(),
            'to' => (new DocumentVersionResource($to->load('author:id,name')))->resolve(),
        ]);
    }

    public function download(LegalDocument $document): StreamedResponse
    {
        $this->authorize('download', $document);

        abort_unless(Storage::disk($document->disk)->exists($document->path), 404, 'Document file was not found.');

        return Storage::disk($document->disk)->download($document->path, $document->original_filename);
    }

    public function onlyOfficeConfig(
        Request $request,
        LegalDocument $document,
        OnlyOfficeConfigService $onlyOffice,
    ): JsonResponse {
        $this->authorize('view', $document);

        if (! $onlyOffice->isConfigured()) {
            return response()->json([
                'configured' => false,
                'available' => false,
            ]);
        }

        if (! $onlyOffice->canEditInOnlyOffice($document)) {
            return response()->json([
                'configured' => true,
                'available' => false,
                'reason' => $document->content_html ? 'html_draft' : 'not_word_document',
            ]);
        }

        return response()->json([
            'configured' => true,
            'available' => true,
            'editor_url' => $onlyOffice->editorScriptUrl(),
            'document_server_url' => $onlyOffice->documentServerUrl(),
            'config' => $onlyOffice->buildEditorConfig($document, $request->user()),
        ]);
    }

    public function onlyOfficeFile(Request $request, LegalDocument $document): StreamedResponse
    {
        abort_unless($request->hasValidSignature(), 403, 'Invalid or expired document link.');

        abort_unless(Storage::disk($document->disk)->exists($document->path), 404, 'Document file was not found.');

        return Storage::disk($document->disk)->response(
            $document->path,
            $document->original_filename,
            ['Content-Type' => $document->mime_type ?? 'application/octet-stream'],
        );
    }

    public function onlyOfficeCallback(
        Request $request,
        LegalDocument $document,
        OnlyOfficeConfigService $onlyOffice,
    ): JsonResponse {
        if (! $onlyOffice->isConfigured()) {
            return response()->json(['error' => 1]);
        }

        $payload = $onlyOffice->resolveCallbackPayload($request->all());
        $status = (int) ($payload['status'] ?? 0);

        if (in_array($status, [2, 6], true)) {
            $downloadUrl = $payload['url'] ?? null;
            if (! is_string($downloadUrl) || $downloadUrl === '') {
                return response()->json(['error' => 1]);
            }

            $response = Http::timeout(120)->get($downloadUrl);
            if (! $response->successful()) {
                return response()->json(['error' => 1]);
            }

            $contents = $response->body();
            Storage::disk($document->disk)->put($document->path, $contents);

            $document->update([
                'size' => strlen($contents),
                'version' => ((int) ($document->version ?? 1)) + 1,
            ]);

            activity('document')
                ->performedOn($document)
                ->withProperties([
                    'legal_matter_id' => $document->legal_matter_id,
                    'onlyoffice_status' => $status,
                    'version' => $document->version,
                ])
                ->log('Document saved via OnlyOffice');
        }

        return response()->json(['error' => 0]);
    }

    public function exportPdf(LegalDocument $document)
    {
        $this->authorize('download', $document);

        $html = $document->content_html;
        if (! $html && Storage::disk($document->disk)->exists($document->path)) {
            $html = Storage::disk($document->disk)->get($document->path);
        }
        abort_unless(is_string($html) && $html !== '', 422, 'Document has no HTML content to export.');

        $wrapped = '<!DOCTYPE html><html><head><meta charset="utf-8"><title>'
            .e($document->name)
            .'</title><style>body{font-family:DejaVu Sans,sans-serif;padding:24px;line-height:1.5;}</style></head><body>'
            .$html
            .'</body></html>';

        if (class_exists(\Dompdf\Dompdf::class)) {
            $dompdf = new \Dompdf\Dompdf;
            $dompdf->loadHtml($wrapped);
            $dompdf->setPaper('A4');
            $dompdf->render();

            return response($dompdf->output(), 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="'.str_replace('"', '', $document->name).'.pdf"',
            ]);
        }

        return response($wrapped, 200, [
            'Content-Type' => 'text/html',
            'Content-Disposition' => 'attachment; filename="'.str_replace('"', '', $document->name).'.html"',
        ]);
    }

    public function destroy(LegalDocument $document): JsonResponse
    {
        $this->authorize('delete', $document);

        Storage::disk($document->disk)->delete($document->path);
        $document->delete();

        return response()->json(['message' => 'Document deleted successfully.']);
    }
}
