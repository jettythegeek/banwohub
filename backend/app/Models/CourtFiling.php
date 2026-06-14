<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class CourtFiling extends Model
{
    use LogsActivity;

    /** @var list<string> */
    public const STATUSES = [
        'draft',
        'under_review',
        'approved',
        'ready_to_file',
        'filed',
        'accepted_by_court',
        'rejected_by_court',
        'correction_required',
        'resubmitted',
        'hearing_date_assigned',
        'completed',
    ];

    /** @var list<string> */
    public const FILING_METHODS = ['manual', 'e_filing', 'mail', 'in_person'];

    protected $fillable = [
        'organization_id',
        'legal_matter_id',
        'court_form_instance_id',
        'legal_motion_id',
        'title',
        'court',
        'filing_date',
        'filed_by',
        'filing_method',
        'court_reference_number',
        'document_ids',
        'status',
        'court_response',
        'notes',
        'correction_deadline',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'filing_date' => 'date',
            'correction_deadline' => 'date',
            'document_ids' => 'array',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['title', 'status', 'court', 'court_reference_number', 'filing_date'])
            ->logOnlyDirty()
            ->useLogName('filing');
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function legalMatter(): BelongsTo
    {
        return $this->belongsTo(LegalMatter::class);
    }

    public function courtFormInstance(): BelongsTo
    {
        return $this->belongsTo(CourtFormInstance::class);
    }

    public function legalMotion(): BelongsTo
    {
        return $this->belongsTo(LegalMotion::class);
    }

    public function filedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'filed_by');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function canTransitionTo(string $status): bool
    {
        return match ($this->status) {
            'draft' => in_array($status, ['under_review', 'ready_to_file'], true),
            'under_review' => in_array($status, ['approved', 'draft'], true),
            'approved' => in_array($status, ['ready_to_file', 'under_review'], true),
            'ready_to_file' => $status === 'filed',
            'filed' => in_array($status, ['accepted_by_court', 'rejected_by_court'], true),
            'rejected_by_court' => in_array($status, ['correction_required', 'resubmitted'], true),
            'correction_required' => in_array($status, ['resubmitted', 'ready_to_file'], true),
            'resubmitted' => in_array($status, ['filed', 'accepted_by_court', 'rejected_by_court'], true),
            'accepted_by_court' => in_array($status, ['hearing_date_assigned', 'completed'], true),
            'hearing_date_assigned' => $status === 'completed',
            'completed' => false,
            default => false,
        };
    }
}
