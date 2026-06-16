<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class IntakeSubmission extends Model
{
    use SoftDeletes;

    public const STATUSES = [
        'draft',
        'submitted',
        'in_review',
        'approved',
        'rejected',
        'more_info_requested',
    ];

    protected $fillable = [
        'organization_id',
        'intake_form_id',
        'client_id',
        'reviewed_by',
        'converted_client_id',
        'converted_legal_matter_id',
        'submitter_name',
        'submitter_email',
        'submitter_phone',
        'status',
        'data',
        'review_notes',
        'submitted_at',
        'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'data' => 'array',
            'submitted_at' => 'datetime',
            'reviewed_at' => 'datetime',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function intakeForm(): BelongsTo
    {
        return $this->belongsTo(IntakeForm::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function convertedClient(): BelongsTo
    {
        return $this->belongsTo(Client::class, 'converted_client_id');
    }

    public function convertedLegalMatter(): BelongsTo
    {
        return $this->belongsTo(LegalMatter::class, 'converted_legal_matter_id');
    }
}
