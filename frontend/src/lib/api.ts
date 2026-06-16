import axios from 'axios'
import { requestDone, requestStart } from '@/lib/progress'
import { resolveApiUrl as resolveBaseApiUrl } from '@/lib/api-url'
import type {
  AiChatResponse,
  PublicChatResponse,
  AiContractReviewResponse,
  AiLetterPackResponse,
  AiResearchAuthoritiesResponse,
  AiStructuredResponse,
  DocumentClause,
  AiGovernanceLog,
  AiGovernanceSettings,
  AiProviderConfig,
  AiProviderName,
  AiProvidersSettingsResponse,
  ApprovalRequest,
  ApprovalSubjectType,
  AuditLog,
  AuditLogFilters,
  SignatureField,
  SignatureRequest,
  AppNotification,
  CaseCalendarEvent,
  CaseActivity,
  CaseDocument,
  DocumentVersion,
  DocumentVersionCompare,
  CaseExpense,
  CaseExpenseSummary,
  TrustLedgerEntry,
  TrustLedgerSummary,
  CourtFiling,
  CourtFormInstance,
  CourtFormTemplate,
  EvidenceExhibitIndex,
  EvidenceCustodyLog,
  EvidenceItem,
  LegalBrief,
  BriefCitation,
  LegalMotion,
  KnowledgeArticle,
  LegalResearchEntry,
  MotionTemplate,
  ResearchFolder,
  ResearchProject,
  ResearchChatMessage,
  ResearchSavedItem,
  EdiscoveryCollection,
  EdiscoveryDocument,
  EdiscoveryReviewAssignment,
  EdiscoveryReviewProgress,
  EdiscoveryTag,
  LegalAnalyticsDashboard,
  LegalAnalyticsHint,
  LegalProjectBudget,
  LegalProjectMilestone,
  IntegrationSetting,
  LegalProjectWorkload,
  TaskWorkloadBoard,
  TrainingComplianceRow,
  TrainingCourse,
  TrainingEnrollment,
  TrainingQuizQuestion,
  CaseNote,
  CaseTask,
  ClientContact,
  ClientContactType,
  DocumentFolder,
  TaskAttachment,
  TaskComment,
  TaskChecklistItem,
  CommunicationLog,
  ConflictCheck,
  IntakeForm,
  IntakeSubmission,
  PaginatedResponse,
  Invoice,
  InvoiceSummary,
  InvoiceAgingSummary,
  CaseOverviewMetrics,
  MergeFieldDefinition,
  ReportSummary,
  SearchResponse,
  ServiceItem,
  Message,
  MessageThread,
  OnlyOfficeEditorConfig,
  TimeEntry,
  TimeEntrySummary,
  User,
} from '@/types'

const TOKEN_KEY = 'banwohub_token'

function resolveApiUrl(): string {
  if (typeof window !== 'undefined') {
    return resolveBaseApiUrl({
      configuredUrl: import.meta.env.VITE_API_URL as string | undefined,
      origin: window.location.origin,
      port: window.location.port,
    })
  }

  return resolveBaseApiUrl({
    configuredUrl: import.meta.env.VITE_API_URL as string | undefined,
  })
}

export const api = axios.create({
  baseURL: resolveApiUrl(),
  withCredentials: true,
  headers: {
    Accept: 'application/json',
    'Content-Type': 'application/json',
  },
})

api.interceptors.request.use((config) => {
  requestStart()
  return config
})

api.interceptors.response.use(
  (response) => {
    requestDone()
    return response
  },
  (error) => {
    requestDone()
    return Promise.reject(error)
  },
)

export function setAuthToken(token: string | null) {
  if (token) {
    api.defaults.headers.common.Authorization = `Bearer ${token}`
  } else {
    delete api.defaults.headers.common.Authorization
  }
}

export function getStoredToken(): string | null {
  if (typeof window === 'undefined') return null
  return localStorage.getItem(TOKEN_KEY)
}

export function persistToken(token: string | null) {
  if (typeof window === 'undefined') return
  if (token) {
    localStorage.setItem(TOKEN_KEY, token)
    setAuthToken(token)
  } else {
    localStorage.removeItem(TOKEN_KEY)
    setAuthToken(null)
  }
}

if (typeof window !== 'undefined') {
  const token = getStoredToken()
  if (token) setAuthToken(token)
}

export async function forgotPassword(email: string): Promise<{
  message: string
  reset_link?: string
}> {
  const { data } = await api.post<{ message: string; reset_link?: string }>(
    '/auth/forgot-password',
    { email },
  )
  return data
}

export async function resetPassword(payload: {
  token: string
  email: string
  password: string
  password_confirmation: string
}): Promise<{ message: string }> {
  const { data } = await api.post<{ message: string }>(
    '/auth/reset-password',
    payload,
  )
  return data
}

export const usersApi = {
  async listActive(): Promise<User[]> {
    const { data } = await api.get<PaginatedResponse<User>>('/users', {
      params: { per_page: 100, active: true },
    })
    return data.data
  },
}

type ListPayload<T> = T[] | { data: T[] }

function unwrapList<T>(payload: ListPayload<T>): T[] {
  return Array.isArray(payload) ? payload : payload.data
}

type ResourcePayload<T> = T | { data: T }

function unwrapResource<T>(payload: ResourcePayload<T>): T {
  return 'data' in (payload as { data?: T }) ? (payload as { data: T }).data : (payload as T)
}

export type CaseNotePayload = {
  title?: string | null
  body: string
  note_type: string
  visibility: string
}

export const caseNotesApi = {
  async list(caseId: number): Promise<CaseNote[]> {
    const { data } = await api.get<ListPayload<CaseNote>>('/case-notes', {
      params: { legal_matter_id: caseId },
    })
    return unwrapList(data)
  },
  async create(caseId: number, payload: CaseNotePayload): Promise<CaseNote> {
    const { data } = await api.post<ResourcePayload<CaseNote>>('/case-notes', {
      ...payload,
      legal_matter_id: caseId,
    })
    return unwrapResource(data)
  },
  async update(noteId: number, payload: CaseNotePayload): Promise<CaseNote> {
    const { data } = await api.patch<ResourcePayload<CaseNote>>(
      `/case-notes/${noteId}`,
      payload,
    )
    return unwrapResource(data)
  },
  async delete(noteId: number): Promise<void> {
    await api.delete(`/case-notes/${noteId}`)
  },
}

export type CaseTaskPayload = {
  title: string
  description?: string | null
  assignee_id: number | null
  due_at?: string | null
  priority: string
  status?: string
  checklist?: TaskChecklistItem[]
}

export const caseTasksApi = {
  async list(caseId: number): Promise<CaseTask[]> {
    const { data } = await api.get<ListPayload<CaseTask>>('/tasks', {
      params: { legal_matter_id: caseId, per_page: 100 },
    })
    return unwrapList(data)
  },
  async get(taskId: number): Promise<CaseTask> {
    const { data } = await api.get<ResourcePayload<CaseTask>>(`/tasks/${taskId}`)
    return unwrapResource(data)
  },
  async create(caseId: number, payload: CaseTaskPayload): Promise<CaseTask> {
    const { data } = await api.post<ResourcePayload<CaseTask>>('/tasks', {
      ...payload,
      legal_matter_id: caseId,
    })
    return unwrapResource(data)
  },
  async update(taskId: number, payload: Partial<CaseTaskPayload>): Promise<CaseTask> {
    const { data } = await api.patch<ResourcePayload<CaseTask>>(`/tasks/${taskId}`, payload)
    return unwrapResource(data)
  },
  async listAttachments(taskId: number): Promise<TaskAttachment[]> {
    const { data } = await api.get<ListPayload<TaskAttachment>>(`/tasks/${taskId}/attachments`)
    return unwrapList(data)
  },
  async uploadAttachment(taskId: number, file: File): Promise<TaskAttachment> {
    const form = new FormData()
    form.append('file', file)
    const { data } = await api.post<ResourcePayload<TaskAttachment>>(
      `/tasks/${taskId}/attachments`,
      form,
      { headers: { 'Content-Type': 'multipart/form-data' } },
    )
    return unwrapResource(data)
  },
  async deleteAttachment(taskId: number, attachmentId: number): Promise<void> {
    await api.delete(`/tasks/${taskId}/attachments/${attachmentId}`)
  },
  async downloadAttachment(taskId: number, attachmentId: number, filename: string): Promise<void> {
    const { data } = await api.get<Blob>(
      `/tasks/${taskId}/attachments/${attachmentId}/download`,
      { responseType: 'blob' },
    )
    const url = URL.createObjectURL(data)
    const link = document.createElement('a')
    link.href = url
    link.download = filename
    document.body.appendChild(link)
    link.click()
    link.remove()
    URL.revokeObjectURL(url)
  },
  async listComments(taskId: number): Promise<TaskComment[]> {
    const { data } = await api.get<ListPayload<TaskComment>>(`/tasks/${taskId}/comments`)
    return unwrapList(data)
  },
  async addComment(taskId: number, body: string): Promise<TaskComment> {
    const { data } = await api.post<ResourcePayload<TaskComment>>(`/tasks/${taskId}/comments`, {
      body,
    })
    return unwrapResource(data)
  },
}

export type CaseCalendarEventPayload = {
  title: string
  description?: string | null
  user_id: number | null
  event_type: string
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
}

export const calendarHubApi = {
  async list(filters: {
    from?: string
    to?: string
    user_id?: number
    category?: import('@/lib/enums').CalendarHubCategory
  } = {}): Promise<import('@/types').CalendarHubResponse> {
    const { data } = await api.get<import('@/types').CalendarHubResponse>('/calendar-hub', {
      params: filters,
    })
    return data
  },
  async exportIcs(filters: {
    from?: string
    to?: string
    user_id?: number
    category?: import('@/lib/enums').CalendarHubCategory
  } = {}): Promise<void> {
    const { data, headers } = await api.get<Blob>('/calendar-hub/export.ics', {
      params: filters,
      responseType: 'blob',
    })
    const disposition = headers['content-disposition'] as string | undefined
    const match = disposition?.match(/filename="?([^";]+)"?/)
    const filename = match?.[1] ?? 'banwolaw-calendar.ics'
    const url = URL.createObjectURL(data)
    const link = document.createElement('a')
    link.href = url
    link.download = filename
    link.click()
    URL.revokeObjectURL(url)
  },
}

export const caseCalendarApi = {
  async list(caseId: number): Promise<CaseCalendarEvent[]> {
    const { data } = await api.get<ListPayload<CaseCalendarEvent>>('/calendar-events', {
      params: { legal_matter_id: caseId, per_page: 100 },
    })
    return unwrapList(data)
  },
  async create(
    caseId: number,
    payload: CaseCalendarEventPayload,
  ): Promise<CaseCalendarEvent> {
    const { data } = await api.post<ResourcePayload<CaseCalendarEvent>>(
      '/calendar-events',
      {
        ...payload,
        legal_matter_id: caseId,
      },
    )
    return unwrapResource(data)
  },
}

export type CaseDocumentUploadPayload = {
  file: File
  name?: string
  document_type?: string
  category?: string
}

export const caseDocumentsApi = {
  async list(caseId: number, options?: { portalPending?: boolean }): Promise<CaseDocument[]> {
    const { data } = await api.get<ListPayload<CaseDocument>>('/documents', {
      params: {
        legal_matter_id: caseId,
        per_page: 100,
        portal_pending: options?.portalPending ? 1 : undefined,
      },
    })
    return unwrapList(data)
  },
  async listTemplates(): Promise<CaseDocument[]> {
    const { data } = await api.get<ListPayload<CaseDocument>>('/documents', {
      params: { document_type: 'organization_template', per_page: 100 },
    })
    return unwrapList(data)
  },
  async upload(
    caseId: number,
    payload: CaseDocumentUploadPayload,
  ): Promise<CaseDocument> {
    const form = new FormData()
    form.append('file', payload.file)
    form.append('legal_matter_id', String(caseId))
    form.append('document_type', payload.document_type ?? 'pleading')
    if (payload.name) form.append('name', payload.name)
    if (payload.category) form.append('category', payload.category)

    const { data } = await api.post<ResourcePayload<CaseDocument>>('/documents', form, {
      headers: { 'Content-Type': 'multipart/form-data' },
    })
    return unwrapResource(data)
  },
  async uploadTemplate(payload: {
    name: string
    content_html: string
    category?: string
  }): Promise<CaseDocument> {
    const form = new FormData()
    form.append('document_type', 'organization_template')
    form.append('name', payload.name)
    form.append('content_html', payload.content_html)
    if (payload.category) form.append('category', payload.category)
    form.append(
      'file',
      new Blob([payload.content_html], { type: 'text/html' }),
      `${payload.name}.html`,
    )

    const { data } = await api.post<ResourcePayload<CaseDocument>>('/documents', form, {
      headers: { 'Content-Type': 'multipart/form-data' },
    })
    return unwrapResource(data)
  },
  async listMergeFields(): Promise<MergeFieldDefinition[]> {
    const { data } = await api.get<{ fields: MergeFieldDefinition[] }>('/documents/merge-fields')
    return data.fields ?? []
  },
  async generateDraft(caseId: number, templateId: number, name?: string): Promise<CaseDocument> {
    const { data } = await api.post<ResourcePayload<CaseDocument>>('/documents/generate-draft', {
      legal_matter_id: caseId,
      template_id: templateId,
      name,
    })
    return unwrapResource(data)
  },
  async updateContent(
    documentId: number,
    contentHtml: string,
    changeSummary?: string,
  ): Promise<CaseDocument> {
    const { data } = await api.patch<ResourcePayload<CaseDocument>>(`/documents/${documentId}`, {
      content_html: contentHtml,
      change_summary: changeSummary,
    })
    return unwrapResource(data)
  },
  async listVersions(documentId: number): Promise<DocumentVersion[]> {
    const { data } = await api.get<ListPayload<DocumentVersion>>(
      `/documents/${documentId}/versions`,
    )
    return unwrapList(data)
  },
  async compareVersions(
    documentId: number,
    fromVersion: number,
    toVersion: number,
  ): Promise<DocumentVersionCompare> {
    const { data } = await api.get<DocumentVersionCompare>(
      `/documents/${documentId}/versions/compare`,
      { params: { from_version: fromVersion, to_version: toVersion } },
    )
    return data
  },
  async onlyOfficeConfig(documentId: number): Promise<OnlyOfficeEditorConfig> {
    const { data } = await api.get<OnlyOfficeEditorConfig>(
      `/documents/${documentId}/onlyoffice-config`,
    )
    return data
  },
  async updateVisibility(documentId: number, clientVisible: boolean): Promise<CaseDocument> {
    const { data } = await api.patch<ResourcePayload<CaseDocument>>(`/documents/${documentId}`, {
      client_visible: clientVisible,
    })
    return unwrapResource(data)
  },
  async saveAiDraft(payload: {
    caseId: number
    contentHtml: string
    name?: string
    templateId?: number | null
    governanceLogId?: number | null
  }): Promise<CaseDocument> {
    const { data } = await api.post<ResourcePayload<CaseDocument>>('/documents/ai-draft', {
      legal_matter_id: payload.caseId,
      content_html: payload.contentHtml,
      name: payload.name,
      template_id: payload.templateId ?? undefined,
      ai_governance_log_id: payload.governanceLogId ?? undefined,
    })
    return unwrapResource(data)
  },
  async updateAiReview(documentId: number, status: string): Promise<CaseDocument> {
    const { data } = await api.patch<ResourcePayload<CaseDocument>>(
      `/documents/${documentId}/ai-review`,
      { ai_review_status: status },
    )
    return unwrapResource(data)
  },
  async download(documentId: number, filename: string): Promise<void> {
    const { data } = await api.get<Blob>(`/documents/${documentId}/download`, {
      responseType: 'blob',
    })
    const url = URL.createObjectURL(data)
    const link = document.createElement('a')
    link.href = url
    link.download = filename
    document.body.appendChild(link)
    link.click()
    link.remove()
    URL.revokeObjectURL(url)
  },
  async exportPdf(documentId: number, filename: string): Promise<void> {
    const { data, headers } = await api.get<Blob>(`/documents/${documentId}/export-pdf`, {
      responseType: 'blob',
    })
    const contentType = headers['content-type'] ?? ''
    const ext = contentType.includes('pdf') ? '.pdf' : '.html'
    const url = URL.createObjectURL(data)
    const link = document.createElement('a')
    link.href = url
    link.download = filename.replace(/\.[^.]+$/, '') + ext
    document.body.appendChild(link)
    link.click()
    link.remove()
    URL.revokeObjectURL(url)
  },
  async assignFolder(documentId: number, folderId: number | null): Promise<CaseDocument> {
    const { data } = await api.put<ResourcePayload<CaseDocument>>(`/documents/${documentId}`, {
      document_folder_id: folderId,
    })
    return unwrapResource(data)
  },
  async checkout(documentId: number): Promise<CaseDocument> {
    const { data } = await api.post<ResourcePayload<CaseDocument>>(
      `/documents/${documentId}/checkout`,
    )
    return unwrapResource(data)
  },
  async checkin(documentId: number): Promise<CaseDocument> {
    const { data } = await api.post<ResourcePayload<CaseDocument>>(
      `/documents/${documentId}/checkin`,
    )
    return unwrapResource(data)
  },
}

export const documentFoldersApi = {
  async list(caseId: number): Promise<DocumentFolder[]> {
    const { data } = await api.get<ListPayload<DocumentFolder>>('/document-folders', {
      params: { legal_matter_id: caseId },
    })
    return unwrapList(data)
  },
  async create(payload: {
    legal_matter_id: number
    name: string
    parent_id?: number | null
  }): Promise<DocumentFolder> {
    const { data } = await api.post<ResourcePayload<DocumentFolder>>('/document-folders', payload)
    return unwrapResource(data)
  },
  async update(
    folderId: number,
    payload: Partial<{ name: string; parent_id: number | null }>,
  ): Promise<DocumentFolder> {
    const { data } = await api.patch<ResourcePayload<DocumentFolder>>(
      `/document-folders/${folderId}`,
      payload,
    )
    return unwrapResource(data)
  },
  async remove(folderId: number): Promise<void> {
    await api.delete(`/document-folders/${folderId}`)
  },
}

export const clientContactsApi = {
  async list(clientId: number): Promise<ClientContact[]> {
    const { data } = await api.get<ListPayload<ClientContact>>('/client-contacts', {
      params: { client_id: clientId, per_page: 100 },
    })
    return unwrapList(data)
  },
  async create(payload: {
    client_id: number
    type: ClientContactType
    name: string
    email?: string | null
    phone?: string | null
    title?: string | null
  }): Promise<ClientContact> {
    const { data } = await api.post<ResourcePayload<ClientContact>>('/client-contacts', payload)
    return unwrapResource(data)
  },
  async update(
    id: number,
    payload: Partial<{
      type: ClientContactType
      name: string
      email: string | null
      phone: string | null
      title: string | null
    }>,
  ): Promise<ClientContact> {
    const { data } = await api.put<ResourcePayload<ClientContact>>(`/client-contacts/${id}`, payload)
    return unwrapResource(data)
  },
  async remove(id: number): Promise<void> {
    await api.delete(`/client-contacts/${id}`)
  },
}

export const serviceItemsApi = {
  async list(activeOnly = true): Promise<ServiceItem[]> {
    const { data } = await api.get<ListPayload<ServiceItem>>('/service-items', {
      params: { per_page: 100, active_only: activeOnly ? 1 : 0 },
    })
    return unwrapList(data)
  },
  async create(payload: {
    name: string
    description?: string | null
    default_rate?: number
  }): Promise<ServiceItem> {
    const { data } = await api.post<ResourcePayload<ServiceItem>>('/service-items', payload)
    return unwrapResource(data)
  },
  async update(
    id: number,
    payload: Partial<{ name: string; description: string | null; default_rate: number; is_active: boolean }>,
  ): Promise<ServiceItem> {
    const { data } = await api.put<ResourcePayload<ServiceItem>>(`/service-items/${id}`, payload)
    return unwrapResource(data)
  },
  async remove(id: number): Promise<void> {
    await api.delete(`/service-items/${id}`)
  },
}

export const documentClausesApi = {
  async list(filters: { category?: string; keyword?: string } = {}): Promise<DocumentClause[]> {
    const { data } = await api.get<ListPayload<DocumentClause>>('/document-clauses', {
      params: { per_page: 100, ...filters },
    })
    return unwrapList(data)
  },
  async create(payload: {
    title: string
    category: string
    body_html: string
    tags?: string[]
  }): Promise<DocumentClause> {
    const { data } = await api.post<ResourcePayload<DocumentClause>>('/document-clauses', payload)
    return unwrapResource(data)
  },
  async update(
    clauseId: number,
    payload: Partial<{
      title: string
      category: string
      body_html: string
      tags: string[]
    }>,
  ): Promise<DocumentClause> {
    const { data } = await api.patch<ResourcePayload<DocumentClause>>(
      `/document-clauses/${clauseId}`,
      payload,
    )
    return unwrapResource(data)
  },
  async remove(clauseId: number): Promise<void> {
    await api.delete(`/document-clauses/${clauseId}`)
  },
}

export const caseActivityApi = {
  async list(caseId: number): Promise<CaseActivity[]> {
    const { data } = await api.get<{ data: CaseActivity[] }>(`/cases/${caseId}/activity`)
    return data.data
  },
}

export type ConflictCheckPayload = {
  search_terms: string[]
  notes?: string | null
  status?: string | null
}

export const conflictChecksApi = {
  async list(caseId?: number, status?: string): Promise<ConflictCheck[]> {
    const { data } = await api.get<ListPayload<ConflictCheck>>('/conflict-checks', {
      params: {
        legal_matter_id: caseId,
        status: status || undefined,
        per_page: 100,
      },
    })
    return unwrapList(data)
  },
  async create(caseId: number, payload: ConflictCheckPayload): Promise<ConflictCheck> {
    const { data } = await api.post<ResourcePayload<ConflictCheck>>('/conflict-checks', {
      ...payload,
      legal_matter_id: caseId,
    })
    return unwrapResource(data)
  },
  async update(
    checkId: number,
    payload: Partial<Pick<ConflictCheck, 'status' | 'decision' | 'notes'>>,
  ): Promise<ConflictCheck> {
    const { data } = await api.patch<ResourcePayload<ConflictCheck>>(
      `/conflict-checks/${checkId}`,
      payload,
    )
    return unwrapResource(data)
  },
  async export(checkId: number, format: 'csv' | 'html' = 'csv'): Promise<void> {
    const { data, headers } = await api.get<Blob>(`/conflict-checks/${checkId}/export`, {
      params: { format },
      responseType: 'blob',
    })
    const ext = format === 'html' ? '.html' : '.csv'
    const url = URL.createObjectURL(data)
    const link = document.createElement('a')
    link.href = url
    link.download = `conflict-check-${checkId}${ext}`
    document.body.appendChild(link)
    link.click()
    link.remove()
    URL.revokeObjectURL(url)
  },
}

export type IntakeFormPayload = {
  name: string
  description?: string | null
  case_type?: string | null
  status?: string
  fields: { name: string; label: string; type: string; required?: boolean }[]
}

export const intakeFormsApi = {
  async list(status?: string): Promise<IntakeForm[]> {
    const { data } = await api.get<ListPayload<IntakeForm>>('/intake-forms', {
      params: { status: status || undefined, per_page: 100 },
    })
    return unwrapList(data)
  },
  async create(payload: IntakeFormPayload): Promise<IntakeForm> {
    const { data } = await api.post<ResourcePayload<IntakeForm>>('/intake-forms', payload)
    return unwrapResource(data)
  },
  async update(id: number, payload: Partial<IntakeFormPayload>): Promise<IntakeForm> {
    const { data } = await api.patch<ResourcePayload<IntakeForm>>(
      `/intake-forms/${id}`,
      payload,
    )
    return unwrapResource(data)
  },
}

export type IntakeSubmissionPayload = {
  intake_form_id: number
  submitter_name?: string | null
  submitter_email?: string | null
  submitter_phone?: string | null
  status?: string
  data: Record<string, unknown>
  review_notes?: string | null
}

export const intakeSubmissionsApi = {
  async list(status?: string): Promise<IntakeSubmission[]> {
    const { data } = await api.get<ListPayload<IntakeSubmission>>('/intake-submissions', {
      params: { status: status || undefined, per_page: 100 },
    })
    return unwrapList(data)
  },
  async create(payload: IntakeSubmissionPayload): Promise<IntakeSubmission> {
    const { data } = await api.post<ResourcePayload<IntakeSubmission>>(
      '/intake-submissions',
      payload,
    )
    return unwrapResource(data)
  },
  async update(
    id: number,
    payload: Partial<IntakeSubmissionPayload>,
  ): Promise<IntakeSubmission> {
    const { data } = await api.patch<ResourcePayload<IntakeSubmission>>(
      `/intake-submissions/${id}`,
      payload,
    )
    return unwrapResource(data)
  },
  async convert(id: number): Promise<IntakeSubmission> {
    const { data } = await api.post<ResourcePayload<IntakeSubmission>>(
      `/intake-submissions/${id}/convert`,
    )
    return unwrapResource(data)
  },
  async approve(id: number, reviewNotes?: string): Promise<IntakeSubmission> {
    const { data } = await api.post<ResourcePayload<IntakeSubmission>>(
      `/intake-submissions/${id}/approve`,
      { review_notes: reviewNotes },
    )
    return unwrapResource(data)
  },
  async reject(id: number, reviewNotes?: string): Promise<IntakeSubmission> {
    const { data } = await api.post<ResourcePayload<IntakeSubmission>>(
      `/intake-submissions/${id}/reject`,
      { review_notes: reviewNotes },
    )
    return unwrapResource(data)
  },
  async requestInfo(id: number, reviewNotes: string): Promise<IntakeSubmission> {
    const { data } = await api.post<ResourcePayload<IntakeSubmission>>(
      `/intake-submissions/${id}/request-info`,
      { review_notes: reviewNotes },
    )
    return unwrapResource(data)
  },
}

export type TimeEntryPayload = {
  legal_matter_id?: number | null
  legal_task_id?: number | null
  user_id?: number | null
  description?: string | null
  started_at?: string | null
  ended_at?: string | null
  duration_minutes?: number | null
  billable?: boolean
  rate?: number | null
  status?: string
}

export type TimeEntryFilters = {
  legal_matter_id?: number
  legal_task_id?: number
  user_id?: number
  status?: string
  billable?: boolean
  per_page?: number
}

export type TimerStartPayload = {
  legal_matter_id?: number | null
  legal_task_id?: number | null
  description?: string | null
  billable?: boolean
  rate?: number | null
}

export const timeEntriesApi = {
  async list(
    filters: TimeEntryFilters = {},
  ): Promise<{ entries: TimeEntry[]; summary: TimeEntrySummary | null }> {
    const { data } = await api.get<{ data: TimeEntry[]; meta?: { summary?: TimeEntrySummary } }>(
      '/time-entries',
      {
        params: { per_page: 100, ...filters },
      },
    )
    return { entries: data.data, summary: data.meta?.summary ?? null }
  },
  async create(payload: TimeEntryPayload): Promise<TimeEntry> {
    const { data } = await api.post<ResourcePayload<TimeEntry>>('/time-entries', payload)
    return unwrapResource(data)
  },
  async update(id: number, payload: Partial<TimeEntryPayload>): Promise<TimeEntry> {
    const { data } = await api.patch<ResourcePayload<TimeEntry>>(`/time-entries/${id}`, payload)
    return unwrapResource(data)
  },
  async delete(id: number): Promise<void> {
    await api.delete(`/time-entries/${id}`)
  },
  async approve(id: number): Promise<TimeEntry> {
    const { data } = await api.post<ResourcePayload<TimeEntry>>(`/time-entries/${id}/approve`)
    return unwrapResource(data)
  },
  async running(): Promise<TimeEntry | null> {
    const { data } = await api.get<{ data: TimeEntry | null }>('/time-entries/running')
    return data.data
  },
  async startTimer(payload: TimerStartPayload = {}): Promise<TimeEntry> {
    const { data } = await api.post<ResourcePayload<TimeEntry>>('/time-entries/timer/start', payload)
    return unwrapResource(data)
  },
  async stopTimer(id: number): Promise<TimeEntry> {
    const { data } = await api.post<ResourcePayload<TimeEntry>>(`/time-entries/${id}/stop`)
    return unwrapResource(data)
  },
}

export type InvoicePayload = {
  client_id: number
  legal_matter_id?: number | null
  issue_date?: string | null
  due_date?: string | null
  tax_rate?: number | null
  discount_amount?: number | null
  currency?: string
  notes?: string | null
  line_items: Array<{
    description: string
    quantity: number
    unit_price: number
    line_type?: string
    time_entry_id?: number | null
    service_item_id?: number | null
    sort_order?: number
  }>
}

export type GenerateInvoicePayload = {
  client_id: number
  legal_matter_id?: number | null
  time_entry_ids?: number[]
  issue_date?: string | null
  due_date?: string | null
  tax_rate?: number | null
  discount_amount?: number | null
  notes?: string | null
}

export type InvoiceFilters = {
  client_id?: number
  legal_matter_id?: number
  status?: string
  search?: string
  per_page?: number
}

export const invoicesApi = {
  async list(
    filters: InvoiceFilters = {},
  ): Promise<{ invoices: Invoice[]; summary: InvoiceSummary | null }> {
    const { data } = await api.get<{ data: Invoice[]; meta?: { summary?: InvoiceSummary } }>(
      '/invoices',
      { params: { per_page: 100, ...filters } },
    )
    return { invoices: data.data, summary: data.meta?.summary ?? null }
  },
  async agingSummary(): Promise<InvoiceAgingSummary> {
    const { data } = await api.get<InvoiceAgingSummary>('/invoices/aging-summary')
    return data
  },
  async get(id: number): Promise<Invoice> {
    const { data } = await api.get<Invoice>(`/invoices/${id}`)
    return data
  },
  async create(payload: InvoicePayload): Promise<Invoice> {
    const { data } = await api.post<Invoice>('/invoices', payload)
    return data
  },
  async update(id: number, payload: Partial<InvoicePayload>): Promise<Invoice> {
    const { data } = await api.put<Invoice>(`/invoices/${id}`, payload)
    return data
  },
  async delete(id: number): Promise<void> {
    await api.delete(`/invoices/${id}`)
  },
  async generateFromTimeEntries(payload: GenerateInvoicePayload): Promise<Invoice> {
    const { data } = await api.post<Invoice>('/invoices/generate-from-time-entries', payload)
    return data
  },
  async markSent(id: number): Promise<Invoice> {
    const { data } = await api.post<Invoice>(`/invoices/${id}/mark-sent`)
    return data
  },
  async recordPayment(
    id: number,
    payload: { amount: number; payment_method?: string; notes?: string },
  ): Promise<Invoice> {
    const { data } = await api.post<Invoice>(`/invoices/${id}/record-payment`, payload)
    return data
  },
  async exportPdf(id: number, filename: string): Promise<void> {
    const { data, headers } = await api.get<Blob>(`/invoices/${id}/export-pdf`, {
      responseType: 'blob',
    })
    const contentType = headers['content-type'] ?? ''
    const ext = contentType.includes('pdf') ? '.pdf' : '.html'
    const url = URL.createObjectURL(data)
    const link = document.createElement('a')
    link.href = url
    link.download = filename.replace(/\.[^.]+$/, '') + ext
    document.body.appendChild(link)
    link.click()
    link.remove()
    URL.revokeObjectURL(url)
  },
}

export type ApprovalFilters = {
  subject_type?: ApprovalSubjectType
  subject_id?: number
  status?: string
  per_page?: number
}

export const approvalsApi = {
  async list(filters: ApprovalFilters = {}): Promise<{ requests: ApprovalRequest[] }> {
    const { data } = await api.get<ListPayload<ApprovalRequest>>('/approval-requests', {
      params: { per_page: 25, ...filters },
    })
    return { requests: unwrapList(data) }
  },
  async get(id: number): Promise<ApprovalRequest> {
    const { data } = await api.get<ApprovalRequest>(`/approval-requests/${id}`)
    return data
  },
  async submit(payload: {
    subject_type: ApprovalSubjectType
    subject_id: number
    reviewer_id?: number
    notes?: string
    requires_approval?: boolean
  }): Promise<ApprovalRequest> {
    const { data } = await api.post<ApprovalRequest>('/approval-requests', payload)
    return data
  },
  async review(
    id: number,
    payload: { action: 'approve' | 'reject' | 'request_changes'; comment?: string },
  ): Promise<ApprovalRequest> {
    const { data } = await api.patch<ApprovalRequest>(`/approval-requests/${id}/review`, payload)
    return data
  },
}

export type SignatureFilters = {
  legal_matter_id?: number
  document_id?: number
  status?: string
  per_page?: number
}

export const signaturesApi = {
  async list(filters: SignatureFilters = {}): Promise<{ requests: SignatureRequest[] }> {
    const { data } = await api.get<ListPayload<SignatureRequest>>('/signature-requests', {
      params: { per_page: 25, ...filters },
    })
    return { requests: unwrapList(data) }
  },
  async get(id: number): Promise<SignatureRequest> {
    const { data } = await api.get<SignatureRequest>(`/signature-requests/${id}`)
    return data
  },
  async send(payload: {
    document_id: number
    message?: string
    fields?: SignatureField[]
  }): Promise<SignatureRequest> {
    const { data } = await api.post<SignatureRequest>('/signature-requests', payload)
    return data
  },
}

export type ReportFilters = {
  from_date?: string
  to_date?: string
}

export const reportsApi = {
  async summary(filters: ReportFilters = {}): Promise<ReportSummary> {
    const { data } = await api.get<ReportSummary>('/reports/summary', { params: filters })
    return data
  },
  async exportCsv(filters: ReportFilters & { dataset?: string } = {}): Promise<void> {
    const { data, headers } = await api.get<Blob>('/reports/export.csv', {
      params: filters,
      responseType: 'blob',
    })
    const disposition = headers['content-disposition'] as string | undefined
    const match = disposition?.match(/filename="?([^"]+)"?/)
    const filename = match?.[1] ?? `banwolaw-reports-${new Date().toISOString().slice(0, 10)}.csv`
    const url = URL.createObjectURL(data)
    const link = document.createElement('a')
    link.href = url
    link.download = filename
    document.body.appendChild(link)
    link.click()
    link.remove()
    URL.revokeObjectURL(url)
  },
}

export type MessageThreadFilters = {
  client_id?: number
  legal_matter_id?: number
  unread_only?: boolean
  per_page?: number
}

export type CreateMessageThreadPayload = {
  client_id?: number
  legal_matter_id?: number | null
  subject: string
  body: string
  attachments?: Array<{ name: string; url?: string | null }>
}

export type SendMessagePayload = {
  body: string
  attachments?: Array<{ name: string; url?: string | null }>
}

export const messageThreadsApi = {
  async list(filters: MessageThreadFilters = {}): Promise<MessageThread[]> {
    const { data } = await api.get<ListPayload<MessageThread>>('/message-threads', {
      params: { per_page: 100, ...filters },
    })
    return unwrapList(data)
  },
  async get(id: number): Promise<MessageThread> {
    const { data } = await api.get<ResourcePayload<MessageThread>>(`/message-threads/${id}`)
    return unwrapResource(data)
  },
  async create(payload: CreateMessageThreadPayload): Promise<MessageThread> {
    const { data } = await api.post<ResourcePayload<MessageThread>>('/message-threads', payload)
    return unwrapResource(data)
  },
  async sendMessage(threadId: number, payload: SendMessagePayload): Promise<Message> {
    const { data } = await api.post<ResourcePayload<Message>>(
      `/message-threads/${threadId}/messages`,
      payload,
    )
    return unwrapResource(data)
  },
  async markRead(threadId: number): Promise<void> {
    await api.post(`/message-threads/${threadId}/mark-read`)
  },
}

export type CommunicationLogFilters = {
  client_id?: number
  legal_matter_id?: number
  channel?: string
}

export type CommunicationLogPayload = {
  client_id: number
  legal_matter_id?: number | null
  channel: string
  subject?: string | null
  body?: string | null
  occurred_at?: string | null
  client_feedback?: string | null
  satisfaction_score?: number | null
}

export type CaseExpensePayload = {
  legal_matter_id: number
  category?: string | null
  description: string
  amount: number
  expense_date: string
  billable?: boolean
}

export const caseExpensesApi = {
  async list(
    filters: { legal_matter_id?: number; billable?: boolean } = {},
  ): Promise<{ expenses: CaseExpense[]; summary: CaseExpenseSummary | null }> {
    const { data } = await api.get<{
      data: CaseExpense[]
      meta?: { summary?: CaseExpenseSummary }
    }>('/case-expenses', { params: { per_page: 100, ...filters } })
    return { expenses: data.data, summary: data.meta?.summary ?? null }
  },
  async create(payload: CaseExpensePayload): Promise<CaseExpense> {
    const { data } = await api.post<ResourcePayload<CaseExpense>>('/case-expenses', payload)
    return unwrapResource(data)
  },
  async remove(id: number): Promise<void> {
    await api.delete(`/case-expenses/${id}`)
  },
}

export type TrustLedgerEntryPayload = {
  legal_matter_id: number
  entry_type: 'deposit' | 'disbursement' | 'adjustment'
  amount: number
  description?: string | null
  occurred_at?: string
}

export const trustLedgerApi = {
  async list(
    filters: { legal_matter_id?: number } = {},
  ): Promise<{ entries: TrustLedgerEntry[]; summary: TrustLedgerSummary | null }> {
    const { data } = await api.get<{
      data: TrustLedgerEntry[]
      meta?: { summary?: TrustLedgerSummary }
    }>('/trust-ledger-entries', { params: { per_page: 100, ...filters } })
    return { entries: data.data, summary: data.meta?.summary ?? null }
  },
  async create(payload: TrustLedgerEntryPayload): Promise<TrustLedgerEntry> {
    const { data } = await api.post<ResourcePayload<TrustLedgerEntry>>('/trust-ledger-entries', payload)
    return unwrapResource(data)
  },
  async remove(id: number): Promise<void> {
    await api.delete(`/trust-ledger-entries/${id}`)
  },
}

export const communicationLogsApi = {
  async list(filters: CommunicationLogFilters = {}): Promise<CommunicationLog[]> {
    const { data } = await api.get<ListPayload<CommunicationLog>>('/communication-logs', {
      params: { per_page: 100, ...filters },
    })
    return unwrapList(data)
  },
  async get(id: number): Promise<CommunicationLog> {
    const { data } = await api.get<ResourcePayload<CommunicationLog>>(`/communication-logs/${id}`)
    return unwrapResource(data)
  },
  async create(payload: CommunicationLogPayload): Promise<CommunicationLog> {
    const { data } = await api.post<ResourcePayload<CommunicationLog>>('/communication-logs', payload)
    return unwrapResource(data)
  },
  async update(id: number, payload: Partial<CommunicationLogPayload>): Promise<CommunicationLog> {
    const { data } = await api.put<ResourcePayload<CommunicationLog>>(
      `/communication-logs/${id}`,
      payload,
    )
    return unwrapResource(data)
  },
  async remove(id: number): Promise<void> {
    await api.delete(`/communication-logs/${id}`)
  },
}

export type LawyerAvailabilityPayload = {
  user_id?: number
  slots: Array<{
    day_of_week: number
    start_time: string
    end_time: string
    slot_duration_minutes?: number
    consultation_types?: string[]
    consultation_fee?: number | null
    location?: string | null
    online_meeting?: boolean
    is_active?: boolean
  }>
}

export type CreateAppointmentPayload = {
  client_id?: number | null
  user_id: number
  legal_matter_id?: number | null
  consultation_type: string
  starts_at: string
  ends_at: string
  location?: string | null
  online_meeting?: boolean
  fee?: number | null
  notes?: string | null
  status?: string
}

export const lawyerAvailabilityApi = {
  async list(userId?: number): Promise<import('@/types').LawyerAvailabilitySlot[]> {
    const { data } = await api.get<ListPayload<import('@/types').LawyerAvailabilitySlot>>(
      '/lawyer-availability',
      { params: { user_id: userId } },
    )
    return unwrapList(data)
  },
  async update(payload: LawyerAvailabilityPayload): Promise<import('@/types').LawyerAvailabilitySlot[]> {
    const { data } = await api.put<ListPayload<import('@/types').LawyerAvailabilitySlot>>(
      '/lawyer-availability',
      payload,
    )
    return unwrapList(data)
  },
}

export const appointmentsApi = {
  async list(filters: {
    user_id?: number
    client_id?: number
    status?: string
    from?: string
    to?: string
  } = {}): Promise<import('@/types').Appointment[]> {
    const { data } = await api.get<ListPayload<import('@/types').Appointment>>('/appointments', {
      params: { per_page: 100, ...filters },
    })
    return unwrapList(data)
  },
  async get(id: number): Promise<import('@/types').Appointment> {
    const { data } = await api.get<ResourcePayload<import('@/types').Appointment>>(`/appointments/${id}`)
    return unwrapResource(data)
  },
  async availableSlots(userId: number, date: string): Promise<import('@/types').AvailableSlot[]> {
    const { data } = await api.get<{ data: import('@/types').AvailableSlot[] }>(
      '/appointments/available-slots',
      { params: { user_id: userId, date } },
    )
    return data.data ?? []
  },
  async create(payload: CreateAppointmentPayload): Promise<import('@/types').Appointment> {
    const { data } = await api.post<ResourcePayload<import('@/types').Appointment>>('/appointments', payload)
    return unwrapResource(data)
  },
  async update(id: number, payload: Partial<CreateAppointmentPayload>): Promise<import('@/types').Appointment> {
    const { data } = await api.patch<ResourcePayload<import('@/types').Appointment>>(
      `/appointments/${id}`,
      payload,
    )
    return unwrapResource(data)
  },
  async cancel(id: number): Promise<import('@/types').Appointment> {
    const { data } = await api.patch<ResourcePayload<import('@/types').Appointment>>(
      `/appointments/${id}`,
      { status: 'cancelled' },
    )
    return unwrapResource(data)
  },
}

export const notificationsApi = {
  async list(unread?: boolean): Promise<AppNotification[]> {
    const { data } = await api.get<ListPayload<AppNotification>>('/notifications', {
      params: { unread: unread ? true : undefined, per_page: 100 },
    })
    return unwrapList(data)
  },
  async markRead(id: number): Promise<AppNotification> {
    const { data } = await api.post<ResourcePayload<AppNotification>>(
      `/notifications/${id}/read`,
    )
    return unwrapResource(data)
  },
  async markAllRead(): Promise<{ updated: number }> {
    const { data } = await api.post<{ updated: number }>('/notifications/mark-all-read')
    return data
  },
}

export type AiGovernanceLogFilters = {
  action_type?: string
  user_id?: number
  per_page?: number
  page?: number
}

export const integrationsApi = {
  async list(): Promise<IntegrationSetting[]> {
    const { data } = await api.get<{ integrations: IntegrationSetting[] }>('/settings/integrations')
    return data.integrations ?? []
  },
  async googleCalendarConnect(): Promise<{ configured: boolean; auth_url?: string; message?: string }> {
    const { data } = await api.get<{ configured: boolean; auth_url?: string; message?: string }>(
      '/integrations/google-calendar/connect',
    )
    return data
  },
  async googleCalendarDisconnect(): Promise<{ disconnected: boolean }> {
    const { data } = await api.post<{ disconnected: boolean }>('/integrations/google-calendar/disconnect')
    return data
  },
}

export const taskWorkloadApi = {
  async board(): Promise<TaskWorkloadBoard> {
    const { data } = await api.get<TaskWorkloadBoard>('/task-workload')
    return data
  },
}

export const aiProvidersApi = {
  async list(): Promise<AiProvidersSettingsResponse> {
    const { data } = await api.get<AiProvidersSettingsResponse>('/settings/ai-providers')
    return data
  },
  async update(payload: {
    provider: AiProviderName
    api_key?: string
    is_enabled?: boolean
    model?: string | null
    settings?: Record<string, unknown>
  }): Promise<AiProvidersSettingsResponse & { provider: AiProviderConfig }> {
    const { data } = await api.put<AiProvidersSettingsResponse & { provider: AiProviderConfig }>(
      '/settings/ai-providers',
      payload,
    )
    return data
  },
  async setActive(provider: AiProviderName): Promise<AiProvidersSettingsResponse> {
    const { data } = await api.put<AiProvidersSettingsResponse>('/settings/ai-providers/active', {
      provider,
    })
    return data
  },
  async testConnection(
    provider: AiProviderName,
    apiKey?: string,
  ): Promise<
    AiProvidersSettingsResponse & {
      success: boolean
      message: string
      provider: AiProviderConfig
      last_test_success_at?: string | null
    }
  > {
    const { data } = await api.post<
      AiProvidersSettingsResponse & {
        success: boolean
        message: string
        provider: AiProviderConfig
        last_test_success_at?: string | null
      }
    >(`/settings/ai-providers/${provider}/test-connection`, apiKey ? { api_key: apiKey } : {})
    return data
  },
}

export const publicChatApi = {
  async chat(payload: {
    message: string
    session_id?: string
    name?: string
    email?: string
    phone?: string
  }): Promise<PublicChatResponse> {
    const { data } = await api.post<PublicChatResponse>('/public/chat', payload)
    return data
  },
}

export const aiApi = {
  async governanceSettings(): Promise<AiGovernanceSettings> {
    const { data } = await api.get<AiGovernanceSettings>('/ai/governance/settings')
    return data
  },
  async governanceLogs(
    filters: AiGovernanceLogFilters = {},
  ): Promise<{ logs: AiGovernanceLog[]; meta: PaginatedResponse<AiGovernanceLog>['meta'] }> {
    const { data } = await api.get<PaginatedResponse<AiGovernanceLog>>('/ai/governance/logs', {
      params: filters,
    })
    return { logs: data.data, meta: data.meta }
  },
  async chat(payload: {
    message: string
    context?: 'staff' | 'lawyer'
    legal_matter_id?: number | null
  }): Promise<AiChatResponse> {
    const { data } = await api.post<AiChatResponse>('/ai/chat', payload)
    return data
  },
  async health(): Promise<{ available: boolean; stub_mode: boolean }> {
    const { data } = await api.get<{ available: boolean; stub_mode: boolean }>('/ai/health')
    return data
  },
  async draftAssist(payload: {
    legal_matter_id: number
    template_id?: number | null
  }): Promise<AiChatResponse> {
    const { data } = await api.post<AiChatResponse>('/ai/draft-assist', payload)
    return data
  },
  async summarizeDocument(legalDocumentId: number): Promise<AiChatResponse> {
    const { data } = await api.post<AiChatResponse>('/ai/summarize-document', {
      legal_document_id: legalDocumentId,
    })
    return data
  },
  async summarizeResearchNotes(payload: {
    legal_matter_id: number
    case_note_ids?: number[]
  }): Promise<AiChatResponse> {
    const { data } = await api.post<AiChatResponse>('/ai/research/summarize-notes', payload)
    return data
  },
  async suggestAuthorities(payload: {
    legal_matter_id: number
    issue: string
    case_note_ids?: number[]
  }): Promise<AiResearchAuthoritiesResponse> {
    const { data } = await api.post<AiResearchAuthoritiesResponse>(
      '/ai/research/suggest-authorities',
      payload,
    )
    return data
  },
  async briefOutline(payload: {
    legal_matter_id: number
    title: string
    issue?: string
    case_note_ids?: number[]
  }): Promise<AiChatResponse> {
    const { data } = await api.post<AiChatResponse>('/ai/brief/outline', payload)
    return data
  },
  async briefRewrite(payload: {
    legal_matter_id: number
    section_html: string
    instruction?: string
  }): Promise<AiChatResponse> {
    const { data } = await api.post<AiChatResponse>('/ai/brief/rewrite', payload)
    return data
  },
  async motionStructureCheck(payload: {
    legal_matter_id: number
    title: string
    motion_type?: string
    content_html: string
    required_sections?: string[]
  }): Promise<AiChatResponse> {
    const { data } = await api.post<AiChatResponse>('/ai/motion/structure-check', payload)
    return data
  },
  async contractReview(legalDocumentId: number): Promise<AiContractReviewResponse> {
    const { data } = await api.post<AiContractReviewResponse>('/ai/contract/review', {
      legal_document_id: legalDocumentId,
    })
    return data
  },
  async generateLetterPack(payload: {
    legal_matter_id: number
    letter_types?: string[]
    context?: string
  }): Promise<AiLetterPackResponse> {
    const { data } = await api.post<AiLetterPackResponse>('/ai/letters/generate-pack', payload)
    return data
  },
  async briefGenerateFromFacts(payload: {
    legal_matter_id: number
    legal_brief_id?: number
    title?: string
    brief_type?: string
    jurisdiction?: string
    court_type?: string
    cause_of_action?: string
    case_facts: string
    statutes?: string
    desired_outcome?: string
    citation_style?: string
  }): Promise<AiStructuredResponse> {
    const { data } = await api.post<AiStructuredResponse>('/ai/brief/generate-from-facts', payload)
    return data
  },
  async briefBuildArguments(payload: {
    legal_matter_id: number
    legal_brief_id?: number
    issue: string
  }): Promise<AiStructuredResponse> {
    const { data } = await api.post<AiStructuredResponse>('/ai/brief/build-arguments', payload)
    return data
  },
  async briefAnalyzeOpposition(payload: {
    legal_matter_id: number
    legal_brief_id?: number
    content_html?: string
    issue?: string
  }): Promise<AiStructuredResponse> {
    const { data } = await api.post<AiStructuredResponse>('/ai/brief/analyze-opposition', payload)
    return data
  },
  async briefEnhance(payload: {
    legal_matter_id: number
    content_html: string
    enhancement_goal?: 'strengthen' | 'tone' | 'clarity' | 'dedupe'
  }): Promise<AiStructuredResponse> {
    const { data } = await api.post<AiStructuredResponse>('/ai/brief/enhance', payload)
    return data
  },
  async briefFormatCourt(payload: {
    legal_matter_id: number
    legal_brief_id?: number
    content_html?: string
    court_type: string
    jurisdiction?: string
    citation_style?: string
  }): Promise<AiStructuredResponse> {
    const { data } = await api.post<AiStructuredResponse>('/ai/brief/format-court', payload)
    return data
  },
  async researchQuery(payload: {
    query: string
    legal_matter_id?: number
    jurisdiction?: string
    court_type?: string
  }): Promise<AiStructuredResponse> {
    const { data } = await api.post<AiStructuredResponse>('/ai/research/query', payload)
    return data
  },
  async researchSearchCases(payload: {
    issue: string
    jurisdiction?: string
    court_type?: string
    legal_matter_id?: number
  }): Promise<AiStructuredResponse> {
    const { data } = await api.post<AiStructuredResponse>('/ai/research/search-cases', payload)
    return data
  },
  async researchGenerateMemo(payload: {
    issue: string
    legal_matter_id?: number
    research_project_id?: number
    memo_type?: 'research_memo' | 'issue_analysis' | 'client_advisory' | 'risk_assessment'
  }): Promise<AiStructuredResponse> {
    const { data } = await api.post<AiStructuredResponse>('/ai/research/generate-memo', payload)
    return data
  },
  async researchAnalyzeStatute(payload: {
    statute_text: string
    jurisdiction?: string
    question?: string
  }): Promise<AiStructuredResponse> {
    const { data } = await api.post<AiStructuredResponse>('/ai/research/analyze-statute', payload)
    return data
  },
  async researchStrategy(payload: {
    issue: string
    legal_matter_id: number
    context?: string
  }): Promise<AiStructuredResponse> {
    const { data } = await api.post<AiStructuredResponse>('/ai/research/strategy', payload)
    return data
  },
  async researchChat(payload: {
    research_project_id: number
    message: string
  }): Promise<AiStructuredResponse> {
    const { data } = await api.post<AiStructuredResponse>('/ai/research/chat', payload)
    return data
  },
}

export const courtFormTemplatesApi = {
  async list(filters: {
    jurisdiction?: string
    court?: string
    case_type?: string
    filing_type?: string
  } = {}): Promise<CourtFormTemplate[]> {
    const { data } = await api.get<ListPayload<CourtFormTemplate>>('/court-form-templates', {
      params: { per_page: 100, ...filters },
    })
    return unwrapList(data)
  },
  async prefill(
    templateId: number,
    legalMatterId: number,
  ): Promise<{
    template: CourtFormTemplate
    field_values: Record<string, string | null>
    sources: Record<string, unknown>
  }> {
    const { data } = await api.post<{
      template: CourtFormTemplate
      field_values: Record<string, string | null>
      sources: Record<string, unknown>
    }>(`/court-form-templates/${templateId}/prefill`, { legal_matter_id: legalMatterId })
    return data
  },
}

export const courtFormInstancesApi = {
  async list(filters: { legal_matter_id?: number; status?: string } = {}): Promise<CourtFormInstance[]> {
    const { data } = await api.get<ListPayload<CourtFormInstance>>('/court-form-instances', {
      params: { per_page: 100, ...filters },
    })
    return unwrapList(data)
  },
  async create(payload: {
    legal_matter_id: number
    court_form_template_id: number
    title?: string
    field_values?: Record<string, string | null>
  }): Promise<CourtFormInstance> {
    const { data } = await api.post<ResourcePayload<CourtFormInstance>>(
      '/court-form-instances',
      payload,
    )
    return unwrapResource(data)
  },
  async update(
    id: number,
    payload: Partial<{
      title: string
      field_values: Record<string, string | null>
      status: string
    }>,
  ): Promise<CourtFormInstance> {
    const { data } = await api.put<ResourcePayload<CourtFormInstance>>(
      `/court-form-instances/${id}`,
      payload,
    )
    return unwrapResource(data)
  },
  async createFiling(id: number, court?: string): Promise<CourtFormInstance> {
    const { data } = await api.post<ResourcePayload<CourtFormInstance>>(
      `/court-form-instances/${id}/create-filing`,
      court ? { court } : {},
    )
    return unwrapResource(data)
  },
}

export const evidenceApi = {
  async list(filters: {
    legal_matter_id?: number
    status?: string
    evidence_type?: string
    exhibits_only?: boolean
  } = {}): Promise<{
    items: EvidenceItem[]
    statuses: string[]
    evidenceTypes: string[]
    custodyActions: string[]
  }> {
    const { data } = await api.get<{
      data: EvidenceItem[]
      meta?: {
        statuses?: string[]
        evidence_types?: string[]
        custody_actions?: string[]
      }
    }>('/evidence-items', {
      params: {
        per_page: 100,
        ...filters,
        exhibits_only: filters.exhibits_only ? 1 : undefined,
      },
    })
    return {
      items: data.data,
      statuses: data.meta?.statuses ?? [],
      evidenceTypes: data.meta?.evidence_types ?? [],
      custodyActions: data.meta?.custody_actions ?? [],
    }
  },
  async upload(
    caseId: number,
    payload: {
      file?: File
      title: string
      description?: string
      evidence_type: string
      source?: string
      date_obtained?: string
      relevance?: string
      tags?: string[]
    },
  ): Promise<EvidenceItem> {
    const form = new FormData()
    form.append('legal_matter_id', String(caseId))
    form.append('title', payload.title)
    form.append('evidence_type', payload.evidence_type)
    if (payload.file) form.append('file', payload.file)
    if (payload.description) form.append('description', payload.description)
    if (payload.source) form.append('source', payload.source)
    if (payload.date_obtained) form.append('date_obtained', payload.date_obtained)
    if (payload.relevance) form.append('relevance', payload.relevance)
    if (payload.tags?.length) {
      payload.tags.forEach((tag, index) => form.append(`tags[${index}]`, tag))
    }

    const { data } = await api.post<ResourcePayload<EvidenceItem>>('/evidence-items', form, {
      headers: { 'Content-Type': 'multipart/form-data' },
    })
    return unwrapResource(data)
  },
  async update(
    id: number,
    payload: Partial<{
      title: string
      description: string
      evidence_type: string
      source: string
      date_obtained: string
      relevance: string
      tags: string[]
    }>,
  ): Promise<EvidenceItem> {
    const { data } = await api.put<ResourcePayload<EvidenceItem>>(`/evidence-items/${id}`, payload)
    return unwrapResource(data)
  },
  async updateStatus(id: number, status: string): Promise<EvidenceItem> {
    const { data } = await api.patch<ResourcePayload<EvidenceItem>>(
      `/evidence-items/${id}/status`,
      { status },
    )
    return unwrapResource(data)
  },
  async assignExhibit(id: number, exhibitNumber?: string): Promise<EvidenceItem> {
    const { data } = await api.post<ResourcePayload<EvidenceItem>>(
      `/evidence-items/${id}/assign-exhibit`,
      exhibitNumber ? { exhibit_number: exhibitNumber } : {},
    )
    return unwrapResource(data)
  },
  async custodyLogs(id: number): Promise<EvidenceCustodyLog[]> {
    const { data } = await api.get<ListPayload<EvidenceCustodyLog>>(
      `/evidence-items/${id}/custody-logs`,
    )
    return unwrapList(data)
  },
  async addCustodyLog(
    id: number,
    payload: {
      action: string
      notes?: string
      location?: string
    },
  ): Promise<EvidenceCustodyLog> {
    const { data } = await api.post<ResourcePayload<EvidenceCustodyLog>>(
      `/evidence-items/${id}/custody-logs`,
      payload,
    )
    return unwrapResource(data)
  },
  async exhibitIndex(legalMatterId: number): Promise<EvidenceExhibitIndex> {
    const { data } = await api.get<EvidenceExhibitIndex>('/evidence-items/exhibit-index', {
      params: { legal_matter_id: legalMatterId },
    })
    return data
  },
  async exportBundle(legalMatterId: number): Promise<Blob> {
    const { data } = await api.get<Blob>('/evidence-items/export-bundle', {
      params: { legal_matter_id: legalMatterId },
      responseType: 'blob',
    })
    return data
  },
}

export const briefsApi = {
  async list(filters: {
    legal_matter_id?: number
    status?: string
  } = {}): Promise<{
    briefs: LegalBrief[]
    statuses: string[]
    authorityTypes: string[]
    briefTypes: string[]
    courtTypes: string[]
    citationStyles: string[]
  }> {
    const { data } = await api.get<{
      data: LegalBrief[]
      meta?: {
        statuses?: string[]
        authority_types?: string[]
        brief_types?: string[]
        court_types?: string[]
        citation_styles?: string[]
      }
    }>('/legal-briefs', { params: { per_page: 100, ...filters } })
    return {
      briefs: data.data,
      statuses: data.meta?.statuses ?? [],
      authorityTypes: data.meta?.authority_types ?? [],
      briefTypes: data.meta?.brief_types ?? [],
      courtTypes: data.meta?.court_types ?? [],
      citationStyles: data.meta?.citation_styles ?? [],
    }
  },
  async get(id: number): Promise<LegalBrief> {
    const { data } = await api.get<ResourcePayload<LegalBrief>>(`/legal-briefs/${id}`)
    return unwrapResource(data)
  },
  async create(payload: {
    legal_matter_id: number
    title: string
    brief_type?: string
    jurisdiction?: string
    court_type?: string
    cause_of_action?: string
    case_facts?: string
    statutes?: string
    desired_outcome?: string
    citation_style?: string
    content_html?: string
  }): Promise<LegalBrief> {
    const { data } = await api.post<ResourcePayload<LegalBrief>>('/legal-briefs', payload)
    return unwrapResource(data)
  },
  async update(
    id: number,
    payload: {
      title?: string
      brief_type?: string
      jurisdiction?: string
      court_type?: string
      cause_of_action?: string
      case_facts?: string
      statutes?: string
      desired_outcome?: string
      citation_style?: string
      content_html?: string
      last_ai_governance_log_id?: number | null
    },
  ): Promise<LegalBrief> {
    const { data } = await api.put<ResourcePayload<LegalBrief>>(`/legal-briefs/${id}`, payload)
    return unwrapResource(data)
  },
  async updateStatus(id: number, status: string): Promise<LegalBrief> {
    const { data } = await api.patch<ResourcePayload<LegalBrief>>(`/legal-briefs/${id}/status`, {
      status,
    })
    return unwrapResource(data)
  },
  async addCitation(
    id: number,
    payload: {
      authority: string
      citation_text: string
      source_note?: string
    },
  ): Promise<BriefCitation> {
    const { data } = await api.post<ResourcePayload<BriefCitation>>(
      `/legal-briefs/${id}/citations`,
      payload,
    )
    return unwrapResource(data)
  },
  async removeCitation(briefId: number, citationId: number): Promise<void> {
    await api.delete(`/legal-briefs/${briefId}/citations/${citationId}`)
  },
  async export(
    id: number,
    format: 'html' | 'word' | 'pdf' | 'court_filing' | 'google_docs' = 'word',
  ): Promise<Blob | { message: string; content_html: string; export_url_hint?: string }> {
    const { data, headers } = await api.get<Blob | { message: string; content_html: string; export_url_hint?: string }>(
      `/legal-briefs/${id}/export`,
      {
        params: { format },
        responseType: format === 'google_docs' ? 'json' : 'blob',
      },
    )
    if (format === 'google_docs') {
      return data as { message: string; content_html: string; export_url_hint?: string }
    }
    return new Blob([data as BlobPart], {
      type: String(headers['content-type'] ?? 'application/octet-stream'),
    })
  },
}

export const motionTemplatesApi = {
  async list(): Promise<MotionTemplate[]> {
    const { data } = await api.get<ListPayload<MotionTemplate>>('/motion-templates')
    return unwrapList(data)
  },
}

export const motionsApi = {
  async list(filters: {
    legal_matter_id?: number
    status?: string
  } = {}): Promise<{ motions: LegalMotion[]; statuses: string[] }> {
    const { data } = await api.get<{
      data: LegalMotion[]
      meta?: { statuses?: string[] }
    }>('/legal-motions', { params: { per_page: 100, ...filters } })
    return {
      motions: data.data,
      statuses: data.meta?.statuses ?? [],
    }
  },
  async get(id: number): Promise<LegalMotion> {
    const { data } = await api.get<ResourcePayload<LegalMotion>>(`/legal-motions/${id}`)
    return unwrapResource(data)
  },
  async create(payload: {
    legal_matter_id: number
    title: string
    motion_template_id?: number
    content_html?: string
    motion_type?: string
  }): Promise<LegalMotion> {
    const { data } = await api.post<ResourcePayload<LegalMotion>>('/legal-motions', payload)
    return unwrapResource(data)
  },
  async update(
    id: number,
    payload: {
      title?: string
      content_html?: string
      last_ai_governance_log_id?: number | null
    },
  ): Promise<LegalMotion> {
    const { data } = await api.put<ResourcePayload<LegalMotion>>(`/legal-motions/${id}`, payload)
    return unwrapResource(data)
  },
  async updateStatus(id: number, status: string): Promise<LegalMotion> {
    const { data } = await api.patch<ResourcePayload<LegalMotion>>(`/legal-motions/${id}/status`, {
      status,
    })
    return unwrapResource(data)
  },
  async createFiling(id: number, payload: { court?: string } = {}): Promise<LegalMotion> {
    const { data } = await api.post<ResourcePayload<LegalMotion>>(
      `/legal-motions/${id}/create-filing`,
      payload,
    )
    return unwrapResource(data)
  },
}

export const courtFilingsApi = {
  async list(filters: {
    legal_matter_id?: number
    status?: string
    court?: string
  } = {}): Promise<{ filings: CourtFiling[]; statuses: string[]; filingMethods: string[] }> {
    const { data } = await api.get<{
      data: CourtFiling[]
      meta?: { statuses?: string[]; filing_methods?: string[] }
    }>('/court-filings', { params: { per_page: 100, ...filters } })
    return {
      filings: data.data,
      statuses: data.meta?.statuses ?? [],
      filingMethods: data.meta?.filing_methods ?? [],
    }
  },
  async create(payload: {
    legal_matter_id: number
    title: string
    court: string
    filing_method?: string
    notes?: string
    court_form_instance_id?: number
  }): Promise<CourtFiling> {
    const { data } = await api.post<ResourcePayload<CourtFiling>>('/court-filings', payload)
    return unwrapResource(data)
  },
  async updateStatus(
    id: number,
    payload: {
      status: string
      court_response?: string
      correction_deadline?: string
      court_reference_number?: string
      filing_date?: string
    },
  ): Promise<CourtFiling> {
    const { data } = await api.patch<ResourcePayload<CourtFiling>>(
      `/court-filings/${id}/status`,
      payload,
    )
    return unwrapResource(data)
  },
}

export const researchEntriesApi = {
  async list(filters: {
    keyword?: string
    jurisdiction?: string
    document_type?: string
  } = {}): Promise<{ entries: LegalResearchEntry[]; documentTypes: string[] }> {
    const { data } = await api.get<{
      data: LegalResearchEntry[]
      meta?: { document_types?: string[] }
    }>('/legal-research-entries', { params: { per_page: 100, ...filters } })
    return {
      entries: data.data,
      documentTypes: data.meta?.document_types ?? [],
    }
  },
  async get(id: number): Promise<LegalResearchEntry> {
    const { data } = await api.get<ResourcePayload<LegalResearchEntry>>(
      `/legal-research-entries/${id}`,
    )
    return unwrapResource(data)
  },
  async create(payload: {
    title: string
    citation?: string
    summary?: string
    jurisdiction?: string
    document_type?: string
    tags?: string[]
  }): Promise<LegalResearchEntry> {
    const { data } = await api.post<ResourcePayload<LegalResearchEntry>>(
      '/legal-research-entries',
      payload,
    )
    return unwrapResource(data)
  },
  async update(
    id: number,
    payload: {
      title?: string
      citation?: string
      summary?: string
      jurisdiction?: string
      document_type?: string
      tags?: string[]
    },
  ): Promise<LegalResearchEntry> {
    const { data } = await api.put<ResourcePayload<LegalResearchEntry>>(
      `/legal-research-entries/${id}`,
      payload,
    )
    return unwrapResource(data)
  },
  async remove(id: number): Promise<void> {
    await api.delete(`/legal-research-entries/${id}`)
  },
}

export const researchFoldersApi = {
  async list(filters: {
    legal_matter_id?: number
    practice_area?: string
  } = {}): Promise<ResearchFolder[]> {
    const { data } = await api.get<ListPayload<ResearchFolder>>('/research-folders', {
      params: { per_page: 100, ...filters },
    })
    return unwrapList(data)
  },
  async get(id: number): Promise<ResearchFolder> {
    const { data } = await api.get<ResourcePayload<ResearchFolder>>(`/research-folders/${id}`)
    return unwrapResource(data)
  },
  async create(payload: {
    name: string
    legal_matter_id?: number
    description?: string
    practice_area?: string
    legal_issue?: string
  }): Promise<ResearchFolder> {
    const { data } = await api.post<ResourcePayload<ResearchFolder>>('/research-folders', payload)
    return unwrapResource(data)
  },
  async update(
    id: number,
    payload: {
      name?: string
      description?: string
      practice_area?: string
      legal_issue?: string
    },
  ): Promise<ResearchFolder> {
    const { data } = await api.put<ResourcePayload<ResearchFolder>>(
      `/research-folders/${id}`,
      payload,
    )
    return unwrapResource(data)
  },
  async remove(id: number): Promise<void> {
    await api.delete(`/research-folders/${id}`)
  },
  async listItems(folderId: number): Promise<ResearchSavedItem[]> {
    const { data } = await api.get<ListPayload<ResearchSavedItem>>(
      `/research-folders/${folderId}/items`,
    )
    return unwrapList(data)
  },
  async saveItem(
    folderId: number,
    payload: {
      legal_research_entry_id: number
      legal_matter_id?: number
      notes?: string
    },
  ): Promise<ResearchSavedItem> {
    const { data } = await api.post<ResourcePayload<ResearchSavedItem>>(
      `/research-folders/${folderId}/items`,
      payload,
    )
    return unwrapResource(data)
  },
}

export const researchProjectsApi = {
  async list(filters: { legal_matter_id?: number } = {}): Promise<ResearchProject[]> {
    const { data } = await api.get<ListPayload<ResearchProject>>('/research-projects', {
      params: { per_page: 100, ...filters },
    })
    return unwrapList(data)
  },
  async get(id: number): Promise<ResearchProject> {
    const { data } = await api.get<ResourcePayload<ResearchProject>>(`/research-projects/${id}`)
    return unwrapResource(data)
  },
  async create(payload: {
    name: string
    legal_matter_id?: number
    description?: string
    case_theory?: string
    jurisdiction?: string
    practice_area?: string
  }): Promise<ResearchProject> {
    const { data } = await api.post<ResourcePayload<ResearchProject>>('/research-projects', payload)
    return unwrapResource(data)
  },
  async update(
    id: number,
    payload: {
      name?: string
      legal_matter_id?: number
      description?: string
      case_theory?: string
      jurisdiction?: string
      practice_area?: string
    },
  ): Promise<ResearchProject> {
    const { data } = await api.put<ResourcePayload<ResearchProject>>(`/research-projects/${id}`, payload)
    return unwrapResource(data)
  },
  async remove(id: number): Promise<void> {
    await api.delete(`/research-projects/${id}`)
  },
  async messages(projectId: number): Promise<ResearchChatMessage[]> {
    const { data } = await api.get<ListPayload<ResearchChatMessage>>(
      `/research-projects/${projectId}/messages`,
    )
    return unwrapList(data)
  },
  async transferToBrief(
    projectId: number,
    payload: {
      legal_matter_id: number
      legal_brief_id?: number
      title?: string
      content_html?: string
      append?: boolean
    },
  ): Promise<LegalBrief> {
    const { data } = await api.post<ResourcePayload<LegalBrief>>(
      `/research-projects/${projectId}/transfer-to-brief`,
      payload,
    )
    return unwrapResource(data)
  },
}

export const researchSavedItemsApi = {
  async list(filters: {
    legal_matter_id?: number
    research_folder_id?: number
  } = {}): Promise<ResearchSavedItem[]> {
    const { data } = await api.get<ListPayload<ResearchSavedItem>>('/research-saved-items', {
      params: { per_page: 100, ...filters },
    })
    return unwrapList(data)
  },
  async remove(id: number): Promise<void> {
    await api.delete(`/research-saved-items/${id}`)
  },
}

export const knowledgeArticlesApi = {
  async list(filters: {
    keyword?: string
    category?: string
    content_type?: string
    practice_area?: string
    tag?: string
    legal_matter_id?: number
    published_only?: boolean
  } = {}): Promise<{
    articles: KnowledgeArticle[]
    contentTypes: string[]
    categories: string[]
  }> {
    const { data } = await api.get<{
      data: KnowledgeArticle[]
      meta?: { content_types?: string[]; categories?: string[] }
    }>('/knowledge-articles', { params: { per_page: 100, ...filters } })
    return {
      articles: data.data,
      contentTypes: data.meta?.content_types ?? [],
      categories: data.meta?.categories ?? [],
    }
  },
  async get(id: number): Promise<KnowledgeArticle> {
    const { data } = await api.get<ResourcePayload<KnowledgeArticle>>(`/knowledge-articles/${id}`)
    return unwrapResource(data)
  },
  async create(payload: {
    legal_matter_id?: number
    title: string
    content?: string
    excerpt?: string
    content_type?: string
    category?: string
    practice_area?: string
    tags?: string[]
    is_published?: boolean
  }): Promise<KnowledgeArticle> {
    const { data } = await api.post<ResourcePayload<KnowledgeArticle>>(
      '/knowledge-articles',
      payload,
    )
    return unwrapResource(data)
  },
  async update(
    id: number,
    payload: {
      legal_matter_id?: number | null
      title?: string
      content?: string
      excerpt?: string
      content_type?: string
      category?: string
      practice_area?: string
      tags?: string[]
      is_published?: boolean
    },
  ): Promise<KnowledgeArticle> {
    const { data } = await api.put<ResourcePayload<KnowledgeArticle>>(
      `/knowledge-articles/${id}`,
      payload,
    )
    return unwrapResource(data)
  },
  async remove(id: number): Promise<void> {
    await api.delete(`/knowledge-articles/${id}`)
  },
}

export const ediscoveryApi = {
  async listCollections(filters: {
    legal_matter_id?: number
    status?: string
  } = {}): Promise<{ collections: EdiscoveryCollection[]; statuses: string[] }> {
    const { data } = await api.get<{
      data: EdiscoveryCollection[]
      meta?: { statuses?: string[] }
    }>('/ediscovery-collections', { params: { per_page: 100, ...filters } })
    return {
      collections: data.data,
      statuses: data.meta?.statuses ?? [],
    }
  },
  async createCollection(payload: {
    legal_matter_id: number
    name: string
    description?: string
  }): Promise<EdiscoveryCollection> {
    const { data } = await api.post<ResourcePayload<EdiscoveryCollection>>(
      '/ediscovery-collections',
      payload,
    )
    return unwrapResource(data)
  },
  async listDocuments(filters: {
    legal_matter_id?: number
    ediscovery_collection_id?: number
    privilege?: string
    relevance?: string
    review_status?: string
    file_type?: string
    tag?: string
    reviewer_id?: number
    keyword?: string
    sender?: string
    recipient?: string
    document_date_from?: string
    document_date_to?: string
  } = {}): Promise<{
    documents: EdiscoveryDocument[]
    privileges: string[]
    relevances: string[]
    reviewStatuses: string[]
    fileTypes: string[]
  }> {
    const { data } = await api.get<{
      data: EdiscoveryDocument[]
      meta?: {
        privileges?: string[]
        relevances?: string[]
        review_statuses?: string[]
        file_types?: string[]
      }
    }>('/ediscovery-documents', { params: { per_page: 100, ...filters } })
    return {
      documents: data.data,
      privileges: data.meta?.privileges ?? [],
      relevances: data.meta?.relevances ?? [],
      reviewStatuses: data.meta?.review_statuses ?? [],
      fileTypes: data.meta?.file_types ?? [],
    }
  },
  async bulkUpload(
    caseId: number,
    collectionId: number,
    files: File[],
    options: {
      default_privilege?: string
      default_relevance?: string
      custom_tags?: string[]
    } = {},
  ): Promise<{ count: number; documents: EdiscoveryDocument[] }> {
    const form = new FormData()
    form.append('legal_matter_id', String(caseId))
    form.append('ediscovery_collection_id', String(collectionId))
    files.forEach((file, index) => form.append(`files[${index}]`, file))
    if (options.default_privilege) form.append('default_privilege', options.default_privilege)
    if (options.default_relevance) form.append('default_relevance', options.default_relevance)
    options.custom_tags?.forEach((tag, index) => form.append(`custom_tags[${index}]`, tag))

    const { data } = await api.post<{
      count: number
      data: EdiscoveryDocument[]
    }>('/ediscovery-documents/bulk-upload', form, {
      headers: { 'Content-Type': 'multipart/form-data' },
    })
    return { count: data.count, documents: data.data }
  },
  async updateTags(
    id: number,
    payload: {
      privilege?: string
      relevance?: string
      custom_tags?: string[]
      notes?: string
    },
  ): Promise<EdiscoveryDocument> {
    const { data } = await api.patch<ResourcePayload<EdiscoveryDocument>>(
      `/ediscovery-documents/${id}/tags`,
      payload,
    )
    return unwrapResource(data)
  },
  async updateReviewStatus(id: number, review_status: string): Promise<EdiscoveryDocument> {
    const { data } = await api.patch<ResourcePayload<EdiscoveryDocument>>(
      `/ediscovery-documents/${id}/review-status`,
      { review_status },
    )
    return unwrapResource(data)
  },
  async assignReviewer(payload: {
    ediscovery_document_id: number
    reviewer_id: number
    notes?: string
  }): Promise<EdiscoveryReviewAssignment> {
    const { data } = await api.post<ResourcePayload<EdiscoveryReviewAssignment>>(
      '/ediscovery-review-assignments',
      payload,
    )
    return unwrapResource(data)
  },
  async listAssignments(filters: {
    legal_matter_id?: number
    ediscovery_document_id?: number
    reviewer_id?: number
    review_status?: string
  } = {}): Promise<EdiscoveryReviewAssignment[]> {
    const { data } = await api.get<ListPayload<EdiscoveryReviewAssignment>>(
      '/ediscovery-review-assignments',
      { params: { per_page: 100, ...filters } },
    )
    return unwrapList(data)
  },
  async updateAssignment(
    id: number,
    payload: { review_status?: string; notes?: string },
  ): Promise<EdiscoveryReviewAssignment> {
    const { data } = await api.put<ResourcePayload<EdiscoveryReviewAssignment>>(
      `/ediscovery-review-assignments/${id}`,
      payload,
    )
    return unwrapResource(data)
  },
  async listTags(filters: {
    legal_matter_id?: number
    category?: string
  } = {}): Promise<EdiscoveryTag[]> {
    const { data } = await api.get<ListPayload<EdiscoveryTag>>('/ediscovery-tags', {
      params: { per_page: 100, ...filters },
    })
    return unwrapList(data)
  },
  async createTag(payload: {
    legal_matter_id?: number
    name: string
    color?: string
    category?: string
  }): Promise<EdiscoveryTag> {
    const { data } = await api.post<ResourcePayload<EdiscoveryTag>>('/ediscovery-tags', payload)
    return unwrapResource(data)
  },
  async reviewProgress(legalMatterId: number): Promise<EdiscoveryReviewProgress> {
    const { data } = await api.get<EdiscoveryReviewProgress>('/ediscovery-review-progress', {
      params: { legal_matter_id: legalMatterId },
    })
    return data
  },
}

export const legalProjectsApi = {
  async listMilestones(filters: {
    legal_matter_id?: number
    status?: string
    assigned_to?: number
  } = {}): Promise<{ milestones: LegalProjectMilestone[]; milestoneTypes: string[]; statuses: string[] }> {
    const { data } = await api.get<{
      data: LegalProjectMilestone[]
      meta?: { milestone_types?: string[]; statuses?: string[] }
    }>('/legal-project-milestones', { params: { per_page: 100, ...filters } })
    return {
      milestones: data.data,
      milestoneTypes: data.meta?.milestone_types ?? [],
      statuses: data.meta?.statuses ?? [],
    }
  },
  async createMilestone(payload: {
    legal_matter_id: number
    title: string
    description?: string
    milestone_type?: string
    status?: string
    due_at?: string
    assigned_to?: number
  }): Promise<LegalProjectMilestone> {
    const { data } = await api.post<ResourcePayload<LegalProjectMilestone>>(
      '/legal-project-milestones',
      payload,
    )
    return unwrapResource(data)
  },
  async updateMilestone(
    id: number,
    payload: Partial<{
      title: string
      description: string
      milestone_type: string
      status: string
      due_at: string
      assigned_to: number
    }>,
  ): Promise<LegalProjectMilestone> {
    const { data } = await api.put<ResourcePayload<LegalProjectMilestone>>(
      `/legal-project-milestones/${id}`,
      payload,
    )
    return unwrapResource(data)
  },
  async listBudgets(filters: {
    legal_matter_id?: number
    category?: string
  } = {}): Promise<{
    budgets: LegalProjectBudget[]
    categories: string[]
    totals: { budgeted: number; actual: number }
  }> {
    const { data } = await api.get<{
      data: LegalProjectBudget[]
      meta?: { categories?: string[]; totals?: { budgeted: number; actual: number } }
    }>('/legal-project-budgets', { params: { per_page: 100, ...filters } })
    return {
      budgets: data.data,
      categories: data.meta?.categories ?? [],
      totals: data.meta?.totals ?? { budgeted: 0, actual: 0 },
    }
  },
  async createBudget(payload: {
    legal_matter_id: number
    category?: string
    description: string
    budgeted_amount?: number
    actual_amount?: number
    notes?: string
  }): Promise<LegalProjectBudget> {
    const { data } = await api.post<ResourcePayload<LegalProjectBudget>>(
      '/legal-project-budgets',
      payload,
    )
    return unwrapResource(data)
  },
  async updateBudget(
    id: number,
    payload: Partial<{
      category: string
      description: string
      budgeted_amount: number
      actual_amount: number
      notes: string
    }>,
  ): Promise<LegalProjectBudget> {
    const { data } = await api.put<ResourcePayload<LegalProjectBudget>>(
      `/legal-project-budgets/${id}`,
      payload,
    )
    return unwrapResource(data)
  },
  async workload(): Promise<LegalProjectWorkload> {
    const { data } = await api.get<LegalProjectWorkload>('/legal-project-workload')
    return data
  },
  async taskBoard(): Promise<TaskWorkloadBoard> {
    return taskWorkloadApi.board()
  },
}

export const legalAnalyticsApi = {
  async dashboard(filters: { from_date?: string; to_date?: string } = {}): Promise<LegalAnalyticsDashboard> {
    const { data } = await api.get<LegalAnalyticsDashboard>('/legal-analytics/dashboard', { params: filters })
    return data
  },
  async hints(): Promise<{ disclaimer: string; requires_review: boolean; hints: LegalAnalyticsHint[] }> {
    const { data } = await api.get<{ disclaimer: string; requires_review: boolean; hints: LegalAnalyticsHint[] }>(
      '/legal-analytics/hints',
    )
    return data
  },
}

export const trainingApi = {
  async listCourses(filters: { keyword?: string } = {}): Promise<TrainingCourse[]> {
    const { data } = await api.get<ListPayload<TrainingCourse>>('/training-courses', {
      params: { per_page: 100, ...filters },
    })
    return unwrapList(data)
  },
  async getCourse(id: number): Promise<TrainingCourse> {
    const { data } = await api.get<ResourcePayload<TrainingCourse>>(`/training-courses/${id}`)
    return unwrapResource(data)
  },
  async createCourse(payload: {
    title: string
    description?: string
    content?: string
    cle_credits?: number
    is_required?: boolean
    quiz_questions?: TrainingQuizQuestion[]
  }): Promise<TrainingCourse> {
    const { data } = await api.post<ResourcePayload<TrainingCourse>>('/training-courses', payload)
    return unwrapResource(data)
  },
  async listEnrollments(filters: {
    user_id?: number
    training_course_id?: number
    status?: string
  } = {}): Promise<TrainingEnrollment[]> {
    const { data } = await api.get<ListPayload<TrainingEnrollment>>('/training-enrollments', {
      params: { per_page: 100, ...filters },
    })
    return unwrapList(data)
  },
  async assignCourse(payload: { training_course_id: number; user_id: number }): Promise<TrainingEnrollment> {
    const { data } = await api.post<ResourcePayload<TrainingEnrollment>>('/training-enrollments', payload)
    return unwrapResource(data)
  },
  async startEnrollment(id: number): Promise<TrainingEnrollment> {
    const { data } = await api.put<ResourcePayload<TrainingEnrollment>>(`/training-enrollments/${id}`, {
      status: 'in_progress',
    })
    return unwrapResource(data)
  },
  async submitQuiz(
    enrollmentId: number,
    answers: number[],
  ): Promise<{
    score: number
    passed: boolean
    passing_score: number
    cle_credits_earned: number | null
    enrollment: TrainingEnrollment
    certificate: { certificate_number: string; issued_at?: string } | null
  }> {
    const { data } = await api.post(`/training-enrollments/${enrollmentId}/submit-quiz`, { answers })
    return data
  },
  async complianceReport(): Promise<{
    required_courses_count: number
    staff_count: number
    rows: TrainingComplianceRow[]
  }> {
    const { data } = await api.get('/training-compliance/report')
    return data
  },
}

export const searchApi = {
  async search(query: string): Promise<SearchResponse> {
    const { data } = await api.get<SearchResponse>('/search', { params: { q: query } })
    return data
  },
}

export const auditApi = {
  async list(
    filters: AuditLogFilters = {},
  ): Promise<{ logs: AuditLog[]; meta: PaginatedResponse<AuditLog>['meta'] }> {
    const { data } = await api.get<PaginatedResponse<AuditLog>>('/audit-logs', { params: filters })
    return { logs: data.data, meta: data.meta }
  },
}

export type TwoFactorEnableResponse = {
  secret: string
  otpauth_url: string
  issuer: string
  account: string
}

export type TwoFactorStatusResponse = {
  enabled: boolean
  confirmed_at: string | null
}

export const twoFactorApi = {
  async status(): Promise<TwoFactorStatusResponse> {
    const { data } = await api.get<TwoFactorStatusResponse>('/auth/two-factor/status')
    return data
  },

  async enable(): Promise<TwoFactorEnableResponse> {
    const { data } = await api.post<TwoFactorEnableResponse>('/auth/two-factor/enable')
    return data
  },

  async confirm(code: string): Promise<{ message: string; enabled: boolean; user?: User }> {
    const { data } = await api.post<{ message: string; enabled: boolean; user?: User }>(
      '/auth/two-factor/confirm',
      { code },
    )
    return data
  },

  async disable(
    password: string,
    code: string,
  ): Promise<{ message: string; enabled: boolean; user?: User }> {
    const { data } = await api.post<{ message: string; enabled: boolean; user?: User }>(
      '/auth/two-factor/disable',
      { password, code },
    )
    return data
  },
}
