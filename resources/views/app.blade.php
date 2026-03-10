<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'Fynla') }} - Financial Planning</title>

    <!-- Favicon -->
    <link rel="icon" type="image/png" href="/images/logos/favicon.png">
    <link rel="icon" type="image/x-icon" href="/images/logos/favicon.ico">

    <!-- Vite CSS -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <!-- Plausible Analytics (privacy-first, no cookies, GDPR-compliant) -->
    @auth
        @if(config('analytics.enabled') && config('analytics.plausible_domain'))
            <script defer data-domain="{{ config('analytics.plausible_domain') }}" src="https://plausible.io/js/script.js"></script>
        @endif
    @endauth

</head>
<body class="antialiased" style="background-color: #F7F6F4;">
    <div id="app"></div>
</body>
</html>
