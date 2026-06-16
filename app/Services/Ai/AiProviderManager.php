<?php

namespace App\Services\Ai;

use App\Models\Organization;
use App\Services\ResearchLibraryContextService;
use Illuminate\Support\Str;
use RuntimeException;

class AiProviderManager
{
    /** @var array<string, AiProviderAdapter> */
    private array $adapters;

    public function __construct(
        private readonly AiPromptBuilder $promptBuilder,
        private readonly AiStructuredResponseParser $responseParser,
        private readonly ResearchLibraryContextService $researchLibrary,
        ?Adapters\OpenAiAdapter $openAi = null,
        ?Adapters\AnthropicAdapter $anthropic = null,
        ?Adapters\GoogleAiAdapter $google = null,
        ?Adapters\DeepseekAdapter $deepseek = null,
    ) {
        $openAi ??= new Adapters\OpenAiAdapter;
        $anthropic ??= new Adapters\AnthropicAdapter;
        $google ??= new Adapters\GoogleAiAdapter;
        $deepseek ??= new Adapters\DeepseekAdapter;

        $this->adapters = [
            $openAi->provider() => $openAi,
            $anthropic->provider() => $anthropic,
            $google->provider() => $google,
            $deepseek->provider() => $deepseek,
        ];
    }

    /**
     * @return list<string>
     */
    public function supportedProviders(): array
    {
        return array_keys(config('ai.providers', []));
    }

    public function activeProviderName(Organization $organization): ?string
    {
        $settings = is_array($organization->settings) ? $organization->settings : [];
        $active = $settings['active_ai_provider'] ?? null;

        return is_string($active) && $active !== '' ? $active : null;
    }

    public function hasActiveProvider(Organization $organization): bool
    {
        $active = $this->activeProviderName($organization);
        if (! $active) {
            return false;
        }

        return $this->activeConfig($organization) !== null;
    }

    public function activeConfig(Organization $organization): ?\App\Models\AiProviderConfig
    {
        $active = $this->activeProviderName($organization);
        if (! $active) {
            return null;
        }

        $config = \App\Models\AiProviderConfig::query()
            ->where('organization_id', $organization->id)
            ->where('provider', $active)
            ->where('is_enabled', true)
            ->first();

        if (! $config || ! $config->api_key) {
            return null;
        }

        return $config;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listForOrganization(Organization $organization): array
    {
        $configs = \App\Models\AiProviderConfig::query()
            ->where('organization_id', $organization->id)
            ->get()
            ->keyBy('provider');

        $active = $this->activeProviderName($organization);
        $rows = [];

        foreach ($this->supportedProviders() as $provider) {
            /** @var \App\Models\AiProviderConfig|null $config */
            $config = $configs->get($provider);
            $rows[] = $this->serializeConfig($provider, $config, $active);
        }

        return $rows;
    }

    public function setActiveProvider(Organization $organization, string $provider): void
    {
        abort_unless(in_array($provider, $this->supportedProviders(), true), 422, 'Unsupported AI provider.');

        $config = \App\Models\AiProviderConfig::query()
            ->where('organization_id', $organization->id)
            ->where('provider', $provider)
            ->first();

        abort_unless(
            $config && $config->api_key && $config->canSelectModel(),
            422,
            'Provider must have a saved API key and a successful connection test before activation.',
        );

        $this->enableExclusive($organization, $provider);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function upsertConfig(Organization $organization, string $provider, array $data): \App\Models\AiProviderConfig
    {
        abort_unless(in_array($provider, $this->supportedProviders(), true), 422, 'Unsupported AI provider.');

        $config = \App\Models\AiProviderConfig::query()->firstOrNew([
            'organization_id' => $organization->id,
            'provider' => $provider,
        ]);

        $apiKeyChanged = false;

        if (! empty($data['api_key']) && is_string($data['api_key'])) {
            $config->api_key = $data['api_key'];
            $config->last_test_success_at = null;
            $apiKeyChanged = true;
        }

        if (array_key_exists('model', $data)) {
            abort_unless(
                $config->canSelectModel(),
                422,
                'Model can only be updated after saving an API key and passing the connection test.',
            );

            $model = $data['model'];
            $config->model = is_string($model) && $model !== '' ? $model : null;
        } elseif ($apiKeyChanged) {
            $config->model = (string) config("ai.providers.{$provider}.default_model");
        }

        if (array_key_exists('settings', $data) && is_array($data['settings'])) {
            $config->settings = $data['settings'];
        }

        if (! $config->model) {
            $config->model = (string) config("ai.providers.{$provider}.default_model");
        }

        $config->organization_id = $organization->id;
        $config->provider = $provider;
        $config->save();

        if (array_key_exists('is_enabled', $data)) {
            $enabled = (bool) $data['is_enabled'];

            if ($enabled) {
                abort_unless(
                    $config->api_key && $config->canSelectModel(),
                    422,
                    'Enable provider only after saving an API key and passing the connection test.',
                );
                $this->enableExclusive($organization, $provider);
                $config = $config->fresh() ?? $config;
            } else {
                $this->disableProvider($organization, $provider);
                $config = $config->fresh() ?? $config;
            }
        } elseif ($apiKeyChanged && $config->is_enabled) {
            $this->disableProvider($organization, $provider);
            $config = $config->fresh() ?? $config;
        }

        return $config;
    }

    public function testConnection(Organization $organization, string $provider, ?string $apiKey = null): array
    {
        abort_unless(in_array($provider, $this->supportedProviders(), true), 422, 'Unsupported AI provider.');

        $config = \App\Models\AiProviderConfig::query()
            ->where('organization_id', $organization->id)
            ->where('provider', $provider)
            ->first();

        $key = $apiKey ?: $config?->api_key;
        if (! $key) {
            return ['success' => false, 'message' => 'API key is required.'];
        }

        $model = $config?->resolvedModel() ?? (string) config("ai.providers.{$provider}.default_model");
        $adapter = $this->adapterFor($provider);

        try {
            $ok = $adapter->testConnection($key, $model);

            if ($ok) {
                if ($config) {
                    $config->last_test_success_at = now();
                    $config->save();
                }

                return [
                    'success' => true,
                    'message' => 'Connection successful.',
                    'last_test_success_at' => $config?->last_test_success_at?->toIso8601String(),
                ];
            }

            if ($config) {
                $config->last_test_success_at = null;
                $config->save();
            }

            return ['success' => false, 'message' => 'Provider rejected the API key.'];
        } catch (\Throwable $exception) {
            if ($config) {
                $config->last_test_success_at = null;
                $config->save();
            }

            return ['success' => false, 'message' => $exception->getMessage()];
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function request(Organization $organization, string $endpoint, array $payload): array
    {
        $config = $this->activeConfig($organization);
        if (! $config) {
            throw new RuntimeException('No active AI provider configured. Add an API key in Settings → AI Providers.');
        }

        $payload = $this->researchLibrary->enrichPayload($organization, $endpoint, $payload);
        $prompts = $this->promptBuilder->build($endpoint, $payload);
        $providerSettings = is_array($config->settings) ? $config->settings : [];
        $callSettings = array_merge($providerSettings, $prompts['settings'] ?? []);

        $adapter = $this->adapterFor($config->provider);
        $result = $adapter->complete(
            $config->api_key,
            $config->resolvedModel(),
            $prompts['system'],
            $prompts['user'],
            $callSettings,
        );

        $parsed = $this->responseParser->parse($endpoint, $result['content'], $payload);

        return [
            'output_id' => (string) Str::uuid(),
            'content' => (string) ($parsed['content'] ?? $result['content']),
            'labeled' => true,
            'label' => config('ai.label'),
            'disclaimer' => config('ai.disclaimer'),
            'model' => $result['model'],
            'requires_review' => true,
            ...$this->structuredFields($parsed),
        ];
    }

    /**
     * @param  array<string, mixed>  $parsed
     * @return array<string, mixed>
     */
    private function structuredFields(array $parsed): array
    {
        $fields = [];
        foreach ([
            'authorities',
            'ranked_authorities',
            'verification_warning',
            'validation',
            'issues',
            'letters',
            'sections',
            'arguments',
            'opposing_arguments',
            'rebuttals',
            'formatting_notes',
            'cases',
            'memo_sections',
            'strategy',
            'statute_analysis',
        ] as $key) {
            if (isset($parsed[$key])) {
                $fields[$key] = $parsed[$key];
            }
        }

        return $fields;
    }

    private function enableExclusive(Organization $organization, string $provider): void
    {
        \App\Models\AiProviderConfig::query()
            ->where('organization_id', $organization->id)
            ->where('provider', '!=', $provider)
            ->update(['is_enabled' => false]);

        \App\Models\AiProviderConfig::query()
            ->where('organization_id', $organization->id)
            ->where('provider', $provider)
            ->update(['is_enabled' => true]);

        $settings = is_array($organization->settings) ? $organization->settings : [];
        $settings['active_ai_provider'] = $provider;
        $organization->update(['settings' => $settings]);
    }

    private function disableProvider(Organization $organization, string $provider): void
    {
        \App\Models\AiProviderConfig::query()
            ->where('organization_id', $organization->id)
            ->where('provider', $provider)
            ->update(['is_enabled' => false]);

        $settings = is_array($organization->settings) ? $organization->settings : [];
        if (($settings['active_ai_provider'] ?? null) === $provider) {
            unset($settings['active_ai_provider']);
            $organization->update(['settings' => $settings]);
        }
    }

    private function adapterFor(string $provider): Contracts\AiProviderAdapter
    {
        if (! isset($this->adapters[$provider])) {
            throw new RuntimeException("AI provider adapter not found: {$provider}");
        }

        return $this->adapters[$provider];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeConfig(string $provider, ?\App\Models\AiProviderConfig $config, ?string $activeProvider): array
    {
        /** @var array<string, mixed> $meta */
        $meta = config("ai.providers.{$provider}", []);
        $hasKey = $config?->api_key !== null && $config->api_key !== '';
        $canSelectModel = $config?->canSelectModel() ?? false;

        /** @var list<string> $models */
        $models = $meta['models'] ?? [];
        if ($models === []) {
            $default = (string) ($meta['default_model'] ?? '');
            $models = $default !== '' ? [$default] : [];
        }

        return [
            'provider' => $provider,
            'label' => (string) ($meta['label'] ?? $provider),
            'description' => (string) ($meta['description'] ?? ''),
            'default_model' => (string) ($meta['default_model'] ?? ''),
            'available_models' => $models,
            'is_enabled' => (bool) ($config?->is_enabled ?? false),
            'is_active' => $activeProvider === $provider,
            'model' => $config?->model ?? ($meta['default_model'] ?? null),
            'api_key_set' => $hasKey,
            'api_key_masked' => $hasKey ? $this->maskApiKey($config->api_key) : null,
            'last_test_success_at' => $config?->last_test_success_at?->toIso8601String(),
            'can_select_model' => $canSelectModel,
            'can_enable' => $canSelectModel,
            'settings' => $config?->settings ?? [],
        ];
    }

    private function maskApiKey(string $apiKey): string
    {
        $length = strlen($apiKey);
        if ($length <= 8) {
            return str_repeat('•', $length);
        }

        return substr($apiKey, 0, 4).'••••'.substr($apiKey, -4);
    }
}
