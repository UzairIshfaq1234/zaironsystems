<?php

namespace App\Mail;

use App\Models\Quote;
use App\Support\Seo;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** Tells the team a client accepted or declined a quote from the shared link. */
class QuoteResponded extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Quote $quote) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: "Quote {$this->quote->number} was {$this->quote->status} by {$this->quote->client->displayName()}");
    }

    public function content(): Content
    {
        $accepted = $this->quote->status === 'accepted';

        return new Content(view: 'mail.action', with: [
            'heading' => $accepted ? 'Quote accepted 🎉' : 'Quote declined',
            'lines' => [
                "{$this->quote->client->displayName()} {$this->quote->status} quote {$this->quote->number} ({$this->quote->money($this->quote->total_cents)}).",
                $accepted ? 'Next step: convert it to an invoice from the dashboard.' : 'Consider following up to understand what would work better for them.',
            ],
            'button' => 'Open the quote',
            'url' => Seo::url("/admin/billing/quotes/{$this->quote->id}"),
        ]);
    }
}
