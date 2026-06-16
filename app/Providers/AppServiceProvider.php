<?php

namespace App\Providers;

use App\Models\AiGovernanceLog;
use App\Models\ApprovalRequest;
use App\Models\AppNotification;
use App\Models\Appointment;
use App\Models\CalendarEvent;
use App\Models\LawyerAvailabilitySlot;
use App\Models\CaseExpense;
use App\Models\CourtFiling;
use App\Models\EdiscoveryCollection;
use App\Models\EdiscoveryDocument;
use App\Models\EdiscoveryReviewAssignment;
use App\Models\EdiscoveryTag;
use App\Models\EvidenceItem;
use App\Models\CourtFormInstance;
use App\Models\CourtFormTemplate;
use App\Models\CaseNote;
use App\Models\Client;
use App\Models\ClientContact;
use App\Models\CommunicationLog;
use App\Models\ConflictCheck;
use App\Models\IntakeForm;
use App\Models\IntakeSubmission;
use App\Models\Invoice;
use App\Models\DocumentClause;
use App\Models\DocumentFolder;
use App\Models\LegalDocument;
use App\Models\LegalBrief;
use App\Models\LegalMotion;
use App\Models\KnowledgeArticle;
use App\Models\LegalProjectBudget;
use App\Models\LegalProjectMilestone;
use App\Models\TrainingCourse;
use App\Models\TrainingEnrollment;
use App\Models\LegalResearchEntry;
use App\Models\LegalMatter;
use App\Models\ResearchFolder;
use App\Models\ResearchProject;
use App\Models\ResearchSavedItem;
use App\Policies\ResearchProjectPolicy;
use App\Models\LegalTask;
use App\Models\MessageThread;
use App\Models\Organization;
use App\Models\SignatureRequest;
use App\Models\ServiceItem;
use App\Models\TimeEntry;
use App\Models\TrustLedgerEntry;
use App\Models\User;
use App\Policies\AiGovernancePolicy;
use App\Policies\ApprovalRequestPolicy;
use App\Policies\AppNotificationPolicy;
use App\Policies\AppointmentPolicy;
use App\Policies\CalendarEventPolicy;
use App\Policies\LawyerAvailabilitySlotPolicy;
use App\Policies\CaseExpensePolicy;
use App\Policies\CourtFilingPolicy;
use App\Policies\EdiscoveryCollectionPolicy;
use App\Policies\EdiscoveryDocumentPolicy;
use App\Policies\EdiscoveryReviewAssignmentPolicy;
use App\Policies\EdiscoveryTagPolicy;
use App\Policies\EvidenceItemPolicy;
use App\Policies\LegalBriefPolicy;
use App\Policies\LegalMotionPolicy;
use App\Policies\KnowledgeArticlePolicy;
use App\Policies\LegalProjectBudgetPolicy;
use App\Policies\LegalProjectMilestonePolicy;
use App\Policies\TrainingCoursePolicy;
use App\Policies\TrainingEnrollmentPolicy;
use App\Policies\LegalResearchEntryPolicy;
use App\Policies\ResearchFolderPolicy;
use App\Policies\ResearchSavedItemPolicy;
use App\Policies\CourtFormInstancePolicy;
use App\Policies\CourtFormTemplatePolicy;
use App\Policies\CaseNotePolicy;
use App\Policies\ClientContactPolicy;
use App\Policies\ClientPolicy;
use App\Policies\CommunicationLogPolicy;
use App\Policies\ConflictCheckPolicy;
use App\Policies\IntakeFormPolicy;
use App\Policies\IntakeSubmissionPolicy;
use App\Policies\InvoicePolicy;
use App\Policies\DocumentClausePolicy;
use App\Policies\DocumentFolderPolicy;
use App\Policies\LegalDocumentPolicy;
use App\Policies\LegalMatterPolicy;
use App\Policies\LegalTaskPolicy;
use App\Policies\MessageThreadPolicy;
use App\Policies\OrganizationPolicy;
use App\Policies\SignatureRequestPolicy;
use App\Policies\ServiceItemPolicy;
use App\Policies\TimeEntryPolicy;
use App\Policies\TrustLedgerEntryPolicy;
use App\Policies\UserPolicy;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        JsonResource::withoutWrapping();

        Gate::policy(AiGovernanceLog::class, AiGovernancePolicy::class);
        Gate::policy(ApprovalRequest::class, ApprovalRequestPolicy::class);
        Gate::policy(AppNotification::class, AppNotificationPolicy::class);
        Gate::policy(Appointment::class, AppointmentPolicy::class);
        Gate::policy(CalendarEvent::class, CalendarEventPolicy::class);
        Gate::policy(LawyerAvailabilitySlot::class, LawyerAvailabilitySlotPolicy::class);
        Gate::policy(CaseExpense::class, CaseExpensePolicy::class);
        Gate::policy(CourtFormTemplate::class, CourtFormTemplatePolicy::class);
        Gate::policy(CourtFormInstance::class, CourtFormInstancePolicy::class);
        Gate::policy(CourtFiling::class, CourtFilingPolicy::class);
        Gate::policy(EdiscoveryCollection::class, EdiscoveryCollectionPolicy::class);
        Gate::policy(EdiscoveryDocument::class, EdiscoveryDocumentPolicy::class);
        Gate::policy(EdiscoveryTag::class, EdiscoveryTagPolicy::class);
        Gate::policy(EdiscoveryReviewAssignment::class, EdiscoveryReviewAssignmentPolicy::class);
        Gate::policy(EvidenceItem::class, EvidenceItemPolicy::class);
        Gate::policy(LegalBrief::class, LegalBriefPolicy::class);
        Gate::policy(LegalMotion::class, LegalMotionPolicy::class);
        Gate::policy(KnowledgeArticle::class, KnowledgeArticlePolicy::class);
        Gate::policy(LegalProjectMilestone::class, LegalProjectMilestonePolicy::class);
        Gate::policy(LegalProjectBudget::class, LegalProjectBudgetPolicy::class);
        Gate::policy(TrainingCourse::class, TrainingCoursePolicy::class);
        Gate::policy(TrainingEnrollment::class, TrainingEnrollmentPolicy::class);
        Gate::policy(LegalResearchEntry::class, LegalResearchEntryPolicy::class);
        Gate::policy(ResearchFolder::class, ResearchFolderPolicy::class);
        Gate::policy(ResearchSavedItem::class, ResearchSavedItemPolicy::class);
        Gate::policy(ResearchProject::class, ResearchProjectPolicy::class);
        Gate::policy(CaseNote::class, CaseNotePolicy::class);
        Gate::policy(Client::class, ClientPolicy::class);
        Gate::policy(ClientContact::class, ClientContactPolicy::class);
        Gate::policy(CommunicationLog::class, CommunicationLogPolicy::class);
        Gate::policy(ConflictCheck::class, ConflictCheckPolicy::class);
        Gate::policy(IntakeForm::class, IntakeFormPolicy::class);
        Gate::policy(IntakeSubmission::class, IntakeSubmissionPolicy::class);
        Gate::policy(Invoice::class, InvoicePolicy::class);
        Gate::policy(DocumentClause::class, DocumentClausePolicy::class);
        Gate::policy(DocumentFolder::class, DocumentFolderPolicy::class);
        Gate::policy(LegalDocument::class, LegalDocumentPolicy::class);
        Gate::policy(LegalMatter::class, LegalMatterPolicy::class);
        Gate::policy(LegalTask::class, LegalTaskPolicy::class);
        Gate::policy(MessageThread::class, MessageThreadPolicy::class);
        Gate::policy(Organization::class, OrganizationPolicy::class);
        Gate::policy(SignatureRequest::class, SignatureRequestPolicy::class);
        Gate::policy(ServiceItem::class, ServiceItemPolicy::class);
        Gate::policy(TimeEntry::class, TimeEntryPolicy::class);
        Gate::policy(TrustLedgerEntry::class, TrustLedgerEntryPolicy::class);
        Gate::policy(User::class, UserPolicy::class);
    }
}
