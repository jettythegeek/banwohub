<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\TimeEntry */
class TimeEntryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'legal_matter_id' => $this->legal_matter_id,
            'legal_task_id' => $this->legal_task_id,
            'user_id' => $this->user_id,
            'description' => $this->description,
            'started_at' => $this->started_at?->toIso8601String(),
            'ended_at' => $this->ended_at?->toIso8601String(),
            'duration_minutes' => $this->duration_minutes,
            'duration_hours' => round($this->duration_minutes / 60, 2),
            'billable' => $this->billable,
            'rate' => $this->rate !== null ? (float) $this->rate : null,
            'amount' => $this->amount(),
            'status' => $this->status,
            'is_running' => $this->is_running,
            'approved_at' => $this->approved_at?->toIso8601String(),
            'case' => $this->whenLoaded('legalMatter', fn () => $this->legalMatter ? [
                'id' => $this->legalMatter->id,
                'title' => $this->legalMatter->title,
                'matter_number' => $this->legalMatter->matter_number,
            ] : null),
            'task' => $this->whenLoaded('legalTask', fn () => $this->legalTask ? [
                'id' => $this->legalTask->id,
                'title' => $this->legalTask->title,
            ] : null),
            'user' => $this->whenLoaded('user', fn () => $this->user ? [
                'id' => $this->user->id,
                'name' => $this->user->name,
                'email' => $this->user->email,
            ] : null),
            'approver' => $this->whenLoaded('approver', fn () => $this->approver ? [
                'id' => $this->approver->id,
                'name' => $this->approver->name,
            ] : null),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
