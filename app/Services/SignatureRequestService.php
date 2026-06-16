<?php

namespace App\Services;

use App\Models\LegalDocument;
use App\Models\Organization;
use App\Models\SignatureRequest;
use App\Models\User;
use App\Support\InAppNotifier;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SignatureRequestService
{
    public function __construct(private InAppNotifier $notifier)
    {
    }

    /**
     * @param  list<array{id: string, type: string, label: string, required?: bool}>  $fields
     */
    public function send(
        LegalDocument $document,
        User $sender,
        ?string $message = null,
        ?array $fields = null,
    ): SignatureRequest {
        $organization = $sender->organization;
        abort_unless($organization, 422, 'User organization is required.');
        abort_unless($document->organization_id === $organization->id, 403);
        abort_unless($document->legal_matter_id, 422, 'Document must belong to a case.');

        $matter = $document->legalMatter;
        abort_unless($matter && $matter->client_id, 422, 'Case must have a linked client for e-signature.');

        $existing = SignatureRequest::query()
            ->where('document_id', $document->id)
            ->where('status', 'pending')
            ->exists();

        if ($existing) {
            throw ValidationException::withMessages([
                'document_id' => 'This document already has a pending signature request.',
            ]);
        }

        if (! $document->client_visible) {
            $document->update(['client_visible' => true]);
        }

        $fieldDefs = $fields ?? $this->defaultFields();
        $this->validateFieldDefinitions($fieldDefs);

        $request = SignatureRequest::query()->create([
            'organization_id' => $organization->id,
            'document_id' => $document->id,
            'legal_matter_id' => $matter->id,
            'client_id' => $matter->client_id,
            'status' => 'pending',
            'fields' => $fieldDefs,
            'sent_by' => $sender->id,
            'message' => $message,
            'audit' => [
                'events' => [
                    $this->auditEvent('sent', [
                        'sent_by' => $sender->id,
                        'sent_by_name' => $sender->name,
                        'document_version' => $document->version,
                    ]),
                ],
            ],
        ]);

        $this->notifyClientSent($request, $sender, $organization);
        $this->logActivity($request, $sender, 'Signature request sent');

        return $request->load(['document', 'legalMatter', 'client', 'sender']);
    }

    /**
     * @param  array<string, mixed>  $fieldValues
     */
    public function sign(SignatureRequest $request, User $signer, array $fieldValues, ?string $ip, string $method = 'canvas'): SignatureRequest
    {
        abort_unless($request->isPending(), 422, 'This signature request is no longer pending.');
        abort_unless($signer->client_id === $request->client_id, 403);

        $this->validateFieldValues($request->fields ?? [], $fieldValues);

        $document = $request->document;
        abort_unless($document, 422, 'Source document was not found.');

        $signedDocument = $this->createSignedCopy($request, $fieldValues, $signer, $method);

        $audit = $request->audit ?? ['events' => []];
        $audit['events'][] = $this->auditEvent('signed', [
            'signer_id' => $signer->id,
            'signer_name' => $signer->name,
            'signer_email' => $signer->email,
            'ip' => $ip,
            'method' => $method,
            'field_values' => $this->sanitizeFieldValuesForAudit($fieldValues),
            'document_version' => $document->version,
            'signed_document_id' => $signedDocument->id,
        ]);

        $request->update([
            'status' => 'signed',
            'signed_at' => now(),
            'signer_ip' => $ip,
            'signed_document_id' => $signedDocument->id,
            'audit' => $audit,
        ]);

        $this->notifyStaffSigned($request, $signer);
        $this->logActivity($request, $signer, 'Document signed via portal');

        return $request->fresh()->load(['document', 'legalMatter', 'client', 'sender', 'signedDocument']);
    }

    public function decline(SignatureRequest $request, User $signer, ?string $ip, ?string $reason = null): SignatureRequest
    {
        abort_unless($request->isPending(), 422, 'This signature request is no longer pending.');
        abort_unless($signer->client_id === $request->client_id, 403);

        $audit = $request->audit ?? ['events' => []];
        $audit['events'][] = $this->auditEvent('declined', [
            'signer_id' => $signer->id,
            'signer_name' => $signer->name,
            'signer_email' => $signer->email,
            'ip' => $ip,
            'reason' => $reason,
        ]);

        $request->update([
            'status' => 'declined',
            'signer_ip' => $ip,
            'audit' => $audit,
        ]);

        $this->notifyStaffDeclined($request, $signer, $reason);
        $this->logActivity($request, $signer, 'Signature request declined');

        return $request->fresh()->load(['document', 'legalMatter', 'client', 'sender']);
    }

    /**
     * @return list<array{id: string, type: string, label: string, required: bool}>
     */
    public function defaultFields(): array
    {
        return [
            ['id' => 'signature', 'type' => 'signature', 'label' => 'Your signature', 'required' => true],
            ['id' => 'date', 'type' => 'date', 'label' => 'Date signed', 'required' => true],
            ['id' => 'printed_name', 'type' => 'text', 'label' => 'Full legal name', 'required' => true],
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $fields
     */
    protected function validateFieldDefinitions(array $fields): void
    {
        if ($fields === []) {
            throw ValidationException::withMessages(['fields' => 'At least one signature field is required.']);
        }

        foreach ($fields as $field) {
            if (! isset($field['id'], $field['type'], $field['label'])) {
                throw ValidationException::withMessages(['fields' => 'Each field requires id, type, and label.']);
            }

            if (! in_array($field['type'], ['signature', 'date', 'text', 'initials'], true)) {
                throw ValidationException::withMessages(['fields' => 'Invalid field type: '.$field['type']]);
            }
        }
    }

    /**
     * @param  list<array<string, mixed>>  $fieldDefs
     * @param  array<string, mixed>  $values
     */
    protected function validateFieldValues(array $fieldDefs, array $values): void
    {
        foreach ($fieldDefs as $field) {
            $id = $field['id'];
            $required = $field['required'] ?? true;
            $value = $values[$id] ?? null;

            if ($required && (! is_string($value) || trim($value) === '')) {
                throw ValidationException::withMessages([
                    "field_values.{$id}" => "{$field['label']} is required.",
                ]);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $fieldValues
     */
    protected function createSignedCopy(
        SignatureRequest $request,
        array $fieldValues,
        User $signer,
        string $method,
    ): LegalDocument {
        $original = $request->document;
        abort_unless($original, 422, 'Source document was not found.');

        $html = $this->buildSignedHtml($original, $request, $fieldValues, $signer, $method);
        $pathPrefix = "organizations/{$request->organization_id}/cases/{$request->legal_matter_id}/signed";
        $baseName = Str::slug($original->name).'-signed-'.now()->format('Ymd-His');
        $htmlPath = "{$pathPrefix}/{$baseName}.html";
        Storage::disk('local')->put($htmlPath, $html);

        $storedPath = $htmlPath;
        $mime = 'text/html';
        $originalFilename = $baseName.'.html';

        if (class_exists(\Dompdf\Dompdf::class)) {
            $wrapped = '<!DOCTYPE html><html><head><meta charset="utf-8"><style>body{font-family:DejaVu Sans,sans-serif;padding:24px;line-height:1.5;}</style></head><body>'.$html.'</body></html>';
            $dompdf = new \Dompdf\Dompdf;
            $dompdf->loadHtml($wrapped);
            $dompdf->setPaper('A4');
            $dompdf->render();
            $pdfPath = "{$pathPrefix}/{$baseName}.pdf";
            Storage::disk('local')->put($pdfPath, $dompdf->output());
            $storedPath = $pdfPath;
            $mime = 'application/pdf';
            $originalFilename = $baseName.'.pdf';
        }

        return LegalDocument::query()->create([
            'organization_id' => $request->organization_id,
            'legal_matter_id' => $request->legal_matter_id,
            'uploaded_by' => $signer->id,
            'document_type' => 'case_document',
            'name' => $original->name.' (Signed)',
            'category' => 'signed',
            'description' => 'Electronically signed copy',
            'content_html' => $html,
            'original_filename' => $originalFilename,
            'mime_type' => $mime,
            'size' => Storage::disk('local')->size($storedPath) ?: 0,
            'disk' => 'local',
            'path' => $storedPath,
            'version' => ($original->version ?? 1) + 1,
            'client_visible' => true,
        ]);
    }

    /**
     * @param  array<string, mixed>  $fieldValues
     */
    protected function buildSignedHtml(
        LegalDocument $original,
        SignatureRequest $request,
        array $fieldValues,
        User $signer,
        string $method,
    ): string {
        $base = $original->content_html;
        if (! $base && Storage::disk($original->disk)->exists($original->path)) {
            $base = Storage::disk($original->disk)->get($original->path);
        }
        $base = is_string($base) ? $base : '<p>Document</p>';

        $blocks = '<div style="margin-top:32px;border-top:1px solid #ccc;padding-top:16px;">';
        $blocks .= '<p><strong>Electronic signature record</strong></p>';
        $blocks .= '<p>Signed by: '.e($signer->name).' ('.e($signer->email).')</p>';
        $blocks .= '<p>Method: '.e($method).' · Request #'.$request->id.'</p>';

        foreach ($request->fields ?? [] as $field) {
            $id = $field['id'];
            $label = $field['label'];
            $value = $fieldValues[$id] ?? '';
            $blocks .= '<div style="margin:12px 0;"><p><strong>'.e($label).'</strong></p>';

            if ($field['type'] === 'signature' && str_starts_with((string) $value, 'data:image')) {
                $blocks .= '<img src="'.e($value).'" alt="Signature" style="max-height:80px;border-bottom:1px solid #333;" />';
            } else {
                $blocks .= '<p>'.e((string) $value).'</p>';
            }

            $blocks .= '</div>';
        }

        $blocks .= '<p style="font-size:12px;color:#666;">Signed at '.now()->toIso8601String().'</p>';
        $blocks .= '</div>';

        return $base.$blocks;
    }

    /**
     * @param  array<string, mixed>  $fieldValues
     * @return array<string, string>
     */
    protected function sanitizeFieldValuesForAudit(array $fieldValues): array
    {
        $sanitized = [];
        foreach ($fieldValues as $key => $value) {
            if (is_string($value) && str_starts_with($value, 'data:image')) {
                $sanitized[$key] = '[signature_image]';
            } else {
                $sanitized[$key] = is_string($value) ? $value : (string) $value;
            }
        }

        return $sanitized;
    }

    /**
     * @param  array<string, mixed>  $meta
     * @return array<string, mixed>
     */
    protected function auditEvent(string $action, array $meta = []): array
    {
        return array_merge([
            'action' => $action,
            'at' => now()->toIso8601String(),
        ], $meta);
    }

    protected function notifyClientSent(SignatureRequest $request, User $sender, Organization $organization): void
    {
        $document = $request->document;
        $data = [
            'signature_request_id' => $request->id,
            'legal_matter_id' => $request->legal_matter_id,
            'document_id' => $request->document_id,
            'client_id' => $request->client_id,
        ];

        User::query()
            ->where('organization_id', $organization->id)
            ->where('client_id', $request->client_id)
            ->where('is_active', true)
            ->get()
            ->each(fn (User $user) => $this->notifier->notifyUser(
                $user,
                'signature_request_sent',
                'Document ready to sign',
                $document ? "\"{$document->name}\" is ready for your signature." : 'A document is ready for your signature.',
                $data,
                $sender,
            ));
    }

    protected function notifyStaffSigned(SignatureRequest $request, User $signer): void
    {
        $document = $request->document;
        $matter = $request->legalMatter;
        $data = [
            'signature_request_id' => $request->id,
            'legal_matter_id' => $request->legal_matter_id,
            'document_id' => $request->document_id,
            'signed_document_id' => $request->signed_document_id,
            'client_id' => $request->client_id,
        ];

        $this->notifier->notifyPermission(
            $request->organization,
            'signatures.view',
            'signature_completed',
            'Document signed',
            "{$signer->name} signed ".($document ? "\"{$document->name}\"" : 'a document')
                .($matter ? " on {$matter->title}" : '.'),
            $data,
            $signer,
        );
    }

    protected function notifyStaffDeclined(SignatureRequest $request, User $signer, ?string $reason): void
    {
        $document = $request->document;
        $body = "{$signer->name} declined to sign ".($document ? "\"{$document->name}\"" : 'a document').'.';
        if ($reason) {
            $body .= " Reason: {$reason}";
        }

        $this->notifier->notifyPermission(
            $request->organization,
            'signatures.view',
            'signature_declined',
            'Signature declined',
            $body,
            [
                'signature_request_id' => $request->id,
                'legal_matter_id' => $request->legal_matter_id,
                'document_id' => $request->document_id,
                'client_id' => $request->client_id,
            ],
            $signer,
        );
    }

    protected function logActivity(SignatureRequest $request, User $actor, string $message): void
    {
        activity('signature')
            ->performedOn($request)
            ->causedBy($actor)
            ->withProperties([
                'document_id' => $request->document_id,
                'legal_matter_id' => $request->legal_matter_id,
                'status' => $request->status,
            ])
            ->log($message);
    }
}
