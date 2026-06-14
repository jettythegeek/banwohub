<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class TrainingEnrollment extends Model
{
    /** @var list<string> */
    public const STATUSES = ['assigned', 'in_progress', 'completed', 'failed'];

    protected $fillable = [
        'organization_id',
        'training_course_id',
        'user_id',
        'status',
        'quiz_score',
        'cle_credits_earned',
        'started_at',
        'completed_at',
        'assigned_by',
    ];

    protected function casts(): array
    {
        return [
            'cle_credits_earned' => 'decimal:2',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(TrainingCourse::class, 'training_course_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function assigner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    public function certificate(): HasOne
    {
        return $this->hasOne(TrainingCertificate::class);
    }
}
