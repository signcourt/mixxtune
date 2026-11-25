<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        @php($__brand = app(\App\Services\V2\SystemSettingService::class)->branding())
        {{-- W201: tab title + icon follow the brand, not the hard-coded app name --}}
        <title inertia>{{ $__brand['name'] ?? config('app.name', 'Laravel') }}</title>
        @if(!empty($__brand['favicon_url']))
        <link rel="icon" href="{{ $__brand['favicon_url'] }}">
        @endif

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        <!-- W90: night mode is set before the page paints (no white flash) -->
        <script>try{if(localStorage.getItem('mt-theme')==='night'){document.documentElement.classList.add('mt-night')}}catch(e){}</script>

        <!-- Scripts -->
        {{-- W201: only the routes this visitor may use (see App\Support\ZiggyExposure) --}}
        {!! \App\Support\ZiggyExposure::html(request()) !!}
        @viteReactRefresh
        @vite(['resources/js/app.jsx', "resources/js/Pages/{$page['component']}.jsx"])
        @inertiaHead
    </head>
    <body class="font-sans antialiased">
        @inertia
    </body>
</html>
