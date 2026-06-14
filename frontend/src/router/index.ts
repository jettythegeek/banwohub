import { createRouter, createWebHistory } from 'vue-router'
import { routeDone, routeStart } from '@/lib/progress'
import { useAuthStore } from '@/stores/auth'
import { usePortalAuthStore } from '@/stores/portalAuth'

const AppLayout = () => import('@/components/layout/AppLayout.vue')
const PortalLayout = () => import('@/components/layout/PortalLayout.vue')

const loaders = {
  login: () => import('@/views/LoginView.vue'),
  forgotPassword: () => import('@/views/ForgotPasswordView.vue'),
  resetPassword: () => import('@/views/ResetPasswordView.vue'),
  dashboard: () => import('@/views/DashboardView.vue'),
  clientsList: () => import('@/views/clients/ClientsListView.vue'),
  clientForm: () => import('@/views/clients/ClientFormView.vue'),
  clientDetail: () => import('@/views/clients/ClientDetailView.vue'),
  casesList: () => import('@/views/cases/CasesListView.vue'),
  caseForm: () => import('@/views/cases/CaseFormView.vue'),
  caseDetail: () => import('@/views/cases/CaseDetailView.vue'),
  intake: () => import('@/views/IntakeView.vue'),
  conflictChecks: () => import('@/views/ConflictChecksView.vue'),
  timeTracking: () => import('@/views/TimeTrackingView.vue'),
  invoicesList: () => import('@/views/invoices/InvoicesListView.vue'),
  invoiceDetail: () => import('@/views/invoices/InvoiceDetailView.vue'),
  notifications: () => import('@/views/NotificationsView.vue'),
  settings: () => import('@/views/SettingsView.vue'),
  search: () => import('@/views/SearchView.vue'),
  audit: () => import('@/views/AuditView.vue'),
  users: () => import('@/views/settings/UsersView.vue'),
  portalLogin: () => import('@/views/portal/PortalLoginView.vue'),
  portalDashboard: () => import('@/views/portal/PortalDashboardView.vue'),
  portalCases: () => import('@/views/portal/PortalCasesListView.vue'),
  portalCaseDetail: () => import('@/views/portal/PortalCaseDetailView.vue'),
  portalInvoices: () => import('@/views/portal/PortalInvoicesListView.vue'),
  portalInvoiceDetail: () => import('@/views/portal/PortalInvoiceDetailView.vue'),
  portalInvoicePaymentSuccess: () => import('@/views/portal/PortalInvoicePaymentSuccessView.vue'),
  portalInvoicePaymentCancel: () => import('@/views/portal/PortalInvoicePaymentCancelView.vue'),
  portalMessages: () => import('@/views/portal/PortalMessagesView.vue'),
  messages: () => import('@/views/MessagesView.vue'),
  calendar: () => import('@/views/CalendarView.vue'),
  reports: () => import('@/views/ReportsView.vue'),
  aiAssistant: () => import('@/views/AiAssistantView.vue'),
  aiGovernance: () => import('@/views/AiGovernanceView.vue'),
  filings: () => import('@/views/FilingsView.vue'),
  evidence: () => import('@/views/EvidenceView.vue'),
  briefs: () => import('@/views/BriefsView.vue'),
  briefEditor: () => import('@/views/briefs/BriefEditorView.vue'),
  motions: () => import('@/views/MotionsView.vue'),
  motionEditor: () => import('@/views/motions/MotionEditorView.vue'),
  research: () => import('@/views/ResearchView.vue'),
  ediscovery: () => import('@/views/EdiscoveryView.vue'),
  knowledge: () => import('@/views/KnowledgeView.vue'),
  legalProjects: () => import('@/views/LegalProjectsView.vue'),
  legalAnalytics: () => import('@/views/LegalAnalyticsView.vue'),
  training: () => import('@/views/TrainingView.vue'),
  portalAppointments: () => import('@/views/portal/PortalAppointmentsView.vue'),
  portalIntake: () => import('@/views/portal/PortalIntakeView.vue'),
  portalProfileSettings: () => import('@/views/portal/PortalProfileSettingsView.vue'),
  portalSignatureSign: () => import('@/views/portal/PortalSignatureSignView.vue'),
  publicSupport: () => import('@/views/PublicSupportChatView.vue'),
}

/** Map nav route names to their lazy chunk for hover / idle prefetching. */
const prefetchMap: Record<string, () => Promise<unknown>> = {
  dashboard: loaders.dashboard,
  clients: loaders.clientsList,
  'client-new': loaders.clientForm,
  cases: loaders.casesList,
  'case-new': loaders.caseForm,
  intake: loaders.intake,
  'conflict-checks': loaders.conflictChecks,
  'time-tracking': loaders.timeTracking,
  invoices: loaders.invoicesList,
  'invoice-new': loaders.invoiceDetail,
  'invoice-detail': loaders.invoiceDetail,
  messages: loaders.messages,
  calendar: loaders.calendar,
  reports: loaders.reports,
  'ai-assistant': loaders.aiAssistant,
  'ai-governance': loaders.aiGovernance,
  filings: loaders.filings,
  evidence: loaders.evidence,
  briefs: loaders.briefs,
  motions: loaders.motions,
  research: loaders.research,
  ediscovery: loaders.ediscovery,
  knowledge: loaders.knowledge,
  'legal-projects': loaders.legalProjects,
  'legal-analytics': loaders.legalAnalytics,
  training: loaders.training,
  notifications: loaders.notifications,
  search: loaders.search,
  audit: loaders.audit,
  settings: loaders.settings,
  'settings-users': loaders.users,
}

export function prefetchByName(name?: string | null) {
  if (!name) return
  prefetchMap[name]?.()
}

export function prefetchAll() {
  Object.values(prefetchMap).forEach((load) => load())
}

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes: [
    {
      path: '/login',
      name: 'login',
      component: loaders.login,
      meta: { guest: true },
    },
    {
      path: '/forgot-password',
      name: 'forgot-password',
      component: loaders.forgotPassword,
      meta: { guest: true },
    },
    {
      path: '/reset-password',
      name: 'reset-password',
      component: loaders.resetPassword,
      meta: { guest: true },
    },
    {
      path: '/portal/login',
      name: 'portal-login',
      component: loaders.portalLogin,
      meta: { portalGuest: true },
    },
    {
      path: '/support',
      name: 'public-support',
      component: loaders.publicSupport,
      meta: { public: true, title: 'Support' },
    },
    {
      path: '/chat',
      redirect: { name: 'public-support' },
    },
    {
      path: '/portal',
      component: PortalLayout,
      meta: { requiresPortalAuth: true },
      children: [
        { path: '', name: 'portal-dashboard', component: loaders.portalDashboard, meta: { title: 'Portal' } },
        { path: 'cases', name: 'portal-cases', component: loaders.portalCases, meta: { title: 'My cases' } },
        { path: 'cases/:id', name: 'portal-case-detail', component: loaders.portalCaseDetail, meta: { title: 'Case' } },
        { path: 'invoices', name: 'portal-invoices', component: loaders.portalInvoices, meta: { title: 'Invoices' } },
        { path: 'invoices/:id', name: 'portal-invoice-detail', component: loaders.portalInvoiceDetail, meta: { title: 'Invoice' } },
        { path: 'invoices/:id/payment/success', name: 'portal-invoice-payment-success', component: loaders.portalInvoicePaymentSuccess, meta: { title: 'Payment success' } },
        { path: 'invoices/:id/payment/cancel', name: 'portal-invoice-payment-cancel', component: loaders.portalInvoicePaymentCancel, meta: { title: 'Payment cancelled' } },
        { path: 'messages', name: 'portal-messages', component: loaders.portalMessages, meta: { title: 'Messages' } },
        { path: 'appointments', name: 'portal-appointments', component: loaders.portalAppointments, meta: { title: 'Appointments' } },
        { path: 'intake', name: 'portal-intake', component: loaders.portalIntake, meta: { title: 'Intake forms' } },
        { path: 'intake/:id', name: 'portal-intake-detail', component: loaders.portalIntake, meta: { title: 'Intake form' } },
        { path: 'profile', name: 'portal-profile', component: loaders.portalProfileSettings, meta: { title: 'Profile settings' } },
        { path: 'sign/:id', name: 'portal-signature-sign', component: loaders.portalSignatureSign, meta: { title: 'Sign document' } },
      ],
    },
    {
      path: '/',
      component: AppLayout,
      meta: { requiresAuth: true },
      children: [
        { path: '', redirect: { name: 'dashboard' } },
        {
          path: 'dashboard',
          name: 'dashboard',
          component: loaders.dashboard,
          meta: { title: 'Dashboard' },
        },
        {
          path: 'clients',
          name: 'clients',
          component: loaders.clientsList,
          meta: { title: 'Clients' },
        },
        {
          path: 'clients/new',
          name: 'client-new',
          component: loaders.clientForm,
          meta: { title: 'New client' },
        },
        {
          path: 'clients/:id',
          name: 'client-detail',
          component: loaders.clientDetail,
          meta: { title: 'Client' },
        },
        {
          path: 'clients/:id/edit',
          name: 'client-edit',
          component: loaders.clientForm,
          meta: { title: 'Edit client' },
        },
        {
          path: 'cases',
          name: 'cases',
          component: loaders.casesList,
          meta: { title: 'Cases' },
        },
        {
          path: 'cases/new',
          name: 'case-new',
          component: loaders.caseForm,
          meta: { title: 'New case' },
        },
        {
          path: 'cases/:id',
          name: 'case-detail',
          component: loaders.caseDetail,
          meta: { title: 'Case' },
        },
        {
          path: 'cases/:id/edit',
          name: 'case-edit',
          component: loaders.caseForm,
          meta: { title: 'Edit case' },
        },
        {
          path: 'cases/:id/:workspaceTab(notes|tasks|time|expenses|invoices|messages|calendar|documents|research|knowledge|conflicts|filings|evidence|briefs|motions|e-discovery|project)',
          name: 'case-workspace',
          component: loaders.caseDetail,
          meta: { title: 'Case' },
        },
        {
          path: 'intake',
          name: 'intake',
          component: loaders.intake,
          meta: { title: 'Intake' },
        },
        {
          path: 'conflict-checks',
          name: 'conflict-checks',
          component: loaders.conflictChecks,
          meta: { title: 'Conflict checks' },
        },
        {
          path: 'time-tracking',
          name: 'time-tracking',
          component: loaders.timeTracking,
          meta: { title: 'Time tracking', permission: 'time-entries.view' },
        },
        {
          path: 'invoices',
          name: 'invoices',
          component: loaders.invoicesList,
          meta: { title: 'Invoices', permission: 'invoices.view' },
        },
        {
          path: 'invoices/new',
          name: 'invoice-new',
          component: loaders.invoiceDetail,
          meta: { title: 'New invoice', permission: 'invoices.create' },
        },
        {
          path: 'invoices/:id',
          name: 'invoice-detail',
          component: loaders.invoiceDetail,
          meta: { title: 'Invoice', permission: 'invoices.view' },
        },
        {
          path: 'messages',
          name: 'messages',
          component: loaders.messages,
          meta: { title: 'Messages', permission: 'messages.view' },
        },
        {
          path: 'calendar',
          name: 'calendar',
          component: loaders.calendar,
          meta: { title: 'Calendar', permission: 'appointments.view' },
        },
        {
          path: 'reports',
          name: 'reports',
          component: loaders.reports,
          meta: { title: 'Reports', permission: 'reports.view' },
        },
        {
          path: 'ai-assistant',
          name: 'ai-assistant',
          component: loaders.aiAssistant,
          meta: { title: 'AI assistant', permission: 'ai.use' },
        },
        {
          path: 'ai-governance',
          name: 'ai-governance',
          component: loaders.aiGovernance,
          meta: { title: 'AI governance', permission: 'ai.governance.view' },
        },
        {
          path: 'filings',
          name: 'filings',
          component: loaders.filings,
          meta: { title: 'Court filings', permission: 'filings.view' },
        },
        {
          path: 'evidence',
          name: 'evidence',
          component: loaders.evidence,
          meta: { title: 'Evidence', permission: 'evidence.view' },
        },
        {
          path: 'briefs',
          name: 'briefs',
          component: loaders.briefs,
          meta: { title: 'AI Brief Writer', permission: 'briefs.view' },
        },
        {
          path: 'briefs/:id',
          name: 'brief-editor',
          component: loaders.briefEditor,
          meta: { title: 'Brief editor', permission: 'briefs.view' },
        },
        {
          path: 'motions',
          name: 'motions',
          component: loaders.motions,
          meta: { title: 'Legal motions', permission: 'motions.view' },
        },
        {
          path: 'motions/:id',
          name: 'motion-editor',
          component: loaders.motionEditor,
          meta: { title: 'Motion editor', permission: 'motions.view' },
        },
        {
          path: 'research',
          name: 'research',
          component: loaders.research,
          meta: { title: 'AI Legal Research Command Center', permission: 'research.view' },
        },
        {
          path: 'e-discovery',
          name: 'e-discovery',
          component: loaders.ediscovery,
          meta: { title: 'E-discovery', permission: 'ediscovery.view' },
        },
        {
          path: 'knowledge',
          name: 'knowledge',
          component: loaders.knowledge,
          meta: { title: 'Knowledge base', permission: 'knowledge.view' },
        },
        {
          path: 'legal-projects',
          name: 'legal-projects',
          component: loaders.legalProjects,
          meta: { title: 'Legal projects', permission: 'projects.view' },
        },
        {
          path: 'legal-analytics',
          name: 'legal-analytics',
          component: loaders.legalAnalytics,
          meta: { title: 'Legal analytics', permission: 'analytics.view' },
        },
        {
          path: 'training',
          name: 'training',
          component: loaders.training,
          meta: { title: 'Training & CLE', permission: 'training.view' },
        },
        {
          path: 'notifications',
          name: 'notifications',
          component: loaders.notifications,
          meta: { title: 'Notifications' },
        },
        {
          path: 'search',
          name: 'search',
          component: loaders.search,
          meta: { title: 'Search' },
        },
        {
          path: 'audit',
          name: 'audit',
          component: loaders.audit,
          meta: { title: 'Audit trail', permission: 'audit.view' },
        },
        {
          path: 'settings',
          name: 'settings',
          component: loaders.settings,
          meta: { title: 'Settings' },
        },
        {
          path: 'settings/users',
          name: 'settings-users',
          component: loaders.users,
          meta: { title: 'Team', permission: 'users.manage' },
        },
      ],
    },
    { path: '/:pathMatch(.*)*', redirect: '/' },
  ],
})

router.beforeEach(async (to) => {
  routeStart()

  if (to.meta.public) {
    return true
  }

  const auth = useAuthStore()
  const portalAuth = usePortalAuthStore()

  if (to.path.startsWith('/portal')) {
    await portalAuth.ensureLoaded()

    if (to.meta.requiresPortalAuth && !portalAuth.user) {
      return { name: 'portal-login', query: { redirect: to.fullPath } }
    }

    if (to.meta.portalGuest && portalAuth.user) {
      return { name: 'portal-dashboard' }
    }

    return true
  }

  await auth.ensureLoaded()

  if (to.meta.requiresAuth && !auth.user) {
    return { name: 'login', query: { redirect: to.fullPath } }
  }

  if (to.meta.guest && auth.user) {
    if (auth.user.roles?.includes('Client')) {
      return { name: 'portal-dashboard' }
    }
    return { name: 'dashboard' }
  }

  if (auth.user?.roles?.includes('Client')) {
    return { name: 'portal-dashboard' }
  }

  const requiredPermission = to.meta.permission as string | undefined
  if (
    requiredPermission &&
    !auth.user?.permissions?.includes(requiredPermission)
  ) {
    return { name: 'dashboard' }
  }

  return true
})

router.afterEach(() => {
  routeDone()
})

router.onError(() => {
  routeDone()
})

export default router
