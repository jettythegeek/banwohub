<?php

namespace App\Services\Ai\Adapters;

use App\Services\Ai\Contracts\AiProviderAdapter;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class AnthropicAdapter implements AiProviderAdapter
{
    public function provider(): string
    {
        return 'anthropic';
    }

    public function testConnection(string $apiKey, string $model): bool
    {
        $response = Http::withHeaders([
            'x-api-key' => $apiKey,
            'anthropic-version' => '2023-06-01',
        ])
            ->timeout(15)
            ->acceptJson()
            ->post('https://api.anthropic.com/v1/messages', [
                'model' => $model,
                'max_tokens' => 5,
                'messages' => [
                    ['role' => 'user', 'content' => 'Reply with OK only.'],
                ],
            ]);

        return $response->successful();
    }

    public function complete(string $apiKey, string $model, string $systemPrompt, string $userPrompt, array $settings = []): array
    {
        $response = Http::withHeaders([
            'x-api-key' => $apiKey,
            'anthropic-version' => '2023-06-01',
        ])
            ->timeout(60)
            ->acceptJson()
            ->post('https://api.anthropic.com/v1/messages', [
                'model' => $model,
                'max_tokens' => (int) ($settings['max_tokens'] ?? 2048),
                'system' => $systemPrompt,
                'messages' => [
                    ['role' => 'user', 'content' => $userPrompt],
                ],
            ]);

        if (! $response->successful()) {
            throw new RuntimeException('Anthropic request failed: '.$response->status());
        }

        /** @var array<string, mixed> $body */
        $body = $response->json();
        $blocks = data_get($body, 'content', []);
        $content = '';

        if (is_array($blocks)) {
            foreach ($blocks as $block) {
                if (is_array($block) && ($block['type'] ?? '') === 'text') {
                    $content .= (string) ($block['text'] ?? '');
                }
            }
        }

        return [
            'content' => $content,
            'model' => (string) data_get($body, 'model', $model),
        ];
    }
}
