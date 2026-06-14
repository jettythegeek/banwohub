<?php

namespace App\Http\Controllers\Api\V1\Concerns;

use App\Models\LegalBrief;
use App\Models\ResearchProject;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

trait ValidatesBriefWriterPayload
{
    /**
     * @return array<string, mixed>
     */
    protected function briefWriterContext(Request $request, LegalBrief $brief): array
    {
        return [
            'brief_id' => $brief->id,
            'brief_title' => $brief->title,
            'brief_type' => $brief->brief_type,
            'jurisdiction' => $brief->jurisdiction,
            'court_type' => $brief->court_type,
            'cause_of_action' => $brief->cause_of_action,
            'case_facts' => $brief->case_facts,
            'statutes' => $brief->statutes,
            'desired_outcome' => $brief->desired_outcome,
            'citation_style' => $brief->citation_style ?? 'bluebook',
            'content_html' => $brief->content_html,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function validatedBriefWriterOverrides(Request $request): array
    {
        return $request->validate([
            'brief_type' => ['nullable', 'string', Rule::in(LegalBrief::BRIEF_TYPES)],
            'jurisdiction' => ['nullable', 'string', 'max:120'],
            'court_type' => ['nullable', 'string', Rule::in(LegalBrief::COURT_TYPES)],
            'cause_of_action' => ['nullable', 'string', 'max:500'],
            'case_facts' => ['nullable', 'string', 'max:16000'],
            'statutes' => ['nullable', 'string', 'max:4000'],
            'desired_outcome' => ['nullable', 'string', 'max:2000'],
            'citation_style' => ['nullable', 'string', Rule::in(LegalBrief::CITATION_STYLES)],
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    protected function mergeBriefWriterContext(LegalBrief $brief, array $overrides = []): array
    {
        $context = [
            'brief_id' => $brief->id,
            'brief_title' => $brief->title,
            'brief_type' => $brief->brief_type,
            'jurisdiction' => $brief->jurisdiction,
            'court_type' => $brief->court_type,
            'cause_of_action' => $brief->cause_of_action,
            'case_facts' => $brief->case_facts,
            'statutes' => $brief->statutes,
            'desired_outcome' => $brief->desired_outcome,
            'citation_style' => $brief->citation_style ?? 'bluebook',
            'content_html' => $brief->content_html,
        ];

        foreach ($overrides as $key => $value) {
            if ($value !== null && $value !== '') {
                $context[$key] = $value;
            }
        }

        return $context;
    }

    /**
     * @return array<string, mixed>
     */
    protected function validatedResearchCommandPayload(Request $request): array
    {
        return $request->validate([
            'legal_matter_id' => ['nullable', 'integer', 'exists:legal_matters,id'],
            'research_project_id' => ['nullable', 'integer', 'exists:research_projects,id'],
            'query' => ['nullable', 'string', 'max:8000'],
            'issue' => ['nullable', 'string', 'max:4000'],
            'jurisdiction' => ['nullable', 'string', 'max:120'],
            'court_type' => ['nullable', 'string', Rule::in(LegalBrief::COURT_TYPES)],
            'statute_text' => ['nullable', 'string', 'max:16000'],
            'message' => ['nullable', 'string', 'max:8000'],
            'context' => ['nullable', 'string', 'max:4000'],
        ]);
    }

    protected function researchProjectForOrganization(int $projectId, int $organizationId): ResearchProject
    {
        $project = ResearchProject::query()->findOrFail($projectId);
        abort_unless($project->organization_id === $organizationId, 404);

        return $project;
    }
}
