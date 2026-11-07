<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Admin;
use App\Models\ActivityLog;
use App\Models\Enquiry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EnquiryController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(Enquiry::STATUSES)],
            'service' => ['nullable', 'string', 'max:80'],
        ]);

        $page = Enquiry::query()
            ->when($request->input('q'), function ($query, string $q) {
                $like = '%'.addcslashes($q, '%_\\').'%';
                $query->where(fn ($w) => $w->where('name', 'like', $like)->orWhere('email', 'like', $like)->orWhere('company', 'like', $like));
            })
            ->when($request->input('status'), fn ($query, $status) => $query->where('status', $status))
            ->when($request->input('service'), fn ($query, $service) => $query->where('service', $service))
            ->latest()
            ->paginate(15);

        return response()->json(Admin::page($page, fn ($e) => Admin::enquiry($e)));
    }

    public function show(Enquiry $enquiry): JsonResponse
    {
        return response()->json(['data' => Admin::enquiry($enquiry, full: true)]);
    }

    public function update(Request $request, Enquiry $enquiry): JsonResponse
    {
        $data = $request->validate([
            'status' => ['sometimes', Rule::in(Enquiry::STATUSES)],
            'notes' => ['sometimes', 'nullable', 'string', 'max:5000'],
        ]);

        $before = $enquiry->status;
        $enquiry->update($data);

        if (isset($data['status']) && $data['status'] !== $before) {
            ActivityLog::record('enquiry.status', "Enquiry #{$enquiry->id} moved from {$before} to {$enquiry->status}", $enquiry);
        } elseif (array_key_exists('notes', $data)) {
            ActivityLog::record('enquiry.notes', "Notes updated on enquiry #{$enquiry->id}", $enquiry);
        }

        return response()->json(['data' => Admin::enquiry($enquiry, full: true)]);
    }

    public function destroy(Enquiry $enquiry): JsonResponse
    {
        if ($enquiry->brief_path) {
            Storage::disk('local')->delete($enquiry->brief_path);
        }
        $enquiry->delete();
        ActivityLog::record('enquiry.deleted', "Deleted enquiry #{$enquiry->id} from {$enquiry->name}");

        return response()->json(status: 204);
    }

    /** Streams the private attachment; never exposes its storage path. */
    public function brief(Enquiry $enquiry): StreamedResponse
    {
        abort_unless($enquiry->brief_path && Storage::disk('local')->exists($enquiry->brief_path), 404);
        ActivityLog::record('enquiry.download', "Downloaded the attachment on enquiry #{$enquiry->id}", $enquiry);

        return Storage::disk('local')->download($enquiry->brief_path, $enquiry->brief_name ?: 'brief');
    }
}
