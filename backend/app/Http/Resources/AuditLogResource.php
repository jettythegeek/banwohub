<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \Spatie\Activitylog\Models\Activity */
class AuditLogResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $properties = $this->properties instanceof \Illuminate\Support\Collection
            ? $this->properties->toArray()
            : (array) ($this->properties ?? []);

        $changes = $this->attribute_changes instanceof \Illuminate\Support\Collection
            ? $this->attribute_changes->toArray()
            : (array) ($this->attribute_changes ?? []);

        return [
            'id' => $this->id,
            'action' => $this->description,
            'event' => $this->event,
            'module' => $this->log_name,
            'subject_type' => $this->subject_type ? class_basename((string) $this->subject_type) : null,
            'subject_id' => $this->subject_id,
            'user' => $this->whenLoaded('causer', function () {
                if (! $this->causer instanceof User) {
                    return null;
                }

                return [
                    'id' => $this->causer->id,
                    'name' => $this->causer->name,
                    'email' => $this->causer->email,
                ];
            }),
            'ip_address' => $properties['ip'] ?? $properties['ip_address'] ?? null,
            'previous_value' => $changes['old'] ?? $properties['old'] ?? $properties['from'] ?? null,
            'new_value' => $changes['attributes'] ?? $properties['attributes'] ?? $properties['to'] ?? null,
            'properties' => $properties,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
