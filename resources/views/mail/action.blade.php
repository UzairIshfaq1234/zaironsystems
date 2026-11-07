{{-- Generic "one button" email: invitations, password resets. --}}
<x-mail-layout :title="$heading" :preheader="$lines[0] ?? null">
    <h1 style="margin:0 0 16px;font-size:22px;color:#1d1515;">{{ $heading }}</h1>
    @foreach ($lines as $line)
        <p style="margin:0 0 14px;">{{ $line }}</p>
    @endforeach
    <table role="presentation" cellpadding="0" cellspacing="0" style="margin:24px 0;">
        <tr>
            <td style="border-radius:999px;background:#800000;">
                <a href="{{ $url }}" style="display:inline-block;padding:14px 28px;font-weight:bold;color:#ffffff;text-decoration:none;border-radius:999px;">{{ $button }}</a>
            </td>
        </tr>
    </table>
    @foreach ($outro ?? [] as $line)
        <p style="margin:0 0 10px;font-size:13px;color:#6b5c5c;">{{ $line }}</p>
    @endforeach
    <p style="margin:20px 0 0;font-size:12px;color:#8f8282;word-break:break-all;">If the button doesn't work, copy this link into your browser:<br>{{ $url }}</p>
</x-mail-layout>
