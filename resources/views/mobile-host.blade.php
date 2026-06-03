<!DOCTYPE html>
<html lang="en-GB">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#1F2A44">
    <title>Fynla</title>
    <style>
        html, body { margin: 0; padding: 0; height: 100%; background: #1F2A44; }
        /* iOS Safari: 100% of a fixed wrapper is unreliable with the collapsing
           address bar; pin to the small/dynamic viewport so the iframe always
           fills the screen instead of computing to 0 height ("fails to load"). */
        .m-frame-wrap { position: fixed; inset: 0; height: 100vh; height: 100svh; }
        iframe { border: 0; width: 100%; height: 100%; min-height: 100svh; display: block; background: #F7F6F4; }
    </style>
</head>
<body>
    {{-- The mobile view hosts the real, responsive public funnel (homepage →
         /savetax → /savetax/plan → register), then the isolated mobile app at
         /m/app once authenticated. Pages load inside this same-origin iframe;
         RedirectPhoneToMobile skips the phone→/m redirect for Sec-Fetch-Dest
         iframe loads so the funnel runs in-frame without looping. --}}
    <div class="m-frame-wrap">
        <iframe src="{{ url('/') }}" title="Fynla" allow="clipboard-read; clipboard-write"></iframe>
    </div>
</body>
</html>
