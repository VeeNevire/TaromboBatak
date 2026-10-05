<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" @class(['dark' => ($appearance ?? 'system') == 'dark'])>
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        @php($preview = app(\App\Services\SharePreviewService::class)->forPage($page))
        <meta name="description" content="{{ $preview['description'] }}">
        <meta property="og:site_name" content="Tarombo Batak">
        <meta property="og:locale" content="id_ID">
        <meta property="og:type" content="article">
        <meta property="og:title" content="{{ $preview['title'] }}">
        <meta property="og:description" content="{{ $preview['description'] }}">
        <meta property="og:image" content="{{ $preview['image'] }}">
        <meta property="og:image:alt" content="{{ $preview['title'] }}">
        <meta property="og:url" content="{{ $preview['url'] }}">
        <meta name="twitter:card" content="summary_large_image">
        <meta name="twitter:title" content="{{ $preview['title'] }}">
        <meta name="twitter:description" content="{{ $preview['description'] }}">
        <meta name="twitter:image" content="{{ $preview['image'] }}">

        {{-- Inline script to detect system dark mode preference and apply it immediately --}}
        <script>
            (function() {
                const appearance = '{{ $appearance ?? "system" }}';

                if (appearance === 'system') {
                    const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;

                    if (prefersDark) {
                        document.documentElement.classList.add('dark');
                    }
                }
            })();
        </script>

        {{-- Inline style to set the HTML background color based on our theme in app.css --}}
        <style>
            html {
                background-color: oklch(1 0 0);
            }

            html.dark {
                background-color: oklch(0.145 0 0);
            }
        </style>

        <link rel="icon" href="/Brand.png" type="image/png">
        <link rel="icon" href="/favicon.ico" sizes="any">
        <link rel="icon" href="/favicon.svg" type="image/svg+xml">
        <link rel="apple-touch-icon" href="/apple-touch-icon.png">

        @fonts

        @if (filled(config('services.google.analytics_measurement_id')))
            <script async src="https://www.googletagmanager.com/gtag/js?id={{ config('services.google.analytics_measurement_id') }}"></script>
            <script>
                window.dataLayer = window.dataLayer || [];
                window.gtag = function () { dataLayer.push(arguments); };
                window.gtag('js', new Date());
                window.gtag('config', '{{ config('services.google.analytics_measurement_id') }}', {
                    send_page_view: false,
                });
            </script>
        @endif

        @viteReactRefresh
        @vite(['resources/css/app.css', 'resources/js/app.tsx', "resources/js/pages/{$page['component']}.tsx"])
        <x-inertia::head>
            <title>{{ config('app.name', 'Laravel') }}</title>
        </x-inertia::head>
    </head>
    <body class="font-sans antialiased">
        <x-inertia::app />
    </body>
</html>
