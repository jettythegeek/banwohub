<?php

namespace App\Http\Controllers\Api\V1\Portal;

use App\Http\Concerns\ResolvesPortalClient;
use App\Http\Controllers\Controller;
use App\Http\Resources\AppointmentResource;
use App\Models\Appointment;
use App\Models\LawyerAvailabilitySlot;
use App\Models\LegalMatter;
use App\Models\User;
use App\Services\AppointmentBookingService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

class PortalAppointmentController extends Controller
{
    use ResolvesPortalClient;

    public function lawyers(Request $request): JsonResponse
    {
        $client = $this->portalClientFor($request->user());

        $lawyerIds = LawyerAvailabilitySlot::query()
            ->where('organization_id', $client->organization_id)
            ->where('is_active', true)
            ->distinct()
            ->pluck('user_id');

        $lawyers = User::query()
            ->where('organization_id', $client->organization_id)
            ->where('is_active', true)
            ->whereIn('id', $lawyerIds)
            ->whereHas('roles', fn ($q) => $q->whereIn('name', ['Lawyer', 'Partner', 'Firm Admin']))
            ->orderBy('name')
            ->get(['id', 'name', 'job_title']);

        return response()->json(['data' => $lawyers]);
    }

    public function availableSlots(Request $request, AppointmentBookingService $booking): JsonResponse
    {
        $client = $this->portalClientFor($request->user());

        $data = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'date' => ['required', 'date', 'after_or_equal:today'],
        ]);

        abort_unless(
            User::query()
                ->where('organization_id', $client->organization_id)
                ->whereKey($data['user_id'])
                ->exists(),
            404
        );

        $slots = $booking->getAvailableSlots(
            $client->organization_id,
            (int) $data['user_id'],
            Carbon::parse($data['date'])
        );

        return response()->json(['data' => $slots]);
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $client = $this->portalClientFor($request->user());

        $appointments = Appointment::query()
            ->with(['lawyer:id,name', 'legalMatter:id,title,matter_number'])
            ->where('organization_id', $client->organization_id)
            ->where('client_id', $client->id)
            ->orderByDesc('starts_at')
            ->paginate($request->integer('per_page', 25));

        return AppointmentResource::collection($appointments);
    }

    public function store(Request $request, AppointmentBookingService $booking): JsonResponse
    {
        $user = $request->user();
        $client = $this->portalClientFor($user);

        $data = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'legal_matter_id' => ['nullable', 'integer'],
            'consultation_type' => ['required', 'string', Rule::in(Appointment::CONSULTATION_TYPES)],
            'starts_at' => ['required', 'date', 'after:now'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
            'location' => ['nullable', 'string', 'max:255'],
            'online_meeting' => ['nullable', 'boolean'],
            'fee' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);

        abort_unless(
            User::query()
                ->where('organization_id', $client->organization_id)
                ->whereKey($data['user_id'])
                ->exists(),
            404
        );

        if (! empty($data['legal_matter_id'])) {
            $this->assertPortalMatter($client, (int) $data['legal_matter_id']);
        }

        if ($data['consultation_type'] === 'internal_meeting') {
            abort(422, 'Internal meetings cannot be booked via the portal.');
        }

        $appointment = $booking->bookPortalAppointment([
            'organization_id' => $client->organization_id,
            'client_id' => $client->id,
            'user_id' => (int) $data['user_id'],
            'legal_matter_id' => $data['legal_matter_id'] ?? null,
            'consultation_type' => $data['consultation_type'],
            'starts_at' => $data['starts_at'],
            'ends_at' => $data['ends_at'],
            'location' => $data['location'] ?? null,
            'online_meeting' => $data['online_meeting'] ?? false,
            'fee' => $data['fee'] ?? null,
            'notes' => $data['notes'] ?? null,
        ], $user);

        return (new AppointmentResource($appointment))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Request $request, Appointment $appointment): AppointmentResource
    {
        $client = $this->portalClientFor($request->user());
        abort_unless(
            $appointment->organization_id === $client->organization_id
            && $appointment->client_id === $client->id,
            404
        );

        return new AppointmentResource(
            $appointment->load(['lawyer', 'legalMatter', 'calendarEvent'])
        );
    }

    public function cancel(Request $request, Appointment $appointment, AppointmentBookingService $booking): AppointmentResource
    {
        $client = $this->portalClientFor($request->user());
        abort_unless(
            $appointment->organization_id === $client->organization_id
            && $appointment->client_id === $client->id,
            404
        );

        abort_unless(in_array($appointment->status, ['pending', 'confirmed'], true), 422);

        return new AppointmentResource($booking->cancel($appointment, $request->user()));
    }

    protected function assertPortalMatter(\App\Models\Client $client, int $matterId): LegalMatter
    {
        return LegalMatter::query()
            ->where('organization_id', $client->organization_id)
            ->where('client_id', $client->id)
            ->findOrFail($matterId);
    }
}
