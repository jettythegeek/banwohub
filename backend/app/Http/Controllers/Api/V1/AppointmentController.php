<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Concerns\ResolvesOrganization;
use App\Http\Controllers\Api\V1\Concerns\ValidatesOrganizationAccess;
use App\Http\Controllers\Controller;
use App\Http\Resources\AppointmentResource;
use App\Models\Appointment;
use App\Services\AppointmentBookingService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

class AppointmentController extends Controller
{
    use ResolvesOrganization;
    use ValidatesOrganizationAccess;

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Appointment::class);

        $organization = $this->organizationFor($request->user());

        $appointments = Appointment::query()
            ->with(['lawyer:id,name', 'client:id,name', 'legalMatter:id,title,matter_number'])
            ->where('organization_id', $organization->id)
            ->when($request->filled('user_id'), fn ($q) => $q->where('user_id', $request->integer('user_id')))
            ->when($request->filled('client_id'), fn ($q) => $q->where('client_id', $request->integer('client_id')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->filled('from'), fn ($q) => $q->where('starts_at', '>=', $request->date('from')))
            ->when($request->filled('to'), fn ($q) => $q->where('starts_at', '<=', $request->date('to')))
            ->orderBy('starts_at')
            ->paginate($request->integer('per_page', 25));

        return AppointmentResource::collection($appointments);
    }

    public function availableSlots(Request $request, AppointmentBookingService $booking): JsonResponse
    {
        $this->authorize('viewAny', Appointment::class);

        $organization = $this->organizationFor($request->user());

        $data = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'date' => ['required', 'date', 'after_or_equal:today'],
        ]);

        $this->userForOrganization((int) $data['user_id'], $organization->id);

        $slots = $booking->getAvailableSlots(
            $organization->id,
            (int) $data['user_id'],
            Carbon::parse($data['date'])
        );

        return response()->json(['data' => $slots]);
    }

    public function store(Request $request, AppointmentBookingService $booking): JsonResponse
    {
        $this->authorize('create', Appointment::class);

        $organization = $this->organizationFor($request->user());
        $data = $this->validatedData($request);
        $lawyer = $this->userForOrganization((int) $data['user_id'], $organization->id);

        if (! empty($data['client_id'])) {
            $this->clientForOrganization((int) $data['client_id'], $organization->id);
        }
        if (! empty($data['legal_matter_id'])) {
            $this->legalMatterForOrganization((int) $data['legal_matter_id'], $organization->id);
        }

        $appointment = $booking->createStaffAppointment([
            ...$data,
            'organization_id' => $organization->id,
            'user_id' => $lawyer->id,
        ], $request->user());

        return (new AppointmentResource($appointment))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Appointment $appointment): AppointmentResource
    {
        $this->authorize('view', $appointment);

        return new AppointmentResource(
            $appointment->load(['lawyer', 'client', 'legalMatter', 'calendarEvent'])
        );
    }

    public function update(Request $request, Appointment $appointment, AppointmentBookingService $booking): AppointmentResource
    {
        $this->authorize('update', $appointment);

        $data = $this->validatedData($request, partial: true);

        if (isset($data['status']) && $data['status'] === 'confirmed' && $appointment->status === 'pending') {
            $appointment = $booking->confirm($appointment, $request->user());
            unset($data['status']);
        }

        if (isset($data['status']) && $data['status'] === 'cancelled') {
            return new AppointmentResource($booking->cancel($appointment, $request->user()));
        }

        if ($data !== []) {
            $appointment->update($data);
        }

        return new AppointmentResource($appointment->fresh()->load(['lawyer', 'client', 'legalMatter', 'calendarEvent']));
    }

    public function destroy(Appointment $appointment, AppointmentBookingService $booking): JsonResponse
    {
        $this->authorize('delete', $appointment);

        $booking->cancel($appointment, request()->user());
        $appointment->delete();

        return response()->json(['message' => 'Appointment deleted successfully.']);
    }

    /**
     * @return array<string, mixed>
     */
    protected function validatedData(Request $request, bool $partial = false): array
    {
        return $request->validate([
            'client_id' => [$partial ? 'sometimes' : 'nullable', 'integer', 'exists:clients,id'],
            'user_id' => [$partial ? 'sometimes' : 'required', 'integer', 'exists:users,id'],
            'legal_matter_id' => ['nullable', 'integer', 'exists:legal_matters,id'],
            'consultation_type' => [$partial ? 'sometimes' : 'required', 'string', Rule::in(Appointment::CONSULTATION_TYPES)],
            'status' => ['nullable', 'string', Rule::in(Appointment::STATUSES)],
            'starts_at' => [$partial ? 'sometimes' : 'required', 'date'],
            'ends_at' => [$partial ? 'sometimes' : 'required', 'date', 'after:starts_at'],
            'location' => ['nullable', 'string', 'max:255'],
            'online_meeting' => ['nullable', 'boolean'],
            'fee' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string'],
            'metadata' => ['nullable', 'array'],
        ]);
    }
}
