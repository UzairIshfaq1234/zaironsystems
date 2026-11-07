<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreJobApplicationRequest;
use App\Models\JobApplication;
use App\Models\JobOpening;
use App\Notifications\PortalAlert;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

class CareerController extends Controller
{
    /** Open roles for the careers page. */
    public function openings(): JsonResponse
    {
        $openings = JobOpening::query()
            ->open()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get(['id', 'title', 'team', 'type', 'location', 'description']);

        return response()->json(['data' => $openings]);
    }

    public function apply(StoreJobApplicationRequest $request): JsonResponse
    {
        $cv = $request->file('cv');

        $application = JobApplication::create([
            ...$request->safe()->only(['job_opening_id', 'name', 'email', 'phone', 'links', 'message']),
            // Stored on the private "local" disk (storage/app/private) under a random name; never web-accessible.
            'cv_path' => $cv->store('cvs'),
            'cv_name' => Str::limit(basename($cv->getClientOriginalName()), 175),
            'ip_address' => $request->ip(),
            'user_agent' => Str::limit((string) $request->userAgent(), 495),
        ]);

        $application->load('opening');
        PortalAlert::toTeam('careers.view', new PortalAlert(
            'career',
            "New application for {$application->opening->title}",
            $application->name.' · '.$application->email,
            "/careers/applications/{$application->id}",
        ));

        return response()->json([
            'message' => 'Thanks for applying. Our team reviews every application and will be in touch.',
        ], 201);
    }
}
