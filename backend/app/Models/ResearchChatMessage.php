<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ResearchChatMessage extends Model
{
    protected $fillable = [
        'organization_id',
        'research_project_id',
        'role',
        'content',
        'ai_governance_log_id',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function researchProject(): BelongsTo
    {
        return $this->belongsTo(ResearchProject::class);
    }

    public function aiGovernanceLog(): BelongsTo
    {
        return $this->belongsTo(AiGovernanceLog::class, 'ai_governance_log_id');
    }
}
