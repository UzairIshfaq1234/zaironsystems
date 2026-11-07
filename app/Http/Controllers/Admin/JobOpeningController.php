<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Admin;
use App\Models\ActivityLog;
use App\Models\JobOpening;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class JobOpeningController extends Controller
{
    public function index(): JsonResponse
    {
        $openings = JobOpening::query()
            ->withCount('applications')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        return response()->json(['data' => $openings->map(fn (JobOpening $o) => Admin::jobOpening($o))->values()]);
    }

    public function store(Request $request): JsonResponse
    {
        $opening = JobOpening::create($this->validated($request));
        ActivityLog::record('career.opening.created', "Created job opening “{$opening->title}”", $opening);

        return response()->json(['data' => Admin::jobOpening($opening)], 201);
    }

    public function update(Request $request, JobOpening $jobOpening): JsonResponse
    {
        $jobOpening->update($this->validated($request, partial: true));
        ActivityLog::record('career.opening.updated', "Updated job opening “{$jobOpening->title}”", $jobOpening);

        return response()->json(['data' => Admin::jobOpening($jobOpening)]);
    }

    public function destroy(JobOpening $jobOpening): JsonResponse
    {
        ActivityLog::record('career.opening.deleted', "Deleted job opening “{$jobOpening->title}”", $jobOpening);
        $jobOpening->delete();

        return response()->json(status: 204);
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, bool $partial = false): array
    {
        $required = $partial ? 'sometimes' : 'required';

        return $request->validate([
            'title' => [$required, 'string', 'max:160'],
            'team' => [$required, 'string', 'max:80'],
            'type' => [$required, 'string', 'max:40'],
            'location' => [$required, 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:5000'],
            'status' => ['sometimes', Rule::in(JobOpening::STATUSES)],
            'sort_order' => ['sometimes', 'integer', 'min:0', 'max:999'],
        ]);
    }
}
