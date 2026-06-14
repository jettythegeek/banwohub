<?php

namespace App\Console\Commands;

use App\Models\CalendarEvent;
use App\Support\InAppNotifier;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendCalendarReminders extends Command
{
    protected $signature = 'calendar:send-reminders';

    protected $description = 'Send in-app and email reminders for due calendar events';

    public function handle(InAppNotifier $notifier): int
    {
        CalendarEvent::query()
            ->whereNull('reminder_at')
            ->whereNotNull('reminder_days_before')
            ->whereNotNull('starts_at')
            ->where('starts_at', '>', now())
            ->each(function (CalendarEvent $event): void {
                $event->applyReminderDaysBefore();
                if ($event->isDirty('reminder_at')) {
                    $event->save();
                }
            });

        $events = CalendarEvent::query()
            ->with(['user', 'legalMatter'])
            ->whereNotNull('reminder_at')
            ->whereNull('reminder_sent_at')
            ->where('reminder_at', '<=', now())
            ->get();

        foreach ($events as $event) {
            if (! $event->user) {
                continue;
            }

            $data = [
                'calendar_event_id' => $event->id,
                'legal_matter_id' => $event->legal_matter_id,
                'starts_at' => $event->starts_at?->toIso8601String(),
            ];

            $notifier->notifyUser(
                $event->user,
                'calendar_reminder',
                'Upcoming calendar event',
                $event->title.' starts '.$event->starts_at?->diffForHumans(),
                $data
            );

            if ($event->user->email) {
                try {
                    Mail::raw(
                        "Reminder: {$event->title}\nStarts: {$event->starts_at}\nLocation: ".($event->location ?: 'TBD'),
                        fn ($message) => $message->to($event->user->email)->subject('Calendar reminder: '.$event->title)
                    );
                } catch (\Throwable $e) {
                    Log::info('calendar_reminder_email', [
                        'event_id' => $event->id,
                        'email' => $event->user->email,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            $event->update(['reminder_sent_at' => now()]);
            $this->info("Reminder sent for event #{$event->id}");
        }

        return self::SUCCESS;
    }
}
