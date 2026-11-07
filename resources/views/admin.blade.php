<!DOCTYPE html>
<html lang="en" data-theme="dark">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <meta name="robots" content="noindex, nofollow">
        <meta name="theme-color" content="#0d0a0b">
        <title>ZAIRON Systems Admin</title>
        <link rel="icon" href="/favicon.svg" type="image/svg+xml">
        {{-- Apply the saved theme before first paint (nonce-allowed by the CSP). --}}
        <script nonce="{{ Vite::cspNonce() }}">try{var t=localStorage.getItem('zairon-admin-theme');if(t==='light'||t==='dark')document.documentElement.dataset.theme=t;}catch(e){}</script>
        @viteReactRefresh
        @vite(['resources/css/admin.css', 'resources/js/admin/main.jsx'])
    </head>
    <body>
        <div id="admin-root"></div>
        <noscript><p class="a-noscript">The ZAIRON Systems admin portal needs JavaScript.</p></noscript>
    </body>
</html>
