<?php

namespace Database\Seeders;

use App\Models\MotionTemplate;
use Illuminate\Database\Seeder;

class MotionTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $templates = [
            [
                'slug' => 'motion_to_dismiss',
                'name' => 'Motion to Dismiss',
                'description' => 'Challenge the sufficiency of the complaint or jurisdiction.',
                'required_sections' => ['caption', 'introduction', 'statement_of_facts', 'argument', 'conclusion'],
            ],
            [
                'slug' => 'summary_judgment',
                'name' => 'Motion for Summary Judgment',
                'description' => 'Request judgment when no genuine dispute of material fact exists.',
                'required_sections' => ['caption', 'introduction', 'statement_of_undisputed_facts', 'argument', 'conclusion'],
            ],
            [
                'slug' => 'extension_of_time',
                'name' => 'Motion for Extension of Time',
                'description' => 'Request additional time to file or respond.',
                'required_sections' => ['caption', 'introduction', 'grounds', 'requested_relief'],
            ],
            [
                'slug' => 'injunction',
                'name' => 'Motion for Injunction',
                'description' => 'Seek temporary or preliminary injunctive relief.',
                'required_sections' => ['caption', 'introduction', 'statement_of_facts', 'legal_standard', 'argument', 'conclusion'],
            ],
            [
                'slug' => 'motion_to_compel',
                'name' => 'Motion to Compel',
                'description' => 'Compel discovery responses or production.',
                'required_sections' => ['caption', 'introduction', 'background', 'argument', 'requested_order'],
            ],
            [
                'slug' => 'stay_of_execution',
                'name' => 'Motion for Stay of Execution',
                'description' => 'Stay enforcement of a judgment or order pending appeal.',
                'required_sections' => ['caption', 'introduction', 'grounds', 'argument', 'conclusion'],
            ],
            [
                'slug' => 'bail',
                'name' => 'Motion for Bail',
                'description' => 'Request release on bail or modification of bail conditions.',
                'required_sections' => ['caption', 'introduction', 'background', 'argument', 'requested_relief'],
            ],
            [
                'slug' => 'substitution_of_service',
                'name' => 'Motion for Substitution of Service',
                'description' => 'Request alternate service methods when standard service fails.',
                'required_sections' => ['caption', 'introduction', 'efforts_to_serve', 'proposed_method', 'conclusion'],
            ],
        ];

        foreach ($templates as $template) {
            MotionTemplate::query()->updateOrCreate(
                [
                    'organization_id' => null,
                    'slug' => $template['slug'],
                ],
                [
                    'name' => $template['name'],
                    'description' => $template['description'],
                    'structure_html' => $this->structureHtml($template['name'], $template['required_sections']),
                    'required_sections' => $template['required_sections'],
                    'is_active' => true,
                ],
            );
        }
    }

    /**
     * @param  list<string>  $sections
     */
    private function structureHtml(string $name, array $sections): string
    {
        $blocks = array_map(
            fn (string $section): string => '<h2>'.str_replace('_', ' ', ucfirst($section)).'</h2><p>[Draft content]</p>',
            $sections,
        );

        return '<h1>'.$name.'</h1>'.implode('', $blocks);
    }
}
