<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Concerns\ResolvesOrganization;
use App\Http\Controllers\Controller;
use App\Services\Ai\AiProviderManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AiProviderSettingsController extends Controller
{
    use ResolvesOrganization;

    public function index(Request $request, AiProviderManager $manager): JsonResponse
    {
        abort_unless($request->user()?->can('ai.providers.manage'), 403);

        $organization = $this->organizationFor($request->user());

        return response()->json($this->settingsPayload($manager, $organization));
    }

    public function update(Request $request, AiProviderManager $manager): JsonResponse
    {
        abort_unless($request->user()?->can('ai.providers.manage'), 403);

        $organization = $this->organizationFor($request->user());
        $providers = array_keys(config('ai.providers', []));

        $data = $request->validate([
            'provider' => ['required', 'string', Rule::in($providers)],
            'api_key' => ['nullable', 'string', 'max:500'],
            'is_enabled' => ['sometimes', 'boolean'],
            'model' => ['nullable', 'string', 'max:100'],
            'settings' => ['nullable', 'array'],
        ]);

        $provider = (string) $data['provider'];
        unset($data['provider']);

        $manager->upsertConfig($organization, $provider, $data);

        $organization = $organization->fresh();

        return response()->json([
            'active_provider' => $manager->activeProviderName($organization),
            'provider' => collect($manager->listForOrganization($organization))
                ->firstWhere('provider', $provider),
            'providers' => $manager->listForOrganization($organization),
        ]);
    }

    public function setActive(Request $request, AiProviderManager $manager): JsonResponse
    {
        abort_unless($request->user()?->can('ai.providers.manage'), 403);

        $organization = $this->organizationFor($request->user());
        $providers = array_keys(config('ai.providers', []));

        $data = $request->validate([
            'provider' => ['required', 'string', Rule::in($providers)],
        ]);

        $manager->setActiveProvider($organization, $data['provider']);

        $organization = $organization->fresh();

        return response()->json($this->settingsPayload($manager, $organization));
    }

    public function testConnection(Request $request, string $provider, AiProviderManager $manager): JsonResponse
    {
        abort_unless($request->user()?->can('ai.providers.manage'), 403);

        $organization = $this->organizationFor($request->user());
        $providers = array_keys(config('ai.providers', []));

        abort_unless(in_array($provider, $providers, true), 404);

        $data = $request->validate([
            'api_key' => ['nullable', 'string', 'max:500'],
        ]);

        $result = $manager->testConnection(
            $organization,
            $provider,
            isset($data['api_key']) ? (string) $data['api_key'] : null,
        );

        $organization = $organization->fresh();

        $payload = array_merge($result, [
            'provider' => collect($manager->listForOrganization($organization))
                ->firstWhere('provider', $provider),
            'providers' => $manager->listForOrganization($organization),
            'active_provider' => $manager->activeProviderName($organization),
        ]);

        return response()->json($payload, $result['success'] ? 200 : 422);
    }

    /**
     * @return array{active_provider: string|null, providers: array<int, array<string, mixed>>}
     */
    private function settingsPayload(AiProviderManager $manager, \App\Models\Organization $organization): array
    {
        return [
            'active_provider' => $manager->activeProviderName($organization),
            'providers' => $manager->listForOrganization($organization),
        ];
    }
}
