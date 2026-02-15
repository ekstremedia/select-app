<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
        <meta name="robots" content="noindex, nofollow">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <meta name="reverb-key" content="{{ config('reverb.apps.apps.0.key', '') }}">
        <meta name="reverb-port" content="{{ config('reverb.apps.apps.0.options.port', config('broadcasting.connections.reverb.port', 8080)) }}">

        @php
            $meta = $page['props']['meta'] ?? [];
            $title = $meta['title'] ?? 'Select';
            $description = $meta['description'] ?? 'Det klassiske akronym-spillet fra #select på EFnet';
            $url = $meta['url'] ?? url()->current();
            $image = $meta['image'] ?? url('/og-image.png');
        @endphp

        <title>{{ $title }}</title>
        <meta name="description" content="{{ $description }}">

        <!-- Open Graph -->
        <meta property="og:type" content="website">
        <meta property="og:site_name" content="Select">
        <meta property="og:title" content="{{ $title }}">
        <meta property="og:description" content="{{ $description }}">
        <meta property="og:url" content="{{ $url }}">
        <meta property="og:image" content="{{ $image }}">
        <meta property="og:image:width" content="1200">
        <meta property="og:image:height" content="630">
        <meta property="og:locale" content="nb_NO">

        <!-- Twitter Card -->
        <meta name="twitter:card" content="summary_large_image">
        <meta name="twitter:title" content="{{ $title }}">
        <meta name="twitter:description" content="{{ $description }}">
        <meta name="twitter:image" content="{{ $image }}">

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=source-sans-3:400,500,600,700" rel="stylesheet" />

        @inertiaHead
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="antialiased">
        @inertia
    </body>
</html>
