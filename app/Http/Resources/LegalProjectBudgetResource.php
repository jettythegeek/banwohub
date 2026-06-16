<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\LegalProjectBudget */
class LegalProjectBudgetResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $budgeted = (float) $this->budgeted_amount;
        $actual = (float) $this->actual_amount;

        return [
            'id' => $this->id,
            'legal_matter_id' => $this->legal_matter_id,
            'category' => $this->category,
            'description' => $this->description,
            'budgeted_amount' => $budgeted,
            'actual_amount' => $actual,
            'variance' => round($budgeted - $actual, 2),
            'notes' => $this->notes,
            'legal_matter' => $this->whenLoaded('legalMatter', fn () => $this->legalMatter ? [
                'id' => $this->legalMatter->id,
                'title' => $this->legalMatter->title,
                'matter_number' => $this->legalMatter->matter_number,
            ] : null),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
