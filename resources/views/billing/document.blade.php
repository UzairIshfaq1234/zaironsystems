@php
    $label = $isInvoice ? 'Invoice' : 'Quote';
    $status = $doc->displayStatus();
    $statusLabels = ['sent' => $isInvoice ? 'Due' : 'Awaiting response', 'partial' => 'Partly paid', 'paid' => 'Paid', 'overdue' => 'Overdue', 'void' => 'Void', 'accepted' => 'Accepted', 'declined' => 'Declined', 'expired' => 'Expired', 'invoiced' => 'Accepted'];
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ $label }} {{ $doc->number }} · {{ $company['company_name'] }}</title>
    <link rel="icon" href="/favicon.svg" type="image/svg+xml">
    @vite(['resources/css/document.css'])
</head>
<body>
    <div class="toolbar">
        <span>{{ $label }} {{ $doc->number }}</span>
        <span class="toolbar-actions">
            <button type="button" id="print">Print</button>
            <a class="download" href="{{ route('billing.pdf', [$doc::KIND, $doc->public_token]) }}" download>
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 4v11M7 10l5 5 5-5M5 20h14"/></svg>Download PDF
            </a>
        </span>
    </div>

    @if (! $isInvoice && in_array($doc->status, ['accepted', 'invoiced', 'declined'], true))
        <p class="banner {{ $doc->status === 'declined' ? 'muted' : 'ok' }}">
            {{ $doc->status === 'declined' ? 'You declined this quote. Reply to our email if you would like changes.' : 'Thank you! This quote has been accepted. We will be in touch with next steps.' }}
        </p>
    @endif

    <main class="sheet">
        @if (in_array($status, ['paid', 'void'], true))
            <span class="stamp {{ $status }}">{{ $statusLabels[$status] }}</span>
        @endif

        <header class="head">
            <div class="brand">
                <svg viewBox="0 0 100 100" aria-hidden="true"><polygon points="12,12 88,12 88,30 71.2,44 44.5,44 62,30 12,30"/><polygon points="29.5,56 56.8,56 40,70 88,70 88,88 12,88 12,70"/><circle cx="50.5" cy="50" r="3.8"/></svg>
                <div>
                    <strong>{{ $company['company_name'] }}</strong>
                    @if ($company['company_address'])<span class="pre">{{ $company['company_address'] }}</span>@endif
                    <span>{{ $company['company_email'] }}@if ($company['company_phone']) · {{ $company['company_phone'] }}@endif</span>
                    @if ($company['tax_number'])<span>Tax no. {{ $company['tax_number'] }}</span>@endif
                </div>
            </div>
            <div class="title">
                <h1>{{ $label }}</h1>
                <p class="number">{{ $doc->number }}</p>
                <span class="status s-{{ $status }}">{{ $statusLabels[$status] ?? ucfirst($status) }}</span>
            </div>
        </header>

        <section class="meta">
            <div>
                <h2>{{ $isInvoice ? 'Bill to' : 'Prepared for' }}</h2>
                <strong>{{ $doc->client->company ?: $doc->client->name }}</strong>
                @if ($doc->client->company)<span>{{ $doc->client->name }}</span>@endif
                <span>{{ $doc->client->email }}</span>
                @if ($doc->client->address)<span class="pre">{{ $doc->client->address }}</span>@endif
                @if ($doc->client->tax_number)<span>Tax no. {{ $doc->client->tax_number }}</span>@endif
            </div>
            <dl>
                <dt>Issue date</dt><dd>{{ $doc->issue_date->toFormattedDateString() }}</dd>
                @if ($isInvoice)
                    <dt>Due date</dt><dd>{{ $doc->due_date->toFormattedDateString() }}</dd>
                @elseif ($doc->valid_until)
                    <dt>Valid until</dt><dd>{{ $doc->valid_until->toFormattedDateString() }}</dd>
                @endif
                <dt>Currency</dt><dd>{{ $doc->currency }}</dd>
            </dl>
        </section>

        <table class="items">
            <thead>
                <tr><th>Description</th><th class="num">Qty</th><th class="num">Unit price</th><th class="num">Amount</th></tr>
            </thead>
            <tbody>
                @foreach ($doc->items as $item)
                    <tr>
                        <td class="pre">{{ $item->description }}</td>
                        <td class="num">{{ rtrim(rtrim(number_format((float) $item->quantity, 2), '0'), '.') }}</td>
                        <td class="num">{{ $doc->money($item->unit_cents) }}</td>
                        <td class="num">{{ $doc->money($item->amount_cents) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <div class="totals">
            <dl>
                <dt>Subtotal</dt><dd>{{ $doc->money($doc->subtotal_cents) }}</dd>
                @if ($doc->discount_cents > 0)<dt>Discount</dt><dd>−{{ $doc->money($doc->discount_cents) }}</dd>@endif
                @if ((float) $doc->tax_rate > 0)<dt>Tax ({{ rtrim(rtrim(number_format((float) $doc->tax_rate, 2), '0'), '.') }}%)</dt><dd>{{ $doc->money($doc->tax_cents) }}</dd>@endif
                <dt class="grand">Total</dt><dd class="grand">{{ $doc->money($doc->total_cents) }}</dd>
                @if ($isInvoice && $doc->paid_cents > 0)
                    <dt>Paid</dt><dd>−{{ $doc->money($doc->paid_cents) }}</dd>
                    <dt class="grand">Balance due</dt><dd class="grand">{{ $doc->money($doc->balanceCents()) }}</dd>
                @endif
            </dl>
        </div>

        @if ($isInvoice && $company['payment_instructions'] && ! in_array($status, ['paid', 'void'], true))
            <section class="note"><h2>How to pay</h2><p class="pre">{{ $company['payment_instructions'] }}</p></section>
        @endif
        @if ($notes)
            <section class="note"><h2>Notes</h2><p class="pre">{{ $notes }}</p></section>
        @endif
        @if ($terms)
            <section class="note"><h2>Terms &amp; conditions</h2><p class="pre">{{ $terms }}</p></section>
        @endif

        <footer class="foot">Thank you for your business · {{ $company['company_name'] }} · {{ $company['company_email'] }}</footer>
    </main>

    @if (! $isInvoice && $doc->isOpen())
        <div class="respond">
            <p>Happy with this quote?</p>
            <form method="post" action="{{ route('billing.respond', $doc->public_token) }}">
                @csrf
                <button type="submit" name="answer" value="accepted" class="accept">Accept quote</button>
                <button type="submit" name="answer" value="declined" class="decline">Decline</button>
            </form>
        </div>
    @endif

    <script nonce="{{ Vite::cspNonce() }}">document.getElementById('print').addEventListener('click', function () { window.print(); });</script>
</body>
</html>
