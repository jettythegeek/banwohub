<?php

namespace App\Services\Ai\Contracts;

interface AiProviderAdapter
{
    public function provider(): string;

    public function testConnection(string $apiKey, string $model): bool;

    /**
     * @param  array<string, mixed>  $settings
     * @return array{content: string, model: string}
     */
    public function complete(string $apiKey, string $model, string $systemPrompt, string $userPrompt, array $settings = []): array;
}
