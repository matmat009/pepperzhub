@php
    $component = $page['component'] ?? '';
    $forceLightTheme = str_starts_with($component, 'storefront/') || str_starts_with($component, 'auth/');
    $themeScope = $forceLightTheme ? 'forced-light' : 'admin';
@endphp

<!DOCTYPE html>
<html
    lang="{{ str_replace('_', '-', app()->getLocale()) }}"
    data-theme-scope="{{ $themeScope }}"
    @class(['dark' => ! $forceLightTheme && ($appearance ?? 'system') === 'dark'])
    @style(['color-scheme: light' => $forceLightTheme])
>
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        {{-- Resolve the first paint before CSS or Vue can expose the saved admin theme. --}}
        <script>
            (function() {
                const root = document.documentElement;
                const forceLight = @json($forceLightTheme);
                const appearance = @json($appearance ?? 'system');

                if (forceLight) {
                    root.classList.remove('dark');
                    root.style.colorScheme = 'light';

                    return;
                }

                const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
                const useDark = appearance === 'dark' || (appearance === 'system' && prefersDark);

                root.classList.toggle('dark', useDark);
                root.style.colorScheme = useDark ? 'dark' : 'light';
            })();
        </script>

        {{-- Inline style to set the HTML background color based on our theme in app.css --}}
        <style>
            html {
                background-color: oklch(1 0 0);
                color-scheme: light;
            }

            html.dark {
                background-color: oklch(0.145 0 0);
                color-scheme: dark;
            }
        </style>

        <link rel="icon" href="/favicon.ico" sizes="any">
        <link rel="icon" href="/favicon.svg" type="image/svg+xml">
        <link rel="apple-touch-icon" href="/apple-touch-icon.png">

        @fonts

        @vite(['resources/css/app.css', 'resources/js/app.ts', "resources/js/pages/{$page['component']}.vue"])
        <x-inertia::head>
            <title>{{ config('app.name', 'Laravel') }}</title>
        </x-inertia::head>
    </head>
    <body class="font-sans antialiased">
        <x-inertia::app />
    </body>
</html>
