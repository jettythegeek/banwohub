<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiGovernanceLog extends Model
{
    public const ACTIONS = [
        'chatbot',
        'document_summarize',
        'draft_assist',
        'case_qa',
        'intake_summary',
        'timeline_summary',
    ];

    public const BOT_CONTEXTS = ['public', 'staff', 'lawyer'];

    protected $fillable = [
        'organization_id',
        'user_id',
        'action_type',
        'bot_context',
        'legal_matter_id',
        'legal_document_id',
        'output_id',
        'model',
        'status',
        'output_preview',
        'prompt_context',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'prompt_context' => 'array',
            'metadata' => 'array',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function legalMatter(): BelongsTo
    {
        return $this->belongsTo(LegalMatter::class);
    }

    public function legalDocument(): BelongsTo
    {
        return $this->belongsTo(LegalDocument::class);
    }
}
