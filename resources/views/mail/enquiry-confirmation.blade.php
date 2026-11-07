{{-- To the visitor. Deliberately repeats none of their message, so the form can't be used to relay spam. --}}
<x-mail-layout title="We received your message" preheader="A senior engineer will reply within one business day.">
    <h1 style="margin:0 0 16px;font-size:22px;">Thanks, {{ \Illuminate\Support\Str::limit($enquiry->name, 60) }}. We've got it.</h1>
    <p style="margin:0 0 14px;">Your message reached the ZAIRON Systems team. A senior engineer is reviewing it and will reply within one business day with questions or a time for a free discovery call.</p>
    <p style="margin:0 0 6px;font-weight:bold;">What happens next</p>
    <ol style="margin:0 0 18px;padding-left:20px;">
        <li style="margin-bottom:6px;">We reply within a day.</li>
        <li style="margin-bottom:6px;">A free 30-minute discovery call.</li>
        <li>A clear proposal with a fixed, itemised quote.</li>
    </ol>
    <p style="margin:0;">Need to add something? Just reply to this email.</p>
    <p style="margin:20px 0 0;">— The ZAIRON Systems team</p>
</x-mail-layout>
