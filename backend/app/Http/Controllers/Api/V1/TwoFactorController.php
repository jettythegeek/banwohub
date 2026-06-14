<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\TotpService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class TwoFactorController extends Controller
{
    public function __construct(private readonly TotpService $totp)
    {
    }

    public function status(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'enabled' => $user->hasTwoFactorEnabled(),
            'confirmed_at' => $user->two_factor_confirmed_at?->toIso8601String(),
        ]);
    }

    public function enable(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->hasTwoFactorEnabled()) {
            throw ValidationException::withMessages([
                'two_factor' => ['Two-factor authentication is already enabled.'],
            ]);
        }

        $secret = $this->totp->generateSecret();
        $issuer = config('app.name', 'Banwolaw Hub');
        $otpauthUrl = $this->totp->provisioningUri($secret, $user->email, $issuer);

        $user->forceFill([
            'two_factor_secret' => $secret,
            'two_factor_enabled' => false,
            'two_factor_confirmed_at' => null,
        ])->save();

        activity('auth')
            ->causedBy($user)
            ->withProperties([
                'ip' => $request->ip(),
                'organization_id' => $user->organization_id,
                'event' => 'two_factor_setup_started',
            ])
            ->log('Two-factor setup started');

        return response()->json([
            'secret' => $secret,
            'otpauth_url' => $otpauthUrl,
            'issuer' => $issuer,
            'account' => $user->email,
        ]);
    }

    public function confirm(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'size:6'],
        ]);

        $user = $request->user();

        if ($user->hasTwoFactorEnabled()) {
            throw ValidationException::withMessages([
                'two_factor' => ['Two-factor authentication is already enabled.'],
            ]);
        }

        $secret = $user->two_factor_secret;
        if (! $secret || ! $this->totp->verify($secret, $validated['code'])) {
            throw ValidationException::withMessages([
                'code' => ['The verification code is invalid.'],
            ]);
        }

        $user->forceFill([
            'two_factor_enabled' => true,
            'two_factor_confirmed_at' => now(),
        ])->save();

        activity('auth')
            ->causedBy($user)
            ->withProperties([
                'ip' => $request->ip(),
                'organization_id' => $user->organization_id,
                'event' => 'two_factor_enabled',
            ])
            ->log('Two-factor authentication enabled');

        return response()->json([
            'message' => 'Two-factor authentication is now enabled.',
            'enabled' => true,
            'user' => new UserResource($user->load('roles', 'organization')),
        ]);
    }

    public function disable(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'password' => ['required', 'string'],
            'code' => ['required', 'string', 'size:6'],
        ]);

        $user = $request->user();

        if (! $user->hasTwoFactorEnabled()) {
            throw ValidationException::withMessages([
                'two_factor' => ['Two-factor authentication is not enabled.'],
            ]);
        }

        if (! Hash::check($validated['password'], $user->password)) {
            throw ValidationException::withMessages([
                'password' => ['The password is incorrect.'],
            ]);
        }

        $secret = $user->two_factor_secret;
        if (! $secret || ! $this->totp->verify($secret, $validated['code'])) {
            throw ValidationException::withMessages([
                'code' => ['The verification code is invalid.'],
            ]);
        }

        $user->forceFill([
            'two_factor_secret' => null,
            'two_factor_enabled' => false,
            'two_factor_confirmed_at' => null,
        ])->save();

        activity('auth')
            ->causedBy($user)
            ->withProperties([
                'ip' => $request->ip(),
                'organization_id' => $user->organization_id,
                'event' => 'two_factor_disabled',
            ])
            ->log('Two-factor authentication disabled');

        return response()->json([
            'message' => 'Two-factor authentication has been disabled.',
            'enabled' => false,
            'user' => new UserResource($user->load('roles', 'organization')),
        ]);
    }

    public function verifyChallenge(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'challenge_token' => ['required', 'string'],
            'code' => ['required', 'string', 'size:6'],
        ]);

        $cacheKey = $this->challengeCacheKey($validated['challenge_token']);
        $userId = Cache::get($cacheKey);

        if (! $userId) {
            throw ValidationException::withMessages([
                'challenge_token' => ['The login challenge has expired. Please sign in again.'],
            ]);
        }

        $user = User::query()->find($userId);
        if (! $user || ! $user->hasTwoFactorEnabled()) {
            Cache::forget($cacheKey);

            throw ValidationException::withMessages([
                'challenge_token' => ['The login challenge is invalid.'],
            ]);
        }

        $secret = $user->two_factor_secret;
        if (! $secret || ! $this->totp->verify($secret, $validated['code'])) {
            activity('auth')
                ->causedBy($user)
                ->withProperties([
                    'ip' => $request->ip(),
                    'organization_id' => $user->organization_id,
                    'event' => 'two_factor_challenge_failed',
                ])
                ->log('Two-factor challenge failed');

            throw ValidationException::withMessages([
                'code' => ['The verification code is invalid.'],
            ]);
        }

        Cache::forget($cacheKey);

        activity('auth')
            ->causedBy($user)
            ->withProperties([
                'ip' => $request->ip(),
                'organization_id' => $user->organization_id,
                'event' => 'login',
            ])
            ->log('User login');

        $token = $user->createToken('banwohub-api')->plainTextToken;

        return response()->json([
            'token' => $token,
            'token_type' => 'Bearer',
            'user' => new UserResource($user->load('roles', 'organization')),
        ]);
    }

    public static function challengeCacheKey(string $token): string
    {
        return 'two_factor_challenge:'.hash('sha256', $token);
    }

    public static function storeChallenge(string $token, int $userId): void
    {
        Cache::put(self::challengeCacheKey($token), $userId, now()->addMinutes(5));
    }
}
