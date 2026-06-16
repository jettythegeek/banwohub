<?php

namespace App\Services\Ai\Adapters;

use App\Services\Ai\Contracts\AiProviderAdapter;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class GoogleAiAdapter implements AiProviderAdapter
{
    public function provider(): string
    {
        return 'google';
    }

    public function testConnection(string $apiKey, string $model): bool
    {
        $response = Http::timeout(15)
            ->acceptJson()
            ->post($this->endpoint($model, $apiKey), [
                'contents' => [
                    ['parts' => [['text' => 'Reply with OK only.']]],
                ],
                'generationConfig' => ['maxOutputTokens' => 5],
            ]);

        return $response->successful();
    }

    public function complete(string $apiKey, string $model, string $systemPrompt, string $userPrompt, array $settings = []): array
    {
        $response = Http::timeout(60)
            ->acceptJson()
            ->post($this->endpoint($model, $apiKey), [
                'systemInstruction' => [
                    'parts' => [['text' => $systemPrompt]],
                ],
                'contents' => [
                    ['parts' => [['text' => $userPrompt]]],
                ],
                'generationConfig' => [
                    'temperature' => (float) ($settings['temperature'] ?? 0.3),
                    'maxOutputTokens' => (int) ($settings['max_tokens'] ?? 2048),
                ],
            ]);

        if (! $response->successful()) {
            throw new RuntimeException('Google AI request failed: '.$response->status());
        }

        /** @var array<string, mixed> $body */
        $body = $response->json();
        $content = (string) data_get($body, 'candidates.0.content.parts.0.text', '');

        return [
            'content' => $content,
            'model' => $model,
        ];
    }

    private function endpoint(string $model, string $apiKey): string
    {
        return 'https://generativelanguage.googleapis.com/v1beta/models/'
            .urlencode($model)
            .':generateContent?key='.urlencode($apiKey);
    }
}
