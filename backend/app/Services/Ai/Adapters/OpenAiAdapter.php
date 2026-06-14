<?php

namespace App\Services\Ai\Adapters;

use App\Services\Ai\Contracts\AiProviderAdapter;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class OpenAiAdapter implements AiProviderAdapter
{
    public function provider(): string
    {
        return 'openai';
    }

    public function testConnection(string $apiKey, string $model): bool
    {
        $response = Http::withToken($apiKey)
            ->timeout(15)
            ->acceptJson()
            ->post('https://api.openai.com/v1/chat/completions', [
                'model' => $model,
                'messages' => [
                    ['role' => 'user', 'content' => 'Reply with OK only.'],
                ],
                'max_tokens' => 5,
            ]);

        return $response->successful();
    }

    public function complete(string $apiKey, string $model, string $systemPrompt, string $userPrompt, array $settings = []): array
    {
        $payload = [
            'model' => $model,
            'messages' => [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user', 'content' => $userPrompt],
            ],
            'temperature' => (float) ($settings['temperature'] ?? 0.3),
            'max_tokens' => (int) ($settings['max_tokens'] ?? 2048),
        ];

        if (($settings['response_format'] ?? '') === 'json_object') {
            $payload['response_format'] = ['type' => 'json_object'];
        }

        $response = Http::withToken($apiKey)
            ->timeout(120)
            ->acceptJson()
            ->post('https://api.openai.com/v1/chat/completions', $payload);

        if (! $response->successful()) {
            throw new RuntimeException('OpenAI request failed: '.$response->status());
        }

        /** @var array<string, mixed> $body */
        $body = $response->json();
        $content = (string) data_get($body, 'choices.0.message.content', '');

        return [
            'content' => $content,
            'model' => (string) data_get($body, 'model', $model),
        ];
    }
}
