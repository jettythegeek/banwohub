<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\PublicChatLead;
use App\Services\AiGovernanceService;
use App\Services\AiRateLimiter;
use App\Services\AiServiceClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use RuntimeException;

class PublicAiChatController extends Controller
{
    public function chat(
        Request $request,
        AiServiceClient $client,
        AiGovernanceService $governance,
        AiRateLimiter $limiter,
    ): JsonResponse {
        $data = $request->validate([
            'message' => ['required', 'string', 'max:4000'],
            'session_id' => ['nullable', 'string', 'max:64'],
            'name' => ['nullable', 'string', 'max:120'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:40'],
        ]);

        abort_if(
            $request->hasAny(['legal_matter_id', 'client_id', 'context']),
            422,
            'Public chat cannot access internal case data.',
        );

        $organization = $this->resolvePublicOrganization();
        $ip = (string) ($request->ip() ?? 'unknown');
        $sessionId = (string) ($data['session_id'] ?? Str::uuid());

        try {
            $limiter->ensurePublicWithinLimit($ip);

            $payload = [
                'message' => $data['message'],
                'context' => 'public',
                'firm_name' => $organization->name,
            ];

            $result = $client->request('chatbot', $payload, $organization);

            $governance->log(
                $organization,
                null,
                'public_chatbot',
                [
                    'message' => $data['message'],
                    'session_id' => $sessionId,
                    'has_lead' => $this->hasLeadContact($data),
                ],
                $result['output_id'],
                $result['model'],
                $result['content'],
                'success',
                'public',
            );

            if ($this->hasLeadContact($data)) {
                PublicChatLead::query()->create([
                    'organization_id' => $organization->id,
                    'session_id' => $sessionId,
                    'name' => $data['name'] ?? null,
                    'email' => $data['email'] ?? null,
                    'phone' => $data['phone'] ?? null,
                    'message' => $data['message'],
                    'ai_response_preview' => Str::limit($result['content'], 500),
                    'ip_address' => $ip !== 'unknown' ? $ip : null,
                    'user_agent' => Str::limit((string) $request->userAgent(), 500),
                    'metadata' => [
                        'output_id' => $result['output_id'],
                    ],
                ]);
            }

            return response()->json([
                'output_id' => $result['output_id'],
                'content' => $result['content'],
                'labeled' => $result['labeled'],
                'label' => $result['label'],
                'disclaimer' => $this->publicDisclaimer($result['disclaimer']),
                'requires_review' => $result['requires_review'],
                'model' => $result['model'],
                'session_id' => $sessionId,
                'lead_captured' => $this->hasLeadContact($data),
            ]);
        } catch (RuntimeException $exception) {
            $governance->log(
                $organization,
                null,
                'public_chatbot',
                ['message' => $data['message'], 'session_id' => $sessionId],
                null,
                null,
                $exception->getMessage(),
                'error',
                'public',
            );

            return response()->json(['message' => $exception->getMessage()], 429);
        }
    }

    private function resolvePublicOrganization(): Organization
    {
        $configuredId = config('ai.public_organization_id');

        if ($configuredId) {
            $organization = Organization::query()->find((int) $configuredId);
            if ($organization) {
                return $organization;
            }
        }

        $organization = Organization::query()->orderBy('id')->first();
        abort_unless($organization, 503, 'Public support is temporarily unavailable.');

        return $organization;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function hasLeadContact(array $data): bool
    {
        return filled($data['name'] ?? null)
            || filled($data['email'] ?? null)
            || filled($data['phone'] ?? null);
    }

    private function publicDisclaimer(string $fallback): string
    {
        $configured = config('ai.public_disclaimer');

        return is_string($configured) && $configured !== '' ? $configured : $fallback;
    }
}
