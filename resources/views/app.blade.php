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

    <!-- Favicon — use asset() so the path respects APP_URL (works for both
         root deployments like fynla.org and subdirectory ones like
         csjones.co/fynla) -->
    <link rel="icon" type="image/png" href="{{ asset('images/logos/favicon.png') }}">
    <link rel="icon" type="image/x-icon" href="{{ asset('images/logos/favicon.ico') }}">

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

    <!-- Meta Pixel Code -->
    <script>
    !function(f,b,e,v,n,t,s)
    {if(f.fbq)return;n=f.fbq=function(){n.callMethod?
    n.callMethod.apply(n,arguments):n.queue.push(arguments)};
    if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';
    n.queue=[];t=b.createElement(e);t.async=!0;
    t.src=v;s=b.getElementsByTagName(e)[0];
    s.parentNode.insertBefore(t,s)}(window, document,'script',
    'https://connect.facebook.net/en_US/fbevents.js');
    fbq('init', '1878962689749080');
    fbq('track', 'PageView');
    </script>
    <noscript><img height="1" width="1" style="display:none"
    src="https://www.facebook.com/tr?id=1878962689749080&ev=PageView&noscript=1"
    /></noscript>
    <!-- End Meta Pixel Code -->

</head>
<body class="antialiased" style="background-color: #F7F6F4;">
    <div id="app"></div>
</body>
</html>
