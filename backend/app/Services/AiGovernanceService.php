<?php

namespace App\Services;

use App\Models\AiGovernanceLog;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Str;
use Spatie\Activitylog\Facades\Activity;

class AiGovernanceService
{
    /**
     * @param  array<string, mixed>  $promptContext
     * @param  array<string, mixed>  $metadata
     */
    public function log(
        Organization $organization,
        ?User $user,
        string $actionType,
        array $promptContext,
        ?string $outputId,
        ?string $model,
        string $outputPreview,
        string $status = 'success',
        ?string $botContext = null,
        ?int $legalMatterId = null,
        ?int $legalDocumentId = null,
        array $metadata = [],
    ): AiGovernanceLog {
        $log = AiGovernanceLog::query()->create([
            'organization_id' => $organization->id,
            'user_id' => $user?->id,
            'action_type' => $actionType,
            'bot_context' => $botContext,
            'legal_matter_id' => $legalMatterId,
            'legal_document_id' => $legalDocumentId,
            'output_id' => $outputId,
            'model' => $model,
            'status' => $status,
            'output_preview' => Str::limit($outputPreview, 2000),
            'prompt_context' => $promptContext,
            'metadata' => $metadata,
        ]);

        activity('ai')
            ->causedBy($user)
            ->withProperties([
                'ai_governance_log_id' => $log->id,
                'action_type' => $actionType,
                'output_id' => $outputId,
                'legal_matter_id' => $legalMatterId,
            ])
            ->log("AI action: {$actionType}");

        return $log;
    }
}
