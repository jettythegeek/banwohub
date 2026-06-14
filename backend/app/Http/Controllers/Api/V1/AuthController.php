<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\TotpService;
use App\Http\Controllers\Api\V1\TwoFactorController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = \App\Models\User::query()
            ->where('email', $credentials['email'])
            ->first();

        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            activity('auth')
                ->withProperties([
                    'email' => $credentials['email'],
                    'ip' => $request->ip(),
                    'organization_id' => $user?->organization_id,
                    'event' => 'login_failed',
                ])
                ->log('Failed login attempt');

            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        if (! $user->is_active) {
            throw ValidationException::withMessages([
                'email' => ['This account is inactive.'],
            ]);
        }

        if ($user->isPortalClient()) {
            throw ValidationException::withMessages([
                'email' => ['Please sign in through the client portal.'],
            ]);
        }

        if ($user->hasTwoFactorEnabled()) {
            $challengeToken = app(TotpService::class)->challengeToken();
            TwoFactorController::storeChallenge($challengeToken, $user->id);

            return response()->json([
                'two_factor_required' => true,
                'challenge_token' => $challengeToken,
            ]);
        }

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

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()?->delete();

        return response()->json(['message' => 'Logged out successfully.']);
    }

    public function me(Request $request): UserResource
    {
        return new UserResource(
            $request->user()->load('roles', 'permissions', 'organization')
        );
    }

    public function forgotPassword(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
        ]);

        $user = User::query()->where('email', $validated['email'])->first();

        $payload = [
            'message' => 'If an account exists for that email, we have sent password reset instructions.',
        ];

        if ($user && $user->is_active) {
            $token = Password::broker()->createToken($user);
            $user->sendPasswordResetNotification($token);

            if (config('app.debug')) {
                $email = urlencode($user->email);
                $base = rtrim(config('app.password_reset_frontend_url'), '/');
                $resetUrl = "{$base}?token={$token}&email={$email}";

                Log::info('Password reset link (debug response)', [
                    'email' => $user->email,
                    'url' => $resetUrl,
                ]);

                $payload['reset_link'] = $resetUrl;
            }
        }

        return response()->json($payload);
    }

    public function resetPassword(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', PasswordRule::defaults()],
        ]);

        $status = Password::reset(
            $validated,
            function (User $user, string $password): void {
                $user->forceFill([
                    'password' => $password,
                ])->save();
            },
        );

        if ($status === Password::PASSWORD_RESET) {
            return response()->json([
                'message' => 'Your password has been reset. You can sign in with your new password.',
            ]);
        }

        throw ValidationException::withMessages([
            'email' => [__($status)],
        ]);
    }
}
