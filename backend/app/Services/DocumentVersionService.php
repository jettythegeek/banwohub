<?php

namespace App\Services;

use App\Models\DocumentVersion;
use App\Models\LegalDocument;
use App\Models\User;

class DocumentVersionService
{
    public function recordVersion(
        LegalDocument $document,
        string $contentHtml,
        User $user,
        ?string $changeSummary = null,
        string $source = 'human',
    ): DocumentVersion {
        return DocumentVersion::query()->updateOrCreate(
            [
                'document_id' => $document->id,
                'version_number' => (int) ($document->version ?? 1),
            ],
            [
                'content_html' => $contentHtml,
                'created_by' => $user->id,
                'change_summary' => $changeSummary,
                'source' => in_array($source, DocumentVersion::SOURCES, true) ? $source : 'human',
            ],
        );
    }
}
