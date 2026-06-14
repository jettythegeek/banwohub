<?php

namespace App\Services;

use App\Models\Organization;
use App\Services\Ai\AiProviderManager;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

class AiServiceClient
{
    public function __construct(
        private readonly AiProviderManager $providerManager,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function request(string $endpoint, array $payload, ?Organization $organization = null): array
    {
        if ($organization && $this->providerManager->hasActiveProvider($organization)) {
            return $this->normalizeResponse(
                $this->providerManager->request($organization, $endpoint, $payload)
            );
        }

        if ($this->hasRemoteService()) {
            return $this->normalizeResponse($this->remoteRequest($endpoint, $payload));
        }

        throw new RuntimeException(
            'AI is not configured. Add an API key in Settings → AI Providers, or set AI_SERVICE_URL and AI_OPENAI_API_KEY for the AI service.'
        );
    }

    /**
     * @return array{status: string, service: string, provider?: string}|null
     */
    public function health(?Organization $organization = null): ?array
    {
        if ($organization && $this->providerManager->hasActiveProvider($organization)) {
            return [
                'status' => 'ok',
                'service' => 'banwohub-ai-provider',
                'provider' => $this->providerManager->activeProviderName($organization),
            ];
        }

        if ($this->hasRemoteService()) {
            $url = rtrim((string) config('ai.service_url'), '/').'/health';
            $response = Http::timeout(5)->acceptJson()->get($url);

            if ($response->successful()) {
                /** @var array{status: string, service: string} $body */
                $body = $response->json();

                return $body;
            }
        }

        return null;
    }

    private function hasRemoteService(): bool
    {
        $url = (string) config('ai.service_url');

        return $url !== '' && ! config('ai.stub_mode');
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function remoteRequest(string $endpoint, array $payload): array
    {
        $url = rtrim((string) config('ai.service_url'), '/').'/v1/'.$endpoint;
        $response = Http::withToken((string) config('ai.service_key'))
            ->timeout(120)
            ->acceptJson()
            ->post($url, $payload);

        if ($response->successful()) {
            /** @var array<string, mixed> $body */
            $body = $response->json();

            return $body;
        }

        if ($response->status() === 401) {
            throw new RuntimeException('AI service authentication failed.');
        }

        if ($response->status() === 503) {
            throw new RuntimeException((string) ($response->json('message') ?? 'AI service not configured.'));
        }

        throw new RuntimeException('AI service unavailable.');
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>
     */
    private function normalizeResponse(array $body): array
    {
        $normalized = [
            'output_id' => (string) ($body['output_id'] ?? Str::uuid()),
            'content' => (string) ($body['content'] ?? ''),
            'labeled' => (bool) ($body['labeled'] ?? true),
            'label' => (string) ($body['label'] ?? config('ai.label')),
            'disclaimer' => (string) ($body['disclaimer'] ?? config('ai.disclaimer')),
            'model' => (string) ($body['model'] ?? 'unknown'),
            'requires_review' => (bool) ($body['requires_review'] ?? true),
        ];

        foreach ([
            'authorities',
            'verification_warning',
            'issues',
            'letters',
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
        ] as $key) {
            if (isset($body[$key])) {
                $normalized[$key] = $body[$key];
            }
        }

        return $normalized;
    }
}
