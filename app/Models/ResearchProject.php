<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class ResearchProject extends Model
{
    use LogsActivity;

    protected $fillable = [
        'organization_id',
        'legal_matter_id',
        'name',
        'description',
        'case_theory',
        'jurisdiction',
        'practice_area',
        'created_by',
        'updated_by',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'jurisdiction', 'practice_area'])
            ->logOnlyDirty()
            ->useLogName('research');
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function legalMatter(): BelongsTo
    {
        return $this->belongsTo(LegalMatter::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function chatMessages(): HasMany
    {
        return $this->hasMany(ResearchChatMessage::class)->orderBy('created_at');
    }
}
