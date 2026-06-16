<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class Client extends Model
{
    use LogsActivity;
    use SoftDeletes;

    protected $fillable = [
        'organization_id',
        'client_number',
        'type',
        'name',
        'email',
        'phone',
        'company_name',
        'address',
        'status',
        'notes',
        'created_by',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'email', 'phone', 'status', 'company_name', 'client_number'])
            ->logOnlyDirty()
            ->useLogName('client');
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function legalMatters(): HasMany
    {
        return $this->hasMany(LegalMatter::class);
    }

    public function portalUsers(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function portalUser(): HasOne
    {
        return $this->hasOne(User::class)->whereNotNull('client_id');
    }

    public function communicationLogs(): HasMany
    {
        return $this->hasMany(CommunicationLog::class);
    }

    public function contacts(): HasMany
    {
        return $this->hasMany(ClientContact::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }
}
