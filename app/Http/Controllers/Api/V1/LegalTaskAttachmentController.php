<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\TaskAttachmentResource;
use App\Models\LegalTask;
use App\Models\TaskAttachment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LegalTaskAttachmentController extends Controller
{
    public function index(LegalTask $task): AnonymousResourceCollection
    {
        $this->authorize('view', $task);

        $attachments = $task->attachments()
            ->with('uploader:id,name')
            ->latest()
            ->get();

        return TaskAttachmentResource::collection($attachments);
    }

    public function store(Request $request, LegalTask $task): JsonResponse
    {
        $this->authorize('update', $task);

        $data = $request->validate([
            'file' => ['required', 'file', 'max:20480'],
        ]);

        $file = $request->file('file');
        $pathPrefix = "organizations/{$task->organization_id}/cases/{$task->legal_matter_id}/tasks/{$task->id}";
        $path = $file->store($pathPrefix, 'local');

        $attachment = $task->attachments()->create([
            'uploaded_by' => $request->user()->id,
            'name' => $file->getClientOriginalName(),
            'path' => $path,
            'disk' => 'local',
            'mime_type' => $file->getClientMimeType(),
            'size' => $file->getSize() ?: 0,
        ]);

        return (new TaskAttachmentResource($attachment->load('uploader:id,name')))
            ->response()
            ->setStatusCode(201);
    }

    public function download(LegalTask $task, TaskAttachment $task_attachment): StreamedResponse
    {
        $this->authorize('view', $task);
        abort_unless($task_attachment->legal_task_id === $task->id, 404);
        $attachment = $task_attachment;

        abort_unless(
            Storage::disk($attachment->disk)->exists($attachment->path),
            404,
            'Attachment file not found.'
        );

        return Storage::disk($attachment->disk)->download($attachment->path, $attachment->name);
    }

    public function destroy(LegalTask $task, TaskAttachment $task_attachment): JsonResponse
    {
        $this->authorize('update', $task);
        abort_unless($task_attachment->legal_task_id === $task->id, 404);
        $attachment = $task_attachment;

        if (Storage::disk($attachment->disk)->exists($attachment->path)) {
            Storage::disk($attachment->disk)->delete($attachment->path);
        }

        $attachment->delete();

        return response()->json(['message' => 'Attachment deleted successfully.']);
    }
}
