<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TrainingCertificate extends Model
{
    protected $fillable = [
        'organization_id',
        'training_enrollment_id',
        'certificate_number',
        'issued_at',
    ];

    protected function casts(): array
    {
        return [
            'issued_at' => 'datetime',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(TrainingEnrollment::class, 'training_enrollment_id');
    }
}
