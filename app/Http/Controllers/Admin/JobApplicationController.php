<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Admin;
use App\Models\ActivityLog;
use App\Models\JobApplication;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class JobApplicationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(JobApplication::STATUSES)],
            'opening' => ['nullable', 'integer'],
        ]);

        $page = JobApplication::query()
            ->with('opening:id,title')
            ->when($request->input('q'), function ($query, string $q) {
                $like = '%'.addcslashes($q, '%_\\').'%';
                $query->where(fn ($w) => $w->where('name', 'like', $like)->orWhere('email', 'like', $like));
            })
            ->when($request->input('status'), fn ($query, $status) => $query->where('status', $status))
            ->when($request->input('opening'), fn ($query, $opening) => $query->where('job_opening_id', $opening))
            ->latest()
            ->paginate(15);

        return response()->json(Admin::page($page, fn ($a) => Admin::jobApplication($a)));
    }

    public function show(JobApplication $jobApplication): JsonResponse
    {
        $jobApplication->load('opening:id,title');

        return response()->json(['data' => Admin::jobApplication($jobApplication, full: true)]);
    }

    public function update(Request $request, JobApplication $jobApplication): JsonResponse
    {
        $data = $request->validate([
            'status' => ['sometimes', Rule::in(JobApplication::STATUSES)],
            'notes' => ['sometimes', 'nullable', 'string', 'max:5000'],
        ]);

        $before = $jobApplication->status;
        $jobApplication->update($data);

        if (isset($data['status']) && $data['status'] !== $before) {
            ActivityLog::record('career.status', "Application #{$jobApplication->id} moved from {$before} to {$jobApplication->status}", $jobApplication);
        } elseif (array_key_exists('notes', $data)) {
            ActivityLog::record('career.notes', "Notes updated on application #{$jobApplication->id}", $jobApplication);
        }

        $jobApplication->load('opening:id,title');

        return response()->json(['data' => Admin::jobApplication($jobApplication, full: true)]);
    }

    public function destroy(JobApplication $jobApplication): JsonResponse
    {
        if ($jobApplication->cv_path) {
            Storage::disk('local')->delete($jobApplication->cv_path);
        }
        ActivityLog::record('career.deleted', "Deleted application #{$jobApplication->id} from {$jobApplication->name}");
        $jobApplication->delete();

        return response()->json(status: 204);
    }

    /** Streams the private CV; never exposes its storage path. */
    public function cv(JobApplication $jobApplication): StreamedResponse
    {
        abort_unless($jobApplication->cv_path && Storage::disk('local')->exists($jobApplication->cv_path), 404);
        ActivityLog::record('career.cv', "Downloaded the CV for application #{$jobApplication->id}", $jobApplication);

        return Storage::disk('local')->download($jobApplication->cv_path, $jobApplication->cv_name ?: 'cv');
    }
}
