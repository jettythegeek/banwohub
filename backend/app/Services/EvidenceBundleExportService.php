<?php

namespace App\Services;

use App\Models\EvidenceItem;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use ZipArchive;

class EvidenceBundleExportService
{
    /**
     * @return array{items: list<array<string, mixed>>, total: int}
     */
    public function exhibitIndex(int $legalMatterId, int $organizationId): array
    {
        $items = $this->exhibitsForMatter($legalMatterId, $organizationId);

        return [
            'items' => $items->map(fn (EvidenceItem $item) => $this->indexRow($item))->values()->all(),
            'total' => $items->count(),
        ];
    }

    public function createBundleZip(int $legalMatterId, int $organizationId): string
    {
        $items = $this->exhibitsForMatter($legalMatterId, $organizationId);
        $index = $this->exhibitIndex($legalMatterId, $organizationId);

        $tmpDir = storage_path('app/tmp');
        if (! is_dir($tmpDir)) {
            mkdir($tmpDir, 0755, true);
        }

        $zipPath = $tmpDir.'/evidence-bundle-'.uniqid('', true).'.zip';
        $zip = new ZipArchive;
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('Could not create exhibit bundle archive.');
        }

        $indexLines = ['EXHIBIT INDEX', str_repeat('=', 40), ''];
        foreach ($index['items'] as $row) {
            $indexLines[] = sprintf(
                '%s — %s (%s)',
                $row['exhibit_number'],
                $row['title'],
                $row['evidence_type']
            );
            if (! empty($row['description'])) {
                $indexLines[] = '  Description: '.$row['description'];
            }
            if (! empty($row['source'])) {
                $indexLines[] = '  Source: '.$row['source'];
            }
            if (! empty($row['date_obtained'])) {
                $indexLines[] = '  Date obtained: '.$row['date_obtained'];
            }
            $indexLines[] = '';
        }

        $zip->addFromString('exhibit-index.txt', implode("\n", $indexLines));

        foreach ($items as $item) {
            if (! $item->path || ! Storage::disk($item->disk)->exists($item->path)) {
                continue;
            }
            $safeNumber = preg_replace('/[^A-Za-z0-9._-]/', '_', (string) $item->exhibit_number) ?: 'unnumbered';
            $filename = $safeNumber.'_'.($item->original_filename ?: 'file');
            $zip->addFile(Storage::disk($item->disk)->path($item->path), $filename);
        }

        $zip->close();

        return $zipPath;
    }

    /**
     * @return Collection<int, EvidenceItem>
     */
    protected function exhibitsForMatter(int $legalMatterId, int $organizationId): Collection
    {
        return EvidenceItem::query()
            ->where('organization_id', $organizationId)
            ->where('legal_matter_id', $legalMatterId)
            ->whereNotNull('exhibit_number')
            ->orderBy('exhibit_number')
            ->get();
    }

    /**
     * @return array<string, mixed>
     */
    protected function indexRow(EvidenceItem $item): array
    {
        return [
            'id' => $item->id,
            'exhibit_number' => $item->exhibit_number,
            'title' => $item->title,
            'description' => $item->description,
            'evidence_type' => $item->evidence_type,
            'source' => $item->source,
            'date_obtained' => $item->date_obtained?->toDateString(),
            'status' => $item->status,
            'original_filename' => $item->original_filename,
        ];
    }
}
