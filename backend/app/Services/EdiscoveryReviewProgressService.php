<?php

namespace App\Services;

use App\Models\EdiscoveryDocument;
use App\Models\EdiscoveryReviewAssignment;
use Illuminate\Support\Facades\DB;

class EdiscoveryReviewProgressService
{
    /**
     * @return array<string, mixed>
     */
    public function progress(int $legalMatterId, int $organizationId): array
    {
        $documentsQuery = EdiscoveryDocument::query()
            ->where('organization_id', $organizationId)
            ->where('legal_matter_id', $legalMatterId);

        $totalDocuments = (clone $documentsQuery)->count();

        $byReviewStatus = (clone $documentsQuery)
            ->select('review_status', DB::raw('count(*) as count'))
            ->groupBy('review_status')
            ->pluck('count', 'review_status')
            ->all();

        $byPrivilege = (clone $documentsQuery)
            ->select('privilege', DB::raw('count(*) as count'))
            ->groupBy('privilege')
            ->pluck('count', 'privilege')
            ->all();

        $byRelevance = (clone $documentsQuery)
            ->select('relevance', DB::raw('count(*) as count'))
            ->groupBy('relevance')
            ->pluck('count', 'relevance')
            ->all();

        $byReviewer = EdiscoveryReviewAssignment::query()
            ->where('ediscovery_review_assignments.organization_id', $organizationId)
            ->whereHas('document', fn ($q) => $q->where('legal_matter_id', $legalMatterId))
            ->join('users', 'users.id', '=', 'ediscovery_review_assignments.reviewer_id')
            ->select(
                'ediscovery_review_assignments.reviewer_id',
                'users.name as reviewer_name',
                DB::raw("sum(case when ediscovery_review_assignments.review_status = 'assigned' then 1 else 0 end) as assigned"),
                DB::raw("sum(case when ediscovery_review_assignments.review_status = 'in_progress' then 1 else 0 end) as in_progress"),
                DB::raw("sum(case when ediscovery_review_assignments.review_status = 'completed' then 1 else 0 end) as completed"),
                DB::raw("sum(case when ediscovery_review_assignments.review_status = 'skipped' then 1 else 0 end) as skipped"),
            )
            ->groupBy('ediscovery_review_assignments.reviewer_id', 'users.name')
            ->get()
            ->map(fn ($row) => [
                'reviewer_id' => (int) $row->reviewer_id,
                'reviewer_name' => $row->reviewer_name,
                'assigned' => (int) $row->assigned,
                'in_progress' => (int) $row->in_progress,
                'completed' => (int) $row->completed,
                'skipped' => (int) $row->skipped,
                'total' => (int) $row->assigned + (int) $row->in_progress + (int) $row->completed + (int) $row->skipped,
            ])
            ->values()
            ->all();

        $reviewedCount = (int) ($byReviewStatus['reviewed'] ?? 0);
        $completionRate = $totalDocuments > 0
            ? round(($reviewedCount / $totalDocuments) * 100, 1)
            : 0.0;

        return [
            'legal_matter_id' => $legalMatterId,
            'total_documents' => $totalDocuments,
            'completion_rate' => $completionRate,
            'by_review_status' => $byReviewStatus,
            'by_privilege' => $byPrivilege,
            'by_relevance' => $byRelevance,
            'by_reviewer' => $byReviewer,
        ];
    }
}
