<?php

namespace App\Services;

use App\Models\LegalResearchEntry;
use App\Models\Organization;
use App\Models\ResearchSavedItem;
use Illuminate\Support\Str;

class ResearchLibraryContextService
{
    /**
     * @return list<array<string, mixed>>
     */
    public function searchForOrganization(Organization $organization, string $query, ?int $legalMatterId = null, int $limit = 12): array
    {
        $terms = $this->extractTerms($query);
        if ($terms === []) {
            return [];
        }

        $entriesQuery = LegalResearchEntry::query()
            ->where('organization_id', $organization->id)
            ->where(function ($q) use ($terms): void {
                foreach ($terms as $term) {
                    $like = '%'.$term.'%';
                    $q->orWhere('title', 'like', $like)
                        ->orWhere('citation', 'like', $like)
                        ->orWhere('summary', 'like', $like)
                        ->orWhere('jurisdiction', 'like', $like);
                }
            })
            ->orderByDesc('updated_at')
            ->limit($limit);

        $entries = $entriesQuery->get();

        if ($legalMatterId !== null) {
            $savedEntryIds = ResearchSavedItem::query()
                ->where('organization_id', $organization->id)
                ->where('legal_matter_id', $legalMatterId)
                ->pluck('legal_research_entry_id')
                ->all();

            if ($savedEntryIds !== []) {
                $matterEntries = LegalResearchEntry::query()
                    ->where('organization_id', $organization->id)
                    ->whereIn('id', $savedEntryIds)
                    ->orderByDesc('updated_at')
                    ->limit($limit)
                    ->get();

                $entries = $matterEntries->merge($entries)->unique('id')->take($limit);
            }
        }

        return $entries->map(fn (LegalResearchEntry $entry) => [
            'id' => $entry->id,
            'title' => $entry->title,
            'citation' => $entry->citation,
            'summary' => Str::limit((string) $entry->summary, 500),
            'document_type' => $entry->document_type,
            'jurisdiction' => $entry->jurisdiction,
        ])->values()->all();
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function enrichPayload(Organization $organization, string $endpoint, array $payload): array
    {
        if (! in_array($endpoint, [
            'research/query',
            'research/search-cases',
            'research/suggest-authorities',
            'research/generate-memo',
            'research/strategy',
            'brief/build-arguments',
            'brief/generate-from-facts',
        ], true)) {
            return $payload;
        }

        $searchText = (string) ($payload['query']
            ?? $payload['issue']
            ?? $payload['case_facts']
            ?? '');

        if ($searchText === '') {
            return $payload;
        }

        $matterId = isset($payload['legal_matter_id']) ? (int) $payload['legal_matter_id'] : null;
        $saved = $this->searchForOrganization($organization, $searchText, $matterId);

        if ($saved !== []) {
            $payload['saved_authorities'] = $saved;
            $payload['saved_authorities_text'] = collect($saved)
                ->map(fn (array $item) => '- '.($item['citation'] ?: $item['title']).': '.($item['summary'] ?? ''))
                ->join("\n");
        }

        return $payload;
    }

    /**
     * @return list<string>
     */
    private function extractTerms(string $query): array
    {
        $normalized = preg_replace('/[^\p{L}\p{N}\s§.-]/u', ' ', $query) ?? $query;
        $parts = preg_split('/\s+/', strtolower(trim($normalized))) ?: [];

        return array_values(array_filter($parts, fn (string $part) => strlen($part) >= 3));
    }
}
