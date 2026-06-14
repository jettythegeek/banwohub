<?php

namespace App\Services\Ai;

use Illuminate\Support\Str;

class AiPromptBuilder
{
    /**
     * @param  array<string, mixed>  $payload
     * @return array{system: string, user: string, settings?: array<string, mixed>}
     */
    public function build(string $endpoint, array $payload): array
    {
        $parser = app(AiStructuredResponseParser::class);
        $structured = $parser->expectsStructuredJson($endpoint);

        $system = 'You are Banwolaw Hub AI, a legal practice assistant for licensed attorneys. '
            .'Provide professional, accurate drafting and research support. '
            .'Never present output as final legal advice. All citations must be real and verifiable — '
            .'if uncertain, say so explicitly. '
            .($structured
                ? 'Respond with valid JSON only matching the requested schema. No markdown fences or prose outside JSON.'
                : 'Use HTML for document content when drafting briefs, motions, or letters.');

        $user = match ($endpoint) {
            'chatbot' => $this->chatbotPrompt($payload),
            'document/summarize' => $this->summarizePrompt($payload),
            'document/draft-assist' => $this->draftAssistPrompt($payload),
            'case/qa' => $this->caseQaPrompt($payload),
            'intake/summary' => $this->intakeSummaryPrompt($payload),
            'timeline/summary' => $this->timelineSummaryPrompt($payload),
            'research/summarize-notes' => $this->researchSummarizePrompt($payload),
            'research/suggest-authorities' => $this->researchSuggestAuthoritiesPrompt($payload),
            'brief/outline' => $this->briefOutlinePrompt($payload),
            'brief/rewrite-section' => $this->briefRewritePrompt($payload),
            'brief/generate-from-facts' => $this->briefGenerateFromFactsPrompt($payload),
            'brief/build-arguments' => $this->briefBuildArgumentsPrompt($payload),
            'brief/analyze-opposition' => $this->briefAnalyzeOppositionPrompt($payload),
            'brief/enhance' => $this->briefEnhancePrompt($payload),
            'brief/format-court' => $this->briefFormatCourtPrompt($payload),
            'research/query' => $this->researchQueryPrompt($payload),
            'research/search-cases' => $this->researchSearchCasesPrompt($payload),
            'research/generate-memo' => $this->researchGenerateMemoPrompt($payload),
            'research/analyze-statute' => $this->researchAnalyzeStatutePrompt($payload),
            'research/strategy' => $this->researchStrategyPrompt($payload),
            'research/chat' => $this->researchChatPrompt($payload),
            'motion/structure-check' => $this->motionStructureCheckPrompt($payload),
            'contract/review' => $this->contractReviewPrompt($payload),
            'letters/generate-pack' => $this->letterPackPrompt($payload),
            default => 'Respond helpfully to the legal practice request with professional detail.',
        };

        $result = ['system' => $system, 'user' => $user];
        if ($structured) {
            $result['settings'] = ['response_format' => 'json_object', 'max_tokens' => 4096];
        }

        return $result;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function chatbotPrompt(array $payload): string
    {
        $message = (string) ($payload['message'] ?? '');
        $context = (string) ($payload['context'] ?? 'staff');
        $caseTitle = (string) ($payload['case_title'] ?? '');
        $firmName = (string) ($payload['firm_name'] ?? 'Banwolaw');

        if ($context === 'public') {
            return "Public FAQ assistant for {$firmName}. No legal advice. User: {$message}";
        }

        $parts = ["Staff/lawyer assistant. Context: {$context}."];
        if ($caseTitle !== '') {
            $parts[] = "Matter: {$caseTitle}.";
        }
        $parts[] = "User: {$message}";

        return implode("\n", $parts);
    }

    /** @param array<string, mixed> $payload */
    private function summarizePrompt(array $payload): string
    {
        $name = (string) ($payload['document_name'] ?? 'document');
        $preview = Str::limit((string) ($payload['content_preview'] ?? ''), 6000);

        return "Summarize legal document \"{$name}\" for staff:\n\n{$preview}";
    }

    /** @param array<string, mixed> $payload */
    private function draftAssistPrompt(array $payload): string
    {
        return 'Draft HTML for template "'.($payload['template_name'] ?? 'template')
            .'" regarding matter "'.($payload['case_title'] ?? 'matter').'".';
    }

    /** @param array<string, mixed> $payload */
    private function caseQaPrompt(array $payload): string
    {
        return 'Answer about matter "'.($payload['case_title'] ?? 'matter').'": '
            .($payload['question'] ?? '')."\nRemind: verify authorities independently.";
    }

    /** @param array<string, mixed> $payload */
    private function intakeSummaryPrompt(array $payload): string
    {
        return 'Summarize intake for '.($payload['client_name'] ?? 'client')
            .' ('.($payload['field_count'] ?? 0).' fields). Highlight risks and next steps.';
    }

    /** @param array<string, mixed> $payload */
    private function timelineSummaryPrompt(array $payload): string
    {
        return 'Summarize timeline for "'.($payload['case_title'] ?? 'matter')
            .'" ('.($payload['event_count'] ?? 0).' events). Note deadlines.';
    }

    /** @param array<string, mixed> $payload */
    private function researchSummarizePrompt(array $payload): string
    {
        return 'Summarize '.($payload['note_count'] ?? 0).' research notes for "'
            .($payload['case_title'] ?? 'matter')."\":\n\n"
            .Str::limit((string) ($payload['notes_text'] ?? ''), 12000);
    }

    /** @param array<string, mixed> $payload */
    private function researchSuggestAuthoritiesPrompt(array $payload): string
    {
        $parts = [
            'Suggest relevant cases, statutes, regulations.',
            'Matter: '.($payload['case_title'] ?? 'matter'),
            'Issue: '.($payload['issue'] ?? ''),
            'Practice area: '.($payload['practice_area'] ?? ''),
        ];
        if (! empty($payload['saved_authorities_text'])) {
            $parts[] = "Organization library matches (prefer and mark verified):\n".$payload['saved_authorities_text'];
        }
        if (! empty($payload['notes_text'])) {
            $parts[] = 'Notes: '.Str::limit((string) $payload['notes_text'], 8000);
        }
        $parts[] = 'JSON schema: {"content":"summary text","authorities":[{"type":"case|statute|regulation","citation":"","relevance":"","verified":false}]}';

        return implode("\n\n", $parts);
    }

    /** @param array<string, mixed> $payload */
    private function briefOutlinePrompt(array $payload): string
    {
        return 'Create HTML brief outline for "'.($payload['brief_title'] ?? 'Brief')
            .'" in matter "'.($payload['case_title'] ?? 'matter')
            .'" on issue: '.($payload['issue'] ?? 'general').'. Include standard sections.';
    }

    /** @param array<string, mixed> $payload */
    private function briefRewritePrompt(array $payload): string
    {
        return 'Rewrite this brief section per instruction: '.($payload['instruction'] ?? 'Improve clarity')
            ."\n\nSection HTML:\n".Str::limit((string) ($payload['section_html'] ?? ''), 12000)
            ."\nReturn improved HTML only.";
    }

    /** @param array<string, mixed> $payload */
    private function briefGenerateFromFactsPrompt(array $payload): string
    {
        $parts = [
            'Generate a complete '.str_replace('_', ' ', (string) ($payload['brief_type'] ?? 'memorandum_of_law')).' brief.',
            'Title: '.($payload['brief_title'] ?? 'Brief'),
            'Matter: '.($payload['case_title'] ?? 'matter'),
            'Jurisdiction: '.($payload['jurisdiction'] ?? 'unspecified'),
            'Court type: '.($payload['court_type'] ?? 'federal'),
            'Citation style: '.($payload['citation_style'] ?? 'bluebook'),
            'Cause of action: '.($payload['cause_of_action'] ?? ''),
            'Facts: '.Str::limit((string) ($payload['case_facts'] ?? ''), 12000),
            'Statutes: '.Str::limit((string) ($payload['statutes'] ?? ''), 4000),
            'Desired outcome: '.($payload['desired_outcome'] ?? ''),
        ];
        if (! empty($payload['saved_authorities_text'])) {
            $parts[] = 'Use these library authorities where relevant:\n'.$payload['saved_authorities_text'];
        }
        $parts[] = 'JSON: {"content":"narrative summary","content_html":"<full brief HTML>","sections":[{"key":"","title":"","status":"draft"}]}';

        return implode("\n", $parts);
    }

    /** @param array<string, mixed> $payload */
    private function briefBuildArgumentsPrompt(array $payload): string
    {
        $parts = [
            'Build ranked legal argument theories.',
            'Matter: '.($payload['case_title'] ?? ''),
            'Issue: '.($payload['issue'] ?? ''),
            'Practice area: '.($payload['practice_area'] ?? ''),
        ];
        if (! empty($payload['content_html'])) {
            $parts[] = 'Brief context: '.Str::limit(strip_tags((string) $payload['content_html']), 4000);
        }
        $parts[] = 'JSON: {"content":"overview","arguments":[{"rank":1,"title":"","theory":"","strength":"high|medium|low","weaknesses":[],"authorities":[]}]}';

        return implode("\n", $parts);
    }

    /** @param array<string, mixed> $payload */
    private function briefAnalyzeOppositionPrompt(array $payload): string
    {
        return 'Predict opposing arguments and rebuttals for matter "'.($payload['case_title'] ?? 'matter').'". Issue: '
            .($payload['issue'] ?? '')."\nBrief:\n".Str::limit((string) ($payload['content_html'] ?? ''), 12000)
            ."\nJSON: {\"content\":\"summary\",\"opposing_arguments\":[{\"argument\":\"\",\"likelihood\":\"high|medium|low\",\"authority_hooks\":[]}],\"rebuttals\":[{\"opposing_argument\":\"\",\"rebuttal\":\"\",\"response_section\":\"\"}]}";
    }

    /** @param array<string, mixed> $payload */
    private function briefEnhancePrompt(array $payload): string
    {
        return 'Enhance brief for goal: '.($payload['enhancement_goal'] ?? 'clarity')
            ."\n\nHTML:\n".Str::limit((string) ($payload['content_html'] ?? ''), 14000)
            ."\nReturn improved HTML only (not JSON).";
    }

    /** @param array<string, mixed> $payload */
    private function briefFormatCourtPrompt(array $payload): string
    {
        return 'Apply '.($payload['court_type'] ?? 'federal').' court formatting and '
            .($payload['citation_style'] ?? 'bluebook').' citations to this brief. Jurisdiction: '
            .($payload['jurisdiction'] ?? '')."\n\nHTML:\n"
            .Str::limit((string) ($payload['content_html'] ?? ''), 14000)
            ."\nJSON: {\"content_html\":\"formatted HTML\",\"formatting_notes\":[{\"note\":\"\"}]}";
    }

    /** @param array<string, mixed> $payload */
    private function researchQueryPrompt(array $payload): string
    {
        $parts = [
            'Answer this legal research question in plain English with analysis.',
            'Query: '.($payload['query'] ?? ''),
            'Practice area: '.($payload['practice_area'] ?? ''),
            'Jurisdiction: '.($payload['jurisdiction'] ?? ''),
            'Court type: '.($payload['court_type'] ?? ''),
        ];
        if (! empty($payload['saved_authorities_text'])) {
            $parts[] = "Library authorities:\n".$payload['saved_authorities_text'];
        }
        $parts[] = 'JSON: {"content":"analysis","authorities":[{"type":"","citation":"","relevance":"","verified":false}]}';

        return implode("\n", $parts);
    }

    /** @param array<string, mixed> $payload */
    private function researchSearchCasesPrompt(array $payload): string
    {
        $parts = [
            'Identify relevant case law with holdings, facts, principles, similarity scores.',
            'Issue: '.($payload['issue'] ?? ''),
            'Jurisdiction: '.($payload['jurisdiction'] ?? ''),
            'Court type: '.($payload['court_type'] ?? ''),
        ];
        if (! empty($payload['saved_authorities_text'])) {
            $parts[] = "Prioritize matching organization library entries:\n".$payload['saved_authorities_text'];
        }
        $parts[] = 'JSON: {"content":"summary","cases":[{"citation":"","court":"","holding":"","facts":"","principles":[],"similarity_score":0.0,"verification_status":"unverified"}],"authorities":[]}';

        return implode("\n", $parts);
    }

    /** @param array<string, mixed> $payload */
    private function researchGenerateMemoPrompt(array $payload): string
    {
        $project = is_array($payload['project'] ?? null) ? $payload['project'] : [];

        return 'Generate '.($payload['memo_type'] ?? 'research_memo').' memo. Issue: '
            .($payload['issue'] ?? '').'. Matter: '.($payload['case_title'] ?? '')
            .'. Project: '.($project['name'] ?? '').'. Theory: '.($project['case_theory'] ?? '')
            ."\nJSON: {\"content\":\"summary\",\"memo_sections\":[{\"title\":\"Question Presented|Brief Answer|Facts|Analysis|Risk Assessment|Recommendation\",\"content\":\"\"}]}";
    }

    /** @param array<string, mixed> $payload */
    private function researchAnalyzeStatutePrompt(array $payload): string
    {
        return 'Plain-English statute analysis. Jurisdiction: '.($payload['jurisdiction'] ?? '')
            .'. Question: '.($payload['question'] ?? '')."\nStatute text:\n"
            .Str::limit((string) ($payload['statute_text'] ?? ''), 14000)
            ."\nJSON: {\"content\":\"summary\",\"statute_analysis\":[{\"provision\":\"\",\"plain_english\":\"\",\"compliance_notes\":\"\"}]}";
    }

    /** @param array<string, mixed> $payload */
    private function researchStrategyPrompt(array $payload): string
    {
        return 'Litigation strategy for issue: '.($payload['issue'] ?? '')
            .'. Matter: '.($payload['case_title'] ?? '').'. Practice area: '
            .($payload['practice_area'] ?? '').'. Context: '.($payload['context'] ?? '')
            ."\nJSON: {\"content\":\"overview\",\"strategy\":{\"claims\":[],\"defenses\":[],\"procedural_options\":[],\"jurisdictional_concerns\":[],\"evidentiary_support\":[]}}";
    }

    /** @param array<string, mixed> $payload */
    private function researchChatPrompt(array $payload): string
    {
        $history = is_array($payload['history'] ?? null) ? $payload['history'] : [];
        $historyText = collect($history)
            ->map(fn (array $m) => strtoupper((string) ($m['role'] ?? 'user')).': '.($m['content'] ?? ''))
            ->join("\n");

        return 'Research project: '.($payload['project_name'] ?? '').'. Theory: '
            .($payload['case_theory'] ?? '').'. Jurisdiction: '.($payload['jurisdiction'] ?? '')
            ."\n\nConversation:\n{$historyText}\n\nUser: ".($payload['message'] ?? '')
            ."\nProvide a focused research assistant reply. Warn about citation verification.";
    }

    /** @param array<string, mixed> $payload */
    private function motionStructureCheckPrompt(array $payload): string
    {
        return 'Review motion structure for "'.($payload['motion_title'] ?? 'Motion')
            .'" (type: '.($payload['motion_type'] ?? 'general').'). Required sections: '
            .json_encode($payload['required_sections'] ?? [])
            ."\n\nContent:\n".Str::limit(strip_tags((string) ($payload['content_html'] ?? '')), 8000)
            ."\nReturn HTML review with missing sections, citation gaps, and filing checklist.";
    }

    /** @param array<string, mixed> $payload */
    private function contractReviewPrompt(array $payload): string
    {
        return 'Review contract "'.($payload['document_name'] ?? 'contract').'". Binary: '
            .(($payload['binary_document'] ?? false) ? 'yes' : 'no')."\nText:\n"
            .Str::limit((string) ($payload['content_preview'] ?? ''), 12000)
            ."\nJSON: {\"content\":\"summary\",\"issues\":[{\"severity\":\"high|medium|low\",\"title\":\"\",\"description\":\"\",\"clause_ref\":\"\"}]}";
    }

    /** @param array<string, mixed> $payload */
    private function letterPackPrompt(array $payload): string
    {
        return 'Generate letter pack for client '.($payload['client_name'] ?? 'Client')
            .', matter "'.($payload['case_title'] ?? 'matter').'", lawyer '
            .($payload['lawyer_name'] ?? 'Counsel').'. Types: '
            .json_encode($payload['letter_types'] ?? ['engagement']).'. Context: '
            .($payload['context'] ?? '')
            ."\nJSON: {\"content\":\"summary\",\"letters\":[{\"type\":\"\",\"title\":\"\",\"content_html\":\"\"}]}";
    }
}
