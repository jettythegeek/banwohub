<?php

namespace App\Services;

use App\Models\TrainingCertificate;
use App\Models\TrainingEnrollment;
use Illuminate\Support\Str;

class TrainingCertificateService
{
    public function issue(TrainingEnrollment $enrollment): TrainingCertificate
    {
        if ($enrollment->certificate) {
            return $enrollment->certificate;
        }

        return TrainingCertificate::query()->create([
            'organization_id' => $enrollment->organization_id,
            'training_enrollment_id' => $enrollment->id,
            'certificate_number' => $this->generateNumber($enrollment),
            'issued_at' => now(),
        ]);
    }

    protected function generateNumber(TrainingEnrollment $enrollment): string
    {
        return sprintf(
            'CLE-%s-%s-%s',
            $enrollment->organization_id,
            $enrollment->training_course_id,
            Str::upper(Str::random(8))
        );
    }
}
