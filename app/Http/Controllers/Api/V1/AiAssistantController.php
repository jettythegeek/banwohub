<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Concerns\ResolvesOrganization;
use App\Http\Controllers\Api\V1\Concerns\ValidatesOrganizationAccess;
use App\Http\Controllers\Api\V1\Concerns\ValidatesBriefWriterPayload;
use App\Http\Controllers\Controller;
use App\Models\CalendarEvent;
use App\Models\CaseNote;
use App\Models\IntakeSubmission;
use App\Models\LegalBrief;
use App\Models\LegalDocument;
use App\Models\LegalMatter;
use App\Models\ResearchChatMessage;
use App\Models\ResearchProject;
use App\Services\AiGovernanceService;
use App\Services\AiRateLimiter;
use App\Services\AiServiceClient;
use App\Services\DocumentMergeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use RuntimeException;

class AiAssistantController extends Controller
{
    use ResolvesOrganization;
    use ValidatesOrganizationAccess;
    use ValidatesBriefWriterPayload;

    public function health(Request $request, AiServiceClient $client): JsonResponse
    {
        $organization = $this->organizationFor($request->user());
        $health = $client->health($organization);
        $usesProvider = $health !== null && isset($health['provider']);

        return response()->json([
            'available' => $health !== null,
            'health' => $health,
            'stub_mode' => $health === null,
            'active_provider' => $health['provider'] ?? null,
        ]);
    }

    public function chat(Request $request, AiServiceClient $client, AiGovernanceService $governance, AiRateLimiter $limiter): JsonResponse
    {
        $this->authorizeAiUse($request);

        $data = $request->validate([
            'message' => ['required', 'string', 'max:8000'],
            'context' => ['nullable', 'string', Rule::in(['staff', 'lawyer'])],
            'legal_matter_id' => ['nullable', 'integer', 'exists:legal_matters,id'],
        ]);

        return $this->invoke(
            $request,
            'chatbot',
            'chatbot',
            $client,
            $governance,
            $limiter,
            [
                'message' => $data['message'],
                'context' => $data['context'] ?? 'staff',
                'case_title' => $this->matterTitle($request, $data['legal_matter_id'] ?? null),
            ],
            $data['context'] ?? 'staff',
            $data['legal_matter_id'] ?? null,
        );
    }

    public function summarizeDocument(
        Request $request,
        AiServiceClient $client,
        AiGovernanceService $governance,
        AiRateLimiter $limiter,
        DocumentMergeService $mergeService,
    ): JsonResponse {
        $this->authorizeAiUse($request);

        $data = $request->validate([
            'legal_document_id' => ['required', 'integer', 'exists:legal_documents,id'],
        ]);

        $organization = $this->organizationFor($request->user());
        $document = LegalDocument::query()->findOrFail($data['legal_document_id']);
        abort_unless($document->organization_id === $organization->id, 404);

        $preview = $mergeService->templateContent($document);

        return $this->invoke(
            $request,
            'document/summarize',
            'document_summarize',
            $client,
            $governance,
            $limiter,
            [
                'document_name' => $document->name,
                'content_preview' => strip_tags($preview),
            ],
            'staff',
            $document->legal_matter_id,
            $document->id,
        );
    }

    public function draftAssist(
        Request $request,
        AiServiceClient $client,
        AiGovernanceService $governance,
        AiRateLimiter $limiter,
        DocumentMergeService $mergeService,
    ): JsonResponse {
        $this->authorizeAiUse($request);

        $data = $request->validate([
            'legal_matter_id' => ['required', 'integer', 'exists:legal_matters,id'],
            'template_id' => ['nullable', 'integer', 'exists:legal_documents,id'],
        ]);

        $organization = $this->organizationFor($request->user());
        $matter = $this->legalMatterForOrganization((int) $data['legal_matter_id'], $organization->id);

        $templateName = 'template';
        if (! empty($data['template_id'])) {
            $template = LegalDocument::query()->findOrFail($data['template_id']);
            abort_unless($template->organization_id === $organization->id, 404);
            $templateName = $template->name;
        }

        return $this->invoke(
            $request,
            'document/draft-assist',
            'draft_assist',
            $client,
            $governance,
            $limiter,
            [
                'template_name' => $templateName,
                'case_title' => $matter->title,
            ],
            'lawyer',
            $matter->id,
            $data['template_id'] ?? null,
        );
    }

    public function caseQa(Request $request, AiServiceClient $client, AiGovernanceService $governance, AiRateLimiter $limiter): JsonResponse
    {
        $this->authorizeAiUse($request);

        $data = $request->validate([
            'legal_matter_id' => ['required', 'integer', 'exists:legal_matters,id'],
            'question' => ['required', 'string', 'max:4000'],
        ]);

        $organization = $this->organizationFor($request->user());
        $matter = $this->legalMatterForOrganization((int) $data['legal_matter_id'], $organization->id);

        return $this->invoke(
            $request,
            'case/qa',
            'case_qa',
            $client,
            $governance,
            $limiter,
            [
                'question' => $data['question'],
                'case_title' => $matter->title,
            ],
            'lawyer',
            $matter->id,
        );
    }

    public function intakeSummary(Request $request, AiServiceClient $client, AiGovernanceService $governance, AiRateLimiter $limiter): JsonResponse
    {
        $this->authorizeAiUse($request);

        $data = $request->validate([
            'intake_submission_id' => ['required', 'integer', 'exists:intake_submissions,id'],
        ]);

        $organization = $this->organizationFor($request->user());
        $submission = IntakeSubmission::query()->with('client:id,name')->findOrFail($data['intake_submission_id']);
        abort_unless($submission->organization_id === $organization->id, 404);

        $fields = is_array($submission->data) ? count($submission->data) : 0;

        return $this->invoke(
            $request,
            'intake/summary',
            'intake_summary',
            $client,
            $governance,
            $limiter,
            [
                'client_name' => $submission->client?->name ?? $submission->submitter_name ?? 'client',
                'field_count' => $fields,
            ],
            'staff',
            $submission->converted_legal_matter_id,
        );
    }

    public function summarizeResearchNotes(
        Request $request,
        AiServiceClient $client,
        AiGovernanceService $governance,
        AiRateLimiter $limiter,
    ): JsonResponse {
        $this->authorizeAiUse($request);

        $data = $request->validate([
            'legal_matter_id' => ['required', 'integer', 'exists:legal_matters,id'],
            'case_note_ids' => ['nullable', 'array'],
            'case_note_ids.*' => ['integer', 'exists:case_notes,id'],
        ]);

        $organization = $this->organizationFor($request->user());
        $matter = $this->legalMatterForOrganization((int) $data['legal_matter_id'], $organization->id);
        $notes = $this->researchNotesForMatter(
            $organization->id,
            $matter->id,
            $data['case_note_ids'] ?? null,
        );

        if ($notes->isEmpty()) {
            return response()->json(['message' => 'No research notes found for this matter.'], 422);
        }

        return $this->invoke(
            $request,
            'research/summarize-notes',
            'research_summarize_notes',
            $client,
            $governance,
            $limiter,
            [
                'case_title' => $matter->title,
                'note_count' => $notes->count(),
                'notes_text' => $this->formatNotesForAi($notes),
            ],
            'lawyer',
            $matter->id,
        );
    }

    public function suggestAuthorities(
        Request $request,
        AiServiceClient $client,
        AiGovernanceService $governance,
        AiRateLimiter $limiter,
    ): JsonResponse {
        $this->authorizeAiUse($request);

        $data = $request->validate([
            'legal_matter_id' => ['required', 'integer', 'exists:legal_matters,id'],
            'issue' => ['required', 'string', 'max:2000'],
            'case_note_ids' => ['nullable', 'array'],
            'case_note_ids.*' => ['integer', 'exists:case_notes,id'],
        ]);

        $organization = $this->organizationFor($request->user());
        $matter = $this->legalMatterForOrganization((int) $data['legal_matter_id'], $organization->id);
        $notes = $this->researchNotesForMatter(
            $organization->id,
            $matter->id,
            $data['case_note_ids'] ?? null,
        );

        return $this->invoke(
            $request,
            'research/suggest-authorities',
            'research_suggest_authorities',
            $client,
            $governance,
            $limiter,
            [
                'case_title' => $matter->title,
                'issue' => $data['issue'],
                'practice_area' => $matter->practice_area,
                'note_count' => $notes->count(),
                'notes_text' => $notes->isNotEmpty() ? $this->formatNotesForAi($notes) : null,
            ],
            'lawyer',
            $matter->id,
        );
    }

    public function briefOutline(
        Request $request,
        AiServiceClient $client,
        AiGovernanceService $governance,
        AiRateLimiter $limiter,
    ): JsonResponse {
        $this->authorizeAiUse($request);

        $data = $request->validate([
            'legal_matter_id' => ['required', 'integer', 'exists:legal_matters,id'],
            'title' => ['required', 'string', 'max:255'],
            'issue' => ['nullable', 'string', 'max:2000'],
            'case_note_ids' => ['nullable', 'array'],
            'case_note_ids.*' => ['integer', 'exists:case_notes,id'],
        ]);

        $organization = $this->organizationFor($request->user());
        $matter = $this->legalMatterForOrganization((int) $data['legal_matter_id'], $organization->id);
        $notes = $this->researchNotesForMatter(
            $organization->id,
            $matter->id,
            $data['case_note_ids'] ?? null,
        );

        return $this->invoke(
            $request,
            'brief/outline',
            'brief_outline',
            $client,
            $governance,
            $limiter,
            [
                'case_title' => $matter->title,
                'brief_title' => $data['title'],
                'issue' => $data['issue'] ?? null,
                'practice_area' => $matter->practice_area,
                'note_count' => $notes->count(),
                'notes_text' => $notes->isNotEmpty() ? $this->formatNotesForAi($notes) : null,
            ],
            'lawyer',
            $matter->id,
        );
    }

    public function motionStructureCheck(
        Request $request,
        AiServiceClient $client,
        AiGovernanceService $governance,
        AiRateLimiter $limiter,
    ): JsonResponse {
        $this->authorizeAiUse($request);

        $data = $request->validate([
            'legal_matter_id' => ['required', 'integer', 'exists:legal_matters,id'],
            'title' => ['required', 'string', 'max:255'],
            'motion_type' => ['nullable', 'string', 'max:100'],
            'content_html' => ['required', 'string', 'max:32000'],
            'required_sections' => ['nullable', 'array'],
            'required_sections.*' => ['string', 'max:100'],
        ]);

        $organization = $this->organizationFor($request->user());
        $matter = $this->legalMatterForOrganization((int) $data['legal_matter_id'], $organization->id);

        return $this->invoke(
            $request,
            'motion/structure-check',
            'motion_structure_check',
            $client,
            $governance,
            $limiter,
            [
                'case_title' => $matter->title,
                'motion_title' => $data['title'],
                'motion_type' => $data['motion_type'] ?? null,
                'content_html' => $data['content_html'],
                'required_sections' => $data['required_sections'] ?? [],
            ],
            'lawyer',
            $matter->id,
        );
    }

    public function briefRewrite(
        Request $request,
        AiServiceClient $client,
        AiGovernanceService $governance,
        AiRateLimiter $limiter,
    ): JsonResponse {
        $this->authorizeAiUse($request);

        $data = $request->validate([
            'legal_matter_id' => ['required', 'integer', 'exists:legal_matters,id'],
            'section_html' => ['required', 'string', 'max:16000'],
            'instruction' => ['nullable', 'string', 'max:1000'],
        ]);

        $organization = $this->organizationFor($request->user());
        $matter = $this->legalMatterForOrganization((int) $data['legal_matter_id'], $organization->id);

        return $this->invoke(
            $request,
            'brief/rewrite-section',
            'brief_rewrite',
            $client,
            $governance,
            $limiter,
            [
                'case_title' => $matter->title,
                'section_html' => $data['section_html'],
                'instruction' => $data['instruction'] ?? 'Improve clarity and legal precision.',
            ],
            'lawyer',
            $matter->id,
        );
    }

    public function briefGenerateFromFacts(
        Request $request,
        AiServiceClient $client,
        AiGovernanceService $governance,
        AiRateLimiter $limiter,
    ): JsonResponse {
        $this->authorizeAiUse($request);

        $data = $request->validate([
            'legal_matter_id' => ['required', 'integer', 'exists:legal_matters,id'],
            'legal_brief_id' => ['nullable', 'integer', 'exists:legal_briefs,id'],
            'title' => ['nullable', 'string', 'max:255'],
            'brief_type' => ['nullable', 'string', Rule::in(LegalBrief::BRIEF_TYPES)],
            'jurisdiction' => ['nullable', 'string', 'max:120'],
            'court_type' => ['nullable', 'string', Rule::in(LegalBrief::COURT_TYPES)],
            'cause_of_action' => ['nullable', 'string', 'max:500'],
            'case_facts' => ['required', 'string', 'max:16000'],
            'statutes' => ['nullable', 'string', 'max:4000'],
            'desired_outcome' => ['nullable', 'string', 'max:2000'],
            'citation_style' => ['nullable', 'string', Rule::in(LegalBrief::CITATION_STYLES)],
        ]);

        $organization = $this->organizationFor($request->user());
        $matter = $this->legalMatterForOrganization((int) $data['legal_matter_id'], $organization->id);

        $context = [
            'case_title' => $matter->title,
            'brief_title' => $data['title'] ?? 'Draft brief',
            ...array_filter([
                'brief_type' => $data['brief_type'] ?? null,
                'jurisdiction' => $data['jurisdiction'] ?? null,
                'court_type' => $data['court_type'] ?? null,
                'cause_of_action' => $data['cause_of_action'] ?? null,
                'case_facts' => $data['case_facts'],
                'statutes' => $data['statutes'] ?? null,
                'desired_outcome' => $data['desired_outcome'] ?? null,
                'citation_style' => $data['citation_style'] ?? 'bluebook',
            ], fn ($value) => $value !== null && $value !== ''),
        ];

        if (! empty($data['legal_brief_id'])) {
            $brief = LegalBrief::query()->findOrFail($data['legal_brief_id']);
            abort_unless($brief->organization_id === $organization->id, 404);
            $context = $this->mergeBriefWriterContext($brief, $context);
        }

        return $this->invoke(
            $request,
            'brief/generate-from-facts',
            'brief_generate_from_facts',
            $client,
            $governance,
            $limiter,
            $context,
            'lawyer',
            $matter->id,
        );
    }

    public function briefBuildArguments(
        Request $request,
        AiServiceClient $client,
        AiGovernanceService $governance,
        AiRateLimiter $limiter,
    ): JsonResponse {
        $this->authorizeAiUse($request);

        $data = $request->validate([
            'legal_matter_id' => ['required', 'integer', 'exists:legal_matters,id'],
            'legal_brief_id' => ['nullable', 'integer', 'exists:legal_briefs,id'],
            'issue' => ['required', 'string', 'max:2000'],
        ]);

        $organization = $this->organizationFor($request->user());
        $matter = $this->legalMatterForOrganization((int) $data['legal_matter_id'], $organization->id);
        $context = [
            'case_title' => $matter->title,
            'issue' => $data['issue'],
            'practice_area' => $matter->practice_area,
        ];

        if (! empty($data['legal_brief_id'])) {
            $brief = LegalBrief::query()->findOrFail($data['legal_brief_id']);
            abort_unless($brief->organization_id === $organization->id, 404);
            $context = array_merge($context, $this->mergeBriefWriterContext($brief));
        }

        return $this->invoke(
            $request,
            'brief/build-arguments',
            'brief_build_arguments',
            $client,
            $governance,
            $limiter,
            $context,
            'lawyer',
            $matter->id,
        );
    }

    public function briefAnalyzeOpposition(
        Request $request,
        AiServiceClient $client,
        AiGovernanceService $governance,
        AiRateLimiter $limiter,
    ): JsonResponse {
        $this->authorizeAiUse($request);

        $data = $request->validate([
            'legal_matter_id' => ['required', 'integer', 'exists:legal_matters,id'],
            'legal_brief_id' => ['nullable', 'integer', 'exists:legal_briefs,id'],
            'content_html' => ['nullable', 'string', 'max:32000'],
            'issue' => ['nullable', 'string', 'max:2000'],
        ]);

        $organization = $this->organizationFor($request->user());
        $matter = $this->legalMatterForOrganization((int) $data['legal_matter_id'], $organization->id);
        $context = [
            'case_title' => $matter->title,
            'issue' => $data['issue'] ?? null,
            'content_html' => $data['content_html'] ?? null,
        ];

        if (! empty($data['legal_brief_id'])) {
            $brief = LegalBrief::query()->findOrFail($data['legal_brief_id']);
            abort_unless($brief->organization_id === $organization->id, 404);
            $context = array_merge($context, $this->mergeBriefWriterContext($brief));
            $context['content_html'] = $context['content_html'] ?? $brief->content_html;
        }

        return $this->invoke(
            $request,
            'brief/analyze-opposition',
            'brief_analyze_opposition',
            $client,
            $governance,
            $limiter,
            $context,
            'lawyer',
            $matter->id,
        );
    }

    public function briefEnhance(
        Request $request,
        AiServiceClient $client,
        AiGovernanceService $governance,
        AiRateLimiter $limiter,
    ): JsonResponse {
        $this->authorizeAiUse($request);

        $data = $request->validate([
            'legal_matter_id' => ['required', 'integer', 'exists:legal_matters,id'],
            'content_html' => ['required', 'string', 'max:32000'],
            'enhancement_goal' => ['nullable', 'string', Rule::in(['strengthen', 'tone', 'clarity', 'dedupe'])],
        ]);

        $organization = $this->organizationFor($request->user());
        $matter = $this->legalMatterForOrganization((int) $data['legal_matter_id'], $organization->id);

        return $this->invoke(
            $request,
            'brief/enhance',
            'brief_enhance',
            $client,
            $governance,
            $limiter,
            [
                'case_title' => $matter->title,
                'content_html' => $data['content_html'],
                'enhancement_goal' => $data['enhancement_goal'] ?? 'clarity',
            ],
            'lawyer',
            $matter->id,
        );
    }

    public function briefFormatCourt(
        Request $request,
        AiServiceClient $client,
        AiGovernanceService $governance,
        AiRateLimiter $limiter,
    ): JsonResponse {
        $this->authorizeAiUse($request);

        $data = $request->validate([
            'legal_matter_id' => ['required', 'integer', 'exists:legal_matters,id'],
            'legal_brief_id' => ['nullable', 'integer', 'exists:legal_briefs,id'],
            'content_html' => ['nullable', 'string', 'max:32000'],
            'court_type' => ['required', 'string', Rule::in(LegalBrief::COURT_TYPES)],
            'jurisdiction' => ['nullable', 'string', 'max:120'],
            'citation_style' => ['nullable', 'string', Rule::in(LegalBrief::CITATION_STYLES)],
        ]);

        $organization = $this->organizationFor($request->user());
        $matter = $this->legalMatterForOrganization((int) $data['legal_matter_id'], $organization->id);
        $context = [
            'case_title' => $matter->title,
            'court_type' => $data['court_type'],
            'jurisdiction' => $data['jurisdiction'] ?? null,
            'citation_style' => $data['citation_style'] ?? 'bluebook',
            'content_html' => $data['content_html'] ?? null,
        ];

        if (! empty($data['legal_brief_id'])) {
            $brief = LegalBrief::query()->findOrFail($data['legal_brief_id']);
            abort_unless($brief->organization_id === $organization->id, 404);
            $context = array_merge($context, $this->mergeBriefWriterContext($brief));
            $context['content_html'] = $context['content_html'] ?? $brief->content_html;
        }

        return $this->invoke(
            $request,
            'brief/format-court',
            'brief_format_court',
            $client,
            $governance,
            $limiter,
            $context,
            'lawyer',
            $matter->id,
        );
    }

    public function researchQuery(
        Request $request,
        AiServiceClient $client,
        AiGovernanceService $governance,
        AiRateLimiter $limiter,
    ): JsonResponse {
        $this->authorizeAiUse($request);

        $data = $request->validate([
            'query' => ['required', 'string', 'max:8000'],
            'legal_matter_id' => ['nullable', 'integer', 'exists:legal_matters,id'],
            'jurisdiction' => ['nullable', 'string', 'max:120'],
            'court_type' => ['nullable', 'string', Rule::in(LegalBrief::COURT_TYPES)],
        ]);

        $organization = $this->organizationFor($request->user());
        $matterTitle = null;
        $practiceArea = null;

        if (! empty($data['legal_matter_id'])) {
            $matter = $this->legalMatterForOrganization((int) $data['legal_matter_id'], $organization->id);
            $matterTitle = $matter->title;
            $practiceArea = $matter->practice_area;
        }

        return $this->invoke(
            $request,
            'research/query',
            'research_query',
            $client,
            $governance,
            $limiter,
            [
                'query' => $data['query'],
                'case_title' => $matterTitle,
                'practice_area' => $practiceArea,
                'jurisdiction' => $data['jurisdiction'] ?? null,
                'court_type' => $data['court_type'] ?? null,
                'legal_matter_id' => $data['legal_matter_id'] ?? null,
            ],
            'lawyer',
            $data['legal_matter_id'] ?? null,
        );
    }

    public function researchSearchCases(
        Request $request,
        AiServiceClient $client,
        AiGovernanceService $governance,
        AiRateLimiter $limiter,
    ): JsonResponse {
        $this->authorizeAiUse($request);

        $data = $request->validate([
            'issue' => ['required', 'string', 'max:4000'],
            'jurisdiction' => ['nullable', 'string', 'max:120'],
            'court_type' => ['nullable', 'string', Rule::in(LegalBrief::COURT_TYPES)],
            'legal_matter_id' => ['nullable', 'integer', 'exists:legal_matters,id'],
        ]);

        $organization = $this->organizationFor($request->user());
        $matterTitle = null;

        if (! empty($data['legal_matter_id'])) {
            $matter = $this->legalMatterForOrganization((int) $data['legal_matter_id'], $organization->id);
            $matterTitle = $matter->title;
        }

        return $this->invoke(
            $request,
            'research/search-cases',
            'research_search_cases',
            $client,
            $governance,
            $limiter,
            [
                'issue' => $data['issue'],
                'jurisdiction' => $data['jurisdiction'] ?? null,
                'court_type' => $data['court_type'] ?? null,
                'case_title' => $matterTitle,
                'legal_matter_id' => $data['legal_matter_id'] ?? null,
            ],
            'lawyer',
            $data['legal_matter_id'] ?? null,
        );
    }

    public function researchGenerateMemo(
        Request $request,
        AiServiceClient $client,
        AiGovernanceService $governance,
        AiRateLimiter $limiter,
    ): JsonResponse {
        $this->authorizeAiUse($request);

        $data = $request->validate([
            'issue' => ['required', 'string', 'max:4000'],
            'legal_matter_id' => ['nullable', 'integer', 'exists:legal_matters,id'],
            'research_project_id' => ['nullable', 'integer', 'exists:research_projects,id'],
            'memo_type' => ['nullable', 'string', Rule::in(['research_memo', 'issue_analysis', 'client_advisory', 'risk_assessment'])],
        ]);

        $organization = $this->organizationFor($request->user());
        $matterTitle = null;
        $projectContext = null;

        if (! empty($data['legal_matter_id'])) {
            $matter = $this->legalMatterForOrganization((int) $data['legal_matter_id'], $organization->id);
            $matterTitle = $matter->title;
        }

        if (! empty($data['research_project_id'])) {
            $project = $this->researchProjectForOrganization((int) $data['research_project_id'], $organization->id);
            $projectContext = [
                'name' => $project->name,
                'case_theory' => $project->case_theory,
                'jurisdiction' => $project->jurisdiction,
            ];
        }

        return $this->invoke(
            $request,
            'research/generate-memo',
            'research_generate_memo',
            $client,
            $governance,
            $limiter,
            [
                'issue' => $data['issue'],
                'memo_type' => $data['memo_type'] ?? 'research_memo',
                'case_title' => $matterTitle,
                'project' => $projectContext,
                'legal_matter_id' => $data['legal_matter_id'] ?? null,
            ],
            'lawyer',
            $data['legal_matter_id'] ?? null,
        );
    }

    public function researchAnalyzeStatute(
        Request $request,
        AiServiceClient $client,
        AiGovernanceService $governance,
        AiRateLimiter $limiter,
    ): JsonResponse {
        $this->authorizeAiUse($request);

        $data = $request->validate([
            'statute_text' => ['required', 'string', 'max:16000'],
            'jurisdiction' => ['nullable', 'string', 'max:120'],
            'question' => ['nullable', 'string', 'max:2000'],
        ]);

        return $this->invoke(
            $request,
            'research/analyze-statute',
            'research_analyze_statute',
            $client,
            $governance,
            $limiter,
            [
                'statute_text' => $data['statute_text'],
                'jurisdiction' => $data['jurisdiction'] ?? null,
                'question' => $data['question'] ?? null,
            ],
            'lawyer',
            null,
        );
    }

    public function researchStrategy(
        Request $request,
        AiServiceClient $client,
        AiGovernanceService $governance,
        AiRateLimiter $limiter,
    ): JsonResponse {
        $this->authorizeAiUse($request);

        $data = $request->validate([
            'issue' => ['required', 'string', 'max:4000'],
            'legal_matter_id' => ['required', 'integer', 'exists:legal_matters,id'],
            'context' => ['nullable', 'string', 'max:4000'],
        ]);

        $organization = $this->organizationFor($request->user());
        $matter = $this->legalMatterForOrganization((int) $data['legal_matter_id'], $organization->id);

        return $this->invoke(
            $request,
            'research/strategy',
            'research_strategy',
            $client,
            $governance,
            $limiter,
            [
                'issue' => $data['issue'],
                'context' => $data['context'] ?? null,
                'case_title' => $matter->title,
                'practice_area' => $matter->practice_area,
            ],
            'lawyer',
            $matter->id,
        );
    }

    public function researchChat(
        Request $request,
        AiServiceClient $client,
        AiGovernanceService $governance,
        AiRateLimiter $limiter,
    ): JsonResponse {
        $this->authorizeAiUse($request);
        abort_unless($request->user()?->can('research.view'), 403);

        $data = $request->validate([
            'research_project_id' => ['required', 'integer', 'exists:research_projects,id'],
            'message' => ['required', 'string', 'max:8000'],
        ]);

        $organization = $this->organizationFor($request->user());
        $project = $this->researchProjectForOrganization((int) $data['research_project_id'], $organization->id);
        $history = $project->chatMessages()
            ->orderBy('created_at')
            ->limit(20)
            ->get()
            ->map(fn (ResearchChatMessage $message) => [
                'role' => $message->role,
                'content' => $message->content,
            ])
            ->all();

        ResearchChatMessage::query()->create([
            'organization_id' => $organization->id,
            'research_project_id' => $project->id,
            'role' => 'user',
            'content' => $data['message'],
        ]);

        $response = $this->invoke(
            $request,
            'research/chat',
            'research_chat',
            $client,
            $governance,
            $limiter,
            [
                'message' => $data['message'],
                'project_name' => $project->name,
                'case_theory' => $project->case_theory,
                'jurisdiction' => $project->jurisdiction,
                'history' => $history,
            ],
            'lawyer',
            $project->legal_matter_id,
        );

        $payload = $response->getData(true);
        $logId = $payload['governance_log_id'] ?? null;

        ResearchChatMessage::query()->create([
            'organization_id' => $organization->id,
            'research_project_id' => $project->id,
            'role' => 'assistant',
            'content' => (string) ($payload['content'] ?? ''),
            'ai_governance_log_id' => is_int($logId) ? $logId : null,
        ]);

        return $response;
    }

    public function contractReview(
        Request $request,
        AiServiceClient $client,
        AiGovernanceService $governance,
        AiRateLimiter $limiter,
        DocumentMergeService $mergeService,
    ): JsonResponse {
        $this->authorizeAiUse($request);

        $data = $request->validate([
            'legal_document_id' => ['required', 'integer', 'exists:legal_documents,id'],
        ]);

        $organization = $this->organizationFor($request->user());
        $document = LegalDocument::query()->findOrFail($data['legal_document_id']);
        abort_unless($document->organization_id === $organization->id, 404);

        $preview = $mergeService->templateContent($document);
        $isBinary = ! $document->content_html && $document->mime_type
            && ! str_starts_with((string) $document->mime_type, 'text/');

        return $this->invoke(
            $request,
            'contract/review',
            'contract_review',
            $client,
            $governance,
            $limiter,
            [
                'document_name' => $document->name,
                'mime_type' => $document->mime_type,
                'content_preview' => strip_tags($preview),
                'binary_document' => $isBinary,
            ],
            'lawyer',
            $document->legal_matter_id,
            $document->id,
        );
    }

    public function generateLetterPack(
        Request $request,
        AiServiceClient $client,
        AiGovernanceService $governance,
        AiRateLimiter $limiter,
    ): JsonResponse {
        $this->authorizeAiUse($request);

        $data = $request->validate([
            'legal_matter_id' => ['required', 'integer', 'exists:legal_matters,id'],
            'letter_types' => ['nullable', 'array'],
            'letter_types.*' => ['string', 'max:100'],
            'context' => ['nullable', 'string', 'max:2000'],
        ]);

        $organization = $this->organizationFor($request->user());
        $matter = $this->legalMatterForOrganization((int) $data['legal_matter_id'], $organization->id);
        $matter->loadMissing(['client', 'leadLawyer']);

        return $this->invoke(
            $request,
            'letters/generate-pack',
            'letter_pack_generate',
            $client,
            $governance,
            $limiter,
            [
                'case_title' => $matter->title,
                'client_name' => $matter->client?->name ?? 'Client',
                'lawyer_name' => $matter->leadLawyer?->name ?? 'Counsel',
                'letter_types' => $data['letter_types'] ?? ['engagement', 'demand', 'status_update'],
                'context' => $data['context'] ?? null,
            ],
            'lawyer',
            $matter->id,
        );
    }

    public function timelineSummary(Request $request, AiServiceClient $client, AiGovernanceService $governance, AiRateLimiter $limiter): JsonResponse
    {
        $this->authorizeAiUse($request);

        $data = $request->validate([
            'legal_matter_id' => ['required', 'integer', 'exists:legal_matters,id'],
            'from_date' => ['nullable', 'date'],
            'to_date' => ['nullable', 'date'],
        ]);

        $organization = $this->organizationFor($request->user());
        $matter = $this->legalMatterForOrganization((int) $data['legal_matter_id'], $organization->id);

        $events = CalendarEvent::query()
            ->where('legal_matter_id', $matter->id)
            ->when($data['from_date'] ?? null, fn ($q, $from) => $q->whereDate('starts_at', '>=', $from))
            ->when($data['to_date'] ?? null, fn ($q, $to) => $q->whereDate('starts_at', '<=', $to))
            ->count();

        return $this->invoke(
            $request,
            'timeline/summary',
            'timeline_summary',
            $client,
            $governance,
            $limiter,
            [
                'event_count' => $events,
                'case_title' => $matter->title,
            ],
            'lawyer',
            $matter->id,
        );
    }

    private function authorizeAiUse(Request $request): void
    {
        abort_unless($request->user()?->can('ai.use'), 403);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function invoke(
        Request $request,
        string $endpoint,
        string $actionType,
        AiServiceClient $client,
        AiGovernanceService $governance,
        AiRateLimiter $limiter,
        array $payload,
        ?string $botContext = null,
        ?int $legalMatterId = null,
        ?int $legalDocumentId = null,
    ): JsonResponse {
        $user = $request->user();
        $organization = $this->organizationFor($user);

        try {
            $limiter->ensureWithinLimit($user);
            $result = $client->request($endpoint, $payload, $organization);

            $log = $governance->log(
                $organization,
                $user,
                $actionType,
                $payload,
                $result['output_id'],
                $result['model'],
                $result['content'],
                'success',
                $botContext,
                $legalMatterId,
                $legalDocumentId,
            );

            $response = [
                'output_id' => $result['output_id'],
                'content' => $result['content'],
                'labeled' => $result['labeled'],
                'label' => $result['label'],
                'disclaimer' => $result['disclaimer'],
                'requires_review' => $result['requires_review'],
                'model' => $result['model'],
                'governance_log_id' => $log->id,
            ];

            if (isset($result['authorities']) && is_array($result['authorities'])) {
                $response['authorities'] = $result['authorities'];
            }

            if (isset($result['verification_warning']) && is_string($result['verification_warning'])) {
                $response['verification_warning'] = $result['verification_warning'];
            }

            if (isset($result['issues']) && is_array($result['issues'])) {
                $response['issues'] = $result['issues'];
            }

            if (isset($result['letters']) && is_array($result['letters'])) {
                $response['letters'] = $result['letters'];
            }

            foreach ([
                'arguments',
                'opposing_arguments',
                'rebuttals',
                'sections',
                'cases',
                'ranked_authorities',
                'memo_sections',
                'strategy',
                'statute_analysis',
                'validation',
                'formatting_notes',
            ] as $structuredKey) {
                if (isset($result[$structuredKey]) && is_array($result[$structuredKey])) {
                    $response[$structuredKey] = $result[$structuredKey];
                }
            }

            return response()->json($response);
        } catch (RuntimeException $exception) {
            $governance->log(
                $organization,
                $user,
                $actionType,
                $payload,
                null,
                null,
                $exception->getMessage(),
                'error',
                $botContext,
                $legalMatterId,
                $legalDocumentId,
            );

            return response()->json(['message' => $exception->getMessage()], 503);
        }
    }

    /**
     * @param  list<int>|null  $noteIds
     * @return \Illuminate\Support\Collection<int, CaseNote>
     */
    private function researchNotesForMatter(int $organizationId, int $matterId, ?array $noteIds)
    {
        $query = CaseNote::query()
            ->where('organization_id', $organizationId)
            ->where('legal_matter_id', $matterId);

        if ($noteIds !== null && $noteIds !== []) {
            $query->whereIn('id', $noteIds);
        } else {
            $query->whereIn('note_type', ['research_summary', 'strategy_note', 'internal_memo']);
        }

        return $query->orderByDesc('updated_at')->get();
    }

    /**
     * @param  \Illuminate\Support\Collection<int, CaseNote>  $notes
     */
    private function formatNotesForAi($notes): string
    {
        return $notes
            ->map(function (CaseNote $note): string {
                $title = $note->title ? "{$note->title}: " : '';

                return "{$title}{$note->body}";
            })
            ->join("\n\n---\n\n");
    }

    private function matterTitle(Request $request, ?int $matterId): ?string
    {
        if (! $matterId) {
            return null;
        }

        $organization = $this->organizationFor($request->user());
        $matter = $this->legalMatterForOrganization($matterId, $organization->id);

        return $matter->title;
    }
}
