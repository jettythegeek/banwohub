<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ResearchSavedItem extends Model
{
    protected $fillable = [
        'organization_id',
        'research_folder_id',
        'legal_research_entry_id',
        'legal_matter_id',
        'notes',
        'saved_by',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function folder(): BelongsTo
    {
        return $this->belongsTo(ResearchFolder::class, 'research_folder_id');
    }

    public function entry(): BelongsTo
    {
        return $this->belongsTo(LegalResearchEntry::class, 'legal_research_entry_id');
    }

    public function legalMatter(): BelongsTo
    {
        return $this->belongsTo(LegalMatter::class);
    }

    public function saver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'saved_by');
    }
}
