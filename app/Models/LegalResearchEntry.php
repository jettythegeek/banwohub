<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class LegalResearchEntry extends Model
{
    use LogsActivity;

    /** @var list<string> */
    public const DOCUMENT_TYPES = [
        'case',
        'statute',
        'regulation',
        'note',
        'principle',
        'paragraph',
        'authority',
    ];

    protected $fillable = [
        'organization_id',
        'title',
        'citation',
        'summary',
        'jurisdiction',
        'document_type',
        'tags',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'tags' => 'array',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['title', 'citation', 'document_type', 'jurisdiction'])
            ->logOnlyDirty()
            ->useLogName('research');
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function savedItems(): HasMany
    {
        return $this->hasMany(ResearchSavedItem::class);
    }
}
