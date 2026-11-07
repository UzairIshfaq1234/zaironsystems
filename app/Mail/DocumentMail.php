<?php

namespace App\Mail;

use App\Models\Invoice;
use App\Models\Quote;
use App\Support\Billing;
use App\Support\DocumentPdf;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Sends a quote or invoice (or an overdue reminder) to the client with a secure view link and,
 * when given, the PDF saved for this send (App\Support\DocumentPdf::storeSentCopy) attached.
 */
class DocumentMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Quote|Invoice $doc, public string $variant = 'sent', public ?string $personal = null, public ?string $pdfPath = null) {}

    /** @return list<Attachment> */
    public function attachments(): array
    {
        // `??`: jobs queued before PDFs existed were serialised without this property.
        $path = $this->pdfPath ?? null;

        return $path
            ? [Attachment::fromStorageDisk('local', $path)->as(DocumentPdf::filename($this->doc))->withMime('application/pdf')]
            : [];
    }

    public function envelope(): Envelope
    {
        $company = Billing::settings();
        $label = $this->doc::KIND === 'invoice' ? 'Invoice' : 'Quote';

        return new Envelope(
            subject: $this->variant === 'reminder'
                ? "Reminder: invoice {$this->doc->number} is overdue"
                : "{$label} {$this->doc->number} from {$company['company_name']}",
            replyTo: [new Address($company['company_email'], $company['company_name'])],
        );
    }

    public function content(): Content
    {
        return new Content(view: 'mail.document', with: ['company' => Billing::settings()]);
    }
}
