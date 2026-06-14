import axios from 'axios'
import { requestDone, requestStart } from '@/lib/progress'
import type {
  Invoice,
  LegalMatter,
  PaymentGateways,
  PortalDashboardData,
  PortalDocument,
  PortalUser,
  Message,
  MessageThread,
  Appointment,
  AvailableSlot,
  PortalLawyer,
  IntakeForm,
  IntakeSubmission,
  SignatureRequest,
} from '@/types'

const PORTAL_TOKEN_KEY = 'banwohub_portal_token'

function resolveApiUrl(): string {
  if (import.meta.env.VITE_API_URL) {
    return import.meta.env.VITE_API_URL as string
  }
  if (typeof window !== 'undefined') {
    const { port } = window.location
    if (port === '3000' || port === '') {
      return 'http://127.0.0.1:8000/api/v1'
    }
    return `${window.location.origin}/api/v1`
  }
  return 'http://127.0.0.1:8000/api/v1'
}

export const portalApi = axios.create({
  baseURL: resolveApiUrl(),
  headers: {
    Accept: 'application/json',
    'Content-Type': 'application/json',
  },
})

portalApi.interceptors.request.use((config) => {
  requestStart()
  return config
})

portalApi.interceptors.response.use(
  (response) => {
    requestDone()
    return response
  },
  (error) => {
    requestDone()
    return Promise.reject(error)
  },
)

export function setPortalAuthToken(token: string | null) {
  if (token) {
    portalApi.defaults.headers.common.Authorization = `Bearer ${token}`
  } else {
    delete portalApi.defaults.headers.common.Authorization
  }
}

export function getStoredPortalToken(): string | null {
  if (typeof window === 'undefined') return null
  return localStorage.getItem(PORTAL_TOKEN_KEY)
}

export function persistPortalToken(token: string | null) {
  if (typeof window === 'undefined') return
  if (token) {
    localStorage.setItem(PORTAL_TOKEN_KEY, token)
    setPortalAuthToken(token)
  } else {
    localStorage.removeItem(PORTAL_TOKEN_KEY)
    setPortalAuthToken(null)
  }
}

if (typeof window !== 'undefined') {
  const token = getStoredPortalToken()
  if (token) setPortalAuthToken(token)
}

type ListPayload<T> = T[] | { data: T[] }

function unwrapList<T>(payload: ListPayload<T>): T[] {
  return Array.isArray(payload) ? payload : payload.data
}

type ResourcePayload<T> = T | { data: T }

function unwrapResource<T>(payload: ResourcePayload<T>): T {
  return 'data' in (payload as { data?: T }) ? (payload as { data: T }).data : (payload as T)
}

export const portalAuthApi = {
  async login(email: string, password: string) {
    const { data } = await portalApi.post<{ token: string; user: PortalUser }>(
      '/portal/auth/login',
      { email, password },
    )
    return data
  },
  async logout() {
    await portalApi.post('/portal/auth/logout')
  },
  async me() {
    const { data } = await portalApi.get<{ data: PortalUser } | PortalUser>('/portal/auth/me')
    return unwrapResource(data)
  },
  async updateProfile(payload: { name: string; phone?: string | null }) {
    const { data } = await portalApi.patch<{ data: PortalUser } | PortalUser>(
      '/portal/auth/profile',
      payload,
    )
    return unwrapResource(data)
  },
}

export const portalDashboardApi = {
  async get(): Promise<PortalDashboardData> {
    const { data } = await portalApi.get<PortalDashboardData>('/portal/dashboard')
    return data
  },
}

export const portalCasesApi = {
  async list(): Promise<LegalMatter[]> {
    const { data } = await portalApi.get<ListPayload<LegalMatter>>('/portal/cases', {
      params: { per_page: 100 },
    })
    return unwrapList(data)
  },
  async get(id: number): Promise<LegalMatter> {
    const { data } = await portalApi.get<ResourcePayload<LegalMatter>>(`/portal/cases/${id}`)
    return unwrapResource(data)
  },
}

export const portalDocumentsApi = {
  async list(caseId?: number, scope: 'shared' | 'pending' = 'shared'): Promise<PortalDocument[]> {
    const { data } = await portalApi.get<ListPayload<PortalDocument>>('/portal/documents', {
      params: {
        per_page: 100,
        legal_matter_id: caseId || undefined,
        scope,
      },
    })
    return unwrapList(data)
  },
  async upload(
    caseId: number,
    payload: { file: File; name?: string; category?: string; description?: string },
  ): Promise<PortalDocument> {
    const form = new FormData()
    form.append('legal_matter_id', String(caseId))
    form.append('file', payload.file)
    if (payload.name) form.append('name', payload.name)
    if (payload.category) form.append('category', payload.category)
    if (payload.description) form.append('description', payload.description)

    const { data } = await portalApi.post<ResourcePayload<PortalDocument>>('/portal/documents', form, {
      headers: { 'Content-Type': 'multipart/form-data' },
    })
    return unwrapResource(data)
  },
  async download(documentId: number, filename: string): Promise<void> {
    const { data } = await portalApi.get<Blob>(`/portal/documents/${documentId}/download`, {
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
}

export const portalInvoicesApi = {
  async list(): Promise<Invoice[]> {
    const { data } = await portalApi.get<ListPayload<Invoice>>('/portal/invoices', {
      params: { per_page: 100 },
    })
    return unwrapList(data)
  },
  async get(id: number): Promise<Invoice> {
    const { data } = await portalApi.get<Invoice>(`/portal/invoices/${id}`)
    return data
  },
  async paymentGateways(): Promise<PaymentGateways> {
    const { data } = await portalApi.get<{ gateways: PaymentGateways }>(
      '/portal/invoices/payment/gateways',
    )
    return data.gateways
  },
  async checkoutStripe(invoiceId: number): Promise<{ checkout_url: string; session_id: string }> {
    const { data } = await portalApi.post<{ checkout_url: string; session_id: string }>(
      `/portal/invoices/${invoiceId}/checkout/stripe`,
    )
    return data
  },
  async checkoutPayPal(invoiceId: number): Promise<{ approval_url: string; order_id: string }> {
    const { data } = await portalApi.post<{ approval_url: string; order_id: string }>(
      `/portal/invoices/${invoiceId}/checkout/paypal`,
    )
    return data
  },
  async capturePayPal(orderId: string): Promise<{ captured: boolean; invoice_id: number }> {
    const { data } = await portalApi.post<{ captured: boolean; invoice_id: number }>(
      '/portal/invoices/payment/paypal/capture',
      { order_id: orderId },
    )
    return data
  },
}

export const portalAppointmentsApi = {
  async lawyers(): Promise<PortalLawyer[]> {
    const { data } = await portalApi.get<{ data: PortalLawyer[] }>('/portal/lawyers')
    return data.data ?? []
  },
  async availableSlots(userId: number, date: string): Promise<AvailableSlot[]> {
    const { data } = await portalApi.get<{ data: AvailableSlot[] }>(
      '/portal/appointments/available-slots',
      { params: { user_id: userId, date } },
    )
    return data.data ?? []
  },
  async list(): Promise<Appointment[]> {
    const { data } = await portalApi.get<ListPayload<Appointment>>('/portal/appointments', {
      params: { per_page: 100 },
    })
    return unwrapList(data)
  },
  async book(payload: {
    user_id: number
    legal_matter_id?: number | null
    consultation_type: string
    starts_at: string
    ends_at: string
    location?: string | null
    online_meeting?: boolean
    fee?: number | null
    notes?: string | null
  }): Promise<Appointment> {
    const { data } = await portalApi.post<ResourcePayload<Appointment>>('/portal/appointments', payload)
    return unwrapResource(data)
  },
  async cancel(id: number): Promise<Appointment> {
    const { data } = await portalApi.post<ResourcePayload<Appointment>>(
      `/portal/appointments/${id}/cancel`,
    )
    return unwrapResource(data)
  },
}

export const portalIntakeApi = {
  async list(): Promise<IntakeForm[]> {
    const { data } = await portalApi.get<ListPayload<IntakeForm>>('/portal/intake-forms', {
      params: { per_page: 100 },
    })
    return unwrapList(data)
  },
  async get(id: number): Promise<IntakeForm> {
    const { data } = await portalApi.get<ResourcePayload<IntakeForm>>(`/portal/intake-forms/${id}`)
    return unwrapResource(data)
  },
  async submit(
    formId: number,
    payload: { data: Record<string, unknown>; submitter_phone?: string },
  ): Promise<IntakeSubmission> {
    const { data } = await portalApi.post<ResourcePayload<IntakeSubmission>>(
      `/portal/intake-forms/${formId}/submit`,
      payload,
    )
    return unwrapResource(data)
  },
}

export const portalSignaturesApi = {
  async list(caseId?: number, status?: string): Promise<SignatureRequest[]> {
    const { data } = await portalApi.get<ListPayload<SignatureRequest>>('/portal/signature-requests', {
      params: {
        per_page: 100,
        legal_matter_id: caseId || undefined,
        status: status || undefined,
      },
    })
    return unwrapList(data)
  },
  async get(id: number): Promise<SignatureRequest> {
    const { data } = await portalApi.get<ResourcePayload<SignatureRequest>>(
      `/portal/signature-requests/${id}`,
    )
    return unwrapResource(data)
  },
  async sign(
    id: number,
    payload: { field_values: Record<string, string>; method?: 'canvas' | 'typed' },
  ): Promise<SignatureRequest> {
    const { data } = await portalApi.post<ResourcePayload<SignatureRequest>>(
      `/portal/signature-requests/${id}/sign`,
      payload,
    )
    return unwrapResource(data)
  },
  async decline(id: number, reason?: string): Promise<SignatureRequest> {
    const { data } = await portalApi.post<ResourcePayload<SignatureRequest>>(
      `/portal/signature-requests/${id}/decline`,
      { reason },
    )
    return unwrapResource(data)
  },
}

export const portalMessagesApi = {
  async list(caseId?: number): Promise<MessageThread[]> {
    const { data } = await portalApi.get<ListPayload<MessageThread>>('/portal/message-threads', {
      params: { per_page: 100, legal_matter_id: caseId || undefined },
    })
    return unwrapList(data)
  },
  async get(id: number): Promise<MessageThread> {
    const { data } = await portalApi.get<ResourcePayload<MessageThread>>(
      `/portal/message-threads/${id}`,
    )
    return unwrapResource(data)
  },
  async create(payload: {
    legal_matter_id?: number | null
    subject: string
    body: string
  }): Promise<MessageThread> {
    const { data } = await portalApi.post<ResourcePayload<MessageThread>>(
      '/portal/message-threads',
      payload,
    )
    return unwrapResource(data)
  },
  async sendMessage(threadId: number, body: string): Promise<Message> {
    const { data } = await portalApi.post<ResourcePayload<Message>>(
      `/portal/message-threads/${threadId}/messages`,
      { body },
    )
    return unwrapResource(data)
  },
  async markRead(threadId: number): Promise<void> {
    await portalApi.post(`/portal/message-threads/${threadId}/mark-read`)
  },
}
