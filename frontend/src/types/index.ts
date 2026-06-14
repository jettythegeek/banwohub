export type User = {
  id: number
  name: string
  email: string
  phone?: string | null
  job_title?: string | null
  is_active?: boolean
  two_factor_enabled?: boolean
  organization_id?: number
  client_id?: number
  roles?: string[]
  permissions?: string[]
}

export type Organization = {
  id: number
  name: string
  slug: string
  legal_name?: string | null
  email?: string | null
  phone?: string | null
  address?: string | null
  practice_areas: string[]
  case_types: string[]
  jurisdictions: string[]
  settings?: Record<string, unknown>
}

export type ClientPortalStatus = {
  has_account: boolean
  login_email?: string | null
  is_active?: boolean | null
}

export type Client = {
  id: number
  client_number?: string | null
  type: string
  name: string
  email?: string | null
  phone?: string | null
  company_name?: string | null
  address?: string | null
  status: string
  notes?: string | null
  portal?: ClientPortalStatus
  legal_matters_count?: number
  open_legal_matters_count?: number
  invoices_count?: number
  contacts_count?: number
  communication_logs_count?: number
  legal_matters?: {
    id: number
    title: string
    status: string
    matter_number?: string | null
    matter_stage?: string | null
    created_at?: string
  }[]
  created_at?: string
  updated_at?: string
}

export type ClientContactType = 'primary' | 'billing' | 'opposing' | 'witness'

export type ClientContact = {
  id: number
  client_id: number
  type: ClientContactType
  name: string
  email?: string | null
  phone?: string | null
  title?: string | null
  created_at?: string
  updated_at?: string
}

export type ServiceItem = {
  id: number
  name: string
  description?: string | null
  default_rate: number
  is_active?: boolean
  created_at?: string
  updated_at?: string
}

export type LegalMatter = {
  id: number
  title: string
  matter_number?: string | null
  practice_area?: string | null
  case_type?: string | null
  court_jurisdiction?: string | null
  status: string
  stage?: string
  matter_stage?: string
  priority: string
  opened_at?: string | null
  expected_close_at?: string | null
  description?: string | null
  billing_type?: 'hourly' | 'fixed' | 'retainer'
  billing_rate?: number | null
  fixed_fee_amount?: number | null
  retainer_minimum_amount?: number | null
  trust_balance?: number | null
  tags?: string[]
  client?: Client | { id: number; name: string }
  lead_lawyer?: { id: number; name: string } | null
  parties?: { id: number; name: string; party_type: string }[]
  assigned_staff?: { id: number; name: string; role: string }[]
  timeline?: TimelineEvent[]
  created_at?: string
  updated_at?: string
}

export type TimelineEvent = {
  id: number
  description: string
  event?: string | null
  properties?: Record<string, unknown>
  created_at?: string
}

export type CaseNote = {
  id: number
  legal_matter_id?: number
  title?: string | null
  body: string
  note_type: string
  visibility: string
  author?: { id: number; name: string } | null
  created_at?: string
  updated_at?: string
}

export type TaskChecklistItem = {
  id: string
  label: string
  done: boolean
}

export type TaskAttachment = {
  id: number
  legal_task_id?: number
  name: string
  size: number
  mime_type?: string | null
  uploader?: { id: number; name: string } | null
  created_at?: string
}

export type TaskComment = {
  id: number
  legal_task_id?: number
  body: string
  user?: { id: number; name: string } | null
  created_at?: string
}

export type CaseTask = {
  id: number
  legal_matter_id?: number
  title: string
  description?: string | null
  status: string
  priority: string
  due_at?: string | null
  assignee_id?: number | null
  assignee?: { id: number; name: string } | null
  case?: { id: number; title: string; matter_number?: string | null }
  checklist?: TaskChecklistItem[]
  attachments?: TaskAttachment[]
  comments?: TaskComment[]
  attachments_count?: number
  comments_count?: number
  completed_at?: string | null
  created_at?: string
  updated_at?: string
}

export type CaseCalendarEvent = {
  id: number
  legal_matter_id?: number
  user_id?: number
  title: string
  description?: string | null
  event_type: string
  category?: string
  hearing_type?: string | null
  hearing_status?: string | null
  deadline_subtype?: string | null
  starts_at: string
  ends_at?: string | null
  location?: string | null
  court_name?: string | null
  court_room?: string | null
  judge_name?: string | null
  reminder_at?: string | null
  reminder_days_before?: number | null
  user?: { id: number; name: string } | null
  case?: { id: number; title: string; matter_number?: string | null }
  created_at?: string
  updated_at?: string
}

export type CalendarHubItem = {
  id: string
  source: 'appointment' | 'calendar_event'
  source_id: number
  category: 'appointment' | 'hearing' | 'deadline' | 'meeting'
  event_type: string
  title: string
  description?: string | null
  starts_at: string
  ends_at?: string | null
  location?: string | null
  status?: string | null
  consultation_type?: string | null
  hearing_type?: string | null
  hearing_status?: string | null
  deadline_subtype?: string | null
  court_name?: string | null
  court_room?: string | null
  judge_name?: string | null
  reminder_at?: string | null
  reminder_days_before?: number | null
  user?: { id: number; name: string } | null
  client?: { id: number; name: string } | null
  case?: { id: number; title: string; matter_number?: string | null } | null
}

export type CalendarHubResponse = {
  data: CalendarHubItem[]
  meta: {
    from: string
    to: string
    category: string
    count: number
    deadline_board: CalendarHubItem[]
    hearing_types: string[]
    hearing_statuses: string[]
    deadline_subtypes: string[]
  }
}

export type DocumentFolder = {
  id: number
  legal_matter_id: number
  parent_id?: number | null
  name: string
  documents_count?: number
  children?: DocumentFolder[]
  created_at?: string
  updated_at?: string
}

export type CaseDocument = {
  id: number
  legal_matter_id?: number
  name: string
  original_filename?: string | null
  document_type?: string | null
  category?: string | null
  description?: string | null
  content_html?: string | null
  parent_template_id?: number | null
  parent_template?: { id: number; name: string } | null
  mime_type?: string | null
  size?: number | null
  version?: number | string | null
  client_visible?: boolean
  uploaded_by_client?: boolean
  portal_reviewed_at?: string | null
  portal_pending_review?: boolean
  ai_generated?: boolean
  ai_review_status?: string | null
  ai_governance_log_id?: number | null
  ai_approved_at?: string | null
  ai_approved_by?: { id: number; name: string } | null
  requires_approval?: boolean
  document_folder_id?: number | null
  document_folder?: { id: number; name: string } | null
  is_checked_out?: boolean
  checked_out_at?: string | null
  checked_out_by?: { id: number; name: string } | null
  download_url?: string
  case?: { id: number; title: string; matter_number?: string | null }
  uploaded_by?: { id: number; name: string } | null
  created_at?: string
  updated_at?: string
}

export type DocumentVersion = {
  id: number
  document_id: number
  version_number: number
  change_summary?: string | null
  source?: 'human' | 'ai' | 'system' | null
  content_html?: string | null
  created_by?: { id: number; name: string } | null
  created_at?: string
}

export type DocumentClause = {
  id: number
  organization_id: number
  title: string
  category: string
  body_html: string
  tags?: string[]
  created_by?: { id: number; name: string } | null
  created_at?: string
  updated_at?: string
}

export type DocumentVersionCompare = {
  from: DocumentVersion
  to: DocumentVersion
}

export type OnlyOfficeEditorConfig = {
  configured: boolean
  available: boolean
  reason?: string
  editor_url?: string
  document_server_url?: string
  config?: Record<string, unknown>
}

export type SearchResultItem = {
  type: 'case' | 'client' | 'document' | 'note' | 'message'
  id: number
  title: string
  subtitle?: string | null
  status?: string | null
  url: string
  legal_matter_id?: number | null
  document_type?: string | null
  note_type?: string | null
  visibility?: string | null
  message_thread_id?: number | null
  client_id?: number | null
}

export type SearchSection = {
  key: string
  label: string
  count: number
}

export type SearchResponse = {
  query: string
  results: {
    cases: SearchResultItem[]
    clients: SearchResultItem[]
    documents: SearchResultItem[]
    notes: SearchResultItem[]
    messages: SearchResultItem[]
  }
  sections?: SearchSection[]
  total: number
}

export type CaseActivity = {
  id: number
  description: string
  event?: string | null
  log_name?: string | null
  properties?: Record<string, unknown>
  actor?: { id: number; name: string } | null
  created_at?: string
}

export type AuditLog = {
  id: number
  action: string
  event?: string | null
  module?: string | null
  subject_type?: string | null
  subject_id?: number | null
  user?: { id: number; name: string; email?: string | null } | null
  ip_address?: string | null
  previous_value?: unknown
  new_value?: unknown
  properties?: Record<string, unknown>
  created_at?: string
}

export type AuditLogFilters = {
  page?: number
  per_page?: number
  user_id?: number
  subject_type?: string
  action?: string
  from_date?: string
  to_date?: string
}

export type CaseDocumentTemplate = {
  id: number
  name: string
  description?: string | null
  template_type?: string | null
  updated_at?: string
}

export type ConflictCheck = {
  id: number
  legal_matter_id?: number | null
  intake_submission_id?: number | null
  search_terms: string[]
  status: string
  decision?: string | null
  notes?: string | null
  matches?: Record<string, unknown[]>
  report?: Record<string, unknown>
  case?: { id: number; title: string; matter_number?: string | null }
  submission?: IntakeSubmission | null
  requester?: { id: number; name: string } | null
  reviewer?: { id: number; name: string } | null
  reviewed_at?: string | null
  created_at?: string
  updated_at?: string
}

export type IntakeField = {
  name: string
  label: string
  type: string
  required?: boolean
  options?: string[]
  conditions?: Record<string, unknown>
}

export type IntakeForm = {
  id: number
  name: string
  description?: string | null
  case_type?: string | null
  status: string
  fields: IntakeField[]
  submissions_count?: number
  created_by?: { id: number; name: string } | null
  created_at?: string
  updated_at?: string
}

export type IntakeSubmission = {
  id: number
  intake_form_id: number
  submitter_name?: string | null
  submitter_email?: string | null
  submitter_phone?: string | null
  status: string
  data: Record<string, unknown>
  review_notes?: string | null
  submitted_at?: string | null
  reviewed_at?: string | null
  form?: IntakeForm | null
  reviewer?: { id: number; name: string } | null
  converted_client?: { id: number; name: string } | null
  converted_case?: { id: number; title: string; matter_number?: string | null } | null
  created_at?: string
  updated_at?: string
}

export type CaseExpense = {
  id: number
  legal_matter_id: number
  user_id?: number
  invoice_id?: number | null
  category?: string | null
  description: string
  amount: number
  expense_date: string
  billable: boolean
  status: string
  user?: { id: number; name: string } | null
  created_at?: string
  updated_at?: string
}

export type CaseExpenseSummary = {
  expense_count: number
  total_amount: number
  billable_amount: number
}

export type TimeEntry = {
  id: number
  legal_matter_id?: number | null
  legal_task_id?: number | null
  user_id?: number
  description?: string | null
  started_at?: string | null
  ended_at?: string | null
  duration_minutes: number
  duration_hours: number
  billable: boolean
  rate?: number | null
  amount?: number | null
  status: string
  is_running: boolean
  approved_at?: string | null
  case?: { id: number; title: string; matter_number?: string | null } | null
  task?: { id: number; title: string } | null
  user?: { id: number; name: string; email?: string } | null
  approver?: { id: number; name: string } | null
  created_at?: string
  updated_at?: string
}

export type TimeEntrySummary = {
  total_minutes: number
  total_hours: number
  billable_minutes: number
  billable_hours: number
  non_billable_minutes: number
  billable_amount: number
  entry_count: number
}

export type InvoiceLineItem = {
  id?: number
  time_entry_id?: number | null
  line_type: string
  description: string
  quantity: number
  unit_price: number
  amount: number
  sort_order?: number
  time_entry?: {
    id: number
    description?: string | null
    duration_minutes: number
    rate?: number | null
  } | null
}

export type PaymentGatewayStatus = {
  enabled: boolean
  message: string | null
}

export type PaymentGateways = {
  stripe: PaymentGatewayStatus
  paypal: PaymentGatewayStatus
}

export type Invoice = {
  id: number
  client_id: number
  legal_matter_id?: number | null
  invoice_number: string
  status: string
  issue_date: string
  due_date?: string | null
  subtotal: number
  tax_rate: number
  tax_amount: number
  discount_amount: number
  total_amount: number
  amount_paid: number
  balance_due: number
  currency: string
  notes?: string | null
  payment_notes?: string | null
  last_payment_method?: string | null
  sent_at?: string | null
  paid_at?: string | null
  requires_approval?: boolean
  client?: { id: number; name: string; email?: string | null } | null
  case?: { id: number; title: string; matter_number?: string | null } | null
  creator?: { id: number; name: string } | null
  line_items?: InvoiceLineItem[]
  payment_gateways?: PaymentGateways
  created_at?: string
  updated_at?: string
}

export type InvoiceSummary = {
  invoice_count: number
  total_billed: number
  outstanding_balance: number
  unpaid_count: number
}

export type InvoiceAgingBucket = {
  label: string
  min_days: number
  max_days: number | null
  amount: number
  count: number
}

export type InvoiceAgingSummary = {
  total_outstanding: number
  invoice_count: number
  buckets: InvoiceAgingBucket[]
}

export type TrustLedgerEntry = {
  id: number
  legal_matter_id?: number
  entry_type: 'deposit' | 'disbursement' | 'adjustment' | string
  amount: number
  description?: string | null
  occurred_at?: string | null
  created_at?: string | null
  updated_at?: string | null
}

export type TrustLedgerSummary = {
  entry_count: number
  balance: number
  retainer_minimum: number | null
}

export type CaseOverviewMetrics = {
  unbilled_revenue: number
  case_value: number
  case_value_source: 'fixed_fee' | 'estimated'
  trust_balance: number | null
  retainer_minimum: number | null
  trust_status: 'not_applicable' | 'empty' | 'active'
  trust_ledger: TrustLedgerEntry[]
  deadlines_count: number
  next_deadline: {
    id: number
    title: string
    starts_at: string
    event_type: string
  } | null
  billing_trend: { month: string; label: string; amount: number }[]
}

export type MergeFieldDefinition = {
  key: string
  label: string
  group: string
  example?: string
}

export type ReportStatusCount = {
  status: string
  count: number
}

export type ReportLawyerTime = {
  user_id: number
  name: string
  billable_minutes: number
  billable_hours: number
  billable_amount: number
  entry_count: number
}

export type ReportSummary = {
  filters: { from_date: string | null; to_date: string | null }
  cases: { total: number; by_status: ReportStatusCount[] }
  revenue: {
    total_billed: number
    total_paid: number
    paid_invoice_count: number
    paid_invoice_total: number
    unpaid_total: number
    unpaid_invoice_count: number
  }
  time_by_lawyer: ReportLawyerTime[]
}

export type AppNotification = {
  id: number
  type: string
  title: string
  body?: string | null
  data?: Record<string, unknown>
  read_at?: string | null
  actor?: { id: number; name: string } | null
  created_at?: string
  updated_at?: string
}

export type DashboardTask = {
  id: number
  title: string
  status: string
  priority: string
  due_at: string | null
  is_overdue: boolean
  case: { id: number; title: string; matter_number: string } | null
}

export type DashboardChartCount = {
  status: string
  count: number
}

export type DashboardInvoiceStatus = {
  status: string
  count: number
  amount: number
}

export type DashboardRevenuePoint = {
  month: string
  label: string
  amount: number
}

export type DashboardActivityPoint = {
  month: string
  label: string
  count: number
}

export type DashboardCharts = {
  cases_by_status: DashboardChartCount[]
  filings_by_status: DashboardChartCount[]
  motions_by_status: DashboardChartCount[]
  invoices_by_status: DashboardInvoiceStatus[]
  task_workload: DashboardChartCount[]
  revenue_trend: DashboardRevenuePoint[]
  case_activity_trend: DashboardActivityPoint[]
  filing_activity_trend: DashboardActivityPoint[]
}

export type DashboardLegalOps = {
  cases: {
    total: number
    active: number
    new: number
    assigned: number
  }
  filings: {
    total: number
    pending_court: number
    corrections: number
    completed: number
  }
  motions: {
    total: number
    draft: number
    review: number
    filing_ready: number
  }
  projects: {
    open_matters: number
    open_tasks: number
    overdue_tasks: number
    pending_milestones: number
  }
}

export type DashboardDocumentAttention = {
  id: number
  name: string
  document_type?: string | null
  reason: 'client_upload' | 'ai_review'
  created_at?: string | null
  case?: { id: number; title: string; matter_number?: string | null } | null
}

export type DashboardPendingApproval = {
  id: number
  subject_type: string
  subject_id: number
  status: string
  submitted_at?: string | null
  submitter?: { id: number; name: string } | null
}

export type DashboardType = 'admin' | 'lawyer' | 'paralegal'

export type DashboardData = {
  dashboard_type: DashboardType
  stats: {
    active_cases: number
    total_clients: number
    assigned_cases: number
    open_tasks: number
    overdue_tasks: number
    unread_messages?: number
  }
  legal_ops: DashboardLegalOps
  charts: DashboardCharts
  recent_clients: { id: number; name: string; status: string; created_at: string }[]
  recent_cases: {
    id: number
    title: string
    status: string
    client_id: number
    created_at: string
    client?: { id: number; name: string } | null
  }[]
  my_tasks: DashboardTask[]
  messages_preview?: MessageThread[]
  pending_approvals?: DashboardPendingApproval[]
  documents_attention?: DashboardDocumentAttention[]
}

export type PaginatedMeta = {
  current_page: number
  last_page: number
  per_page: number
  total: number
}

export type PaginatedResponse<T> = {
  data: T[]
  meta: PaginatedMeta
}

export type PortalUser = {
  id: number
  name: string
  email: string
  phone?: string | null
  client_id?: number | null
  roles?: string[]
  permissions?: string[]
  client?: { id: number; name: string; email?: string | null } | null
  organization?: Organization
}

export type PortalDocument = {
  id: number
  legal_matter_id?: number
  name: string
  category?: string | null
  description?: string | null
  original_filename?: string | null
  mime_type?: string | null
  size?: number | null
  client_visible?: boolean
  uploaded_by_client?: boolean
  portal_reviewed_at?: string | null
  portal_pending_review?: boolean
  portal_status?: 'staff' | 'pending' | 'shared' | 'internal'
  download_url?: string
  case?: { id: number; title: string; matter_number?: string | null } | null
  created_at?: string
  updated_at?: string
}

export type PortalInsight = {
  type: 'case_status' | 'next_appointment' | 'unpaid_invoice'
  severity: 'info' | 'warning' | 'neutral'
  title: string
  message: string
  case?: { id: number; title: string; matter_number?: string | null; status?: string }
  appointment?: {
    id: number
    starts_at?: string | null
    consultation_type?: string
    status?: string
    lawyer?: { id: number; name: string } | null
    case?: { id: number; title: string } | null
  }
  invoice?: {
    id: number
    invoice_number: string
    status: string
    balance_due: number
    issue_date?: string | null
  }
}

export type PortalDashboardActivity = {
  type: 'message' | 'invoice' | 'document' | 'appointment'
  title: string
  description?: string | null
  occurred_at?: string | null
  meta?: Record<string, unknown>
}

export type PortalDashboardAppointment = {
  id: number
  consultation_type: string
  status: string
  starts_at: string
  ends_at?: string | null
  location?: string | null
  online_meeting?: boolean
  lawyer?: { id: number; name: string } | null
  case?: { id: number; title: string; matter_number?: string | null } | null
}

export type PortalDashboardData = {
  stats: {
    active_cases: number
    unpaid_balance: number
    unread_messages?: number
    pending_invoices?: number
    recent_documents?: number
  }
  insights?: PortalInsight[]
  recent_invoices: Array<{
    id: number
    invoice_number: string
    status: string
    total_amount: number
    balance_due: number
    issue_date?: string | null
    case?: { id: number; title: string; matter_number?: string | null } | null
  }>
  recent_documents: Array<{
    id: number
    name: string
    created_at?: string | null
    case?: { id: number; title: string; matter_number?: string | null } | null
  }>
  messages: MessageThread[]
  upcoming_appointments?: PortalDashboardAppointment[]
  activities?: PortalDashboardActivity[]
}

export type Message = {
  id: number
  message_thread_id: number
  body: string
  read_at?: string | null
  attachments?: Array<{ name: string; url?: string | null }>
  sender?: { id: number; name: string; is_client?: boolean } | null
  created_at?: string | null
}

export type LawyerAvailabilitySlot = {
  id: number
  user_id: number
  day_of_week: number
  start_time: string
  end_time: string
  slot_duration_minutes: number
  consultation_types: string[]
  consultation_fee?: number | null
  location?: string | null
  online_meeting: boolean
  is_active: boolean
  user?: { id: number; name: string } | null
}

export type AvailableSlot = {
  starts_at: string
  ends_at: string
  consultation_types: string[]
  fee?: number | null
  location?: string | null
  online_meeting: boolean
}

export type Appointment = {
  id: number
  calendar_event_id?: number | null
  client_id?: number | null
  user_id: number
  legal_matter_id?: number | null
  booked_by_user_id?: number | null
  consultation_type: string
  status: string
  starts_at: string
  ends_at: string
  location?: string | null
  online_meeting: boolean
  fee?: number | null
  payment_status: string
  notes?: string | null
  lawyer?: { id: number; name: string } | null
  client?: { id: number; name: string } | null
  case?: { id: number; title: string; matter_number?: string | null } | null
  created_at?: string | null
  updated_at?: string | null
}

export type PortalLawyer = {
  id: number
  name: string
  job_title?: string | null
}

export type MessageThread = {
  id: number
  subject: string
  client_id: number
  legal_matter_id?: number | null
  last_message_at?: string | null
  unread_count?: number
  client?: { id: number; name: string; email?: string | null } | null
  case?: { id: number; title: string; matter_number?: string | null } | null
  creator?: { id: number; name: string } | null
  latest_message?: Message | null
  messages?: Message[]
  created_at?: string | null
  updated_at?: string | null
}

export type CommunicationLogChannel = 'in_app' | 'email' | 'phone' | 'meeting' | 'note'

export type CommunicationLog = {
  id: number
  client_id: number
  legal_matter_id?: number | null
  message_thread_id?: number | null
  channel: CommunicationLogChannel
  subject?: string | null
  body?: string | null
  occurred_at?: string | null
  client_feedback?: string | null
  satisfaction_score?: number | null
  case?: { id: number; title: string; matter_number?: string | null } | null
  logged_by?: { id: number; name: string } | null
  created_at?: string | null
  updated_at?: string | null
}

export type ApprovalSubjectType = 'legal_document' | 'invoice'

export type ApprovalComment = {
  user_id: number
  user_name: string
  body: string
  action?: string
  created_at: string
}

export type ApprovalRequest = {
  id: number
  organization_id: number
  subject_type: ApprovalSubjectType
  subject_id: number
  status: string
  requires_approval: boolean
  notes?: string | null
  comments?: ApprovalComment[]
  submitted_at?: string | null
  reviewed_at?: string | null
  submitter?: { id: number; name: string } | null
  reviewer?: { id: number; name: string } | null
  created_at?: string | null
  updated_at?: string | null
}

export type SignatureFieldType = 'signature' | 'date' | 'text' | 'initials'

export type SignatureField = {
  id: string
  type: SignatureFieldType
  label: string
  required?: boolean
}

export type SignatureAuditEvent = {
  action: string
  at: string
  [key: string]: unknown
}

export type SignatureRequest = {
  id: number
  organization_id: number
  document_id: number
  legal_matter_id: number
  client_id: number
  status: 'pending' | 'signed' | 'declined'
  fields: SignatureField[]
  message?: string | null
  signed_at?: string | null
  signer_ip?: string | null
  audit?: { events: SignatureAuditEvent[] } | null
  signed_document_id?: number | null
  document?: {
    id: number
    name: string
    content_html?: string | null
    version?: number
    category?: string | null
  } | null
  legal_matter?: { id: number; title: string; matter_number?: string | null } | null
  client?: { id: number; name: string; email?: string | null } | null
  sender?: { id: number; name: string } | null
  signed_document?: { id: number; name: string; original_filename?: string | null } | null
  created_at?: string | null
  updated_at?: string | null
}

export type AiProviderName = 'openai' | 'anthropic' | 'google' | 'deepseek'

export type AiProviderConfig = {
  provider: AiProviderName
  label: string
  description: string
  default_model: string
  available_models: string[]
  is_enabled: boolean
  is_active: boolean
  model?: string | null
  api_key_set: boolean
  api_key_masked?: string | null
  last_test_success_at?: string | null
  can_select_model: boolean
  can_enable: boolean
  settings?: Record<string, unknown>
}

export type AiProvidersSettingsResponse = {
  active_provider: AiProviderName | null
  providers: AiProviderConfig[]
}

export type AiGovernanceSettings = {
  disclaimer: string
  label: string
  review_statuses: string[]
  requires_lawyer_approval: boolean
}

export type AiGovernanceLog = {
  id: number
  action_type: string
  bot_context?: string | null
  legal_matter_id?: number | null
  legal_document_id?: number | null
  output_id?: string | null
  model?: string | null
  status: string
  output_preview?: string | null
  prompt_context?: Record<string, unknown> | null
  metadata?: Record<string, unknown> | null
  user?: { id: number; name: string } | null
  created_at?: string | null
}

export type AiChatResponse = {
  output_id: string
  content: string
  labeled: boolean
  label: string
  disclaimer: string
  requires_review: boolean
  model?: string | null
  governance_log_id?: number | null
}

export type PublicChatResponse = AiChatResponse & {
  session_id?: string
  lead_captured?: boolean
}

export type AiSuggestedAuthority = {
  type: string
  citation: string
  relevance?: string
  verified?: boolean
  rank?: number
  confidence?: number
  jurisdiction_relevance?: string
  precedential_value?: string
  verification_status?: string
  negative_treatment_alert?: boolean
}

export type AiBriefArgument = {
  rank: number
  title: string
  theory: string
  strength?: string
  weaknesses?: string[]
  authorities?: string[]
}

export type AiOpposingArgument = {
  argument: string
  likelihood?: string
  authority_hooks?: string[]
}

export type AiRebuttal = {
  opposing_argument: string
  rebuttal: string
  response_section?: string
}

export type AiCaseResult = {
  citation: string
  court?: string
  holding?: string
  facts?: string
  principles?: string[]
  quotation?: string
  treatment?: string
  similarity_score?: number
  verification_status?: string
}

export type AiResearchValidation = {
  source_authority?: string
  confidence_rating?: number
  jurisdiction_relevance?: string
  negative_treatment_alerts?: string[]
  verification_status?: string
}

export type AiMemoSection = {
  title: string
  content: string
}

export type AiStrategyPlan = {
  claims?: string[]
  defenses?: string[]
  procedural_options?: string[]
  jurisdictional_concerns?: string[]
  evidentiary_support?: string[]
}

export type AiStructuredResponse = AiChatResponse & {
  verification_warning?: string
  authorities?: AiSuggestedAuthority[]
  ranked_authorities?: AiSuggestedAuthority[]
  arguments?: AiBriefArgument[]
  opposing_arguments?: AiOpposingArgument[]
  rebuttals?: AiRebuttal[]
  sections?: Array<{ key: string; title: string; status?: string }>
  cases?: AiCaseResult[]
  memo_sections?: AiMemoSection[]
  strategy?: AiStrategyPlan
  statute_analysis?: Array<{ provision?: string; plain_english?: string; comparison?: string }>
  validation?: AiResearchValidation
  formatting_notes?: Array<{ note: string }>
}

export type AiResearchAuthoritiesResponse = AiChatResponse & {
  verification_warning?: string
  authorities?: AiSuggestedAuthority[]
}

export type AiContractIssue = {
  severity: 'high' | 'medium' | 'low' | string
  title: string
  description: string
  clause_ref?: string | null
}

export type AiContractReviewResponse = AiChatResponse & {
  issues?: AiContractIssue[]
}

export type AiLetterDraft = {
  type: string
  title: string
  content_html: string
}

export type AiLetterPackResponse = AiChatResponse & {
  letters?: AiLetterDraft[]
}

export type AiChatMessage = {
  id: string
  role: 'user' | 'assistant'
  content: string
  label?: string
  disclaimer?: string
  requires_review?: boolean
  output_id?: string
  created_at: string
}

export type CourtFormField = {
  key: string
  label: string
  type: string
  required?: boolean
}

export type CourtFormTemplate = {
  id: number
  organization_id?: number | null
  name: string
  jurisdiction: string
  court?: string | null
  case_type?: string | null
  filing_type?: string | null
  fields: CourtFormField[]
  guidance?: {
    steps?: string[]
    required_attachments?: string[]
    signature_required?: boolean
  } | null
  is_active: boolean
}

export type CourtFormInstance = {
  id: number
  legal_matter_id: number
  court_form_template_id: number
  court_filing_id?: number | null
  title: string
  field_values: Record<string, string | null>
  status: string
  template?: CourtFormTemplate | null
  legal_matter?: { id: number; title: string; matter_number?: string | null } | null
  court_filing?: CourtFiling | null
  created_at?: string
  updated_at?: string
}

export type CourtFiling = {
  id: number
  legal_matter_id: number
  court_form_instance_id?: number | null
  title: string
  court: string
  filing_date?: string | null
  filed_by?: number | null
  filing_method: string
  court_reference_number?: string | null
  document_ids: number[]
  status: string
  court_response?: string | null
  notes?: string | null
  correction_deadline?: string | null
  legal_matter?: { id: number; title: string; matter_number?: string | null } | null
  filed_by_user?: { id: number; name: string } | null
  court_form_instance?: CourtFormInstance | null
  created_at?: string
  updated_at?: string
}

export type EvidenceCustodyLog = {
  id: number
  evidence_item_id: number
  action: string
  notes?: string | null
  location?: string | null
  from_user_id?: number | null
  to_user_id?: number | null
  logged_by?: number | null
  logged_at?: string
  from_user?: { id: number; name: string } | null
  to_user?: { id: number; name: string } | null
  logger?: { id: number; name: string } | null
  created_at?: string
}

export type EvidenceItem = {
  id: number
  legal_matter_id: number
  title: string
  description?: string | null
  evidence_type: string
  source?: string | null
  date_obtained?: string | null
  relevance?: string | null
  exhibit_number?: string | null
  tags: string[]
  status: string
  original_filename?: string | null
  mime_type?: string | null
  size?: number
  has_file?: boolean
  uploaded_by?: number | null
  legal_matter?: { id: number; title: string; matter_number?: string | null } | null
  uploader?: { id: number; name: string } | null
  custody_logs?: EvidenceCustodyLog[]
  created_at?: string
  updated_at?: string
}

export type BriefCitation = {
  id: number
  legal_brief_id: number
  authority: string
  citation_text: string
  sort_order: number
  source_note?: string | null
  created_at?: string
  updated_at?: string
}

export type LegalBrief = {
  id: number
  legal_matter_id: number
  title: string
  brief_type?: string
  jurisdiction?: string | null
  court_type?: string
  cause_of_action?: string | null
  case_facts?: string | null
  statutes?: string | null
  desired_outcome?: string | null
  citation_style?: string
  content_html?: string | null
  status: string
  last_ai_governance_log_id?: number | null
  legal_matter?: { id: number; title: string; matter_number?: string | null } | null
  citations?: BriefCitation[]
  creator?: { id: number; name: string } | null
  created_at?: string
  updated_at?: string
}

export type ResearchProject = {
  id: number
  legal_matter_id?: number | null
  name: string
  description?: string | null
  case_theory?: string | null
  jurisdiction?: string | null
  practice_area?: string | null
  legal_matter?: { id: number; title: string; matter_number?: string | null } | null
  chat_messages_count?: number
  creator?: { id: number; name: string } | null
  created_at?: string
  updated_at?: string
}

export type ResearchChatMessage = {
  id: number
  research_project_id: number
  role: 'user' | 'assistant' | string
  content: string
  ai_governance_log_id?: number | null
  created_at?: string
}

export type MotionTemplate = {
  id: number
  slug: string
  name: string
  description?: string | null
  structure_html?: string | null
  required_sections?: string[]
  is_active?: boolean
}

export type LegalMotion = {
  id: number
  legal_matter_id: number
  motion_template_id?: number | null
  title: string
  motion_type?: string | null
  content_html?: string | null
  status: string
  court_filing_id?: number | null
  last_ai_governance_log_id?: number | null
  legal_matter?: { id: number; title: string; matter_number?: string | null } | null
  template?: MotionTemplate | null
  creator?: { id: number; name: string } | null
  created_at?: string
  updated_at?: string
}

export type EvidenceExhibitIndex = {
  items: Array<{
    id: number
    exhibit_number: string
    title: string
    description?: string | null
    evidence_type: string
    source?: string | null
    date_obtained?: string | null
    status: string
    original_filename?: string | null
  }>
  total: number
}

export type LegalResearchEntry = {
  id: number
  title: string
  citation?: string | null
  summary?: string | null
  jurisdiction?: string | null
  document_type: string
  tags?: string[]
  creator?: { id: number; name: string } | null
  created_at?: string
  updated_at?: string
}

export type ResearchFolder = {
  id: number
  legal_matter_id?: number | null
  name: string
  description?: string | null
  practice_area?: string | null
  legal_issue?: string | null
  items_count?: number
  legal_matter?: { id: number; title: string; matter_number?: string | null } | null
  creator?: { id: number; name: string } | null
  saved_items?: ResearchSavedItem[]
  created_at?: string
  updated_at?: string
}

export type ResearchSavedItem = {
  id: number
  research_folder_id: number
  legal_research_entry_id: number
  legal_matter_id?: number | null
  notes?: string | null
  entry?: LegalResearchEntry | null
  folder?: ResearchFolder | null
  saver?: { id: number; name: string } | null
  created_at?: string
  updated_at?: string
}

export type KnowledgeArticle = {
  id: number
  legal_matter_id?: number | null
  title: string
  content?: string | null
  excerpt?: string | null
  content_type: string
  category: string
  practice_area?: string | null
  tags?: string[]
  is_published?: boolean
  creator?: { id: number; name: string } | null
  legal_matter?: { id: number; title: string; matter_number?: string | null } | null
  created_at?: string
  updated_at?: string
}

export type EdiscoveryCollection = {
  id: number
  legal_matter_id: number
  name: string
  description?: string | null
  status: string
  documents_count?: number
  legal_matter?: { id: number; title: string; matter_number?: string | null } | null
  creator?: { id: number; name: string } | null
  created_at?: string
  updated_at?: string
}

export type EdiscoveryTag = {
  id: number
  legal_matter_id?: number | null
  name: string
  color?: string | null
  category: string
  creator?: { id: number; name: string } | null
  created_at?: string
  updated_at?: string
}

export type EdiscoveryReviewAssignment = {
  id: number
  ediscovery_document_id: number
  reviewer_id: number
  review_status: string
  notes?: string | null
  assigned_at?: string | null
  completed_at?: string | null
  reviewer?: { id: number; name: string } | null
  assigner?: { id: number; name: string } | null
  document?: { id: number; title: string; review_status: string } | null
  created_at?: string
  updated_at?: string
}

export type EdiscoveryDocument = {
  id: number
  legal_matter_id: number
  ediscovery_collection_id: number
  title: string
  notes?: string | null
  document_date?: string | null
  sender?: string | null
  recipient?: string | null
  file_type: string
  privilege: string
  relevance: string
  custom_tags: string[]
  review_status: string
  content_preview?: string | null
  original_filename?: string | null
  mime_type?: string | null
  size?: number
  has_file?: boolean
  uploaded_by?: number | null
  legal_matter?: { id: number; title: string; matter_number?: string | null } | null
  collection?: { id: number; name: string } | null
  uploader?: { id: number; name: string } | null
  review_assignments?: EdiscoveryReviewAssignment[]
  created_at?: string
  updated_at?: string
}

export type EdiscoveryReviewProgress = {
  legal_matter_id: number
  total_documents: number
  completion_rate: number
  by_review_status: Record<string, number>
  by_privilege: Record<string, number>
  by_relevance: Record<string, number>
  by_reviewer: Array<{
    reviewer_id: number
    reviewer_name: string
    assigned: number
    in_progress: number
    completed: number
    skipped: number
    total: number
  }>
}

export type LegalProjectMilestone = {
  id: number
  legal_matter_id: number
  title: string
  description?: string | null
  milestone_type: string
  status: string
  due_at?: string | null
  completed_at?: string | null
  assigned_to?: number | null
  sort_order?: number
  assignee?: { id: number; name: string } | null
  legal_matter?: { id: number; title: string; matter_number?: string | null } | null
  created_at?: string
  updated_at?: string
}

export type LegalProjectBudget = {
  id: number
  legal_matter_id: number
  category: string
  description: string
  budgeted_amount: number
  actual_amount: number
  variance?: number
  notes?: string | null
  legal_matter?: { id: number; title: string; matter_number?: string | null } | null
  created_at?: string
  updated_at?: string
}

export type LegalProjectWorkload = {
  totals: {
    open_matters: number
    open_tasks: number
    overdue_tasks: number
    pending_milestones: number
  }
  by_lawyer: Array<{
    user_id: number
    name: string
    open_matters: number
    open_tasks: number
    overdue_tasks: number
    matter_titles: string[]
  }>
}

export type TaskWorkloadItem = {
  id: number
  title: string
  status: string
  priority: string
  due_at?: string | null
  is_overdue?: boolean
  assignee?: { id: number; name: string } | null
  case?: { id: number; title: string; matter_number?: string | null } | null
}

export type TaskWorkloadBoard = {
  totals: {
    open_tasks: number
    overdue_tasks: number
  }
  board: Record<string, TaskWorkloadItem[]>
  by_assignee: Array<{
    user_id: number
    name: string
    open_tasks: number
    overdue_tasks: number
    tasks: TaskWorkloadItem[]
  }>
}

export type IntegrationSetting = {
  key: string
  name: string
  description: string
  status: 'configured' | 'disabled'
  env_keys: string[]
  oauth?: boolean
  connected?: boolean
  connect_path?: string
}

export type LegalAnalyticsDashboard = {
  disclaimer: string
  filters: { from_date?: string | null; to_date?: string | null }
  case_duration: { average_days: number; sample_size: number; median_days: number }
  outcomes: {
    total_matters: number
    open_count: number
    closed_count: number
    by_status: Array<{ status: string; count: number }>
  }
  case_type_performance: Array<{ case_type: string; count: number; closed_count: number }>
  workload: { open_tasks: number; overdue_tasks: number; pending_milestones: number }
}

export type LegalAnalyticsHint = {
  type: string
  severity: string
  title: string
  message: string
}

export type TrainingQuizQuestion = {
  question: string
  options: string[]
  correct_index?: number
}

export type TrainingCourse = {
  id: number
  title: string
  description?: string | null
  content?: string | null
  video_url?: string | null
  materials_url?: string | null
  cle_credits: number
  is_required: boolean
  is_published?: boolean
  passing_score?: number
  quiz_questions?: TrainingQuizQuestion[]
  quiz_question_count?: number
  enrollments_count?: number
  created_at?: string
  updated_at?: string
}

export type TrainingEnrollment = {
  id: number
  training_course_id: number
  user_id: number
  status: string
  quiz_score?: number | null
  cle_credits_earned?: number | null
  started_at?: string | null
  completed_at?: string | null
  course?: { id: number; title: string; cle_credits: number; is_required: boolean } | null
  user?: { id: number; name: string; email: string } | null
  certificate?: { id: number; certificate_number: string; issued_at?: string } | null
  created_at?: string
  updated_at?: string
}

export type TrainingComplianceRow = {
  user_id: number
  name: string
  email: string
  required_courses_total: number
  required_courses_completed: number
  compliance_percent: number
  cle_credits_earned: number
  enrollments_total: number
  enrollments_completed: number
}
