{{-- A4 quote/invoice PDF rendered by App\Support\DocumentPdf (dompdf: CSS 2.1 + tables, no flex/grid). --}}
@php
    $label = $isInvoice ? 'Invoice' : 'Quotation';
    $status = $doc->displayStatus();
    $statusLabels = ['draft' => 'Draft', 'sent' => $isInvoice ? 'Payment due' : 'Awaiting response', 'partial' => 'Partly paid', 'paid' => 'Paid', 'overdue' => 'Overdue', 'void' => 'Void', 'accepted' => 'Accepted', 'declined' => 'Declined', 'expired' => 'Expired', 'invoiced' => 'Accepted'];
    $tone = match ($status) {
        'paid', 'accepted', 'invoiced' => 'green',
        'overdue' => 'red',
        'partial' => 'amber',
        'void', 'declined', 'expired', 'draft' => 'grey',
        default => 'brand',
    };
    $qty = fn ($q) => rtrim(rtrim(number_format((float) $q, 2), '0'), '.');
    $multiline = fn (?string $text) => nl2br(e(trim((string) $text)), false);
    $dueCents = $isInvoice ? $doc->balanceCents() : $doc->total_cents;
    $dueDate = $isInvoice ? $doc->due_date : $doc->valid_until;
    $logo = 'data:image/svg+xml;base64,'.base64_encode('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><g fill="#ffffff"><polygon points="12,12 88,12 88,30 71.2,44 44.5,44 62,30 12,30"/><polygon points="29.5,56 56.8,56 40,70 88,70 88,88 12,88 12,70"/><circle cx="50.5" cy="50" r="3.8"/></g></svg>');
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>{{ $label }} {{ $doc->number }}</title>
<style>
    @font-face { font-family: 'Manrope'; font-weight: 400; src: url('file://{{ $fontDir }}/Manrope-Regular.ttf') format('truetype'); }
    @font-face { font-family: 'Manrope'; font-weight: 600; src: url('file://{{ $fontDir }}/Manrope-SemiBold.ttf') format('truetype'); }
    @font-face { font-family: 'Manrope'; font-weight: 700; src: url('file://{{ $fontDir }}/Manrope-Bold.ttf') format('truetype'); }
    @font-face { font-family: 'Sora'; font-weight: 600; src: url('file://{{ $fontDir }}/Sora-SemiBold.ttf') format('truetype'); }
    @font-face { font-family: 'Sora'; font-weight: 700; src: url('file://{{ $fontDir }}/Sora-Bold.ttf') format('truetype'); }

    @page { margin: 40px 0 74px 0; }
    body { margin: 0; font-family: 'Manrope', 'DejaVu Sans', sans-serif; font-size: 9pt; line-height: 1.5; color: #2b2124; }
    table { border-collapse: collapse; width: 100%; }
    td, th { vertical-align: top; padding: 0; }
    .head { font-family: 'Sora', 'DejaVu Sans', sans-serif; font-weight: 700; }
    .muted { color: #86777a; }
    .num { text-align: right; white-space: nowrap; }
    .caps { font-size: 6.8pt; font-weight: 700; letter-spacing: 1.3px; text-transform: uppercase; color: #9a8a8d; }

    /* Brand band (bleeds into the top page margin) */
    .band { margin-top: -40px; background: #1a090e; color: #ffffff; padding: 34px 46px 28px; border-bottom: 5px solid #b3142c; }
    .logo { width: 38px; height: 38px; padding: 9px; background: #b3142c; border-radius: 10px; }
    .brand-name { font-size: 15pt; letter-spacing: 3px; padding-top: 4px; }
    .brand-tag { font-size: 7.5pt; color: #e8a3ad; letter-spacing: 1.5px; }
    .company-lines { font-size: 7.8pt; color: #cdbfc2; line-height: 1.55; padding-top: 12px; }
    .kind { font-size: 25pt; letter-spacing: 4px; text-align: right; line-height: 1.1; }
    .doc-number { text-align: right; font-size: 9pt; color: #f1c4cb; letter-spacing: 0.5px; padding-top: 4px; }
    .pill-row { text-align: right; padding-top: 10px; }
    .pill { display: inline-block; padding: 3px 11px; border-radius: 10px; font-size: 7.5pt; font-weight: 700; letter-spacing: 0.6px; text-transform: uppercase; }
    .pill.brand { background: #b3142c; color: #ffffff; }
    .pill.green { background: #16a34a; color: #ffffff; }
    .pill.red { background: #e11d48; color: #ffffff; }
    .pill.amber { background: #d97706; color: #ffffff; }
    .pill.grey { background: #5b4c50; color: #ffffff; }

    .content { padding: 26px 46px 0; }

    /* Parties, dates and the amount-due card */
    .info td { padding-right: 18px; }
    .info td.last { padding-right: 0; }
    .party { font-size: 11pt; font-weight: 700; color: #1a090e; padding-top: 5px; }
    .lines { font-size: 8.3pt; color: #5e5053; line-height: 1.55; }
    .facts td { padding: 2px 0; font-size: 8.3pt; }
    .facts .k { color: #86777a; width: 46%; }
    .facts .v { font-weight: 600; color: #2b2124; }
    .due-card { background: #fbeff1; border: 1px solid #f3d5da; border-radius: 10px; padding: 14px 16px; }
    .due-amount { font-size: 17pt; color: #9b0f24; padding-top: 4px; line-height: 1.15; }
    .due-sub { font-size: 7.8pt; color: #86777a; padding-top: 3px; }

    /* Line items */
    .items { margin-top: 24px; }
    .items th { background: #1a090e; color: #ffffff; font-size: 6.8pt; font-weight: 700; letter-spacing: 1.2px; text-transform: uppercase; padding: 9px 10px; text-align: left; }
    .items th.first { padding-left: 14px; }
    .items th.end { padding-right: 14px; }
    .items td { padding: 10px 10px; border-bottom: 1px solid #efe6e8; font-size: 8.8pt; }
    .items td.first { padding-left: 14px; color: #b3142c; font-weight: 700; width: 22px; }
    .items td.end { padding-right: 14px; font-weight: 700; color: #1a090e; }
    .items tr.alt td { background: #fcf8f9; }
    .item-title { font-weight: 700; color: #1a090e; }
    .item-detail { color: #7b6c70; font-size: 8pt; padding-top: 2px; }

    /* Totals */
    .totals-wrap { margin-top: 14px; }
    .totals td { padding: 5px 14px; font-size: 9pt; }
    .totals .k { color: #6d5e62; }
    .grand-box { background: #1a090e; border-radius: 8px; margin: 6px 0 2px; }
    .grand-box td { color: #ffffff; padding: 11px 14px; font-family: 'Sora', 'DejaVu Sans', sans-serif; font-weight: 700; font-size: 11.5pt; }
    .grand-box td.k { color: #f1c4cb; font-size: 9.5pt; letter-spacing: 1px; padding-top: 13px; }
    .totals .balance td { color: #9b0f24; font-weight: 700; font-size: 10.5pt; padding-top: 9px; }

    /* Payments, instructions, notes */
    .block { margin-top: 20px; page-break-inside: avoid; }
    .block-title { font-size: 7pt; font-weight: 700; letter-spacing: 1.3px; text-transform: uppercase; color: #b3142c; padding-bottom: 6px; }
    .payments td { padding: 6px 0; border-bottom: 1px solid #f1eaeb; font-size: 8.3pt; }
    .card { background: #faf6f7; border-left: 3px solid #b3142c; border-radius: 0 8px 8px 0; padding: 11px 14px; font-size: 8.3pt; color: #4a3d40; line-height: 1.6; }
    /* Compact layout for long documents (DocumentPdf falls back to it so the document stays on one page). */
    .compact .item-detail { display: none; }
    .compact .items td { padding: 2px 10px; line-height: 1.2; }
    .compact .items th { padding: 5px 10px; }
    .compact .items td.first { padding-left: 14px; }
    .compact .items td.end { padding-right: 14px; }
    .compact .band { padding: 16px 40px 12px; }
    .compact .company-lines { padding-top: 6px; line-height: 1.4; }
    .compact .content { padding-top: 14px; }
    .compact .info td { padding-bottom: 0; }
    .compact .items { margin-top: 10px; }
    .compact .totals-wrap { margin-top: 4px; }
    .compact .totals td { padding: 2px 14px; }
    .compact .totals .grand-box td { padding: 7px 14px; }
    .compact .block { margin-top: 8px; }
    .compact .notes-terms td { padding: 6px 10px; }
    .compact .thanks { display: none; }
    .notes-terms { width: 100%; border-collapse: separate; border-spacing: 12px 0; margin-left: -12px; margin-right: -12px; }
    .notes-terms td { width: 50%; vertical-align: top; background: #faf6f7; border-radius: 8px; padding: 12px 14px; }
    .notes-terms .notes { border-left: 3px solid #5b4c50; }
    .notes-terms .terms { border-left: 3px solid #b3142c; }
    .notes-terms .block-title { padding-bottom: 4px; }
    .lines { font-size: 7.9pt; color: #4a3d40; line-height: 1.5; }
    .thanks { margin-top: 26px; font-size: 12pt; color: #1a090e; }
    .thanks span { color: #b3142c; }

    /* Paid / void stamp */
    /* Solid light colours, not opacity: dompdf carries opacity over into later drawing. */
    .stamp { display: inline-block; padding: 4px 16px; border: 3px solid; border-radius: 8px; font-family: 'Sora', 'DejaVu Sans', sans-serif; font-weight: 700; font-size: 20pt; letter-spacing: 5px; transform: rotate(-10deg); }
    .stamp.paid { color: #9fd3b0; border-color: #9fd3b0; }
    .stamp.void { color: #c9bfc1; border-color: #c9bfc1; }

    /* Footer on every page (page numbers are drawn by DocumentPdf) */
    .footer { position: fixed; left: 46px; right: 46px; bottom: -52px; height: 30px; border-top: 1px solid #ece3e5; padding-top: 9px; font-size: 7.5pt; color: #8a7a7d; }
    .footer b { color: #1a090e; }
</style>
</head>
<body>
    <div class="footer">
        <b>{{ $company['company_name'] }}</b> · {{ $company['company_email'] }}@if ($company['company_phone']) · {{ $company['company_phone'] }}@endif @if ($company['tax_number']) · NTN {{ $company['tax_number'] }}@endif
    </div>

    <div class="band">
        <table>
            <tr>
                <td style="width: 58%;">
                    <table style="width: auto;">
                        <tr>
                            <td style="width: 66px;"><img class="logo" src="{{ $logo }}" alt=""></td>
                            <td>
                                <div class="head brand-name">{{ strtoupper($company['company_name']) }}</div>
                                <div class="brand-tag">IDEAS, TO REALITY.</div>
                            </td>
                        </tr>
                    </table>
                    <div class="company-lines">
                        @if ($company['company_address']){!! $multiline($company['company_address']) !!}<br>@endif
                        {{ $company['company_email'] }}@if ($company['company_phone']) &nbsp;·&nbsp; {{ $company['company_phone'] }}@endif
                        @if ($company['tax_number'])<br>NTN / Tax no. {{ $company['tax_number'] }}@endif
                    </div>
                </td>
                <td style="width: 42%;">
                    <div class="head kind">{{ strtoupper($label) }}</div>
                    <div class="doc-number">No. {{ $doc->number }}</div>
                    <div class="pill-row"><span class="pill {{ $tone }}">{{ $statusLabels[$status] ?? ucfirst($status) }}</span></div>
                </td>
            </tr>
        </table>
    </div>

    <div class="content">
        <table class="info">
            <tr>
                <td style="width: 36%;">
                    <div class="caps">{{ $isInvoice ? 'Billed to' : 'Prepared for' }}</div>
                    <div class="party">{{ $doc->client->company ?: $doc->client->name }}</div>
                    <div class="lines">
                        @if ($doc->client->company){{ $doc->client->name }}<br>@endif
                        {{ $doc->client->email }}
                        @if ($doc->client->phone)<br>{{ $doc->client->phone }}@endif
                        @if ($doc->client->address)<br>{!! $multiline($doc->client->address) !!}@endif
                        @if ($doc->client->tax_number)<br>Tax no. {{ $doc->client->tax_number }}@endif
                    </div>
                </td>
                <td style="width: 30%;">
                    <div class="caps">Details</div>
                    <table class="facts" style="margin-top: 5px;">
                        <tr><td class="k">Issued</td><td class="v">{{ $doc->issue_date->format('j M Y') }}</td></tr>
                        @if ($isInvoice)
                            <tr><td class="k">Due</td><td class="v">{{ $doc->due_date->format('j M Y') }}</td></tr>
                        @elseif ($doc->valid_until)
                            <tr><td class="k">Valid until</td><td class="v">{{ $doc->valid_until->format('j M Y') }}</td></tr>
                        @endif
                        <tr><td class="k">Currency</td><td class="v">{{ $doc->currency }}</td></tr>
                        @if ($isInvoice && $doc->quote)
                            <tr><td class="k">Quote</td><td class="v">{{ $doc->quote->number }}</td></tr>
                        @endif
                    </table>
                </td>
                <td class="last" style="width: 34%;">
                    <div class="due-card">
                        <div class="caps" style="color: #b3142c;">{{ $isInvoice ? ($status === 'paid' ? 'Paid in full' : 'Amount due') : 'Quote total' }}</div>
                        <div class="head due-amount">{{ $doc->money($isInvoice && $status === 'paid' ? $doc->total_cents : $dueCents) }}</div>
                        <div class="due-sub">
                            @if ($isInvoice && $status === 'paid')
                                Thank you, nothing is owed.
                            @elseif ($dueDate)
                                {{ $isInvoice ? 'Due by' : 'Valid until' }} {{ $dueDate->format('j F Y') }}
                            @endif
                        </div>
                    </div>
                </td>
            </tr>
        </table>

        <table class="items">
            <thead>
                <tr>
                    <th class="first">#</th>
                    <th>Description</th>
                    <th class="num" style="width: 44px;">Qty</th>
                    <th class="num" style="width: 92px;">Unit price</th>
                    <th class="num end" style="width: 100px;">Amount</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($doc->items as $i => $item)
                    @php [$title, $detail] = array_pad(explode("\n", trim($item->description), 2), 2, null); @endphp
                    <tr class="{{ $i % 2 ? 'alt' : '' }}">
                        <td class="first">{{ str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT) }}</td>
                        <td>
                            <div class="item-title">{{ $title }}</div>
                            @if ($detail)<div class="item-detail">{!! $multiline($detail) !!}</div>@endif
                        </td>
                        <td class="num">{{ $qty($item->quantity) }}</td>
                        <td class="num">{{ $doc->money($item->unit_cents) }}</td>
                        <td class="num end">{{ $doc->money($item->amount_cents) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <table class="totals-wrap">
            <tr>
                <td style="width: 52%; padding-right: 24px;">
                    @if ($isInvoice && $doc->payments->isNotEmpty())
                        <div class="block" style="margin-top: 4px;">
                            <div class="block-title">Payments received</div>
                            <table class="payments">
                                @foreach ($doc->payments as $payment)
                                    <tr>
                                        <td>{{ $payment->paid_on->format('j M Y') }}</td>
                                        <td class="muted">{{ $payment->method }}@if ($payment->reference) · {{ $payment->reference }}@endif</td>
                                        <td class="num" style="font-weight: 700;">{{ $doc->money($payment->amount_cents) }}</td>
                                    </tr>
                                @endforeach
                            </table>
                        </div>
                    @endif
                </td>
                <td style="width: 48%;">
                    <table class="totals">
                        <tr><td class="k">Subtotal</td><td class="num">{{ $doc->money($doc->subtotal_cents) }}</td></tr>
                        @if ($doc->discount_cents > 0)
                            <tr><td class="k">Discount</td><td class="num">−{{ $doc->money($doc->discount_cents) }}</td></tr>
                        @endif
                        @if ((float) $doc->tax_rate > 0)
                            <tr><td class="k">Tax ({{ $qty($doc->tax_rate) }}%)</td><td class="num">{{ $doc->money($doc->tax_cents) }}</td></tr>
                        @endif
                        <tr><td colspan="2" style="padding: 0;"><div class="grand-box"><table><tr><td class="k">TOTAL</td><td class="num">{{ $doc->money($doc->total_cents) }}</td></tr></table></div></td></tr>
                        @if ($isInvoice && $doc->paid_cents > 0)
                            <tr><td class="k" style="padding-top: 9px;">Paid</td><td class="num" style="padding-top: 9px;">−{{ $doc->money($doc->paid_cents) }}</td></tr>
                            <tr class="balance"><td>Balance due</td><td class="num">{{ $doc->money($doc->balanceCents()) }}</td></tr>
                        @endif
                    </table>
                </td>
            </tr>
        </table>

        @if ($isInvoice && $company['payment_instructions'] && ! in_array($status, ['paid', 'void'], true))
            <div class="block">
                <div class="block-title">How to pay</div>
                <div class="card">{!! $multiline($company['payment_instructions']) !!}<br><span class="muted">Please quote <b>{{ $doc->number }}</b> as the payment reference.</span></div>
            </div>
        @endif
        @if ($notes || $terms)
            <table class="block notes-terms">
                <tr>
                    <td class="notes">
                        @if ($notes)
                            <div class="block-title">Notes</div>
                            <div class="lines">{!! $multiline($notes) !!}</div>
                        @endif
                    </td>
                    <td class="terms">
                        @if ($terms)
                            <div class="block-title">Terms &amp; conditions</div>
                            <div class="lines">{!! $multiline($terms) !!}</div>
                        @endif
                    </td>
                </tr>
            </table>
        @endif

        <table class="thanks">
            <tr>
                <td class="head" style="vertical-align: middle;">Thank you for your business<span>.</span></td>
                @if (in_array($status, ['paid', 'void'], true))
                    <td class="num" style="vertical-align: middle;"><span class="stamp {{ $status }}">{{ strtoupper($statusLabels[$status]) }}</span></td>
                @endif
            </tr>
        </table>
    </div>
</body>
</html>
