<!DOCTYPE html>
<html lang="en-GB">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#F7F6F4">
    <title>Fynla</title>
    <style>
        :root {
            --eggshell: #F7F6F4;
            --horizon-500: #1F2A44;
            --horizon-300: #4F5B75;
            --raspberry-500: #C84268;
            --raspberry-600: #A93257;
            --savannah-200: #E8E3D8;
        }
        * { box-sizing: border-box; }
        html, body {
            margin: 0;
            padding: 0;
            height: 100%;
            background: var(--eggshell);
            color: var(--horizon-500);
            font-family: 'Segoe UI', 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            -webkit-font-smoothing: antialiased;
        }
        .m-land {
            min-height: 100%;
            display: flex;
            flex-direction: column;
            padding: 32px 24px env(safe-area-inset-bottom) 24px;
            padding-top: max(48px, env(safe-area-inset-top));
        }
        .m-land__brand {
            font-weight: 900;
            font-size: 22px;
            letter-spacing: -0.01em;
        }
        .m-land__hero {
            flex: 1;
            display: flex;
            flex-direction: column;
            justify-content: center;
            gap: 16px;
            padding: 48px 0;
        }
        .m-land__title {
            font-size: 36px;
            line-height: 1.1;
            font-weight: 900;
            letter-spacing: -0.02em;
            margin: 0;
        }
        .m-land__lede {
            font-size: 16px;
            line-height: 1.5;
            color: var(--horizon-300);
            margin: 0;
            max-width: 32ch;
        }
        .m-land__cta {
            display: block;
            background: var(--raspberry-500);
            color: #ffffff;
            text-decoration: none;
            text-align: center;
            padding: 16px 24px;
            border-radius: 12px;
            font-weight: 700;
            font-size: 16px;
            transition: background-color 120ms ease;
        }
        .m-land__cta:active { background: var(--raspberry-600); }
        .m-land__signin {
            display: block;
            text-align: center;
            margin-top: 16px;
            color: var(--horizon-500);
            text-decoration: none;
            font-size: 15px;
            font-weight: 700;
        }
        .m-land__signin u { text-underline-offset: 3px; }
        .m-land__placeholder {
            margin-top: 24px;
            padding: 10px 12px;
            background: var(--savannah-200);
            border-radius: 8px;
            font-size: 12px;
            color: var(--horizon-300);
            text-align: center;
        }
    </style>
</head>
<body>
    <main class="m-land">
        <div class="m-land__brand">Fynla</div>

        <section class="m-land__hero">
            <h1 class="m-land__title">Your financial life, in one place.</h1>
            <p class="m-land__lede">Track protection, savings, investments, retirement and your estate &mdash; with Fyn at your side.</p>
        </section>

        <a class="m-land__cta" href="{{ url('/m/app') }}">Get started</a>
        <a class="m-land__signin" href="{{ url('/m/app/login') }}"><u>I already have an account</u></a>

        <p class="m-land__placeholder">Landing placeholder &mdash; designer-replaceable HTML.</p>
    </main>
</body>
</html>
