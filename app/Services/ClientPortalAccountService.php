<?php

namespace App\Services;

use App\Models\Client;
use App\Models\Organization;
use App\Models\User;
use App\Notifications\ClientPortalWelcomeNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ClientPortalAccountService
{
    /**
     * @param  array{portal_password_option: string, portal_password?: string|null}  $options
     */
    public function createPortalAccount(Client $client, Organization $organization, array $options): User
    {
        if ($this->findPortalUser($client)) {
            throw ValidationException::withMessages([
                'create_portal_account' => ['This client already has a portal account.'],
            ]);
        }

        if (! $client->email) {
            throw ValidationException::withMessages([
                'email' => ['An email address is required to create a portal account.'],
            ]);
        }

        $this->assertEmailAvailable($client->email);

        [$password, $sendEmail] = $this->resolvePassword($options);

        $user = DB::transaction(function () use ($client, $organization, $password) {
            $user = User::query()->create([
                'organization_id' => $organization->id,
                'client_id' => $client->id,
                'name' => $client->name,
                'email' => $client->email,
                'phone' => $client->phone,
                'password' => $password,
                'is_active' => true,
            ]);

            $user->assignRole('Client');

            return $user;
        });

        if ($sendEmail) {
            $user->notify(new ClientPortalWelcomeNotification(
                organization: $organization,
                plainPassword: $password,
            ));
        }

        return $user;
    }

    /**
     * @param  array{portal_password_option: string, portal_password?: string|null}  $options
     */
    public function resetPortalPassword(User $portalUser, Client $client, Organization $organization, array $options): void
    {
        [$password, $sendEmail] = $this->resolvePassword($options);

        $portalUser->update(['password' => $password]);

        if ($sendEmail) {
            $portalUser->notify(new ClientPortalWelcomeNotification(
                organization: $organization,
                plainPassword: $password,
                isPasswordReset: true,
            ));
        }
    }

    public function syncPortalEmail(User $portalUser, string $email): void
    {
        if ($portalUser->email === $email) {
            return;
        }

        $this->assertEmailAvailable($email, $portalUser->id);

        $portalUser->update(['email' => $email]);
    }

    public function findPortalUser(Client $client): ?User
    {
        return $client->portalUser;
    }

    /**
     * @return array{0: string, 1: bool, 2: array<string, mixed>}
     */
    public function portalStatus(Client $client): array
    {
        $user = $this->findPortalUser($client);

        if (! $user) {
            return [
                'has_account' => false,
                'login_email' => null,
                'is_active' => null,
            ];
        }

        return [
            'has_account' => true,
            'login_email' => $user->email,
            'is_active' => $user->is_active,
        ];
    }

    protected function assertEmailAvailable(string $email, ?int $ignoreUserId = null): void
    {
        $query = User::query()->where('email', $email);

        if ($ignoreUserId) {
            $query->where('id', '!=', $ignoreUserId);
        }

        if ($query->exists()) {
            throw ValidationException::withMessages([
                'email' => ['This email is already used by another account.'],
            ]);
        }
    }

    /**
     * @param  array{portal_password_option: string, portal_password?: string|null}  $options
     * @return array{0: string, 1: bool}
     */
    protected function resolvePassword(array $options): array
    {
        $option = $options['portal_password_option'];

        if ($option === 'manual') {
            $password = $options['portal_password'] ?? null;

            if (! is_string($password) || $password === '') {
                throw ValidationException::withMessages([
                    'portal_password' => ['A password is required when setting it manually.'],
                ]);
            }

            return [$password, false];
        }

        if ($option === 'email') {
            return [Str::password(12), true];
        }

        throw ValidationException::withMessages([
            'portal_password_option' => ['Invalid portal password option.'],
        ]);
    }
}
