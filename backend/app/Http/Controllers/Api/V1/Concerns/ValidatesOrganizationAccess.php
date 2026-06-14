<?php

namespace App\Http\Controllers\Api\V1\Concerns;

use App\Models\Client;
use App\Models\IntakeForm;
use App\Models\IntakeSubmission;
use App\Models\LegalMatter;
use App\Models\LegalTask;
use App\Models\User;

trait ValidatesOrganizationAccess
{
    protected function legalMatterForOrganization(int $id, int $organizationId): LegalMatter
    {
        return LegalMatter::query()
            ->where('organization_id', $organizationId)
            ->findOrFail($id);
    }

    protected function userForOrganization(int $id, int $organizationId): User
    {
        return User::query()
            ->where('organization_id', $organizationId)
            ->findOrFail($id);
    }

    protected function legalTaskForOrganization(int $id, int $organizationId): LegalTask
    {
        return LegalTask::query()
            ->where('organization_id', $organizationId)
            ->findOrFail($id);
    }

    protected function clientForOrganization(int $id, int $organizationId): Client
    {
        return Client::query()
            ->where('organization_id', $organizationId)
            ->findOrFail($id);
    }

    protected function intakeFormForOrganization(int $id, int $organizationId): IntakeForm
    {
        return IntakeForm::query()
            ->where('organization_id', $organizationId)
            ->findOrFail($id);
    }

    protected function intakeSubmissionForOrganization(int $id, int $organizationId): IntakeSubmission
    {
        return IntakeSubmission::query()
            ->where('organization_id', $organizationId)
            ->findOrFail($id);
    }
}
