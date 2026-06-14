<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\ResearchSavedItem */
class ResearchSavedItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'research_folder_id' => $this->research_folder_id,
            'legal_research_entry_id' => $this->legal_research_entry_id,
            'legal_matter_id' => $this->legal_matter_id,
            'notes' => $this->notes,
            'entry' => $this->whenLoaded('entry', fn () => new LegalResearchEntryResource($this->entry)),
            'folder' => $this->whenLoaded('folder', fn () => new ResearchFolderResource($this->folder)),
            'saver' => $this->whenLoaded('saver', fn () => [
                'id' => $this->saver?->id,
                'name' => $this->saver?->name,
            ]),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
