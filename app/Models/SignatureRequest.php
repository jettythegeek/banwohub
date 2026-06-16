<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SignatureRequest extends Model
{
    /** @var list<string> */
    public const STATUSES = ['pending', 'signed', 'declined'];

    protected $fillable = [
        'organization_id',
        'document_id',
        'legal_matter_id',
        'client_id',
        'status',
        'fields',
        'sent_by',
        'signed_document_id',
        'signed_at',
        'signer_ip',
        'audit',
        'message',
    ];

    protected function casts(): array
    {
        return [
            'fields' => 'array',
            'audit' => 'array',
            'signed_at' => 'datetime',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(LegalDocument::class, 'document_id');
    }

    public function legalMatter(): BelongsTo
    {
        return $this->belongsTo(LegalMatter::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sent_by');
    }

    public function signedDocument(): BelongsTo
    {
        return $this->belongsTo(LegalDocument::class, 'signed_document_id');
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }
}
