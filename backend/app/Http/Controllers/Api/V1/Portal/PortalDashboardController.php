<?php

namespace App\Http\Controllers\Api\V1\Portal;

use App\Http\Controllers\Controller;
use App\Http\Concerns\ResolvesPortalClient;
use App\Http\Resources\MessageThreadResource;
use App\Models\Appointment;
use App\Models\Invoice;
use App\Models\LegalDocument;
use App\Models\LegalMatter;
use App\Models\MessageThread;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PortalDashboardController extends Controller
{
    use ResolvesPortalClient;

    public function index(Request $request): JsonResponse
    {
        $client = $this->portalClientFor($request->user());

        $activeCases = LegalMatter::query()
            ->where('organization_id', $client->organization_id)
            ->where('client_id', $client->id)
            ->whereNotIn('status', ['closed', 'archived'])
            ->count();

        $recentInvoices = Invoice::query()
            ->with(['legalMatter:id,title,matter_number'])
            ->where('client_id', $client->id)
            ->whereNotIn('status', ['draft', 'cancelled'])
            ->latest('issue_date')
            ->limit(5)
            ->get(['id', 'invoice_number', 'status', 'total_amount', 'balance_due', 'issue_date', 'legal_matter_id']);

        $recentDocuments = LegalDocument::query()
            ->with(['legalMatter:id,title,matter_number'])
            ->where('organization_id', $client->organization_id)
            ->where('client_visible', true)
            ->whereNotIn('document_type', LegalDocument::ORGANIZATION_TYPES)
            ->whereHas('legalMatter', fn ($q) => $q->where('client_id', $client->id))
            ->latest()
            ->limit(5)
            ->get(['id', 'name', 'legal_matter_id', 'created_at']);

        $primaryCase = LegalMatter::query()
            ->where('organization_id', $client->organization_id)
            ->where('client_id', $client->id)
            ->whereNotIn('status', ['closed', 'archived'])
            ->latest('updated_at')
            ->first(['id', 'title', 'matter_number', 'status', 'stage', 'matter_stage']);

        $nextAppointment = Appointment::query()
            ->with(['lawyer:id,name', 'legalMatter:id,title,matter_number'])
            ->where('organization_id', $client->organization_id)
            ->where('client_id', $client->id)
            ->whereIn('status', ['pending', 'confirmed'])
            ->where('starts_at', '>=', now())
            ->orderBy('starts_at')
            ->first();

        $unpaidInvoice = Invoice::query()
            ->with(['legalMatter:id,title,matter_number'])
            ->where('client_id', $client->id)
            ->whereIn('status', ['sent', 'partial', 'overdue'])
            ->where('balance_due', '>', 0)
            ->orderByDesc('issue_date')
            ->first(['id', 'invoice_number', 'status', 'balance_due', 'issue_date', 'legal_matter_id']);

        $recentThreads = MessageThread::query()
            ->with(['legalMatter:id,title,matter_number', 'latestMessage.sender:id,name,client_id'])
            ->where('organization_id', $client->organization_id)
            ->where('client_id', $client->id)
            ->orderByDesc('last_message_at')
            ->limit(5)
            ->get();

        $unpaidBalance = (float) Invoice::query()
            ->where('client_id', $client->id)
            ->whereIn('status', ['sent', 'partial', 'overdue'])
            ->sum('balance_due');

        $pendingInvoices = Invoice::query()
            ->where('client_id', $client->id)
            ->whereIn('status', ['sent', 'partial', 'overdue'])
            ->where('balance_due', '>', 0)
            ->count();

        $recentDocumentCount = LegalDocument::query()
            ->where('organization_id', $client->organization_id)
            ->where('client_visible', true)
            ->whereNotIn('document_type', LegalDocument::ORGANIZATION_TYPES)
            ->whereHas('legalMatter', fn ($q) => $q->where('client_id', $client->id))
            ->where('created_at', '>=', now()->subDays(30))
            ->count();

        $upcomingAppointments = Appointment::query()
            ->with(['lawyer:id,name', 'legalMatter:id,title,matter_number'])
            ->where('organization_id', $client->organization_id)
            ->where('client_id', $client->id)
            ->whereIn('status', ['pending', 'confirmed'])
            ->where('starts_at', '>=', now())
            ->orderBy('starts_at')
            ->limit(5)
            ->get();

        $threadResources = MessageThreadResource::collection($recentThreads)->resolve();
        $unreadMessages = collect($threadResources)->sum('unread_count');

        $insights = $this->buildInsights($primaryCase, $nextAppointment, $unpaidInvoice, $activeCases);
        $activities = $this->buildActivities(
            $recentThreads,
            $recentInvoices,
            $recentDocuments,
            $upcomingAppointments,
        );

        return response()->json([
            'stats' => [
                'active_cases' => $activeCases,
                'unpaid_balance' => $unpaidBalance,
                'unread_messages' => $unreadMessages,
                'pending_invoices' => $pendingInvoices,
                'recent_documents' => $recentDocumentCount,
            ],
            'insights' => $insights,
            'recent_invoices' => $recentInvoices->map(fn (Invoice $invoice) => [
                'id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'status' => $invoice->status,
                'total_amount' => (float) $invoice->total_amount,
                'balance_due' => (float) $invoice->balance_due,
                'issue_date' => $invoice->issue_date?->toDateString(),
                'case' => $invoice->legalMatter ? [
                    'id' => $invoice->legalMatter->id,
                    'title' => $invoice->legalMatter->title,
                    'matter_number' => $invoice->legalMatter->matter_number,
                ] : null,
            ]),
            'recent_documents' => $recentDocuments->map(fn (LegalDocument $doc) => [
                'id' => $doc->id,
                'name' => $doc->name,
                'created_at' => $doc->created_at?->toIso8601String(),
                'case' => $doc->legalMatter ? [
                    'id' => $doc->legalMatter->id,
                    'title' => $doc->legalMatter->title,
                    'matter_number' => $doc->legalMatter->matter_number,
                ] : null,
            ]),
            'messages' => $threadResources,
            'upcoming_appointments' => $upcomingAppointments->map(fn (Appointment $appt) => [
                'id' => $appt->id,
                'consultation_type' => $appt->consultation_type,
                'status' => $appt->status,
                'starts_at' => $appt->starts_at?->toIso8601String(),
                'ends_at' => $appt->ends_at?->toIso8601String(),
                'location' => $appt->location,
                'online_meeting' => (bool) $appt->online_meeting,
                'lawyer' => $appt->lawyer ? [
                    'id' => $appt->lawyer->id,
                    'name' => $appt->lawyer->name,
                ] : null,
                'case' => $appt->legalMatter ? [
                    'id' => $appt->legalMatter->id,
                    'title' => $appt->legalMatter->title,
                    'matter_number' => $appt->legalMatter->matter_number,
                ] : null,
            ]),
            'activities' => $activities,
        ]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function buildActivities(
        $recentThreads,
        $recentInvoices,
        $recentDocuments,
        $upcomingAppointments,
    ): array {
        $activities = [];

        foreach ($recentThreads as $thread) {
            $activities[] = [
                'type' => 'message',
                'title' => $thread->subject,
                'description' => $thread->latestMessage?->body,
                'occurred_at' => $thread->last_message_at?->toIso8601String(),
                'meta' => [
                    'thread_id' => $thread->id,
                    'case' => $thread->legalMatter ? [
                        'id' => $thread->legalMatter->id,
                        'title' => $thread->legalMatter->title,
                    ] : null,
                ],
            ];
        }

        foreach ($recentInvoices as $invoice) {
            $activities[] = [
                'type' => 'invoice',
                'title' => "Invoice {$invoice->invoice_number}",
                'description' => 'Balance due: $'.number_format((float) $invoice->balance_due, 2),
                'occurred_at' => $invoice->issue_date?->startOfDay()->toIso8601String(),
                'meta' => [
                    'invoice_id' => $invoice->id,
                    'status' => $invoice->status,
                ],
            ];
        }

        foreach ($recentDocuments as $doc) {
            $activities[] = [
                'type' => 'document',
                'title' => $doc->name,
                'description' => 'New shared document',
                'occurred_at' => $doc->created_at?->toIso8601String(),
                'meta' => [
                    'document_id' => $doc->id,
                    'case' => $doc->legalMatter ? [
                        'id' => $doc->legalMatter->id,
                        'title' => $doc->legalMatter->title,
                    ] : null,
                ],
            ];
        }

        foreach ($upcomingAppointments as $appt) {
            $lawyerName = $appt->lawyer?->name ?? 'your legal team';
            $activities[] = [
                'type' => 'appointment',
                'title' => ucfirst(str_replace('_', ' ', $appt->consultation_type)),
                'description' => "With {$lawyerName}",
                'occurred_at' => $appt->starts_at?->toIso8601String(),
                'meta' => [
                    'appointment_id' => $appt->id,
                    'status' => $appt->status,
                ],
            ];
        }

        usort($activities, fn ($a, $b) => strcmp($b['occurred_at'] ?? '', $a['occurred_at'] ?? ''));

        return array_slice($activities, 0, 8);
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function buildInsights(
        ?LegalMatter $primaryCase,
        ?Appointment $nextAppointment,
        ?Invoice $unpaidInvoice,
        int $activeCases,
    ): array {
        $insights = [];

        if ($primaryCase) {
            $statusLabel = str_replace('_', ' ', $primaryCase->status ?? 'active');
            $stageLabel = $primaryCase->matter_stage
                ? str_replace('_', ' ', $primaryCase->matter_stage)
                : ($primaryCase->stage ? str_replace('_', ' ', $primaryCase->stage) : null);

            $insights[] = [
                'type' => 'case_status',
                'severity' => 'info',
                'title' => 'Case status',
                'message' => $activeCases > 1
                    ? "Your most recent matter \"{$primaryCase->title}\" is {$statusLabel}"
                        .($stageLabel ? " ({$stageLabel})" : '')
                        .". You have {$activeCases} active cases."
                    : "Your case \"{$primaryCase->title}\" is {$statusLabel}"
                        .($stageLabel ? " — currently in {$stageLabel} stage." : '.'),
                'case' => [
                    'id' => $primaryCase->id,
                    'title' => $primaryCase->title,
                    'matter_number' => $primaryCase->matter_number,
                    'status' => $primaryCase->status,
                ],
            ];
        } elseif ($activeCases === 0) {
            $insights[] = [
                'type' => 'case_status',
                'severity' => 'neutral',
                'title' => 'Case status',
                'message' => 'You have no active cases at the moment. Your firm will update you when a matter opens.',
            ];
        }

        if ($nextAppointment) {
            $startsAt = $nextAppointment->starts_at?->timezone(config('app.timezone'))->format('M j, Y g:i A');
            $lawyerName = $nextAppointment->lawyer?->name ?? 'your legal team';

            $insights[] = [
                'type' => 'next_appointment',
                'severity' => 'info',
                'title' => 'Next appointment',
                'message' => "Upcoming {$nextAppointment->consultation_type} with {$lawyerName} on {$startsAt}.",
                'appointment' => [
                    'id' => $nextAppointment->id,
                    'starts_at' => $nextAppointment->starts_at?->toIso8601String(),
                    'consultation_type' => $nextAppointment->consultation_type,
                    'status' => $nextAppointment->status,
                    'lawyer' => $nextAppointment->lawyer ? [
                        'id' => $nextAppointment->lawyer->id,
                        'name' => $nextAppointment->lawyer->name,
                    ] : null,
                    'case' => $nextAppointment->legalMatter ? [
                        'id' => $nextAppointment->legalMatter->id,
                        'title' => $nextAppointment->legalMatter->title,
                    ] : null,
                ],
            ];
        }

        if ($unpaidInvoice) {
            $balance = number_format((float) $unpaidInvoice->balance_due, 2);
            $insights[] = [
                'type' => 'unpaid_invoice',
                'severity' => 'warning',
                'title' => 'Outstanding invoice',
                'message' => "Invoice {$unpaidInvoice->invoice_number} has an unpaid balance of \${$balance}.",
                'invoice' => [
                    'id' => $unpaidInvoice->id,
                    'invoice_number' => $unpaidInvoice->invoice_number,
                    'status' => $unpaidInvoice->status,
                    'balance_due' => (float) $unpaidInvoice->balance_due,
                    'issue_date' => $unpaidInvoice->issue_date?->toDateString(),
                ],
            ];
        }

        return $insights;
    }
}
