<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\TrainingCourse */
class TrainingCourseResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'content' => $this->content,
            'video_url' => $this->video_url,
            'materials_url' => $this->materials_url,
            'cle_credits' => (float) $this->cle_credits,
            'is_required' => $this->is_required,
            'is_published' => $this->is_published,
            'passing_score' => $this->passing_score,
            'quiz_questions' => $this->when(
                $request->user()?->can('training.view') ?? false,
                collect($this->quiz_questions ?? [])->map(function (array $question) use ($request): array {
                    $payload = [
                        'question' => $question['question'] ?? '',
                        'options' => $question['options'] ?? [],
                    ];
                    if ($request->user()?->can('training.assign')) {
                        $payload['correct_index'] = $question['correct_index'] ?? 0;
                    }

                    return $payload;
                })->values()->all()
            ),
            'quiz_question_count' => count($this->quiz_questions ?? []),
            'enrollments_count' => $this->when(isset($this->enrollments_count), $this->enrollments_count),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
