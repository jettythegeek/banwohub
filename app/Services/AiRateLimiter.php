<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use RuntimeException;

class AiRateLimiter
{
    public function ensureWithinLimit(User $user): void
    {
        $limit = max(1, (int) config('ai.rate_limit_per_minute', 30));
        $key = 'ai_rate:'.$user->id.':'.now()->format('YmdHi');
        $count = (int) Cache::get($key, 0);

        if ($count >= $limit) {
            throw new RuntimeException('AI rate limit exceeded. Try again in a minute.');
        }

        Cache::put($key, $count + 1, now()->addMinute());
    }

    public function ensurePublicWithinLimit(string $ip): void
    {
        $limit = max(1, (int) config('ai.public_rate_limit_per_minute', 10));
        $key = 'ai_public_rate:'.hash('sha256', $ip).':'.now()->format('YmdHi');
        $count = (int) Cache::get($key, 0);

        if ($count >= $limit) {
            throw new RuntimeException('Too many messages. Please wait a minute and try again.');
        }

        Cache::put($key, $count + 1, now()->addMinute());
    }
}
