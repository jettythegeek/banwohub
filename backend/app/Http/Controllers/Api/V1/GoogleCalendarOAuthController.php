<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Concerns\ResolvesOrganization;
use App\Http\Controllers\Controller;
use App\Models\IntegrationOAuthToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class GoogleCalendarOAuthController extends Controller
{
    use ResolvesOrganization;

    public function connect(Request $request): JsonResponse
    {
        abort_unless($request->user()?->can('organization.manage'), 403);

        $clientId = (string) env('GOOGLE_CALENDAR_CLIENT_ID', '');
        $redirectUri = $this->redirectUri();

        if ($clientId === '' || $redirectUri === '') {
            return response()->json([
                'message' => 'Google Calendar OAuth is not configured. Set GOOGLE_CALENDAR_CLIENT_ID and GOOGLE_CALENDAR_REDIRECT_URI.',
                'configured' => false,
            ], 422);
        }

        $state = Str::random(40);
        Cache::put(
            'google_calendar_oauth:'.$state,
            ['user_id' => $request->user()->id],
            now()->addMinutes(15),
        );

        $params = http_build_query([
            'client_id' => $clientId,
            'redirect_uri' => $redirectUri,
            'response_type' => 'code',
            'scope' => 'https://www.googleapis.com/auth/calendar.events',
            'access_type' => 'offline',
            'prompt' => 'consent',
            'state' => $state,
        ]);

        return response()->json([
            'configured' => true,
            'auth_url' => 'https://accounts.google.com/o/oauth2/v2/auth?'.$params,
        ]);
    }

    public function callback(Request $request): RedirectResponse|JsonResponse
    {
        $frontendUrl = rtrim((string) env('FRONTEND_URL', 'http://banwohub.test'), '/');
        $settingsPath = '/settings?tab=integrations';

        $state = (string) $request->query('state', '');
        $cached = $state !== '' ? Cache::pull('google_calendar_oauth:'.$state) : null;
        $userId = is_array($cached) ? ($cached['user_id'] ?? null) : null;

        if (! $userId) {
            return redirect()->away($frontendUrl.$settingsPath.'&google_calendar=error&reason=invalid_state');
        }

        $user = \App\Models\User::query()->find($userId);
        if (! $user) {
            return redirect()->away($frontendUrl.$settingsPath.'&google_calendar=error&reason=missing_user');
        }

        $organization = $this->organizationFor($user);
        $code = (string) $request->query('code', '');

        IntegrationOAuthToken::query()->updateOrCreate(
            [
                'organization_id' => $organization->id,
                'provider' => IntegrationOAuthToken::PROVIDER_GOOGLE_CALENDAR,
                'user_id' => $user->id,
            ],
            [
                'access_token' => $code !== '' ? 'stub_access_'.$code : 'stub_access_pending',
                'refresh_token' => 'stub_refresh_'.Str::random(16),
                'expires_at' => now()->addHour(),
                'metadata' => [
                    'stub' => true,
                    'connected_at' => now()->toIso8601String(),
                    'note' => 'OAuth callback placeholder — exchange code for tokens when live adapter ships.',
                ],
            ],
        );

        return redirect()->away($frontendUrl.$settingsPath.'&google_calendar=connected');
    }

    public function disconnect(Request $request): JsonResponse
    {
        abort_unless($request->user()?->can('organization.manage'), 403);

        $organization = $this->organizationFor($request->user());

        IntegrationOAuthToken::query()
            ->where('organization_id', $organization->id)
            ->where('provider', IntegrationOAuthToken::PROVIDER_GOOGLE_CALENDAR)
            ->where('user_id', $request->user()->id)
            ->delete();

        return response()->json(['disconnected' => true]);
    }

    protected function redirectUri(): string
    {
        $configured = (string) env('GOOGLE_CALENDAR_REDIRECT_URI', '');

        if ($configured !== '') {
            return $configured;
        }

        $appUrl = rtrim((string) env('APP_URL', ''), '/');

        return $appUrl !== '' ? $appUrl.'/api/v1/integrations/google-calendar/callback' : '';
    }
}
