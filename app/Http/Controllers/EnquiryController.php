<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreEnquiryRequest;
use App\Mail\EnquiryConfirmation;
use App\Mail\EnquiryReceived;
use App\Models\Enquiry;
use App\Notifications\PortalAlert;
use App\Support\Team;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class EnquiryController extends Controller
{
    public function store(StoreEnquiryRequest $request): JsonResponse
    {
        $brief = $request->file('brief');

        $enquiry = Enquiry::create([
            ...$request->safe()->only(['name', 'email', 'company', 'phone', 'services', 'service', 'plan', 'budget', 'details']),
            // Stored on the private "local" disk (storage/app/private) under a random name; never web-accessible.
            'brief_path' => $brief?->store('briefs'),
            'brief_name' => $brief ? Str::limit(basename($brief->getClientOriginalName()), 175) : null,
            'ip_address' => $request->ip(),
            'user_agent' => Str::limit((string) $request->userAgent(), 495),
        ]);

        // Both emails are queued, so a slow or failing mail server never affects the visitor.
        if ($recipients = self::teamRecipients()) {
            Mail::to($recipients)->queue(new EnquiryReceived($enquiry));
        }
        Mail::to($enquiry->email, $enquiry->name)->queue(new EnquiryConfirmation($enquiry));
        $enquiry->refresh(); // load columns the visitor left empty before reading them
        PortalAlert::toTeam('enquiries.view', new PortalAlert(
            'enquiry',
            "New enquiry from {$enquiry->name}",
            collect([$enquiry->company, $enquiry->serviceTitle()])->filter()->implode(' · ') ?: $enquiry->email,
            "/enquiries/{$enquiry->id}",
        ));

        return response()->json([
            'message' => 'Thanks for reaching out. A senior engineer will reply within one business day.',
        ], 201);
    }

    /**
     * ZAIRON_INBOX if set; otherwise every active admin who can see enquiries.
     *
     * @return list<string>
     */
    public static function teamRecipients(): array
    {
        $inbox = config('zairon.inbox');

        return $inbox ? [$inbox] : Team::emailsWith('enquiries.view');
    }
}
