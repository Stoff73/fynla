<!DOCTYPE html>
<html lang="en-GB">
<head>
    <!-- Google Analytics loaded dynamically via cookieConsent.js (requires user consent) -->

    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>Fyn, your financial companion | Fynla is your complete personal finance platform for planning, savings and investments</title>
    <meta name="description" content="Fynla helps you plan savings, investments, retirement, and estate with confidence. One platform for your complete financial picture, powered by our proprietary Fynla Brain.">

    <!-- Canonical & Hreflang -->
    <link rel="canonical" href="https://fynla.org{{ request()->getPathInfo() }}">
    <link rel="alternate" hreflang="en-GB" href="https://fynla.org{{ request()->getPathInfo() }}">
    <link rel="alternate" hreflang="x-default" href="https://fynla.org{{ request()->getPathInfo() }}">

    <!-- Open Graph -->
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="Fynla">
    <meta property="og:title" content="Fynla — UK Financial Planning Made Simple">
    <meta property="og:description" content="Plan savings, investments, retirement, and estate with confidence. One platform for your complete financial picture.">
    <meta property="og:url" content="https://fynla.org{{ request()->getPathInfo() }}">
    <meta property="og:image" content="https://fynla.org/images/logos/LogoHiResFynlaDark.png">
    <meta property="og:locale" content="en_GB">

    <!-- Twitter Card -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="Fynla — UK Financial Planning Made Simple">
    <meta name="twitter:description" content="Plan savings, investments, retirement, and estate with confidence. One platform for your complete financial picture.">
    <meta name="twitter:image" content="https://fynla.org/images/logos/LogoHiResFynlaDark.png">

    <!-- Favicon -->
    <link rel="icon" type="image/png" href="/images/logos/favicon.png">
    <link rel="icon" type="image/x-icon" href="/images/logos/favicon.ico">

    <!-- Structured Data -->
    <script type="application/ld+json">
    {
        "@context": "https://schema.org",
        "@type": "Organization",
        "name": "Fynla",
        "url": "https://fynla.org",
        "logo": "https://fynla.org/images/logos/LogoHiResFynlaDark.png",
        "description": "UK financial planning platform helping individuals and families plan savings, investments, retirement, and estate with confidence."
    }
    </script>
    <script type="application/ld+json">
    {
        "@context": "https://schema.org",
        "@type": "WebApplication",
        "name": "Fynla",
        "url": "https://fynla.org",
        "applicationCategory": "FinanceApplication",
        "operatingSystem": "Web",
        "description": "One platform for your complete financial picture — protection, savings, investment, retirement, and estate planning.",
        "offers": {
            "@type": "Offer",
            "price": "0",
            "priceCurrency": "GBP"
        }
    }
    </script>

    <!-- Vite CSS -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <!-- Plausible Analytics (privacy-first, no cookies, GDPR-compliant) -->
    @if(config('analytics.enabled') && config('analytics.plausible_domain'))
        <script defer data-domain="{{ config('analytics.plausible_domain') }}" src="https://plausible.io/js/script.js"></script>
    @endif

</head>
<body class="antialiased" style="background-color: #F7F6F4;">
    <div id="app"></div>
</body>
</html>
