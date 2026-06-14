<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class LegalMatter extends Model
{
    use LogsActivity;
    use SoftDeletes;

    public const STAGES = ['lead', 'open', 'closed'];

    public const MATTER_STAGES = [
        'intake',
        'conflict_check',
        'active',
        'awaiting_client',
        'in_court',
        'settlement',
        'on_hold',
        'closed',
        'archived',
    ];

    public const PRIORITIES = ['low', 'normal', 'high', 'urgent'];

    protected $fillable = [
        'organization_id',
        'client_id',
        'title',
        'matter_number',
        'practice_area',
        'case_type',
        'court_jurisdiction',
        'status',
        'stage',
        'matter_stage',
        'priority',
        'opened_at',
        'expected_close_at',
        'description',
        'billing_type',
        'billing_rate',
        'fixed_fee_amount',
        'retainer_minimum_amount',
        'trust_balance',
        'tags',
        'lead_lawyer_id',
        'created_by',
    ];

    public const BILLING_TYPES = ['hourly', 'fixed', 'retainer'];

    protected function casts(): array
    {
        return [
            'opened_at' => 'date',
            'expected_close_at' => 'date',
            'tags' => 'array',
            'billing_rate' => 'decimal:2',
            'fixed_fee_amount' => 'decimal:2',
            'retainer_minimum_amount' => 'decimal:2',
            'trust_balance' => 'decimal:2',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['title', 'status', 'stage', 'matter_stage', 'priority', 'lead_lawyer_id', 'matter_number'])
            ->logOnlyDirty()
            ->useLogName('case');
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function leadLawyer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'lead_lawyer_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function parties(): HasMany
    {
        return $this->hasMany(Party::class);
    }

    public function notes(): HasMany
    {
        return $this->hasMany(CaseNote::class);
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(LegalTask::class);
    }

    public function calendarEvents(): HasMany
    {
        return $this->hasMany(CalendarEvent::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(LegalDocument::class);
    }

    public function assignedStaff(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'legal_matter_user')
            ->withPivot('role')
            ->withTimestamps();
    }

    public function milestones(): HasMany
    {
        return $this->hasMany(LegalProjectMilestone::class);
    }

    public function projectBudgets(): HasMany
    {
        return $this->hasMany(LegalProjectBudget::class);
    }
}
