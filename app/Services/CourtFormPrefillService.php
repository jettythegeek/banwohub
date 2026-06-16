<?php

namespace App\Services;

use App\Models\CourtFormTemplate;
use App\Models\LegalMatter;

class CourtFormPrefillService
{
    /**
     * @return array<string, mixed>
     */
    public function prefill(CourtFormTemplate $template, LegalMatter $matter): array
    {
        $matter->loadMissing(['client', 'leadLawyer']);
        $client = $matter->client;
        $lawyer = $matter->leadLawyer;

        $values = [];
        foreach ($template->fields as $field) {
            $key = $field['key'] ?? null;
            if (! is_string($key) || $key === '') {
                continue;
            }

            $values[$key] = $this->resolveFieldValue($key, $matter, $client, $lawyer);
        }

        return [
            'field_values' => $values,
            'sources' => [
                'client' => $client ? [
                    'id' => $client->id,
                    'name' => $client->name,
                ] : null,
                'case' => [
                    'id' => $matter->id,
                    'title' => $matter->title,
                    'matter_number' => $matter->matter_number,
                ],
                'lawyer' => $lawyer ? [
                    'id' => $lawyer->id,
                    'name' => $lawyer->name,
                ] : null,
            ],
        ];
    }

    /**
     * @param  \App\Models\Client|null  $client
     * @param  \App\Models\User|null  $lawyer
     */
    protected function resolveFieldValue(string $key, LegalMatter $matter, $client, $lawyer): mixed
    {
        return match ($key) {
            'client_name' => $client?->name,
            'client_email' => $client?->email,
            'client_phone' => $client?->phone,
            'client_address' => $client?->address,
            'client_company' => $client?->company_name,
            'case_title' => $matter->title,
            'matter_number' => $matter->matter_number,
            'practice_area' => $matter->practice_area,
            'case_type' => $matter->case_type,
            'court_jurisdiction' => $matter->court_jurisdiction,
            'case_description' => $matter->description,
            'lawyer_name' => $lawyer?->name,
            'lawyer_email' => $lawyer?->email,
            'court_name' => $matter->court_jurisdiction,
            default => null,
        };
    }
}
