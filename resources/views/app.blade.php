<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ $seo['title'] }}</title>
        <meta name="description" content="{{ $seo['description'] }}">
        <meta name="robots" content="{{ $seo['noindex'] ? 'noindex, follow' : 'index, follow' }}">
        <link rel="canonical" href="{{ $seo['canonical'] }}">
        <meta name="theme-color" content="#0B0808">

        <meta property="og:type" content="website">
        <meta property="og:site_name" content="ZAIRON Systems">
        <meta property="og:title" content="{{ $seo['title'] }}">
        <meta property="og:description" content="{{ $seo['description'] }}">
        <meta property="og:url" content="{{ $seo['canonical'] }}">
        <meta name="twitter:card" content="{{ $seo['image'] ? 'summary_large_image' : 'summary' }}">
        <meta name="twitter:title" content="{{ $seo['title'] }}">
        <meta name="twitter:description" content="{{ $seo['description'] }}">
        @if ($seo['image'])
            <meta property="og:image" content="{{ $seo['image'] }}">
            <meta name="twitter:image" content="{{ $seo['image'] }}">
        @endif

        <link rel="icon" href="/favicon.svg" type="image/svg+xml">

        @foreach ($seo['schema'] as $schema)
            <script type="application/ld+json" nonce="{{ Vite::cspNonce() }}">{!! json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_THROW_ON_ERROR) !!}</script>
        @endforeach

        @viteReactRefresh
        @vite(['resources/css/app.css', 'resources/js/main.jsx'])
    </head>
    <body>
        <div id="root">{!! $seo['body'] !!}</div>
        <noscript>
            <p class="noscript">
                ZAIRON Systems needs JavaScript to display this page. You can also reach us at
                <a href="mailto:{{ \App\Support\Content::get('site')['email'] }}">{{ \App\Support\Content::get('site')['email'] }}</a>.
            </p>
        </noscript>
    </body>
</html>
