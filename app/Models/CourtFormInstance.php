<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CourtFormInstance extends Model
{
    /** @var list<string> */
    public const STATUSES = [
        'draft',
        'under_review',
        'approved',
        'ready_to_file',
        'filed',
    ];

    protected $fillable = [
        'organization_id',
        'legal_matter_id',
        'court_form_template_id',
        'court_filing_id',
        'title',
        'field_values',
        'status',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'field_values' => 'array',
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

    public function template(): BelongsTo
    {
        return $this->belongsTo(CourtFormTemplate::class, 'court_form_template_id');
    }

    public function courtFiling(): BelongsTo
    {
        return $this->belongsTo(CourtFiling::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
