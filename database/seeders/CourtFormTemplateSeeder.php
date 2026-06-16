<?php

namespace Database\Seeders;

use App\Models\CourtFormTemplate;
use Illuminate\Database\Seeder;

class CourtFormTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $templates = [
            [
                'name' => 'Civil Cover Sheet',
                'jurisdiction' => 'Federal',
                'court' => 'U.S. District Court',
                'case_type' => 'civil',
                'filing_type' => 'initial',
                'fields' => [
                    ['key' => 'client_name', 'label' => 'Plaintiff name', 'type' => 'text', 'required' => true],
                    ['key' => 'client_address', 'label' => 'Plaintiff address', 'type' => 'textarea', 'required' => true],
                    ['key' => 'case_title', 'label' => 'Case caption', 'type' => 'text', 'required' => true],
                    ['key' => 'matter_number', 'label' => 'Matter number', 'type' => 'text', 'required' => false],
                    ['key' => 'court_name', 'label' => 'Court name', 'type' => 'text', 'required' => true],
                    ['key' => 'lawyer_name', 'label' => 'Attorney of record', 'type' => 'text', 'required' => true],
                ],
                'guidance' => [
                    'steps' => [
                        'Confirm case caption matches the complaint.',
                        'Attach the civil cover sheet to the initiating pleading.',
                        'Verify attorney bar number and contact details.',
                    ],
                    'required_attachments' => ['Complaint or petition'],
                    'signature_required' => true,
                ],
            ],
            [
                'name' => 'Notice of Appearance',
                'jurisdiction' => 'State',
                'court' => 'Superior Court',
                'case_type' => 'general',
                'filing_type' => 'notice',
                'fields' => [
                    ['key' => 'client_name', 'label' => 'Client name', 'type' => 'text', 'required' => true],
                    ['key' => 'case_title', 'label' => 'Case title', 'type' => 'text', 'required' => true],
                    ['key' => 'matter_number', 'label' => 'Case number', 'type' => 'text', 'required' => false],
                    ['key' => 'court_jurisdiction', 'label' => 'Jurisdiction', 'type' => 'text', 'required' => true],
                    ['key' => 'lawyer_name', 'label' => 'Attorney name', 'type' => 'text', 'required' => true],
                    ['key' => 'lawyer_email', 'label' => 'Attorney email', 'type' => 'email', 'required' => true],
                ],
                'guidance' => [
                    'steps' => [
                        'Enter the official court case number if assigned.',
                        'Serve all parties per local rules.',
                        'File manually or via e-filing portal when available.',
                    ],
                    'required_attachments' => [],
                    'signature_required' => true,
                ],
            ],
        ];

        foreach ($templates as $template) {
            CourtFormTemplate::query()->updateOrCreate(
                [
                    'organization_id' => null,
                    'name' => $template['name'],
                    'jurisdiction' => $template['jurisdiction'],
                ],
                [
                    ...$template,
                    'is_active' => true,
                ]
            );
        }
    }
}
