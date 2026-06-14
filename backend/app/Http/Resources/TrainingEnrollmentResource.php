<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\TrainingEnrollment */
class TrainingEnrollmentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'training_course_id' => $this->training_course_id,
            'user_id' => $this->user_id,
            'status' => $this->status,
            'quiz_score' => $this->quiz_score,
            'cle_credits_earned' => $this->cle_credits_earned !== null ? (float) $this->cle_credits_earned : null,
            'started_at' => $this->started_at?->toIso8601String(),
            'completed_at' => $this->completed_at?->toIso8601String(),
            'course' => $this->whenLoaded('course', fn () => $this->course ? [
                'id' => $this->course->id,
                'title' => $this->course->title,
                'cle_credits' => (float) $this->course->cle_credits,
                'is_required' => $this->course->is_required,
            ] : null),
            'user' => $this->whenLoaded('user', fn () => $this->user ? [
                'id' => $this->user->id,
                'name' => $this->user->name,
                'email' => $this->user->email,
            ] : null),
            'certificate' => $this->whenLoaded('certificate', fn () => $this->certificate ? [
                'id' => $this->certificate->id,
                'certificate_number' => $this->certificate->certificate_number,
                'issued_at' => $this->certificate->issued_at?->toIso8601String(),
            ] : null),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
