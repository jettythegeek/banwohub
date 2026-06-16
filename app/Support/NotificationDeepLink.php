<?php

namespace App\Support;

class NotificationDeepLink
{
    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function enrich(string $type, array $data): array
    {
        $data['action_url'] = self::resolve($type, $data);

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    /**
     * @param  array<string, mixed>  $data
     */
    protected static function approvalLink(array $data): ?string
    {
        if (($data['subject_type'] ?? '') === 'invoice' && isset($data['invoice_id'])) {
            return "/invoices/{$data['invoice_id']}";
        }

        if (($data['subject_type'] ?? '') === 'legal_document' && isset($data['legal_matter_id'], $data['document_id'])) {
            return "/cases/{$data['legal_matter_id']}/documents?doc={$data['document_id']}";
        }

        return null;
    }

    public static function resolve(string $type, array $data): ?string
    {
        return match ($type) {
            'document_uploaded', 'portal_document_uploaded' => isset($data['legal_matter_id'], $data['document_id'])
                ? "/cases/{$data['legal_matter_id']}/documents?doc={$data['document_id']}"
                : null,
            'task_assigned' => isset($data['legal_matter_id'], $data['task_id'])
                ? "/cases/{$data['legal_matter_id']}/tasks?task={$data['task_id']}"
                : null,
            'calendar_event_created', 'calendar_reminder' => isset($data['legal_matter_id'], $data['calendar_event_id'])
                ? "/cases/{$data['legal_matter_id']}/calendar?event={$data['calendar_event_id']}"
                : null,
            'intake_submitted' => isset($data['intake_submission_id'])
                ? "/intake?tab=submissions&submission={$data['intake_submission_id']}"
                : null,
            'conflict_decision' => isset($data['conflict_check_id'])
                ? "/conflict-checks?check={$data['conflict_check_id']}"
                : null,
            'message_received' => isset($data['message_thread_id'])
                ? (isset($data['legal_matter_id'])
                    ? "/cases/{$data['legal_matter_id']}/messages?thread={$data['message_thread_id']}"
                    : "/messages?thread={$data['message_thread_id']}")
                : null,
            'portal_message_received' => isset($data['message_thread_id'])
                ? (isset($data['legal_matter_id'])
                    ? "/portal/cases/{$data['legal_matter_id']}?tab=messages&thread={$data['message_thread_id']}"
                    : "/portal/messages?thread={$data['message_thread_id']}")
                : null,
            'approval_request', 'approval_completed', 'approval_rejected', 'approval_changes_requested' => self::approvalLink($data),
            'signature_request_sent' => isset($data['signature_request_id'])
                ? "/portal/sign/{$data['signature_request_id']}"
                : null,
            'signature_completed', 'signature_declined' => isset($data['legal_matter_id'], $data['document_id'])
                ? "/cases/{$data['legal_matter_id']}/documents?doc={$data['document_id']}"
                : null,
            default => null,
        };
    }
}
