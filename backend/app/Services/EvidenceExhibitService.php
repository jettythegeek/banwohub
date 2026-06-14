<?php

namespace App\Services;

use App\Models\EvidenceItem;

class EvidenceExhibitService
{
    public function nextExhibitNumber(int $legalMatterId, int $organizationId): string
    {
        $existing = EvidenceItem::query()
            ->where('organization_id', $organizationId)
            ->where('legal_matter_id', $legalMatterId)
            ->whereNotNull('exhibit_number')
            ->pluck('exhibit_number');

        $max = 0;
        foreach ($existing as $number) {
            if (preg_match('/(\d+)/', (string) $number, $matches)) {
                $max = max($max, (int) $matches[1]);
            }
        }

        return 'Exhibit '.($max + 1);
    }

    public function assignExhibitNumber(EvidenceItem $item, ?string $exhibitNumber = null): EvidenceItem
    {
        $number = $exhibitNumber ?: $this->nextExhibitNumber($item->legal_matter_id, $item->organization_id);

        $item->update([
            'exhibit_number' => $number,
            'status' => $item->status === 'approved' || $item->status === 'marked_as_exhibit'
                ? 'marked_as_exhibit'
                : $item->status,
        ]);

        return $item->fresh();
    }
}
