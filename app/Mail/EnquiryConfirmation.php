<?php

namespace App\Mail;

use App\Models\Enquiry;
use App\Support\Content as SiteContent;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** "We received your message" auto-reply to the visitor. */
class EnquiryConfirmation extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Enquiry $enquiry) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'We received your message — ZAIRON Systems',
            replyTo: [new Address(config('zairon.inbox') ?: SiteContent::get('site')['email'], 'ZAIRON Systems')],
        );
    }

    public function content(): Content
    {
        return new Content(view: 'mail.enquiry-confirmation');
    }
}
