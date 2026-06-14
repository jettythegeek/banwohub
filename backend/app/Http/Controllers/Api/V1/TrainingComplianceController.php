<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Concerns\ResolvesOrganization;
use App\Http\Controllers\Controller;
use App\Models\TrainingCourse;
use App\Models\TrainingEnrollment;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TrainingComplianceController extends Controller
{
    use ResolvesOrganization;

    public function report(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('training.assign'), 403);

        $organization = $this->organizationFor($request->user());

        $requiredCourses = TrainingCourse::query()
            ->where(fn ($q) => $q->where('organization_id', $organization->id)->orWhereNull('organization_id'))
            ->where('is_required', true)
            ->where('is_published', true)
            ->get(['id', 'title', 'cle_credits']);

        $staff = User::query()
            ->where('organization_id', $organization->id)
            ->whereHas('roles', fn ($q) => $q->whereIn('name', ['Firm Admin', 'Partner', 'Lawyer', 'Paralegal', 'Secretary']))
            ->orderBy('name')
            ->get(['id', 'name', 'email']);

        $enrollments = TrainingEnrollment::query()
            ->where('organization_id', $organization->id)
            ->whereIn('user_id', $staff->pluck('id'))
            ->with('course:id,title,cle_credits,is_required')
            ->get();

        $rows = $staff->map(function (User $user) use ($requiredCourses, $enrollments): array {
            $userEnrollments = $enrollments->where('user_id', $user->id);
            $completedRequired = $requiredCourses->filter(function (TrainingCourse $course) use ($userEnrollments): bool {
                return $userEnrollments
                    ->where('training_course_id', $course->id)
                    ->where('status', 'completed')
                    ->isNotEmpty();
            });

            $cleEarned = $userEnrollments
                ->where('status', 'completed')
                ->sum(fn (TrainingEnrollment $e) => (float) ($e->cle_credits_earned ?? 0));

            $requiredTotal = $requiredCourses->count();
            $requiredCompleted = $completedRequired->count();

            return [
                'user_id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'required_courses_total' => $requiredTotal,
                'required_courses_completed' => $requiredCompleted,
                'compliance_percent' => $requiredTotal > 0
                    ? round(($requiredCompleted / $requiredTotal) * 100, 1)
                    : 100.0,
                'cle_credits_earned' => round($cleEarned, 2),
                'enrollments_total' => $userEnrollments->count(),
                'enrollments_completed' => $userEnrollments->where('status', 'completed')->count(),
            ];
        })->values()->all();

        return response()->json([
            'required_courses_count' => $requiredCourses->count(),
            'staff_count' => $staff->count(),
            'rows' => $rows,
        ]);
    }
}
