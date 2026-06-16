<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BriefCitation extends Model
{
    protected $fillable = [
        'organization_id',
        'legal_brief_id',
        'authority',
        'citation_text',
        'sort_order',
        'source_note',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function legalBrief(): BelongsTo
    {
        return $this->belongsTo(LegalBrief::class);
    }
}
