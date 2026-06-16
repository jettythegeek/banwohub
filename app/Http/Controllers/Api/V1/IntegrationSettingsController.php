<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Concerns\ResolvesOrganization;
use App\Http\Controllers\Controller;
use App\Models\IntegrationOAuthToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class IntegrationSettingsController extends Controller
{
    use ResolvesOrganization;

    public function index(Request $request): JsonResponse
    {
        abort_unless($request->user()?->can('organization.manage'), 403);

        $organization = $this->organizationFor($request->user());
        $googleConnected = IntegrationOAuthToken::query()
            ->where('organization_id', $organization->id)
            ->where('provider', IntegrationOAuthToken::PROVIDER_GOOGLE_CALENDAR)
            ->where('user_id', $request->user()->id)
            ->exists();

        $googleEnvConfigured = $this->allConfigured([
            'GOOGLE_CALENDAR_CLIENT_ID',
            'GOOGLE_CALENDAR_CLIENT_SECRET',
        ]);

        return response()->json([
            'integrations' => [
                $this->integration(
                    'sms_whatsapp',
                    'SMS / WhatsApp',
                    'Send appointment reminders, deadline alerts, and client notifications via SMS or WhatsApp Business.',
                    $this->smsWhatsAppConfigured(),
                    ['TWILIO_SID', 'TWILIO_AUTH_TOKEN', 'TWILIO_FROM', 'WHATSAPP_BUSINESS_TOKEN'],
                ),
                $this->integration(
                    'google_calendar',
                    'Google Calendar',
                    'Sync hearings, deadlines, and appointments with Google Calendar for lawyers.',
                    $googleEnvConfigured || $googleConnected,
                    ['GOOGLE_CALENDAR_CLIENT_ID', 'GOOGLE_CALENDAR_CLIENT_SECRET', 'GOOGLE_CALENDAR_REDIRECT_URI'],
                    oauth: true,
                    connected: $googleConnected,
                ),
                $this->integration(
                    'court_efiling',
                    'Court e-filing',
                    'Submit court filings electronically via a certified e-filing provider adapter.',
                    filled(env('COURT_EFILING_API_KEY')) && filled(env('COURT_EFILING_BASE_URL')),
                    ['COURT_EFILING_API_KEY', 'COURT_EFILING_BASE_URL', 'COURT_EFILING_PROVIDER'],
                ),
            ],
        ]);
    }

    /**
     * @param  list<string>  $envKeys
     * @return array<string, mixed>
     */
    protected function integration(
        string $key,
        string $name,
        string $description,
        bool $configured,
        array $envKeys,
        bool $oauth = false,
        bool $connected = false,
    ): array {
        $row = [
            'key' => $key,
            'name' => $name,
            'description' => $description,
            'status' => $configured ? 'configured' : 'disabled',
            'env_keys' => $envKeys,
        ];

        if ($oauth) {
            $row['oauth'] = true;
            $row['connected'] = $connected;
            $row['connect_path'] = '/integrations/google-calendar/connect';
        }

        return $row;
    }

    protected function smsWhatsAppConfigured(): bool
    {
        $twilio = $this->allConfigured(['TWILIO_SID', 'TWILIO_AUTH_TOKEN']);

        return $twilio || filled(env('WHATSAPP_BUSINESS_TOKEN'));
    }

    /**
     * @param  list<string>  $keys
     */
    protected function allConfigured(array $keys): bool
    {
        foreach ($keys as $key) {
            if (! filled(env($key))) {
                return false;
            }
        }

        return true;
    }
}
