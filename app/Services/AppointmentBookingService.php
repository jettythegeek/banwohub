<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\CalendarEvent;
use App\Models\LawyerAvailabilitySlot;
use App\Models\User;
use App\Support\InAppNotifier;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AppointmentBookingService
{
    public function __construct(
        private readonly InAppNotifier $notifier,
    ) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function getAvailableSlots(int $organizationId, int $lawyerId, Carbon $date): array
    {
        $dayOfWeek = $date->dayOfWeek;

        $availabilities = LawyerAvailabilitySlot::query()
            ->where('organization_id', $organizationId)
            ->where('user_id', $lawyerId)
            ->where('day_of_week', $dayOfWeek)
            ->where('is_active', true)
            ->orderBy('start_time')
            ->get();

        $slots = [];

        foreach ($availabilities as $availability) {
            $start = Carbon::parse($date->format('Y-m-d').' '.$availability->start_time);
            $end = Carbon::parse($date->format('Y-m-d').' '.$availability->end_time);
            $duration = max(15, (int) $availability->slot_duration_minutes);

            while ($start->copy()->addMinutes($duration)->lte($end)) {
                $slotEnd = $start->copy()->addMinutes($duration);

                if ($slotEnd->isPast()) {
                    $start->addMinutes($duration);

                    continue;
                }

                if (! $this->slotOverlapsBooking($organizationId, $lawyerId, $start, $slotEnd)) {
                    $slots[] = [
                        'starts_at' => $start->toIso8601String(),
                        'ends_at' => $slotEnd->toIso8601String(),
                        'consultation_types' => $availability->consultation_types ?? LawyerAvailabilitySlot::CONSULTATION_TYPES,
                        'fee' => $availability->consultation_fee,
                        'location' => $availability->location,
                        'online_meeting' => $availability->online_meeting,
                    ];
                }

                $start->addMinutes($duration);
            }
        }

        return $slots;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function createStaffAppointment(array $data, User $actor): Appointment
    {
        return DB::transaction(function () use ($data, $actor) {
            $this->assertSlotAvailable(
                (int) $data['organization_id'],
                (int) $data['user_id'],
                Carbon::parse($data['starts_at']),
                Carbon::parse($data['ends_at']),
            );

            $status = $data['status'] ?? 'confirmed';
            $paymentStatus = $this->resolvePaymentStatus(
                $data['consultation_type'],
                isset($data['fee']) ? (float) $data['fee'] : null,
                $status
            );

            $appointment = Appointment::query()->create([
                ...$data,
                'booked_by_user_id' => $actor->id,
                'status' => $status,
                'payment_status' => $paymentStatus,
            ]);

            if ($status === 'confirmed') {
                $this->attachCalendarEvent($appointment);
            }

            $this->notifyBooked($appointment, $actor);

            return $appointment->fresh()->load(['lawyer', 'client', 'legalMatter', 'calendarEvent']);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function bookPortalAppointment(array $data, User $portalUser): Appointment
    {
        return DB::transaction(function () use ($data, $portalUser) {
            $this->assertSlotAvailable(
                (int) $data['organization_id'],
                (int) $data['user_id'],
                Carbon::parse($data['starts_at']),
                Carbon::parse($data['ends_at']),
            );

            $fee = isset($data['fee']) ? (float) $data['fee'] : null;
            $isPaid = $data['consultation_type'] === 'paid_consultation' && $fee > 0;
            $status = $isPaid ? 'pending' : 'confirmed';
            $paymentStatus = $isPaid ? 'pending' : 'none';

            $appointment = Appointment::query()->create([
                ...$data,
                'booked_by_user_id' => $portalUser->id,
                'status' => $status,
                'payment_status' => $paymentStatus,
            ]);

            if ($status === 'confirmed') {
                $this->attachCalendarEvent($appointment);
            }

            $this->notifyBooked($appointment, $portalUser);

            return $appointment->fresh()->load(['lawyer', 'client', 'legalMatter', 'calendarEvent']);
        });
    }

    public function confirm(Appointment $appointment, User $actor): Appointment
    {
        if ($appointment->status === 'confirmed') {
            return $appointment;
        }

        abort_unless(in_array($appointment->status, ['pending'], true), 422, 'Only pending appointments can be confirmed.');

        return DB::transaction(function () use ($appointment, $actor) {
            $appointment->update([
                'status' => 'confirmed',
                'payment_status' => $appointment->payment_status === 'pending' ? 'paid' : $appointment->payment_status,
            ]);

            $this->attachCalendarEvent($appointment->fresh());

            $this->notifier->notifyUser(
                $appointment->lawyer,
                'appointment_confirmed',
                'Appointment confirmed',
                $appointment->consultation_type.' on '.$appointment->starts_at?->format('M j, Y g:i A'),
                ['appointment_id' => $appointment->id],
                $actor
            );

            return $appointment->fresh()->load(['lawyer', 'client', 'legalMatter', 'calendarEvent']);
        });
    }

    public function cancel(Appointment $appointment, User $actor): Appointment
    {
        return DB::transaction(function () use ($appointment, $actor) {
            $appointment->update(['status' => 'cancelled']);

            if ($appointment->calendar_event_id) {
                CalendarEvent::query()->whereKey($appointment->calendar_event_id)->delete();
                $appointment->update(['calendar_event_id' => null]);
            }

            if ($appointment->lawyer) {
                $this->notifier->notifyUser(
                    $appointment->lawyer,
                    'appointment_cancelled',
                    'Appointment cancelled',
                    $appointment->starts_at?->format('M j, Y g:i A'),
                    ['appointment_id' => $appointment->id],
                    $actor
                );
            }

            return $appointment->fresh()->load(['lawyer', 'client', 'legalMatter', 'calendarEvent']);
        });
    }

    protected function attachCalendarEvent(Appointment $appointment): void
    {
        if ($appointment->calendar_event_id) {
            return;
        }

        $startsAt = $appointment->starts_at;
        $reminderAt = $startsAt?->copy()->subDay();
        if ($reminderAt && $reminderAt->isPast()) {
            $reminderAt = $startsAt?->copy()->subHour();
        }

        $title = str_replace('_', ' ', ucfirst($appointment->consultation_type));
        if ($appointment->client) {
            $title .= ' — '.$appointment->client->name;
        }

        $event = CalendarEvent::query()->create([
            'organization_id' => $appointment->organization_id,
            'legal_matter_id' => $appointment->legal_matter_id,
            'user_id' => $appointment->user_id,
            'created_by' => $appointment->booked_by_user_id,
            'event_type' => 'appointment',
            'title' => $title,
            'description' => $appointment->notes,
            'starts_at' => $appointment->starts_at,
            'ends_at' => $appointment->ends_at,
            'location' => $appointment->location,
            'reminder_at' => $reminderAt,
            'metadata' => [
                'appointment_id' => $appointment->id,
                'consultation_type' => $appointment->consultation_type,
                'online_meeting' => $appointment->online_meeting,
            ],
        ]);

        $appointment->update(['calendar_event_id' => $event->id]);
    }

    protected function assertSlotAvailable(
        int $organizationId,
        int $lawyerId,
        Carbon $startsAt,
        Carbon $endsAt,
    ): void {
        if ($this->slotOverlapsBooking($organizationId, $lawyerId, $startsAt, $endsAt)) {
            throw ValidationException::withMessages([
                'starts_at' => ['This time slot is no longer available.'],
            ]);
        }
    }

    protected function slotOverlapsBooking(
        int $organizationId,
        int $lawyerId,
        Carbon $startsAt,
        Carbon $endsAt,
    ): bool {
        return Appointment::query()
            ->where('organization_id', $organizationId)
            ->where('user_id', $lawyerId)
            ->whereIn('status', ['pending', 'confirmed'])
            ->where('starts_at', '<', $endsAt)
            ->where('ends_at', '>', $startsAt)
            ->exists();
    }

    protected function resolvePaymentStatus(string $consultationType, ?float $fee, string $status): string
    {
        if ($consultationType !== 'paid_consultation' || ! $fee || $fee <= 0) {
            return 'none';
        }

        return $status === 'confirmed' ? 'paid' : 'pending';
    }

    protected function notifyBooked(Appointment $appointment, User $actor): void
    {
        if ($appointment->lawyer) {
            $this->notifier->notifyUser(
                $appointment->lawyer,
                'appointment_booked',
                'New appointment',
                $appointment->consultation_type.' on '.$appointment->starts_at?->format('M j, Y g:i A'),
                ['appointment_id' => $appointment->id, 'status' => $appointment->status],
                $actor
            );
        }

        if ($appointment->bookedBy && $appointment->bookedBy->isPortalClient()) {
            $staff = User::query()
                ->where('organization_id', $appointment->organization_id)
                ->where('id', '!=', $appointment->booked_by_user_id)
                ->whereHas('roles', fn ($q) => $q->whereIn('name', ['Firm Admin', 'Secretary', 'Partner']))
                ->limit(5)
                ->get();

            foreach ($staff as $user) {
                $this->notifier->notifyUser(
                    $user,
                    'portal_appointment_booked',
                    'Client booked appointment',
                    ($appointment->client?->name ?? 'Client').' — '.$appointment->starts_at?->format('M j g:i A'),
                    ['appointment_id' => $appointment->id],
                    $actor
                );
            }
        }
    }
}
