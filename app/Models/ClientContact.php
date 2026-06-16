<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClientContact extends Model
{
    public const TYPES = ['primary', 'billing', 'opposing', 'witness'];

    protected $fillable = [
        'client_id',
        'type',
        'name',
        'email',
        'phone',
        'title',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }
}
