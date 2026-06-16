<?php

use App\Http\Controllers\Api\V1\AuditLogController;
use App\Http\Controllers\Api\V1\ApprovalRequestController;
use App\Http\Controllers\Api\V1\AiAssistantController;
use App\Http\Controllers\Api\V1\DocumentClauseController;
use App\Http\Controllers\Api\V1\AiGovernanceController;
use App\Http\Controllers\Api\V1\AiProviderSettingsController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\AppNotificationController;
use App\Http\Controllers\Api\V1\AppointmentController;
use App\Http\Controllers\Api\V1\CalendarEventController;
use App\Http\Controllers\Api\V1\CalendarHubController;
use App\Http\Controllers\Api\V1\LawyerAvailabilityController;
use App\Http\Controllers\Api\V1\CaseExpenseController;
use App\Http\Controllers\Api\V1\TrustLedgerEntryController;
use App\Http\Controllers\Api\V1\CourtFilingController;
use App\Http\Controllers\Api\V1\EvidenceItemController;
use App\Http\Controllers\Api\V1\LegalBriefController;
use App\Http\Controllers\Api\V1\LegalMotionController;
use App\Http\Controllers\Api\V1\LegalResearchEntryController;
use App\Http\Controllers\Api\V1\MotionTemplateController;
use App\Http\Controllers\Api\V1\ResearchFolderController;
use App\Http\Controllers\Api\V1\ResearchProjectController;
use App\Http\Controllers\Api\V1\ResearchSavedItemController;
use App\Http\Controllers\Api\V1\EdiscoveryCollectionController;
use App\Http\Controllers\Api\V1\EdiscoveryDocumentController;
use App\Http\Controllers\Api\V1\EdiscoveryReviewAssignmentController;
use App\Http\Controllers\Api\V1\EdiscoveryTagController;
use App\Http\Controllers\Api\V1\KnowledgeArticleController;
use App\Http\Controllers\Api\V1\LegalAnalyticsController;
use App\Http\Controllers\Api\V1\LegalProjectBudgetController;
use App\Http\Controllers\Api\V1\LegalProjectMilestoneController;
use App\Http\Controllers\Api\V1\LegalProjectWorkloadController;
use App\Http\Controllers\Api\V1\TrainingComplianceController;
use App\Http\Controllers\Api\V1\TrainingCourseController;
use App\Http\Controllers\Api\V1\TrainingEnrollmentController;
use App\Http\Controllers\Api\V1\CourtFormInstanceController;
use App\Http\Controllers\Api\V1\CourtFormTemplateController;
use App\Http\Controllers\Api\V1\CaseNoteController;
use App\Http\Controllers\Api\V1\ClientContactController;
use App\Http\Controllers\Api\V1\ClientController;
use App\Http\Controllers\Api\V1\CommunicationLogController;
use App\Http\Controllers\Api\V1\ConflictCheckController;
use App\Http\Controllers\Api\V1\DashboardController;
use App\Http\Controllers\Api\V1\GoogleCalendarOAuthController;
use App\Http\Controllers\Api\V1\IntegrationSettingsController;
use App\Http\Controllers\Api\V1\IntakeFormController;
use App\Http\Controllers\Api\V1\IntakeSubmissionController;
use App\Http\Controllers\Api\V1\DocumentFolderController;
use App\Http\Controllers\Api\V1\LegalDocumentController;
use App\Http\Controllers\Api\V1\CaseActivityController;
use App\Http\Controllers\Api\V1\LegalMatterController;
use App\Http\Controllers\Api\V1\LegalTaskAttachmentController;
use App\Http\Controllers\Api\V1\LegalTaskCommentController;
use App\Http\Controllers\Api\V1\LegalTaskController;
use App\Http\Controllers\Api\V1\TaskWorkloadController;
use App\Http\Controllers\Api\V1\OrganizationController;
use App\Http\Controllers\Api\V1\InvoiceController;
use App\Http\Controllers\Api\V1\TimeEntryController;
use App\Http\Controllers\Api\V1\MessageThreadController;
use App\Http\Controllers\Api\V1\UserController;
use App\Http\Controllers\Api\V1\Portal\PortalAppointmentController;
use App\Http\Controllers\Api\V1\Portal\PortalAuthController;
use App\Http\Controllers\Api\V1\Portal\PortalCaseController;
use App\Http\Controllers\Api\V1\Portal\PortalDashboardController;
use App\Http\Controllers\Api\V1\Portal\PortalDocumentController;
use App\Http\Controllers\Api\V1\Portal\PortalInvoiceController;
use App\Http\Controllers\Api\V1\Portal\PortalIntakeController;
use App\Http\Controllers\Api\V1\Portal\PortalInvoicePaymentController;
use App\Http\Controllers\Api\V1\Portal\PortalMessageController;
use App\Http\Controllers\Api\V1\PaymentWebhookController;
use App\Http\Controllers\Api\V1\ReportController;
use App\Http\Controllers\Api\V1\SearchController;
use App\Http\Controllers\Api\V1\ServiceItemController;
use App\Http\Controllers\Api\V1\SignatureRequestController;
use App\Http\Controllers\Api\V1\TwoFactorController;
use App\Http\Controllers\Api\V1\Portal\PortalSignatureRequestController;
use App\Http\Controllers\Api\V1\PublicAiChatController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::post('/webhooks/stripe', [PaymentWebhookController::class, 'stripe']);
    Route::post('/webhooks/paypal', [PaymentWebhookController::class, 'paypal']);

    Route::get('/documents/{document}/onlyoffice-file', [LegalDocumentController::class, 'onlyOfficeFile'])
        ->name('onlyoffice.file');
    Route::post('/documents/{document}/onlyoffice-callback', [LegalDocumentController::class, 'onlyOfficeCallback']);

    Route::post('/auth/login', [AuthController::class, 'login']);
    Route::post('/auth/two-factor/verify', [TwoFactorController::class, 'verifyChallenge'])
        ->middleware('throttle:10,1');
    Route::post('/auth/forgot-password', [AuthController::class, 'forgotPassword'])
        ->middleware('throttle:6,1');
    Route::post('/auth/reset-password', [AuthController::class, 'resetPassword'])
        ->middleware('throttle:10,1');

    Route::post('/public/chat', [PublicAiChatController::class, 'chat'])
        ->middleware('throttle:20,1');

    Route::get('/integrations/google-calendar/callback', [GoogleCalendarOAuthController::class, 'callback']);

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::post('/auth/logout', [AuthController::class, 'logout']);
        Route::get('/auth/me', [AuthController::class, 'me']);
        Route::get('/auth/two-factor/status', [TwoFactorController::class, 'status']);
        Route::post('/auth/two-factor/enable', [TwoFactorController::class, 'enable']);
        Route::post('/auth/two-factor/confirm', [TwoFactorController::class, 'confirm']);
        Route::post('/auth/two-factor/disable', [TwoFactorController::class, 'disable']);

        Route::get('/dashboard', [DashboardController::class, 'index'])
            ->middleware('permission:dashboard.view');

        Route::get('/search', SearchController::class);

        Route::get('/audit-logs', [AuditLogController::class, 'index'])
            ->middleware('permission:audit.view');

        Route::get('/reports/summary', [ReportController::class, 'summary'])
            ->middleware('permission:reports.view');
        Route::get('/reports/export.csv', [ReportController::class, 'exportCsv'])
            ->middleware('permission:reports.view');

        Route::get('/organization', [OrganizationController::class, 'show']);
        Route::put('/organization', [OrganizationController::class, 'update'])
            ->middleware('permission:organization.manage');

        Route::get('/settings/ai-providers', [AiProviderSettingsController::class, 'index'])
            ->middleware('permission:ai.providers.manage');
        Route::put('/settings/ai-providers', [AiProviderSettingsController::class, 'update'])
            ->middleware('permission:ai.providers.manage');
        Route::put('/settings/ai-providers/active', [AiProviderSettingsController::class, 'setActive'])
            ->middleware('permission:ai.providers.manage');
        Route::post('/settings/ai-providers/{provider}/test-connection', [AiProviderSettingsController::class, 'testConnection'])
            ->middleware('permission:ai.providers.manage');
        Route::get('/settings/integrations', [IntegrationSettingsController::class, 'index'])
            ->middleware('permission:organization.manage');
        Route::get('/integrations/google-calendar/connect', [GoogleCalendarOAuthController::class, 'connect'])
            ->middleware('permission:organization.manage');
        Route::post('/integrations/google-calendar/disconnect', [GoogleCalendarOAuthController::class, 'disconnect'])
            ->middleware('permission:organization.manage');

        Route::get('/users/roles', [UserController::class, 'roles'])
            ->middleware('permission:users.manage');
        Route::apiResource('users', UserController::class)
            ->middleware('permission:users.manage');

        Route::apiResource('clients', ClientController::class);
        Route::apiResource('client-contacts', ClientContactController::class)
            ->parameters(['client-contacts' => 'client_contact']);
        Route::apiResource('cases', LegalMatterController::class)
            ->parameters(['cases' => 'legal_matter']);
        Route::get('/cases/{legal_matter}/activity', [CaseActivityController::class, 'index']);
        Route::get('/cases/{legal_matter}/overview-metrics', [LegalMatterController::class, 'overviewMetrics']);

        Route::apiResource('case-notes', CaseNoteController::class);
        Route::get('/tasks/{task}/attachments', [LegalTaskAttachmentController::class, 'index']);
        Route::post('/tasks/{task}/attachments', [LegalTaskAttachmentController::class, 'store']);
        Route::get('/tasks/{task}/attachments/{task_attachment}/download', [LegalTaskAttachmentController::class, 'download']);
        Route::delete('/tasks/{task}/attachments/{task_attachment}', [LegalTaskAttachmentController::class, 'destroy']);
        Route::get('/tasks/{task}/comments', [LegalTaskCommentController::class, 'index']);
        Route::post('/tasks/{task}/comments', [LegalTaskCommentController::class, 'store']);
        Route::apiResource('tasks', LegalTaskController::class)
            ->parameters(['tasks' => 'task']);
        Route::get('/calendar-hub', [CalendarHubController::class, 'index']);
        Route::get('/calendar-hub/export.ics', [CalendarHubController::class, 'exportIcs']);
        Route::apiResource('calendar-events', CalendarEventController::class);
        Route::get('/appointments/available-slots', [AppointmentController::class, 'availableSlots']);
        Route::apiResource('appointments', AppointmentController::class);
        Route::get('/lawyer-availability', [LawyerAvailabilityController::class, 'index']);
        Route::put('/lawyer-availability', [LawyerAvailabilityController::class, 'update']);
        Route::get('/documents/{document}/download', [LegalDocumentController::class, 'download'])
            ->middleware('permission:documents.download');
        Route::get('/documents/{document}/export-pdf', [LegalDocumentController::class, 'exportPdf'])
            ->middleware('permission:documents.download');
        Route::get('/documents/merge-fields', [LegalDocumentController::class, 'mergeFieldsCatalog']);
        Route::apiResource('document-clauses', DocumentClauseController::class)
            ->parameters(['document-clauses' => 'document_clause']);
        Route::post('/documents/generate-draft', [LegalDocumentController::class, 'generateDraft']);
        Route::post('/documents/ai-draft', [LegalDocumentController::class, 'saveAiDraft']);
        Route::patch('/documents/{document}/ai-review', [LegalDocumentController::class, 'updateAiReview']);
        Route::post('/documents/{document}/checkout', [LegalDocumentController::class, 'checkout']);
        Route::post('/documents/{document}/checkin', [LegalDocumentController::class, 'checkin']);
        Route::apiResource('document-folders', DocumentFolderController::class)
            ->parameters(['document-folders' => 'document_folder']);
        Route::get('/documents/{document}/versions', [LegalDocumentController::class, 'versions']);
        Route::get('/documents/{document}/versions/compare', [LegalDocumentController::class, 'compareVersions']);
        Route::get('/documents/{document}/versions/{document_version}', [LegalDocumentController::class, 'showVersion']);
        Route::get('/documents/{document}/onlyoffice-config', [LegalDocumentController::class, 'onlyOfficeConfig'])
            ->middleware('permission:documents.view');
        Route::apiResource('documents', LegalDocumentController::class)
            ->parameters(['documents' => 'document']);

        Route::post('/intake-submissions/{intake_submission}/approve', [IntakeSubmissionController::class, 'approve']);
        Route::post('/intake-submissions/{intake_submission}/reject', [IntakeSubmissionController::class, 'reject']);
        Route::post('/intake-submissions/{intake_submission}/request-info', [IntakeSubmissionController::class, 'requestInfo']);
        Route::post('/intake-submissions/{intake_submission}/convert', [IntakeSubmissionController::class, 'convert'])
            ->middleware('permission:intake-submissions.convert');
        Route::apiResource('intake-forms', IntakeFormController::class);
        Route::apiResource('intake-submissions', IntakeSubmissionController::class)
            ->parameters(['intake-submissions' => 'intake_submission']);
        Route::get('/conflict-checks/{conflict_check}/export', [ConflictCheckController::class, 'export']);
        Route::apiResource('conflict-checks', ConflictCheckController::class);

        Route::get('/time-entries/running', [TimeEntryController::class, 'running']);
        Route::post('/time-entries/timer/start', [TimeEntryController::class, 'startTimer']);
        Route::post('/time-entries/{time_entry}/stop', [TimeEntryController::class, 'stopTimer']);
        Route::post('/time-entries/{time_entry}/approve', [TimeEntryController::class, 'approve']);
        Route::apiResource('time-entries', TimeEntryController::class)
            ->parameters(['time-entries' => 'time_entry']);

        Route::apiResource('case-expenses', CaseExpenseController::class)
            ->parameters(['case-expenses' => 'case_expense']);

        Route::apiResource('trust-ledger-entries', TrustLedgerEntryController::class)
            ->only(['index', 'store', 'destroy'])
            ->parameters(['trust-ledger-entries' => 'trust_ledger_entry']);

        Route::get('/invoices/aging-summary', [InvoiceController::class, 'agingSummary']);
        Route::apiResource('service-items', ServiceItemController::class)
            ->parameters(['service-items' => 'service_item']);
        Route::post('/invoices/generate-from-time-entries', [InvoiceController::class, 'generateFromTimeEntries']);
        Route::post('/invoices/generate-from-expenses', [InvoiceController::class, 'generateFromExpenses']);
        Route::post('/invoices/{invoice}/mark-sent', [InvoiceController::class, 'markSent']);
        Route::post('/invoices/{invoice}/record-payment', [InvoiceController::class, 'recordPayment']);
        Route::get('/invoices/{invoice}/export-pdf', [InvoiceController::class, 'exportPdf']);
        Route::apiResource('invoices', InvoiceController::class);

        Route::apiResource('message-threads', MessageThreadController::class)
            ->only(['index', 'store', 'show'])
            ->parameters(['message-threads' => 'message_thread']);
        Route::get('/message-threads/{message_thread}/messages', [MessageThreadController::class, 'messages'])
            ->middleware('permission:messages.view');
        Route::post('/message-threads/{message_thread}/messages', [MessageThreadController::class, 'sendMessage'])
            ->middleware('permission:messages.create');
        Route::post('/message-threads/{message_thread}/mark-read', [MessageThreadController::class, 'markRead'])
            ->middleware('permission:messages.view');

        Route::apiResource('communication-logs', CommunicationLogController::class)
            ->parameters(['communication-logs' => 'communication_log']);

        Route::prefix('ai')->middleware('ai.staff')->group(function (): void {
            Route::get('/governance/settings', [AiGovernanceController::class, 'settings']);
            Route::get('/governance/logs', [AiGovernanceController::class, 'index'])
                ->middleware('permission:ai.governance.view');
            Route::get('/health', [AiAssistantController::class, 'health'])
                ->middleware('permission:ai.use');
            Route::post('/chat', [AiAssistantController::class, 'chat'])
                ->middleware('permission:ai.use');
            Route::post('/summarize-document', [AiAssistantController::class, 'summarizeDocument'])
                ->middleware('permission:ai.use');
            Route::post('/draft-assist', [AiAssistantController::class, 'draftAssist'])
                ->middleware('permission:ai.use');
            Route::post('/case-qa', [AiAssistantController::class, 'caseQa'])
                ->middleware('permission:ai.use');
            Route::post('/intake-summary', [AiAssistantController::class, 'intakeSummary'])
                ->middleware('permission:ai.use');
            Route::post('/timeline-summary', [AiAssistantController::class, 'timelineSummary'])
                ->middleware('permission:ai.use');
            Route::post('/research/summarize-notes', [AiAssistantController::class, 'summarizeResearchNotes'])
                ->middleware('permission:ai.use');
            Route::post('/research/suggest-authorities', [AiAssistantController::class, 'suggestAuthorities'])
                ->middleware('permission:ai.use');
            Route::post('/brief/outline', [AiAssistantController::class, 'briefOutline'])
                ->middleware('permission:ai.use');
            Route::post('/brief/rewrite', [AiAssistantController::class, 'briefRewrite'])
                ->middleware('permission:ai.use');
            Route::post('/brief/generate-from-facts', [AiAssistantController::class, 'briefGenerateFromFacts'])
                ->middleware('permission:ai.use');
            Route::post('/brief/build-arguments', [AiAssistantController::class, 'briefBuildArguments'])
                ->middleware('permission:ai.use');
            Route::post('/brief/analyze-opposition', [AiAssistantController::class, 'briefAnalyzeOpposition'])
                ->middleware('permission:ai.use');
            Route::post('/brief/enhance', [AiAssistantController::class, 'briefEnhance'])
                ->middleware('permission:ai.use');
            Route::post('/brief/format-court', [AiAssistantController::class, 'briefFormatCourt'])
                ->middleware('permission:ai.use');
            Route::post('/research/query', [AiAssistantController::class, 'researchQuery'])
                ->middleware('permission:ai.use');
            Route::post('/research/search-cases', [AiAssistantController::class, 'researchSearchCases'])
                ->middleware('permission:ai.use');
            Route::post('/research/generate-memo', [AiAssistantController::class, 'researchGenerateMemo'])
                ->middleware('permission:ai.use');
            Route::post('/research/analyze-statute', [AiAssistantController::class, 'researchAnalyzeStatute'])
                ->middleware('permission:ai.use');
            Route::post('/research/strategy', [AiAssistantController::class, 'researchStrategy'])
                ->middleware('permission:ai.use');
            Route::post('/research/chat', [AiAssistantController::class, 'researchChat'])
                ->middleware('permission:ai.use');
            Route::post('/motion/structure-check', [AiAssistantController::class, 'motionStructureCheck'])
                ->middleware('permission:ai.use');
            Route::post('/contract/review', [AiAssistantController::class, 'contractReview'])
                ->middleware('permission:ai.use');
            Route::post('/letters/generate-pack', [AiAssistantController::class, 'generateLetterPack'])
                ->middleware('permission:ai.use');
        });

        Route::get('/court-form-templates', [CourtFormTemplateController::class, 'index'])
            ->middleware('permission:court-forms.view');
        Route::get('/court-form-templates/{court_form_template}', [CourtFormTemplateController::class, 'show'])
            ->middleware('permission:court-forms.view');
        Route::post('/court-form-templates/{court_form_template}/prefill', [CourtFormTemplateController::class, 'prefill'])
            ->middleware('permission:court-forms.view');

        Route::apiResource('court-form-instances', CourtFormInstanceController::class)
            ->parameters(['court-form-instances' => 'court_form_instance'])
            ->middleware([
                'index' => 'permission:court-forms.view',
                'show' => 'permission:court-forms.view',
                'store' => 'permission:court-forms.create',
                'update' => 'permission:court-forms.update',
                'destroy' => 'permission:court-forms.update',
            ]);
        Route::post('/court-form-instances/{court_form_instance}/create-filing', [CourtFormInstanceController::class, 'createFiling'])
            ->middleware('permission:court-forms.update|filings.create');

        Route::apiResource('court-filings', CourtFilingController::class)
            ->parameters(['court-filings' => 'court_filing'])
            ->middleware([
                'index' => 'permission:filings.view',
                'show' => 'permission:filings.view',
                'store' => 'permission:filings.create',
                'update' => 'permission:filings.update',
                'destroy' => 'permission:filings.delete',
            ]);
        Route::patch('/court-filings/{court_filing}/status', [CourtFilingController::class, 'updateStatus'])
            ->middleware('permission:filings.update');

        Route::get('/evidence-items/exhibit-index', [EvidenceItemController::class, 'exhibitIndex'])
            ->middleware('permission:evidence.view');
        Route::get('/evidence-items/export-bundle', [EvidenceItemController::class, 'exportBundle'])
            ->middleware('permission:evidence.view');
        Route::apiResource('evidence-items', EvidenceItemController::class)
            ->parameters(['evidence-items' => 'evidence_item'])
            ->middleware([
                'index' => 'permission:evidence.view',
                'show' => 'permission:evidence.view',
                'store' => 'permission:evidence.create',
                'update' => 'permission:evidence.update',
                'destroy' => 'permission:evidence.delete',
            ]);
        Route::patch('/evidence-items/{evidence_item}/status', [EvidenceItemController::class, 'updateStatus'])
            ->middleware('permission:evidence.update');
        Route::post('/evidence-items/{evidence_item}/assign-exhibit', [EvidenceItemController::class, 'assignExhibit'])
            ->middleware('permission:evidence.update');
        Route::get('/evidence-items/{evidence_item}/custody-logs', [EvidenceItemController::class, 'custodyLogs'])
            ->middleware('permission:evidence.view');
        Route::post('/evidence-items/{evidence_item}/custody-logs', [EvidenceItemController::class, 'storeCustodyLog'])
            ->middleware('permission:evidence.update');

        Route::apiResource('legal-briefs', LegalBriefController::class)
            ->parameters(['legal-briefs' => 'legal_brief'])
            ->middleware([
                'index' => 'permission:briefs.view',
                'show' => 'permission:briefs.view',
                'store' => 'permission:briefs.create',
                'update' => 'permission:briefs.update',
                'destroy' => 'permission:briefs.delete',
            ]);
        Route::patch('/legal-briefs/{legal_brief}/status', [LegalBriefController::class, 'updateStatus'])
            ->middleware('permission:briefs.update');
        Route::get('/legal-briefs/{legal_brief}/citations', [LegalBriefController::class, 'citations'])
            ->middleware('permission:briefs.view');
        Route::post('/legal-briefs/{legal_brief}/citations', [LegalBriefController::class, 'storeCitation'])
            ->middleware('permission:briefs.update');
        Route::delete('/legal-briefs/{legal_brief}/citations/{citation}', [LegalBriefController::class, 'destroyCitation'])
            ->middleware('permission:briefs.update');
        Route::get('/legal-briefs/{legal_brief}/export', [LegalBriefController::class, 'export'])
            ->middleware('permission:briefs.view');

        Route::get('/motion-templates', [MotionTemplateController::class, 'index'])
            ->middleware('permission:motions.view');
        Route::apiResource('legal-motions', LegalMotionController::class)
            ->parameters(['legal-motions' => 'legal_motion'])
            ->middleware([
                'index' => 'permission:motions.view',
                'show' => 'permission:motions.view',
                'store' => 'permission:motions.create',
                'update' => 'permission:motions.update',
                'destroy' => 'permission:motions.delete',
            ]);
        Route::patch('/legal-motions/{legal_motion}/status', [LegalMotionController::class, 'updateStatus'])
            ->middleware('permission:motions.update');
        Route::post('/legal-motions/{legal_motion}/create-filing', [LegalMotionController::class, 'createFiling'])
            ->middleware('permission:motions.update|filings.create');

        Route::apiResource('legal-research-entries', LegalResearchEntryController::class)
            ->parameters(['legal-research-entries' => 'legal_research_entry'])
            ->middleware([
                'index' => 'permission:research.view',
                'show' => 'permission:research.view',
                'store' => 'permission:research.create',
                'update' => 'permission:research.update',
                'destroy' => 'permission:research.delete',
            ]);
        Route::apiResource('research-folders', ResearchFolderController::class)
            ->parameters(['research-folders' => 'research_folder'])
            ->middleware([
                'index' => 'permission:research.view',
                'show' => 'permission:research.view',
                'store' => 'permission:research.create',
                'update' => 'permission:research.update',
                'destroy' => 'permission:research.delete',
            ]);
        Route::get('/research-folders/{research_folder}/items', [ResearchFolderController::class, 'items'])
            ->middleware('permission:research.view');
        Route::post('/research-folders/{research_folder}/items', [ResearchFolderController::class, 'storeItem'])
            ->middleware('permission:research.create');
        Route::get('/research-saved-items', [ResearchSavedItemController::class, 'index'])
            ->middleware('permission:research.view');
        Route::delete('/research-saved-items/{research_saved_item}', [ResearchSavedItemController::class, 'destroy'])
            ->middleware('permission:research.delete');
        Route::apiResource('research-projects', ResearchProjectController::class)
            ->parameters(['research-projects' => 'research_project'])
            ->middleware([
                'index' => 'permission:research.view',
                'show' => 'permission:research.view',
                'store' => 'permission:research.create',
                'update' => 'permission:research.update',
                'destroy' => 'permission:research.delete',
            ]);
        Route::get('/research-projects/{research_project}/messages', [ResearchProjectController::class, 'messages'])
            ->middleware('permission:research.view');
        Route::post('/research-projects/{research_project}/transfer-to-brief', [ResearchProjectController::class, 'transferToBrief'])
            ->middleware('permission:research.view');

        Route::apiResource('knowledge-articles', KnowledgeArticleController::class)
            ->parameters(['knowledge-articles' => 'knowledge_article'])
            ->middleware([
                'index' => 'permission:knowledge.view',
                'show' => 'permission:knowledge.view',
                'store' => 'permission:knowledge.create',
                'update' => 'permission:knowledge.update',
                'destroy' => 'permission:knowledge.delete',
            ]);

        Route::apiResource('legal-project-milestones', LegalProjectMilestoneController::class)
            ->parameters(['legal-project-milestones' => 'legal_project_milestone'])
            ->middleware([
                'index' => 'permission:projects.view',
                'show' => 'permission:projects.view',
                'store' => 'permission:projects.create',
                'update' => 'permission:projects.update',
                'destroy' => 'permission:projects.delete',
            ]);
        Route::apiResource('legal-project-budgets', LegalProjectBudgetController::class)
            ->parameters(['legal-project-budgets' => 'legal_project_budget'])
            ->middleware([
                'index' => 'permission:projects.view',
                'show' => 'permission:projects.view',
                'store' => 'permission:projects.create',
                'update' => 'permission:projects.update',
                'destroy' => 'permission:projects.delete',
            ]);
        Route::get('/legal-project-workload', [LegalProjectWorkloadController::class, 'index'])
            ->middleware('permission:projects.view');
        Route::get('/task-workload', [TaskWorkloadController::class, 'index'])
            ->middleware('permission:tasks.view');

        Route::get('/legal-analytics/dashboard', [LegalAnalyticsController::class, 'dashboard'])
            ->middleware('permission:analytics.view');
        Route::get('/legal-analytics/hints', [LegalAnalyticsController::class, 'hints'])
            ->middleware('permission:analytics.view');

        Route::apiResource('training-courses', TrainingCourseController::class)
            ->parameters(['training-courses' => 'training_course'])
            ->middleware([
                'index' => 'permission:training.view',
                'show' => 'permission:training.view',
                'store' => 'permission:training.create',
                'update' => 'permission:training.update',
                'destroy' => 'permission:training.delete',
            ]);
        Route::apiResource('training-enrollments', TrainingEnrollmentController::class)
            ->only(['index', 'store', 'show', 'update'])
            ->parameters(['training-enrollments' => 'training_enrollment'])
            ->middleware([
                'index' => 'permission:training.view|training.assign',
                'show' => 'permission:training.view|training.assign',
                'store' => 'permission:training.assign',
                'update' => 'permission:training.view|training.assign',
            ]);
        Route::post('/training-enrollments/{training_enrollment}/submit-quiz', [TrainingEnrollmentController::class, 'submitQuiz'])
            ->middleware('permission:training.view|training.assign');
        Route::get('/training-compliance/report', [TrainingComplianceController::class, 'report'])
            ->middleware('permission:training.assign');

        Route::get('/ediscovery-review-progress', [EdiscoveryDocumentController::class, 'reviewProgress'])
            ->middleware('permission:ediscovery.view');
        Route::post('/ediscovery-documents/bulk-upload', [EdiscoveryDocumentController::class, 'bulkUpload'])
            ->middleware('permission:ediscovery.create');
        Route::apiResource('ediscovery-collections', EdiscoveryCollectionController::class)
            ->parameters(['ediscovery-collections' => 'ediscovery_collection'])
            ->middleware([
                'index' => 'permission:ediscovery.view',
                'show' => 'permission:ediscovery.view',
                'store' => 'permission:ediscovery.create',
                'update' => 'permission:ediscovery.update',
                'destroy' => 'permission:ediscovery.delete',
            ]);
        Route::apiResource('ediscovery-documents', EdiscoveryDocumentController::class)
            ->parameters(['ediscovery-documents' => 'ediscovery_document'])
            ->middleware([
                'index' => 'permission:ediscovery.view',
                'show' => 'permission:ediscovery.view',
                'store' => 'permission:ediscovery.create',
                'update' => 'permission:ediscovery.update',
                'destroy' => 'permission:ediscovery.delete',
            ]);
        Route::patch('/ediscovery-documents/{ediscovery_document}/tags', [EdiscoveryDocumentController::class, 'updateTags'])
            ->middleware('permission:ediscovery.update');
        Route::patch('/ediscovery-documents/{ediscovery_document}/review-status', [EdiscoveryDocumentController::class, 'updateReviewStatus'])
            ->middleware('permission:ediscovery.update');
        Route::apiResource('ediscovery-tags', EdiscoveryTagController::class)
            ->parameters(['ediscovery-tags' => 'ediscovery_tag'])
            ->middleware([
                'index' => 'permission:ediscovery.view',
                'show' => 'permission:ediscovery.view',
                'store' => 'permission:ediscovery.create',
                'update' => 'permission:ediscovery.update',
                'destroy' => 'permission:ediscovery.delete',
            ]);
        Route::apiResource('ediscovery-review-assignments', EdiscoveryReviewAssignmentController::class)
            ->only(['index', 'store', 'update', 'destroy'])
            ->parameters(['ediscovery-review-assignments' => 'ediscovery_review_assignment'])
            ->middleware([
                'index' => 'permission:ediscovery.view',
                'store' => 'permission:ediscovery.update',
                'update' => 'permission:ediscovery.update',
                'destroy' => 'permission:ediscovery.delete',
            ]);

        Route::get('/approval-requests', [ApprovalRequestController::class, 'index'])
            ->middleware('permission:approvals.view');
        Route::post('/approval-requests', [ApprovalRequestController::class, 'store'])
            ->middleware('permission:approvals.submit');
        Route::get('/approval-requests/{approval_request}', [ApprovalRequestController::class, 'show'])
            ->middleware('permission:approvals.view');
        Route::patch('/approval-requests/{approval_request}/review', [ApprovalRequestController::class, 'review'])
            ->middleware('permission:approvals.review');

        Route::get('/signature-requests', [SignatureRequestController::class, 'index'])
            ->middleware('permission:signatures.view|signatures.send');
        Route::post('/signature-requests', [SignatureRequestController::class, 'store'])
            ->middleware('permission:signatures.send');
        Route::get('/signature-requests/{signature_request}', [SignatureRequestController::class, 'show'])
            ->middleware('permission:signatures.view|signatures.send');

        Route::get('/notifications', [AppNotificationController::class, 'index']);
        Route::get('/notifications/{notification}', [AppNotificationController::class, 'show']);
        Route::post('/notifications/mark-all-read', [AppNotificationController::class, 'markAllRead'])
            ->middleware('permission:notifications.update');
        Route::post('/notifications/{notification}/read', [AppNotificationController::class, 'markRead'])
            ->middleware('permission:notifications.update');
    });

    Route::prefix('portal')->group(function (): void {
        Route::post('/auth/login', [PortalAuthController::class, 'login']);

        Route::middleware(['auth:sanctum', 'portal.client'])->group(function (): void {
            Route::post('/auth/logout', [PortalAuthController::class, 'logout']);
            Route::get('/auth/me', [PortalAuthController::class, 'me']);
            Route::patch('/auth/profile', [PortalAuthController::class, 'updateProfile'])
                ->middleware('permission:portal.profile.update');
            Route::get('/dashboard', [PortalDashboardController::class, 'index'])
                ->middleware('permission:portal.dashboard.view');
            Route::get('/cases', [PortalCaseController::class, 'index'])
                ->middleware('permission:portal.cases.view');
            Route::get('/cases/{legal_matter}', [PortalCaseController::class, 'show'])
                ->middleware('permission:portal.cases.view');
            Route::get('/documents', [PortalDocumentController::class, 'index'])
                ->middleware('permission:portal.documents.view');
            Route::post('/documents', [PortalDocumentController::class, 'store'])
                ->middleware('permission:portal.documents.upload');
            Route::get('/documents/{document}/download', [PortalDocumentController::class, 'download'])
                ->middleware('permission:portal.documents.download');
            Route::get('/invoices/payment/gateways', [PortalInvoicePaymentController::class, 'gateways'])
                ->middleware('permission:portal.invoices.pay');
            Route::post('/invoices/payment/paypal/capture', [PortalInvoicePaymentController::class, 'paypalCapture'])
                ->middleware('permission:portal.invoices.pay');
            Route::get('/invoices', [PortalInvoiceController::class, 'index'])
                ->middleware('permission:portal.invoices.view');
            Route::post('/invoices/{invoice}/checkout/stripe', [PortalInvoicePaymentController::class, 'stripeCheckout'])
                ->middleware('permission:portal.invoices.pay');
            Route::post('/invoices/{invoice}/checkout/paypal', [PortalInvoicePaymentController::class, 'paypalCheckout'])
                ->middleware('permission:portal.invoices.pay');
            Route::get('/invoices/{invoice}', [PortalInvoiceController::class, 'show'])
                ->middleware('permission:portal.invoices.view');
            Route::get('/message-threads', [PortalMessageController::class, 'index'])
                ->middleware('permission:portal.messages.view');
            Route::post('/message-threads', [PortalMessageController::class, 'store'])
                ->middleware('permission:portal.messages.create');
            Route::get('/message-threads/{message_thread}', [PortalMessageController::class, 'show'])
                ->middleware('permission:portal.messages.view');
            Route::post('/message-threads/{message_thread}/messages', [PortalMessageController::class, 'sendMessage'])
                ->middleware('permission:portal.messages.create');
            Route::post('/message-threads/{message_thread}/mark-read', [PortalMessageController::class, 'markRead'])
                ->middleware('permission:portal.messages.view');
            Route::get('/lawyers', [PortalAppointmentController::class, 'lawyers'])
                ->middleware('permission:portal.appointments.view');
            Route::get('/appointments/available-slots', [PortalAppointmentController::class, 'availableSlots'])
                ->middleware('permission:portal.appointments.view');
            Route::get('/appointments', [PortalAppointmentController::class, 'index'])
                ->middleware('permission:portal.appointments.view');
            Route::post('/appointments', [PortalAppointmentController::class, 'store'])
                ->middleware('permission:portal.appointments.book');
            Route::get('/appointments/{appointment}', [PortalAppointmentController::class, 'show'])
                ->middleware('permission:portal.appointments.view');
            Route::post('/appointments/{appointment}/cancel', [PortalAppointmentController::class, 'cancel'])
                ->middleware('permission:portal.appointments.book');
            Route::get('/intake-forms', [PortalIntakeController::class, 'index'])
                ->middleware('permission:portal.intake.view');
            Route::get('/intake-forms/{intake_form}', [PortalIntakeController::class, 'show'])
                ->middleware('permission:portal.intake.view');
            Route::post('/intake-forms/{intake_form}/submit', [PortalIntakeController::class, 'submit'])
                ->middleware('permission:portal.intake.submit');
            Route::get('/signature-requests', [PortalSignatureRequestController::class, 'index'])
                ->middleware('permission:portal.signatures.view');
            Route::get('/signature-requests/{signature_request}', [PortalSignatureRequestController::class, 'show'])
                ->middleware('permission:portal.signatures.view');
            Route::post('/signature-requests/{signature_request}/sign', [PortalSignatureRequestController::class, 'sign'])
                ->middleware('permission:portal.signatures.sign');
            Route::post('/signature-requests/{signature_request}/decline', [PortalSignatureRequestController::class, 'decline'])
                ->middleware('permission:portal.signatures.sign');
        });
    });
});
