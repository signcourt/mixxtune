<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        @php($__brand = app(\App\Services\V2\SystemSettingService::class)->branding())
        {{-- W227: somebody in a distributor's tree gets that distributor's name and logo on the browser tab --}}
        <?php
            $__brand = app(\App\Services\V2\DistributorService::class)->brand($__brand, auth()->user());
            $__w227 = !empty($__brand['distributor']);
            if ($__w227) {
                $__brand['favicon_url'] = !empty($__brand['logo_url']) ? $__brand['logo_url'] : 'data:,';
            }
        ?>
        @if($__w227)
        <meta name="mt-brand" content="{{ $__brand['name'] }}">
        @endif
        {{-- W201: tab title + icon follow the brand, not the hard-coded app name --}}
        <title inertia>{{ $__brand['name'] ?? config('app.name', 'Laravel') }}</title>
        @if(!empty($__brand['favicon_url']))
        <link rel="icon" href="{{ $__brand['favicon_url'] }}">
        @endif

        <!-- Fonts: W235 - Figtree comes from our own /fonts folder (see resources/css/app.css) -->
        <link rel="preload" href="/fonts/figtree/figtree-latin-400-normal.woff2" as="font" type="font/woff2" crossorigin>
        <link rel="preload" href="/fonts/figtree/figtree-latin-600-normal.woff2" as="font" type="font/woff2" crossorigin>

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
