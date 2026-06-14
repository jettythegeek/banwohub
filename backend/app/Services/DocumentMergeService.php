<?php

namespace App\Services;

use App\Models\Client;
use App\Models\LegalDocument;
use App\Models\LegalMatter;
use Illuminate\Support\Carbon;

class DocumentMergeService
{
    /**
     * Catalog of merge fields for template editor UI.
     *
     * @return list<array{key: string, label: string, group: string, example?: string}>
     */
    public function fieldCatalog(): array
    {
        return [
            ['key' => 'client.name', 'label' => 'Client name', 'group' => 'Client', 'example' => 'Jane Doe'],
            ['key' => 'client.client_number', 'label' => 'Client number', 'group' => 'Client', 'example' => 'CL-000001'],
            ['key' => 'client.email', 'label' => 'Client email', 'group' => 'Client'],
            ['key' => 'client.phone', 'label' => 'Client phone', 'group' => 'Client'],
            ['key' => 'client.company_name', 'label' => 'Company name', 'group' => 'Client'],
            ['key' => 'client.address', 'label' => 'Client address', 'group' => 'Client'],
            ['key' => 'case.title', 'label' => 'Case title', 'group' => 'Case', 'example' => 'Smith v. Jones'],
            ['key' => 'case.matter_number', 'label' => 'Matter number', 'group' => 'Case', 'example' => 'CASE-0001'],
            ['key' => 'case.practice_area', 'label' => 'Practice area', 'group' => 'Case'],
            ['key' => 'case.case_type', 'label' => 'Case type', 'group' => 'Case'],
            ['key' => 'case.court_jurisdiction', 'label' => 'Jurisdiction', 'group' => 'Case'],
            ['key' => 'case.status', 'label' => 'Case status', 'group' => 'Case'],
            ['key' => 'case.stage', 'label' => 'Pipeline stage', 'group' => 'Case'],
            ['key' => 'case.matter_stage', 'label' => 'Matter stage', 'group' => 'Case'],
            ['key' => 'case.priority', 'label' => 'Priority', 'group' => 'Case'],
            ['key' => 'case.description', 'label' => 'Case description', 'group' => 'Case'],
            ['key' => 'case.opposing_party', 'label' => 'Opposing party', 'group' => 'Case'],
            ['key' => 'case.opened_at', 'label' => 'Opened date', 'group' => 'Case'],
            ['key' => 'case.expected_close_at', 'label' => 'Expected close', 'group' => 'Case'],
            ['key' => 'case.billing_type', 'label' => 'Billing type', 'group' => 'Billing'],
            ['key' => 'case.billing_rate', 'label' => 'Hourly rate', 'group' => 'Billing'],
            ['key' => 'case.fixed_fee_amount', 'label' => 'Fixed fee', 'group' => 'Billing'],
            ['key' => 'case.retainer_minimum_amount', 'label' => 'Retainer minimum', 'group' => 'Billing'],
            ['key' => 'lawyer.name', 'label' => 'Lead lawyer', 'group' => 'Staff'],
            ['key' => 'lawyer.email', 'label' => 'Lawyer email', 'group' => 'Staff'],
            ['key' => 'organization.name', 'label' => 'Firm name', 'group' => 'Firm'],
            ['key' => 'organization.email', 'label' => 'Firm email', 'group' => 'Firm'],
            ['key' => 'organization.phone', 'label' => 'Firm phone', 'group' => 'Firm'],
            ['key' => 'organization.address', 'label' => 'Firm address', 'group' => 'Firm'],
            ['key' => 'today', 'label' => 'Today (long)', 'group' => 'Dates', 'example' => 'June 6, 2026'],
            ['key' => 'today.short', 'label' => 'Today (short)', 'group' => 'Dates', 'example' => '06/06/2026'],
            ['key' => 'today.iso', 'label' => 'Today (ISO)', 'group' => 'Dates', 'example' => '2026-06-06'],
            ['key' => 'year', 'label' => 'Current year', 'group' => 'Dates', 'example' => '2026'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function mergeFields(LegalMatter $matter): array
    {
        $matter->loadMissing(['client', 'leadLawyer', 'organization', 'parties']);

        $client = $matter->client ?? Client::query()->find($matter->client_id);
        $lawyer = $matter->leadLawyer;
        $organization = $matter->organization;
        $today = now();

        $opposing = $matter->parties
            ->first(fn ($party) => in_array($party->party_type, ['opposing_party', 'defendant', 'opponent'], true));

        return [
            'client.name' => (string) ($client?->name ?? ''),
            'client.client_number' => (string) ($client?->client_number ?? ''),
            'client.email' => (string) ($client?->email ?? ''),
            'client.phone' => (string) ($client?->phone ?? ''),
            'client.company_name' => (string) ($client?->company_name ?? ''),
            'client.address' => (string) ($client?->address ?? ''),
            'case.title' => (string) $matter->title,
            'case.matter_number' => (string) ($matter->matter_number ?? ''),
            'case.practice_area' => (string) ($matter->practice_area ?? ''),
            'case.case_type' => (string) ($matter->case_type ?? ''),
            'case.court_jurisdiction' => (string) ($matter->court_jurisdiction ?? ''),
            'case.status' => (string) $matter->status,
            'case.stage' => (string) ($matter->stage ?? ''),
            'case.matter_stage' => (string) ($matter->matter_stage ?? ''),
            'case.priority' => (string) ($matter->priority ?? ''),
            'case.description' => (string) ($matter->description ?? ''),
            'case.opposing_party' => (string) ($opposing?->name ?? ''),
            'case.opened_at' => $matter->opened_at?->format('F j, Y') ?? '',
            'case.expected_close_at' => $matter->expected_close_at?->format('F j, Y') ?? '',
            'case.billing_type' => (string) ($matter->billing_type ?? ''),
            'case.billing_rate' => $matter->billing_rate !== null ? number_format((float) $matter->billing_rate, 2) : '',
            'case.fixed_fee_amount' => $matter->fixed_fee_amount !== null ? number_format((float) $matter->fixed_fee_amount, 2) : '',
            'case.retainer_minimum_amount' => $matter->retainer_minimum_amount !== null
                ? number_format((float) $matter->retainer_minimum_amount, 2)
                : '',
            'lawyer.name' => (string) ($lawyer?->name ?? ''),
            'lawyer.email' => (string) ($lawyer?->email ?? ''),
            'organization.name' => (string) ($organization?->name ?? ''),
            'organization.email' => (string) ($organization?->email ?? ''),
            'organization.phone' => (string) ($organization?->phone ?? ''),
            'organization.address' => (string) ($organization?->address ?? ''),
            'today' => $today->format('F j, Y'),
            'today.short' => $today->format('m/d/Y'),
            'today.iso' => $today->toDateString(),
            'year' => $today->format('Y'),
        ];
    }

    public function mergeTemplate(string $content, LegalMatter $matter): string
    {
        $fields = $this->mergeFields($matter);

        return preg_replace_callback(
            '/\{\{\s*([a-z0-9_.]+)\s*\}\}/i',
            fn (array $matches) => $fields[$matches[1]] ?? $matches[0],
            $content
        ) ?? $content;
    }

    public function templateContent(LegalDocument $template): string
    {
        if ($template->content_html) {
            return $template->content_html;
        }

        if ($template->path && \Illuminate\Support\Facades\Storage::disk($template->disk)->exists($template->path)) {
            $raw = \Illuminate\Support\Facades\Storage::disk($template->disk)->get($template->path);

            return is_string($raw) ? $raw : '';
        }

        return '<p>Template body</p>';
    }
}
