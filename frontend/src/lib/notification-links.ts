import type { AppNotification } from '@/types'

export function notificationLink(notification: AppNotification): string | null {
  const data = notification.data ?? {}
  if (typeof data.action_url === 'string' && data.action_url) {
    return data.action_url
  }

  if (notification.type === 'document_uploaded' && data.legal_matter_id) {
    return `/cases/${data.legal_matter_id}/documents`
  }
  if (notification.type === 'task_assigned' && data.legal_matter_id) {
    return `/cases/${data.legal_matter_id}/tasks`
  }
  if (
    (notification.type === 'calendar_event_created' ||
      notification.type === 'calendar_reminder') &&
    data.legal_matter_id
  ) {
    return `/cases/${data.legal_matter_id}/calendar`
  }
  if (notification.type === 'intake_submitted' && data.intake_submission_id) {
    return `/intake?tab=submissions&submission=${data.intake_submission_id}`
  }
  if (notification.type === 'conflict_decision' && data.conflict_check_id) {
    return `/conflict-checks?check=${data.conflict_check_id}`
  }

  return null
}
