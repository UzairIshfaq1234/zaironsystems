{{-- To the ZAIRON Systems team. Visitor input is escaped and never parsed as Markdown, so it can't inject links. --}}
<x-mail-layout :title="'New project enquiry #'.$enquiry->id" :preheader="$enquiry->name.' from '.$enquiry->company">
    <h1 style="margin:0 0 6px;font-size:22px;">New project enquiry #{{ $enquiry->id }}</h1>
    <p style="margin:0 0 20px;color:#6b5c5c;">Received {{ $enquiry->created_at->toDayDateTimeString() }}</p>

    <table role="presentation" width="100%" cellpadding="8" cellspacing="0" style="font-size:14px;border-collapse:collapse;">
        @foreach ([
            'Name' => $enquiry->name,
            'Email' => $enquiry->email,
            'Company' => $enquiry->company,
            'Phone' => $enquiry->phone,
            'Service page' => $enquiry->serviceTitle(),
            'Plan' => $enquiry->planTitle(),
            'Needs' => implode(', ', $enquiry->needsLabels()),
            'Budget' => $enquiry->budget,
            'Attachment' => $enquiry->brief_name,
        ] as $label => $value)
            <tr style="border-bottom:1px solid #f0e8e8;">
                <td style="width:130px;color:#6b5c5c;vertical-align:top;">{{ $label }}</td>
                <td style="vertical-align:top;">{{ $value ?: '—' }}</td>
            </tr>
        @endforeach
    </table>

    <p style="margin:24px 0 8px;font-weight:bold;">Project details</p>
    <div style="font-size:14px;line-height:1.6;white-space:pre-wrap;background:#faf7f7;border-radius:10px;padding:14px;">{{ $enquiry->details }}</div>

    <table role="presentation" cellpadding="0" cellspacing="0" style="margin:24px 0 8px;">
        <tr>
            <td style="border-radius:999px;background:#800000;">
                <a href="{{ \App\Support\Seo::url('/admin/enquiries/'.$enquiry->id) }}" style="display:inline-block;padding:12px 24px;font-weight:bold;color:#ffffff;text-decoration:none;border-radius:999px;">Open in dashboard</a>
            </td>
        </tr>
    </table>
    <p style="margin:12px 0 0;font-size:12px;color:#8f8282;">Reply to this email to answer the client directly. Attachments stay on the server and can be downloaded from the dashboard.</p>
</x-mail-layout>
