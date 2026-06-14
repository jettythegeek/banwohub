export type StatusVariant =
  | 'success'
  | 'warning'
  | 'danger'
  | 'info'
  | 'neutral'
  | 'primary'
  | 'accent'

/**
 * Maps the many status / priority strings used across the API to a small set
 * of semantic badge variants. Unknown values fall back to neutral.
 */
const STATUS_VARIANTS: Record<string, StatusVariant> = {
  // shared
  active: 'success',
  open: 'info',
  lead: 'accent',
  new: 'info',
  closed: 'neutral',
  archived: 'neutral',
  inactive: 'neutral',
  prospect: 'accent',

  // case status
  in_court: 'warning',
  awaiting_client_response: 'warning',

  // task status
  not_started: 'neutral',
  in_progress: 'info',
  awaiting_review: 'warning',
  blocked: 'danger',
  completed: 'success',

  // conflict status
  in_review: 'info',
  potential_conflict_found: 'danger',
  cleared: 'success',
  rejected: 'danger',

  // intake status
  pending: 'warning',
  qualified: 'success',
  converted: 'success',
  draft: 'neutral',
  published: 'success',

  // priority
  low: 'neutral',
  normal: 'info',
  high: 'warning',
  urgent: 'danger',

  // invoice-style / generic
  paid: 'success',
  overdue: 'danger',
  unpaid: 'warning',
  sent: 'info',
  partial: 'warning',
  cancelled: 'danger',
  void: 'neutral',
  read: 'neutral',
  unread: 'info',

  // evidence
  uploaded: 'info',
  under_review: 'warning',
  marked_as_exhibit: 'accent',
  filed: 'success',

  // court filing
  ready_to_file: 'accent',
  accepted_by_court: 'success',
  rejected_by_court: 'danger',
  correction_required: 'danger',
  resubmitted: 'info',
  hearing_date_assigned: 'primary',

  // motion
  review: 'warning',
  filing_ready: 'accent',
}

export function statusVariant(status?: string | null): StatusVariant {
  if (!status) return 'neutral'
  return STATUS_VARIANTS[status.toLowerCase()] ?? 'neutral'
}

/** CSS custom property for kanban column dots and semantic badges (see tokens.css). */
const STATUS_DOT_VARS: Record<string, string> = {
  not_started: '--status-task-not-started',
  in_progress: '--status-task-in-progress',
  awaiting_review: '--status-task-awaiting-review',
  blocked: '--status-task-blocked',
  completed: '--status-task-completed',
  new: '--status-case-new',
  active: '--status-case-active',
  in_court: '--status-case-in-court',
  awaiting_client_response: '--status-case-awaiting-client',
  closed: '--status-case-closed',
  pending: '--status-intake-pending',
  in_review: '--status-intake-reviewing',
  rejected: '--status-intake-rejected',
  qualified: '--status-intake-qualified',
}

const INVOICE_STATUS_DOT_VARS: Record<string, string> = {
  paid: '--status-invoice-paid',
  pending: '--status-invoice-pending',
  overdue: '--status-invoice-overdue',
}

export function invoiceStatusDotVar(status?: string | null): string {
  if (!status) return '--status-neutral-fg'
  return INVOICE_STATUS_DOT_VARS[status.toLowerCase()] ?? '--status-neutral-fg'
}

export function statusDotVar(status?: string | null): string {
  if (!status) return '--status-neutral-fg'
  return STATUS_DOT_VARS[status.toLowerCase()] ?? '--status-neutral-fg'
}

/** Intake pipeline column keys (Work Status bar + kanban). */
const INTAKE_PIPELINE_DOT_VARS: Record<string, string> = {
  new: '--status-intake-pending',
  in_review: '--status-intake-reviewing',
  rejected: '--status-intake-rejected',
  qualified: '--status-intake-qualified',
  draft: '--status-neutral-fg',
  submitted: '--status-intake-pending',
  more_info_requested: '--status-intake-reviewing',
  approved: '--status-intake-qualified',
}

export function intakePipelineDotVar(columnOrStatus?: string | null): string {
  if (!columnOrStatus) return '--status-neutral-fg'
  return INTAKE_PIPELINE_DOT_VARS[columnOrStatus.toLowerCase()] ?? '--status-neutral-fg'
}

/** Conflict check status dots (firm-wide list). */
const CONFLICT_STATUS_DOT_VARS: Record<string, string> = {
  not_started: '--status-neutral-fg',
  in_review: '--status-intake-reviewing',
  potential_conflict_found: '--status-task-blocked',
  cleared: '--status-intake-qualified',
  rejected: '--status-intake-rejected',
}

export function conflictStatusDotVar(status?: string | null): string {
  if (!status) return '--status-neutral-fg'
  return CONFLICT_STATUS_DOT_VARS[status.toLowerCase()] ?? '--status-neutral-fg'
}

/** Evidence item status dots (firm-wide list). */
const EVIDENCE_STATUS_DOT_VARS: Record<string, string> = {
  uploaded: '--status-intake-pending',
  under_review: '--status-intake-reviewing',
  approved: '--status-intake-qualified',
  rejected: '--status-intake-rejected',
  marked_as_exhibit: '--status-task-awaiting-review',
  filed: '--status-invoice-paid',
  archived: '--status-case-closed',
}

export function evidenceStatusDotVar(status?: string | null): string {
  if (!status) return '--status-neutral-fg'
  return EVIDENCE_STATUS_DOT_VARS[status.toLowerCase()] ?? '--status-neutral-fg'
}

/** Court filing status dots (firm-wide list). */
const FILING_STATUS_DOT_VARS: Record<string, string> = {
  draft: '--status-neutral-fg',
  under_review: '--status-intake-reviewing',
  approved: '--status-intake-qualified',
  ready_to_file: '--status-task-awaiting-review',
  filed: '--status-invoice-pending',
  accepted_by_court: '--status-invoice-paid',
  rejected_by_court: '--status-intake-rejected',
  correction_required: '--status-task-blocked',
  resubmitted: '--status-intake-pending',
  hearing_date_assigned: '--color-accent-700',
  completed: '--status-invoice-paid',
}

export function filingStatusDotVar(status?: string | null): string {
  if (!status) return '--status-neutral-fg'
  return FILING_STATUS_DOT_VARS[status.toLowerCase()] ?? '--status-neutral-fg'
}

/** Legal motion status dots (firm-wide list). */
const MOTION_STATUS_DOT_VARS: Record<string, string> = {
  draft: '--status-neutral-fg',
  review: '--status-intake-reviewing',
  approved: '--status-intake-qualified',
  filing_ready: '--status-task-awaiting-review',
}

export function motionStatusDotVar(status?: string | null): string {
  if (!status) return '--status-neutral-fg'
  return MOTION_STATUS_DOT_VARS[status.toLowerCase()] ?? '--status-neutral-fg'
}

/** Brief status dots (firm-wide list). */
const BRIEF_STATUS_DOT_VARS: Record<string, string> = {
  draft: '--status-neutral-fg',
  review: '--status-intake-reviewing',
  final: '--status-intake-qualified',
}

export function briefStatusDotVar(status?: string | null): string {
  if (!status) return '--status-neutral-fg'
  return BRIEF_STATUS_DOT_VARS[status.toLowerCase()] ?? '--status-neutral-fg'
}

export function humanize(value?: string | null): string {
  if (!value) return '—'
  return value
    .replace(/_/g, ' ')
    .replace(/\s+/g, ' ')
    .trim()
    .replace(/\b\w/g, (char) => char.toUpperCase())
}

export function initialsOf(name?: string | null): string {
  const trimmed = (name ?? '').trim()
  if (!trimmed) return '—'
  const parts = trimmed.split(/\s+/)
  const first = parts[0]?.[0] ?? ''
  const last = parts.length > 1 ? parts[parts.length - 1][0] : ''
  return (first + last).toUpperCase()
}
