<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\TaskCommentResource;
use App\Models\LegalTask;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class LegalTaskCommentController extends Controller
{
    public function index(LegalTask $task): AnonymousResourceCollection
    {
        $this->authorize('view', $task);

        $comments = $task->comments()
            ->with('user:id,name')
            ->oldest()
            ->get();

        return TaskCommentResource::collection($comments);
    }

    public function store(Request $request, LegalTask $task): JsonResponse
    {
        $this->authorize('update', $task);

        $data = $request->validate([
            'body' => ['required', 'string', 'max:5000'],
        ]);

        $comment = $task->comments()->create([
            'user_id' => $request->user()->id,
            'body' => $data['body'],
        ]);

        return (new TaskCommentResource($comment->load('user:id,name')))
            ->response()
            ->setStatusCode(201);
    }
}
