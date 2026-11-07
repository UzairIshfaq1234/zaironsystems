{{-- One-page salary slip rendered by App\Support\SalarySlipPdf (dompdf: CSS 2.1 + tables). --}}
@php
    $e = $slip->employee;
    $money = fn (int $cents) => \App\Support\Billing::format($cents, 'PKR');
    $monthLabel = \Carbon\CarbonImmutable::createFromFormat('Y-m', $slip->period)->format('F Y');
    $logo = 'data:image/svg+xml;base64,'.base64_encode('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><g fill="#ffffff"><polygon points="12,12 88,12 88,30 71.2,44 44.5,44 62,30 12,30"/><polygon points="29.5,56 56.8,56 40,70 88,70 88,88 12,88 12,70"/><circle cx="50.5" cy="50" r="3.8"/></g></svg>');
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Salary slip {{ $e->code }} {{ $slip->period }}</title>
<style>
    @font-face { font-family: 'Manrope'; font-weight: 400; src: url('file://{{ $fontDir }}/Manrope-Regular.ttf') format('truetype'); }
    @font-face { font-family: 'Manrope'; font-weight: 700; src: url('file://{{ $fontDir }}/Manrope-Bold.ttf') format('truetype'); }
    @font-face { font-family: 'Sora'; font-weight: 700; src: url('file://{{ $fontDir }}/Sora-Bold.ttf') format('truetype'); }

    @page { margin: 0; }
    body { margin: 0; font-family: 'Manrope', 'DejaVu Sans', sans-serif; font-size: 9.5pt; line-height: 1.45; color: #2b2124; }
    table { border-collapse: collapse; width: 100%; }
    td, th { vertical-align: top; padding: 0; }
    .head { font-family: 'Sora', 'DejaVu Sans', sans-serif; font-weight: 700; }
    .muted { color: #86777a; }
    .num { text-align: right; white-space: nowrap; }
    .caps { font-size: 7pt; font-weight: 700; letter-spacing: 1.3px; text-transform: uppercase; color: #9a8a8d; }

    .band { background: #1a090e; color: #fff; padding: 30px 46px 24px; border-bottom: 5px solid #b3142c; }
    .logo { width: 36px; height: 36px; padding: 8px; background: #b3142c; border-radius: 10px; }
    .title { font-size: 20pt; letter-spacing: 3px; text-align: right; }
    .sub { font-size: 8pt; color: #f1c4cb; text-align: right; padding-top: 4px; }
    .content { padding: 24px 46px 0; }

    .facts td { padding: 3px 0; font-size: 9pt; }
    .facts .k { color: #86777a; width: 34%; }
    .facts .v { font-weight: 700; }
    .net { background: #fbeff1; border: 1px solid #f3d5da; border-radius: 10px; padding: 14px 16px; }
    .net-amount { font-size: 18pt; color: #9b0f24; padding-top: 4px; }

    .pay { margin-top: 22px; }
    .pay th { background: #1a090e; color: #fff; font-size: 7pt; font-weight: 700; letter-spacing: 1.2px; text-transform: uppercase; padding: 8px 12px; text-align: left; }
    .pay td { padding: 9px 12px; border-bottom: 1px solid #efe6e8; font-size: 9.5pt; }
    .pay tr.total td { font-family: 'Sora', 'DejaVu Sans', sans-serif; font-weight: 700; font-size: 10.5pt; background: #faf6f7; border-bottom: 0; }
    .pay tr.net td { background: #1a090e; color: #fff; font-family: 'Sora', 'DejaVu Sans', sans-serif; font-weight: 700; font-size: 11.5pt; border-bottom: 0; }
    .note { margin-top: 24px; background: #faf6f7; border-left: 3px solid #b3142c; padding: 12px 14px; font-size: 8.5pt; color: #4a3d40; }
    .foot { margin-top: 36px; font-size: 7.8pt; color: #8a7a7d; border-top: 1px solid #ece3e5; padding-top: 10px; }
</style>
</head>
<body>
    <div class="band">
        <table>
            <tr>
                <td style="width: 55%;">
                    <table style="width: auto;">
                        <tr>
                            <td style="width: 64px; padding-right: 12px;"><img class="logo" src="{{ $logo }}" alt=""></td>
                            <td>
                                <div class="head" style="font-size: 14pt; letter-spacing: 3px;">{{ strtoupper($company['company_name']) }}</div>
                                <div style="font-size: 7.5pt; color: #e8a3ad; letter-spacing: 1.5px;">SALARY SLIP</div>
                            </td>
                        </tr>
                    </table>
                </td>
                <td style="width: 45%;">
                    <div class="head title">{{ $monthLabel }}</div>
                    <div class="sub">Slip for {{ $e->code }} · {{ $slip->period }}</div>
                </td>
            </tr>
        </table>
    </div>

    <div class="content">
        <table>
            <tr>
                <td style="width: 50%; padding-right: 20px;">
                    <div class="caps">Employee</div>
                    <div class="head" style="font-size: 13pt; padding-top: 4px;">{{ $e->name }}</div>
                    <table class="facts" style="margin-top: 6px;">
                        <tr><td class="k">Employee code</td><td class="v">{{ $e->code }}</td></tr>
                        <tr><td class="k">Job title</td><td class="v">{{ $e->jobTitle?->name ?: '—' }}</td></tr>
                        <tr><td class="k">Department</td><td class="v">{{ $e->department?->name ?: '—' }}</td></tr>
                        <tr><td class="k">Joined</td><td class="v">{{ $e->joined_on->format('j M Y') }}</td></tr>
                    </table>
                </td>
                <td style="width: 50%;">
                    <div class="net">
                        <div class="caps" style="color: #b3142c;">Net pay</div>
                        <div class="head net-amount">{{ $money($slip->net_cents) }}</div>
                        <div style="font-size: 8pt; color: #86777a; padding-top: 3px;">Paid to {{ $e->bank_name ?: 'your bank account' }}@if ($e->bank_account) · {{ $e->bank_account }}@endif</div>
                    </div>
                </td>
            </tr>
        </table>

        <table class="pay">
            <thead>
                <tr><th>Earnings &amp; deductions</th><th class="num">Amount</th></tr>
            </thead>
            <tbody>
                @foreach ($slip->items as $item)
                    <tr><td>{{ $item->label }}</td><td class="num">{{ $item->kind === 'deduction' ? '−' : '' }}{{ $money($item->amount_cents) }}</td></tr>
                @endforeach
                <tr class="total"><td>Gross pay</td><td class="num">{{ $money($slip->gross_cents) }}</td></tr>
                <tr><td>Income tax withheld</td><td class="num">−{{ $money($slip->tax_cents) }}</td></tr>
                <tr class="net"><td>Net pay</td><td class="num">{{ $money($slip->net_cents) }}</td></tr>
            </tbody>
        </table>

        <div class="note">
            Gross pay is basic salary and allowances, less any unpaid leave for the month. Income tax is withheld from your
            salary by the company. Questions about this slip? Contact HR.
        </div>

        <div class="foot">
            {{ $company['company_name'] }} · {{ $company['company_email'] }}@if ($company['tax_number']) · NTN {{ $company['tax_number'] }}@endif
            · This is a computer-generated slip and needs no signature.
        </div>
    </div>
</body>
</html>
