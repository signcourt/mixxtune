<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }}@if($artist !== '') - {{ $artist }}@endif</title>
    <meta name="description" content="{{ $description }}">
    <link rel="canonical" href="{{ $url }}">
    <link rel="icon" href="data:,">
    <meta property="og:type" content="music.album">
    <meta property="og:site_name" content="{{ $brand }}">
    <meta property="og:title" content="{{ $shareText }}">
    <meta property="og:description" content="{{ $description }}">
    <meta property="og:url" content="{{ $url }}">
    @if($poster)
    <meta property="og:image" content="{{ $poster }}">
    <meta property="og:image:type" content="image/jpeg">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:image" content="{{ $poster }}">
    @else
    <meta name="twitter:card" content="summary">
    @endif
    <meta name="twitter:title" content="{{ $shareText }}">
    <meta name="twitter:description" content="{{ $description }}">
    <link rel="stylesheet" href="/share/share.css?v=1">
</head>
<body>
    <main class="card">
        @if($poster)
        <img class="poster" src="{{ $poster }}" alt="{{ $title }}" width="600" height="600">
        @endif
        <p class="tag">Out now</p>
        <h1>{{ $title }}</h1>
        @if($artist !== '')<p class="artist">{{ $artist }}</p>@endif

        @if(count($links))
        <p class="listen">Listen on</p>
        <div class="stores">
            @foreach($links as $link)
            <a class="store store-{{ $link['key'] }}" href="{{ $link['url'] }}" target="_blank" rel="noopener nofollow">{{ $link['label'] }}</a>
            @endforeach
        </div>
        @else
        <p class="soon">Available on all major music stores.</p>
        @endif

        <div class="share">
            <a class="sh" href="https://wa.me/?text={{ rawurlencode($shareText.' '.$url) }}" target="_blank" rel="noopener nofollow">Share on WhatsApp</a>
            <a class="sh" href="https://www.facebook.com/sharer/sharer.php?u={{ rawurlencode($url) }}" target="_blank" rel="noopener nofollow">Share on Facebook</a>
        </div>
    </main>
    <footer>Distributed by {{ $brand }}</footer>
</body>
</html>
