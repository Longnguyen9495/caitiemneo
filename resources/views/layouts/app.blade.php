<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Cái Tiệm Neo') }}</title>
        <link rel="icon" type="image/png" href="{{ asset('images/logo-neo.png') }}">
        <link rel="apple-touch-icon" href="{{ asset('images/logo-neo.png') }}">
        <meta property="og:title" content="{{ config('app.name', 'Cái Tiệm Neo') }}">
        <meta property="og:image" content="{{ asset('images/logo-neo.png') }}">
        <meta property="og:type" content="website">

        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Be+Vietnam+Pro:wght@400;500;600;700&family=Playfair+Display:ital,wght@0,400..600;1,400..600&display=swap" rel="stylesheet">
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body>
        <div class="account-shell">
            @include('layouts.navigation')

            @isset($header)
                <header class="account-page-header">
                    <div class="account-container">
                        {{ $header }}
                    </div>
                </header>
            @endisset

            <main>
                {{ $slot }}
            </main>
        </div>
    </body>
</html>
