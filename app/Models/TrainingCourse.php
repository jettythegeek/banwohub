<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TrainingCourse extends Model
{
    protected $fillable = [
        'organization_id',
        'title',
        'description',
        'content',
        'video_url',
        'materials_url',
        'cle_credits',
        'is_required',
        'is_published',
        'quiz_questions',
        'passing_score',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'cle_credits' => 'decimal:2',
            'is_required' => 'boolean',
            'is_published' => 'boolean',
            'quiz_questions' => 'array',
            'passing_score' => 'integer',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(TrainingEnrollment::class);
    }
}
