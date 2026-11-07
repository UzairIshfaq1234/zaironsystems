<?php

namespace App\Http\Controllers;

use App\Mail\QuoteResponded;
use App\Models\ActivityLog;
use App\Models\Invoice;
use App\Models\Quote;
use App\Notifications\PortalAlert;
use App\Support\Billing;
use App\Support\DocumentPdf;
use App\Support\Team;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Mail;

/**
 * The client-facing quote/invoice page, reached only through the unguessable link in the email.
 * Printable (and "Save as PDF") straight from the browser; quotes can be accepted or declined.
 */
class DocumentViewController extends Controller
{
    public function show(Request $request, string $kind, string $token): Response
    {
        $doc = $this->find($kind, $token);
        abort_if($doc->status === 'draft', 404);

        // Record the first time the client opens it (not when a signed-in admin previews it).
        if (! $doc->viewed_at && ! $request->user()) {
            $doc->forceFill(['viewed_at' => now()])->save();
            PortalAlert::toTeam('billing.view', new PortalAlert(
                'viewed', "{$doc->client->displayName()} opened {$doc->number}", ucfirst($kind).' viewed for the first time', "/billing/{$kind}s/{$doc->id}",
            ));
        }

        return response()->view('billing.document', [
            'doc' => $doc->load('items', 'client', ...($kind === 'invoice' ? ['payments'] : [])),
            'company' => Billing::settings(),
            'isInvoice' => $kind === 'invoice',
            'terms' => Billing::termsFor($kind),
            'notes' => $doc->notes ?: ($kind === 'quote' ? Billing::settings()['quote_notes'] : null),
        ])->header('X-Robots-Tag', 'noindex, nofollow')->header('Cache-Control', 'no-store, private');
    }

    /** The client's PDF download, rendered from the document as it is now (e.g. showing payments made since). */
    public function pdf(string $kind, string $token): Response
    {
        $doc = $this->find($kind, $token);
        abort_if($doc->status === 'draft', 404);

        return DocumentPdf::response(DocumentPdf::render($doc), DocumentPdf::filename($doc));
    }

    public function respond(Request $request, string $token): RedirectResponse
    {
        $quote = $this->find('quote', $token);
        $data = $request->validate(['answer' => ['required', 'in:accepted,declined']]);

        if ($quote->isOpen()) {
            $quote->forceFill(['status' => $data['answer'], 'responded_at' => now()])->save();
            ActivityLog::record("quote.{$data['answer']}", "{$quote->client->displayName()} {$data['answer']} quote {$quote->number} online", $quote);
            if ($team = Team::emailsWith('billing.view')) {
                Mail::to($team)->queue(new QuoteResponded($quote));
            }
            PortalAlert::toTeam('billing.view', new PortalAlert(
                'quote', "{$quote->client->displayName()} {$data['answer']} {$quote->number}", $quote->money($quote->total_cents), "/billing/quotes/{$quote->id}",
            ));
        }

        return redirect()->to($quote->publicUrl());
    }

    private function find(string $kind, string $token): Quote|Invoice
    {
        $model = $kind === 'invoice' ? Invoice::class : Quote::class;

        return $model::with('client')->where('public_token', $token)->firstOrFail();
    }
}
