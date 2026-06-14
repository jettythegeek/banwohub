<?php

namespace App\Http\Controllers\Api\V1\Portal;

use App\Http\Controllers\Controller;
use App\Http\Resources\PortalUserResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class PortalAuthController extends Controller
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
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        if (! $user->is_active) {
            throw ValidationException::withMessages([
                'email' => ['This account is inactive.'],
            ]);
        }

        if (! $user->hasRole('Client') || ! $user->client_id) {
            throw ValidationException::withMessages([
                'email' => ['This account is not authorized for the client portal.'],
            ]);
        }

        $token = $user->createToken('banwohub-portal')->plainTextToken;

        return response()->json([
            'token' => $token,
            'token_type' => 'Bearer',
            'user' => new PortalUserResource($user->load('roles', 'organization', 'client')),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()?->delete();

        return response()->json(['message' => 'Logged out successfully.']);
    }

    public function me(Request $request): PortalUserResource
    {
        return new PortalUserResource(
            $request->user()->load('roles', 'permissions', 'organization', 'client')
        );
    }

    public function updateProfile(Request $request): PortalUserResource
    {
        $user = $request->user();

        if (! $user->hasRole('Client') || ! $user->client_id) {
            abort(403, 'This account is not authorized for the client portal.');
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
        ]);

        $user->update($data);

        return new PortalUserResource(
            $user->fresh()->load('roles', 'permissions', 'organization', 'client')
        );
    }
}
