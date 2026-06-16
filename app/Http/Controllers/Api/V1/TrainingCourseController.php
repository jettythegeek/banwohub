<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Concerns\ResolvesOrganization;
use App\Http\Controllers\Controller;
use App\Http\Resources\TrainingCourseResource;
use App\Models\TrainingCourse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class TrainingCourseController extends Controller
{
    use ResolvesOrganization;

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', TrainingCourse::class);

        $organization = $this->organizationFor($request->user());

        $courses = TrainingCourse::query()
            ->withCount('enrollments')
            ->where(fn ($q) => $q->where('organization_id', $organization->id)->orWhereNull('organization_id'))
            ->when($request->boolean('published_only', true), fn ($q) => $q->where('is_published', true))
            ->when($request->filled('keyword'), function ($q) use ($request): void {
                $keyword = '%' . $request->string('keyword') . '%';
                $q->where(function ($inner) use ($keyword): void {
                    $inner->where('title', 'like', $keyword)
                        ->orWhere('description', 'like', $keyword);
                });
            })
            ->orderByDesc('updated_at')
            ->paginate($request->integer('per_page', 25));

        return TrainingCourseResource::collection($courses);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', TrainingCourse::class);

        $user = $request->user();
        $organization = $this->organizationFor($user);
        $data = $this->validatedData($request);

        $course = TrainingCourse::query()->create([
            ...$data,
            'organization_id' => $organization->id,
            'created_by' => $user->id,
        ]);

        return (new TrainingCourseResource($course))
            ->response()
            ->setStatusCode(201);
    }

    public function show(TrainingCourse $trainingCourse): TrainingCourseResource
    {
        $this->authorize('view', $trainingCourse);

        return new TrainingCourseResource($trainingCourse->loadCount('enrollments'));
    }

    public function update(Request $request, TrainingCourse $trainingCourse): TrainingCourseResource
    {
        $this->authorize('update', $trainingCourse);

        $trainingCourse->update($this->validatedData($request, partial: true));

        return new TrainingCourseResource($trainingCourse->fresh()->loadCount('enrollments'));
    }

    public function destroy(TrainingCourse $trainingCourse): JsonResponse
    {
        $this->authorize('delete', $trainingCourse);

        $trainingCourse->delete();

        return response()->json(['message' => 'Training course deleted successfully.']);
    }

    /**
     * @return array<string, mixed>
     */
    protected function validatedData(Request $request, bool $partial = false): array
    {
        $data = $request->validate([
            'title' => [$partial ? 'sometimes' : 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'content' => ['nullable', 'string'],
            'video_url' => ['nullable', 'string', 'max:500'],
            'materials_url' => ['nullable', 'string', 'max:500'],
            'cle_credits' => ['nullable', 'numeric', 'min:0'],
            'is_required' => ['nullable', 'boolean'],
            'is_published' => ['nullable', 'boolean'],
            'passing_score' => ['nullable', 'integer', 'min:1', 'max:100'],
            'quiz_questions' => ['nullable', 'array'],
            'quiz_questions.*.question' => ['required_with:quiz_questions', 'string'],
            'quiz_questions.*.options' => ['required_with:quiz_questions', 'array', 'min:2'],
            'quiz_questions.*.options.*' => ['string'],
            'quiz_questions.*.correct_index' => ['required_with:quiz_questions', 'integer', 'min:0'],
        ]);

        if (array_key_exists('quiz_questions', $data)) {
            $data['quiz_questions'] = array_values($data['quiz_questions'] ?? []);
        }

        return $data;
    }
}
