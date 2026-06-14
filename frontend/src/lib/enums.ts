/** Case pipeline stage (Lead → Open → Closed). */
export const CASE_STAGES = ['lead', 'open', 'closed'] as const

/** Detailed matter workflow stages within a case. */
export const MATTER_STAGES = [
  'intake',
  'conflict_check',
  'active',
  'awaiting_client',
  'in_court',
  'settlement',
  'on_hold',
  'closed',
  'archived',
] as const

/** Shared priority levels for cases and tasks. */
export const PRIORITIES = ['low', 'normal', 'high', 'urgent'] as const

export type CaseStage = (typeof CASE_STAGES)[number]
export type MatterStage = (typeof MATTER_STAGES)[number]
export type Priority = (typeof PRIORITIES)[number]

export const CALENDAR_EVENT_TYPES = [
  'court_hearing',
  'filing_deadline',
  'client_meeting',
  'internal_meeting',
  'appointment',
  'document_review_deadline',
  'payment_due_date',
  'limitation_deadline',
  'follow_up_reminder',
] as const

export const HEARING_TYPES = [
  'motion',
  'trial',
  'deposition',
  'arraignment',
  'status_conference',
  'sentencing',
  'mediation',
  'other',
] as const

export const HEARING_STATUSES = [
  'scheduled',
  'confirmed',
  'continued',
  'completed',
  'cancelled',
] as const

export const DEADLINE_SUBTYPES = ['deadline', 'court_date', 'meeting', 'reminder'] as const

export const CALENDAR_HUB_CATEGORIES = [
  'all',
  'appointments',
  'hearings',
  'deadlines',
] as const

export type CalendarHubCategory = (typeof CALENDAR_HUB_CATEGORIES)[number]

export function humanizeEnum(value?: string | null): string {
  if (!value) return '—'
  return value
    .replace(/_/g, ' ')
    .replace(/\b\w/g, (c) => c.toUpperCase())
}

export function categoryFromEventType(eventType: string): string {
  if (eventType === 'court_hearing') return 'hearing'
  if (eventType === 'appointment') return 'appointment'
  if (
    ['filing_deadline', 'document_review_deadline', 'payment_due_date', 'limitation_deadline', 'follow_up_reminder'].includes(
      eventType,
    )
  ) {
    return 'deadline'
  }
  return 'meeting'
}

export function calendarCategoryBadge(category: string): string {
  switch (category) {
    case 'appointment':
      return 'bw-badge-info'
    case 'hearing':
      return 'bw-badge-danger'
    case 'deadline':
      return 'bw-badge-warning'
    case 'meeting':
      return 'bw-badge-primary'
    default:
      return 'bw-badge-neutral'
  }
}

export const CLIENT_CONTACT_TYPES = ['primary', 'billing', 'opposing', 'witness'] as const

export const PAYMENT_METHODS = [
  'cash',
  'card',
  'upi',
  'bank_transfer',
  'cheque',
] as const

export type PaymentMethod = (typeof PAYMENT_METHODS)[number]

const PAYMENT_METHOD_LABELS: Record<PaymentMethod, string> = {
  cash: 'Cash',
  card: 'Card',
  upi: 'UPI',
  bank_transfer: 'Bank transfer',
  cheque: 'Cheque',
}

export function paymentMethodLabel(method: string): string {
  return PAYMENT_METHOD_LABELS[method as PaymentMethod] ?? method.replace(/_/g, ' ')
}

const CLIENT_CONTACT_TYPE_LABELS: Record<(typeof CLIENT_CONTACT_TYPES)[number], string> = {
  primary: 'Primary',
  billing: 'Billing',
  opposing: 'Opposing party',
  witness: 'Witness',
}

export function clientContactTypeLabel(type: string): string {
  return CLIENT_CONTACT_TYPE_LABELS[type as (typeof CLIENT_CONTACT_TYPES)[number]] ?? type
}

/** PRD legal document types (legal_documents.document_type). */
export const DOCUMENT_TYPES = [
  'engagement_letter',
  'pleading',
  'contract',
  'evidence',
  'correspondence',
  'court_filing',
  'discovery',
  'memo',
  'template',
  'case_note',
] as const

export type DocumentType = (typeof DOCUMENT_TYPES)[number]

const DOCUMENT_TYPE_LABELS: Record<DocumentType, string> = {
  engagement_letter: 'Engagement letter',
  pleading: 'Pleading',
  contract: 'Contract',
  evidence: 'Evidence',
  correspondence: 'Correspondence',
  court_filing: 'Court filing',
  discovery: 'Discovery',
  memo: 'Memo',
  template: 'Template',
  case_note: 'Case note',
}

export function documentTypeLabel(type?: string | null): string {
  if (!type) return 'Document'
  if (type === 'organization_template') return 'Org template'
  if (type === 'case_document') return 'Pleading'
  return DOCUMENT_TYPE_LABELS[type as DocumentType] ?? humanizeEnum(type)
}

export function documentTypeBadge(type?: string | null): string {
  switch (type) {
    case 'engagement_letter':
      return 'bw-badge-primary'
    case 'pleading':
    case 'case_document':
      return 'bw-badge-info'
    case 'contract':
      return 'bw-badge-success'
    case 'evidence':
      return 'bw-badge-warning'
    case 'correspondence':
      return 'bw-badge-neutral'
    case 'court_filing':
      return 'bw-badge-danger'
    case 'discovery':
      return 'bw-badge-info'
    case 'memo':
      return 'bw-badge-neutral'
    case 'template':
    case 'organization_template':
      return 'bw-badge-primary'
    case 'case_note':
      return 'bw-badge-success'
    default:
      return 'bw-badge-neutral'
  }
}
