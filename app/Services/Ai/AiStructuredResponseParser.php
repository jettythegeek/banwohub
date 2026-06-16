<?php

namespace App\Services\Ai;

class AiStructuredResponseParser
{
    public const RESEARCH_VERIFICATION_WARNING =
        'AI-suggested authorities may be incorrect or hallucinated. '
        .'Verify every citation against primary sources before use in filings or client advice.';

    /**
     * @return list<string>
     */
    public function structuredEndpoints(): array
    {
        return [
            'research/suggest-authorities',
            'brief/generate-from-facts',
            'brief/build-arguments',
            'brief/analyze-opposition',
            'brief/format-court',
            'research/query',
            'research/search-cases',
            'research/generate-memo',
            'research/analyze-statute',
            'research/strategy',
            'contract/review',
            'letters/generate-pack',
        ];
    }

    public function expectsStructuredJson(string $endpoint): bool
    {
        return in_array($endpoint, $this->structuredEndpoints(), true);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function parse(string $endpoint, string $rawContent, array $payload = []): array
    {
        $parsed = $this->extractJson($rawContent);

        if ($parsed === null) {
            return $this->fallback($endpoint, $rawContent);
        }

        $result = $this->buildBaseResponse($parsed, $rawContent);

        return match ($endpoint) {
            'brief/generate-from-facts' => $this->mergeBriefGenerate($result, $parsed),
            'brief/build-arguments' => $this->mergeBriefArguments($result, $parsed),
            'brief/analyze-opposition' => $this->mergeOpposition($result, $parsed),
            'brief/format-court' => $this->mergeFormatCourt($result, $parsed),
            'brief/enhance' => $this->mergeEnhance($result, $parsed, $rawContent),
            'research/suggest-authorities' => $this->mergeAuthorities($result, $parsed, $payload),
            'research/query' => $this->mergeResearchQuery($result, $parsed, $payload),
            'research/search-cases' => $this->mergeSearchCases($result, $parsed, $payload),
            'research/generate-memo' => $this->mergeMemo($result, $parsed),
            'research/analyze-statute' => $this->mergeStatute($result, $parsed),
            'research/strategy' => $this->mergeStrategy($result, $parsed),
            'contract/review' => $this->mergeContractReview($result, $parsed),
            'letters/generate-pack' => $this->mergeLetterPack($result, $parsed),
            default => $result,
        };
    }

    /**
     * @return array<string, mixed>|null
     */
    private function extractJson(string $rawContent): ?array
    {
        $trimmed = trim($rawContent);

        if ($trimmed === '') {
            return null;
        }

        if (str_starts_with($trimmed, '{') || str_starts_with($trimmed, '[')) {
            $decoded = json_decode($trimmed, true);

            return is_array($decoded) ? $decoded : null;
        }

        if (preg_match('/```(?:json)?\s*([\s\S]*?)```/i', $trimmed, $matches)) {
            $decoded = json_decode(trim($matches[1]), true);

            return is_array($decoded) ? $decoded : null;
        }

        $start = strpos($trimmed, '{');
        $end = strrpos($trimmed, '}');
        if ($start !== false && $end !== false && $end > $start) {
            $decoded = json_decode(substr($trimmed, $start, $end - $start + 1), true);

            return is_array($decoded) ? $decoded : null;
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $parsed
     * @return array<string, mixed>
     */
    private function buildBaseResponse(array $parsed, string $rawContent): array
    {
        $content = (string) ($parsed['content']
            ?? $parsed['content_html']
            ?? $parsed['summary']
            ?? $rawContent);

        return ['content' => $content];
    }

    /**
     * @return array<string, mixed>
     */
    private function fallback(string $endpoint, string $rawContent): array
    {
        $result = ['content' => $rawContent];

        if (in_array($endpoint, ['research/query', 'research/search-cases', 'research/suggest-authorities'], true)) {
            $result['verification_warning'] = self::RESEARCH_VERIFICATION_WARNING;
            $result['validation'] = [
                'source_authority' => 'ai_generated',
                'confidence_rating' => 0.5,
                'jurisdiction_relevance' => 'unknown',
                'verification_status' => 'requires_manual_verification',
            ];
        }

        return $result;
    }

    /**
     * @param  array<string, mixed>  $result
     * @param  array<string, mixed>  $parsed
     * @return array<string, mixed>
     */
    private function mergeBriefGenerate(array $result, array $parsed): array
    {
        $result['content'] = (string) ($parsed['content_html'] ?? $result['content']);
        if (isset($parsed['sections']) && is_array($parsed['sections'])) {
            $result['sections'] = $parsed['sections'];
        }

        return $result;
    }

    /**
     * @param  array<string, mixed>  $result
     * @param  array<string, mixed>  $parsed
     * @return array<string, mixed>
     */
    private function mergeBriefArguments(array $result, array $parsed): array
    {
        if (isset($parsed['arguments']) && is_array($parsed['arguments'])) {
            $result['arguments'] = $parsed['arguments'];
        }

        return $result;
    }

    /**
     * @param  array<string, mixed>  $result
     * @param  array<string, mixed>  $parsed
     * @return array<string, mixed>
     */
    private function mergeOpposition(array $result, array $parsed): array
    {
        if (isset($parsed['opposing_arguments']) && is_array($parsed['opposing_arguments'])) {
            $result['opposing_arguments'] = $parsed['opposing_arguments'];
        }
        if (isset($parsed['rebuttals']) && is_array($parsed['rebuttals'])) {
            $result['rebuttals'] = $parsed['rebuttals'];
        }

        return $result;
    }

    /**
     * @param  array<string, mixed>  $result
     * @param  array<string, mixed>  $parsed
     * @return array<string, mixed>
     */
    private function mergeFormatCourt(array $result, array $parsed): array
    {
        $result['content'] = (string) ($parsed['content_html'] ?? $result['content']);
        if (isset($parsed['formatting_notes']) && is_array($parsed['formatting_notes'])) {
            $result['formatting_notes'] = $parsed['formatting_notes'];
        }

        return $result;
    }

    /**
     * @param  array<string, mixed>  $result
     * @param  array<string, mixed>  $parsed
     * @return array<string, mixed>
     */
    private function mergeEnhance(array $result, array $parsed, string $rawContent): array
    {
        $result['content'] = (string) ($parsed['content_html'] ?? $parsed['content'] ?? $rawContent);

        return $result;
    }

    /**
     * @param  array<string, mixed>  $result
     * @param  array<string, mixed>  $parsed
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function mergeAuthorities(array $result, array $parsed, array $payload): array
    {
        $authorities = $parsed['authorities'] ?? [];
        if (! is_array($authorities)) {
            $authorities = [];
        }

        $saved = $payload['saved_authorities'] ?? [];
        if (is_array($saved) && $saved !== []) {
            $authorities = $this->mergeSavedAuthorities($authorities, $saved);
        }

        $ranked = $this->rankAuthorities($authorities, $payload);
        $result['authorities'] = $ranked;
        $result['ranked_authorities'] = $ranked;
        $result['verification_warning'] = self::RESEARCH_VERIFICATION_WARNING;
        $result['validation'] = $this->buildValidation($ranked, $payload);

        return $result;
    }

    /**
     * @param  array<string, mixed>  $result
     * @param  array<string, mixed>  $parsed
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function mergeResearchQuery(array $result, array $parsed, array $payload): array
    {
        return $this->mergeAuthorities($result, $parsed, $payload);
    }

    /**
     * @param  array<string, mixed>  $result
     * @param  array<string, mixed>  $parsed
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function mergeSearchCases(array $result, array $parsed, array $payload): array
    {
        if (isset($parsed['cases']) && is_array($parsed['cases'])) {
            $result['cases'] = $this->annotateCasesWithLibraryMatch($parsed['cases'], $payload);
        }

        $merged = $this->mergeAuthorities($result, $parsed, $payload);

        return $merged;
    }

    /**
     * @param  array<string, mixed>  $result
     * @param  array<string, mixed>  $parsed
     * @return array<string, mixed>
     */
    private function mergeMemo(array $result, array $parsed): array
    {
        if (isset($parsed['memo_sections']) && is_array($parsed['memo_sections'])) {
            $result['memo_sections'] = $parsed['memo_sections'];
        }

        return $result;
    }

    /**
     * @param  array<string, mixed>  $result
     * @param  array<string, mixed>  $parsed
     * @return array<string, mixed>
     */
    private function mergeStatute(array $result, array $parsed): array
    {
        if (isset($parsed['statute_analysis']) && is_array($parsed['statute_analysis'])) {
            $result['statute_analysis'] = $parsed['statute_analysis'];
        }
        $result['verification_warning'] = self::RESEARCH_VERIFICATION_WARNING;

        return $result;
    }

    /**
     * @param  array<string, mixed>  $result
     * @param  array<string, mixed>  $parsed
     * @return array<string, mixed>
     */
    private function mergeStrategy(array $result, array $parsed): array
    {
        if (isset($parsed['strategy']) && is_array($parsed['strategy'])) {
            $result['strategy'] = $parsed['strategy'];
        }

        return $result;
    }

    /**
     * @param  array<string, mixed>  $result
     * @param  array<string, mixed>  $parsed
     * @return array<string, mixed>
     */
    private function mergeContractReview(array $result, array $parsed): array
    {
        if (isset($parsed['issues']) && is_array($parsed['issues'])) {
            $result['issues'] = $parsed['issues'];
        }

        return $result;
    }

    /**
     * @param  array<string, mixed>  $result
     * @param  array<string, mixed>  $parsed
     * @return array<string, mixed>
     */
    private function mergeLetterPack(array $result, array $parsed): array
    {
        if (isset($parsed['letters']) && is_array($parsed['letters'])) {
            $result['letters'] = $parsed['letters'];
        }

        return $result;
    }

    /**
     * @param  list<array<string, mixed>>  $aiAuthorities
     * @param  list<array<string, mixed>>  $savedAuthorities
     * @return list<array<string, mixed>>
     */
    private function mergeSavedAuthorities(array $aiAuthorities, array $savedAuthorities): array
    {
        $existingCitations = array_map(
            fn (array $a) => strtolower((string) ($a['citation'] ?? '')),
            $aiAuthorities,
        );

        foreach ($savedAuthorities as $saved) {
            $citation = (string) ($saved['citation'] ?? $saved['title'] ?? '');
            if ($citation === '' || in_array(strtolower($citation), $existingCitations, true)) {
                continue;
            }

            $aiAuthorities[] = [
                'type' => (string) ($saved['document_type'] ?? 'case'),
                'citation' => $citation,
                'relevance' => (string) ($saved['summary'] ?? 'From organization research library.'),
                'verified' => true,
                'library_match' => true,
            ];
            $existingCitations[] = strtolower($citation);
        }

        return $aiAuthorities;
    }

    /**
     * @param  list<array<string, mixed>>  $authorities
     * @param  array<string, mixed>  $payload
     * @return list<array<string, mixed>>
     */
    private function rankAuthorities(array $authorities, array $payload): array
    {
        $jurisdiction = strtolower((string) ($payload['jurisdiction'] ?? ''));

        return array_values(array_map(function (array $authority, int $index) use ($jurisdiction): array {
            $libraryMatch = (bool) ($authority['library_match'] ?? false);
            $confidence = $libraryMatch ? 0.95 : max(0.45, 0.85 - ($index * 0.08));
            $citation = (string) ($authority['citation'] ?? '');
            $jurisdictionRelevance = 'medium';
            if ($jurisdiction !== '' && stripos($citation, $jurisdiction) !== false) {
                $jurisdictionRelevance = 'high';
            }

            return [
                ...$authority,
                'rank' => $index + 1,
                'confidence' => round($confidence, 2),
                'jurisdiction_relevance' => $jurisdictionRelevance,
                'precedential_value' => ($authority['type'] ?? '') === 'case'
                    ? ($libraryMatch ? 'verified_library' : 'requires_verification')
                    : 'persuasive',
                'verification_status' => $libraryMatch ? 'library_verified' : 'unverified',
                'negative_treatment_alert' => false,
            ];
        }, $authorities, array_keys($authorities)));
    }

    /**
     * @param  list<array<string, mixed>>  $authorities
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function buildValidation(array $authorities, array $payload): array
    {
        $libraryMatches = count(array_filter($authorities, fn (array $a) => (bool) ($a['library_match'] ?? false)));
        $total = max(1, count($authorities));
        $confidence = round(min(0.95, 0.55 + ($libraryMatches / $total) * 0.4), 2);

        return [
            'source_authority' => $libraryMatches > 0 ? 'ai_plus_library' : 'ai_generated',
            'confidence_rating' => $confidence,
            'jurisdiction_relevance' => ($payload['jurisdiction'] ?? '') !== '' ? 'contextual' : 'unknown',
            'library_matches' => $libraryMatches,
            'verification_status' => $libraryMatches > 0 ? 'partially_verified' : 'requires_manual_verification',
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $cases
     * @param  array<string, mixed>  $payload
     * @return list<array<string, mixed>>
     */
    private function annotateCasesWithLibraryMatch(array $cases, array $payload): array
    {
        $saved = $payload['saved_authorities'] ?? [];
        if (! is_array($saved) || $saved === []) {
            return $cases;
        }

        $savedCitations = array_map(
            fn (array $s) => strtolower((string) ($s['citation'] ?? '')),
            $saved,
        );

        return array_map(function (array $case) use ($savedCitations): array {
            $citation = strtolower((string) ($case['citation'] ?? ''));
            $matched = $citation !== '' && in_array($citation, $savedCitations, true);

            return [
                ...$case,
                'verification_status' => $matched ? 'library_verified' : ($case['verification_status'] ?? 'unverified'),
                'library_match' => $matched,
            ];
        }, $cases);
    }
}
