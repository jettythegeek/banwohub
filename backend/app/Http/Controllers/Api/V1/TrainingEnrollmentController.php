<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Concerns\ResolvesOrganization;
use App\Http\Controllers\Controller;
use App\Http\Resources\TrainingEnrollmentResource;
use App\Models\TrainingCourse;
use App\Models\TrainingEnrollment;
use App\Models\User;
use App\Services\TrainingCertificateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

class TrainingEnrollmentController extends Controller
{
    use ResolvesOrganization;

    public function __construct(
        protected TrainingCertificateService $certificateService,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', TrainingEnrollment::class);

        $user = $request->user();
        $organization = $this->organizationFor($user);

        $query = TrainingEnrollment::query()
            ->with(['course', 'user:id,name,email', 'certificate'])
            ->where('organization_id', $organization->id);

        if (! $user->can('training.assign')) {
            $query->where('user_id', $user->id);
        } else {
            $query->when($request->filled('user_id'), fn ($q) => $q->where('user_id', $request->integer('user_id')))
                ->when($request->filled('training_course_id'), fn ($q) => $q->where('training_course_id', $request->integer('training_course_id')))
                ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')));
        }

        $enrollments = $query->orderByDesc('updated_at')
            ->paginate($request->integer('per_page', 50));

        return TrainingEnrollmentResource::collection($enrollments)
            ->additional(['meta' => ['statuses' => TrainingEnrollment::STATUSES]]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', TrainingEnrollment::class);

        $assigner = $request->user();
        $organization = $this->organizationFor($assigner);

        $data = $request->validate([
            'training_course_id' => ['required', 'integer', 'exists:training_courses,id'],
            'user_id' => ['required', 'integer', 'exists:users,id'],
        ]);

        TrainingCourse::query()
            ->where(fn ($q) => $q->where('organization_id', $organization->id)->orWhereNull('organization_id'))
            ->findOrFail($data['training_course_id']);

        User::query()
            ->where('organization_id', $organization->id)
            ->findOrFail($data['user_id']);

        $enrollment = TrainingEnrollment::query()->firstOrCreate(
            [
                'organization_id' => $organization->id,
                'training_course_id' => $data['training_course_id'],
                'user_id' => $data['user_id'],
            ],
            [
                'status' => 'assigned',
                'assigned_by' => $assigner->id,
            ]
        );

        return (new TrainingEnrollmentResource($enrollment->load(['course', 'user', 'certificate'])))
            ->response()
            ->setStatusCode($enrollment->wasRecentlyCreated ? 201 : 200);
    }

    public function show(TrainingEnrollment $trainingEnrollment): TrainingEnrollmentResource
    {
        $this->authorize('view', $trainingEnrollment);

        return new TrainingEnrollmentResource($trainingEnrollment->load(['course', 'user', 'certificate']));
    }

    public function update(Request $request, TrainingEnrollment $trainingEnrollment): TrainingEnrollmentResource
    {
        $this->authorize('update', $trainingEnrollment);

        $data = $request->validate([
            'status' => ['sometimes', 'string', Rule::in(TrainingEnrollment::STATUSES)],
        ]);

        if (isset($data['status']) && $data['status'] === 'in_progress' && $trainingEnrollment->started_at === null) {
            $data['started_at'] = now();
        }

        $trainingEnrollment->update($data);

        return new TrainingEnrollmentResource($trainingEnrollment->fresh()->load(['course', 'user', 'certificate']));
    }

    public function submitQuiz(Request $request, TrainingEnrollment $trainingEnrollment): JsonResponse
    {
        $this->authorize('update', $trainingEnrollment);

        if ($trainingEnrollment->user_id !== $request->user()->id && ! $request->user()->can('training.assign')) {
            abort(403);
        }

        $data = $request->validate([
            'answers' => ['required', 'array', 'min:1'],
            'answers.*' => ['integer', 'min:0'],
        ]);

        $course = $trainingEnrollment->course;
        $questions = $course?->quiz_questions ?? [];

        if ($questions === []) {
            return response()->json(['message' => 'This course has no quiz.'], 422);
        }

        if (count($data['answers']) !== count($questions)) {
            return response()->json(['message' => 'Answer every quiz question.'], 422);
        }

        $correct = 0;
        foreach ($questions as $index => $question) {
            $correctIndex = (int) ($question['correct_index'] ?? -1);
            if (($data['answers'][$index] ?? null) === $correctIndex) {
                $correct++;
            }
        }

        $score = (int) round(($correct / count($questions)) * 100);
        $passing = (int) ($course->passing_score ?? 70);
        $passed = $score >= $passing;

        $trainingEnrollment->update([
            'quiz_score' => $score,
            'status' => $passed ? 'completed' : 'failed',
            'cle_credits_earned' => $passed ? $course->cle_credits : null,
            'completed_at' => $passed ? now() : null,
            'started_at' => $trainingEnrollment->started_at ?? now(),
        ]);

        $certificate = null;
        if ($passed) {
            $certificate = $this->certificateService->issue($trainingEnrollment->fresh());
        }

        return response()->json([
            'score' => $score,
            'passed' => $passed,
            'passing_score' => $passing,
            'cle_credits_earned' => $passed ? (float) $course->cle_credits : null,
            'enrollment' => new TrainingEnrollmentResource(
                $trainingEnrollment->fresh()->load(['course', 'user', 'certificate'])
            ),
            'certificate' => $certificate ? [
                'certificate_number' => $certificate->certificate_number,
                'issued_at' => $certificate->issued_at?->toIso8601String(),
            ] : null,
        ]);
    }
}
