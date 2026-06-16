<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class CaseNote extends Model
{
    use SoftDeletes;

    public const TYPES = [
        'private_note',
        'meeting_note',
        'court_note',
        'strategy_note',
        'research_summary',
        'internal_memo',
        'call_note',
        'instruction_note',
    ];

    public const VISIBILITIES = [
        'private',
        'assigned_team',
        'senior_lawyers',
        'admin',
        'client_visible',
    ];

    protected $fillable = [
        'organization_id',
        'legal_matter_id',
        'author_id',
        'note_type',
        'visibility',
        'title',
        'body',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function legalMatter(): BelongsTo
    {
        return $this->belongsTo(LegalMatter::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }
}
