<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class ResearchFolder extends Model
{
    use LogsActivity;

    protected $fillable = [
        'organization_id',
        'legal_matter_id',
        'name',
        'description',
        'practice_area',
        'legal_issue',
        'created_by',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'practice_area', 'legal_issue'])
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

    public function savedItems(): HasMany
    {
        return $this->hasMany(ResearchSavedItem::class);
    }
}
