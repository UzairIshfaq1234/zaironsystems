{{-- Quote / invoice email to a client. $variant: sent | reminder. Everything user-entered is escaped. --}}
@php
    $isInvoice = $doc::KIND === 'invoice';
    $label = $isInvoice ? 'Invoice' : 'Quote';
@endphp
<x-mail-layout :title="$label.' '.$doc->number" :preheader="$label.' '.$doc->number.' · '.$doc->money($isInvoice ? $doc->balanceCents() : $doc->total_cents)">
    <h1 style="margin:0 0 16px;font-size:22px;">
        @if ($variant === 'reminder')
            Friendly reminder: invoice {{ $doc->number }} is overdue
        @else
            {{ $label }} {{ $doc->number }} from {{ $company['company_name'] }}
        @endif
    </h1>
    <p style="margin:0 0 14px;">Hi {{ $doc->client->name }},</p>

    @if ($personal)
        <div style="margin:0 0 16px;white-space:pre-wrap;">{{ $personal }}</div>
    @elseif ($variant === 'reminder')
        <p style="margin:0 0 14px;">Our records show this invoice was due on {{ $doc->due_date->toFormattedDateString() }}. If you've already paid, thank you and please ignore this message.</p>
    @elseif ($isInvoice)
        <p style="margin:0 0 14px;">Please find your invoice below. You can view, print or save it as a PDF using the button.</p>
    @else
        <p style="margin:0 0 14px;">Here is our quote for your project. You can review it and accept or decline online.</p>
    @endif

    <table role="presentation" width="100%" cellpadding="10" cellspacing="0" style="margin:8px 0 4px;background:#faf7f7;border-radius:12px;font-size:14px;">
        <tr>
            <td style="color:#6b5c5c;">{{ $label }} number</td>
            <td align="right" style="font-weight:bold;">{{ $doc->number }}</td>
        </tr>
        <tr>
            <td style="color:#6b5c5c;">{{ $isInvoice ? ($doc->paid_cents > 0 ? 'Balance due' : 'Amount due') : 'Total' }}</td>
            <td align="right" style="font-weight:bold;font-size:18px;color:#800000;">{{ $doc->money($isInvoice ? $doc->balanceCents() : $doc->total_cents) }}</td>
        </tr>
        <tr>
            <td style="color:#6b5c5c;">{{ $isInvoice ? 'Due date' : 'Valid until' }}</td>
            <td align="right">{{ ($isInvoice ? $doc->due_date : $doc->valid_until)?->toFormattedDateString() ?? '—' }}</td>
        </tr>
    </table>

    <table role="presentation" cellpadding="0" cellspacing="0" style="margin:22px 0;">
        <tr>
            <td style="border-radius:999px;background:#800000;">
                <a href="{{ $doc->publicUrl() }}" style="display:inline-block;padding:14px 28px;font-weight:bold;color:#ffffff;text-decoration:none;border-radius:999px;">
                    {{ $isInvoice ? 'View invoice' : 'Review quote' }}
                </a>
            </td>
        </tr>
    </table>

    @if ($isInvoice && $company['payment_instructions'])
        <p style="margin:0 0 6px;font-weight:bold;">How to pay</p>
        <div style="margin:0 0 14px;white-space:pre-wrap;font-size:14px;">{{ $company['payment_instructions'] }}</div>
    @endif

    <p style="margin:16px 0 0;">Questions? Just reply to this email.</p>
    <p style="margin:12px 0 0;">— {{ $company['company_name'] }}</p>
</x-mail-layout>
