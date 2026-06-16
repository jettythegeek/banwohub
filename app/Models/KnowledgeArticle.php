<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class KnowledgeArticle extends Model
{
    use LogsActivity;

    /** @var list<string> */
    public const CONTENT_TYPES = [
        'article',
        'sop',
        'clause_snippet',
        'policy',
        'practice_guide',
        'template',
        'training',
    ];

    /** @var list<string> */
    public const CATEGORIES = [
        'legal_updates',
        'internal_policies',
        'practice_guides',
        'training',
        'templates',
        'research_notes',
        'sops',
        'clauses',
        'case_strategy',
    ];

    protected $fillable = [
        'organization_id',
        'legal_matter_id',
        'title',
        'content',
        'excerpt',
        'content_type',
        'category',
        'practice_area',
        'tags',
        'is_published',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'tags' => 'array',
            'is_published' => 'boolean',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['title', 'content_type', 'category', 'is_published'])
            ->logOnlyDirty()
            ->useLogName('knowledge');
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
}
