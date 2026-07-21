<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <!-- Favicon -->
  <link rel="icon" type="image/png" href="/images/logos/favicon.png" />
  <link rel="icon" type="image/x-icon" href="/images/logos/favicon.ico" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Pension Check — See How Your Pension Compares — Fynla</title>
  <meta name="description" content="Answer 6 quick questions and see a projection of where your pension pot could be by retirement. Personalised pension insights from Fynla in under a minute." />
  <link rel="canonical" href="https://fynla.org/pensioncheck" />

  <!-- Open Graph -->
  <meta property="og:type" content="website" />
  <meta property="og:title" content="Pension Check — See How Your Pension Compares — Fynla" />
  <meta property="og:description" content="Answer 6 quick questions and see a projection of where your pension pot could be by retirement. Personalised pension insights from Fynla in under a minute." />
  <meta property="og:image" content="https://fynla.org/images/og/pensioncheck.jpg" />
  <meta property="og:url" content="https://fynla.org/pensioncheck" />

  <!-- Twitter Card -->
  <meta name="twitter:card" content="summary_large_image" />
  <meta name="twitter:title" content="Pension Check — See How Your Pension Compares — Fynla" />
  <meta name="twitter:description" content="Answer 6 quick questions and see a projection of where your pension pot could be by retirement. Personalised pension insights from Fynla in under a minute." />
  <meta name="twitter:image" content="https://fynla.org/images/og/pensioncheck.jpg" />

  <!-- hreflang -->
  <link rel="alternate" hreflang="en-GB" href="https://fynla.org/pensioncheck" />
  <link rel="alternate" hreflang="x-default" href="https://fynla.org/pensioncheck" />

  <!-- JSON-LD -->
  <script type="application/ld+json">
  {
    "@context": "https://schema.org",
    "@type": "WebPage",
    "name": "Pension Check — See How Your Pension Compares",
    "description": "Answer 6 quick questions and see a projection of where your pension pot could be by retirement.",
    "url": "https://fynla.org/pensioncheck",
    "publisher": {
      "@type": "Organization",
      "name": "Fynla",
      "url": "https://fynla.org"
    }
  }
  </script>

  <!-- Critical inline CSS: above-fold skeleton only (body bg, skip-nav, qr-header, qr-card shell).
       Rules that already exist verbatim in global.css (reset, [hidden], button) are NOT duplicated here. -->
  <style>
    html { height: 100%; }
    body {
      font-family: 'Segoe UI', 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
      background: #F7F6F4;
      color: #1F2A44;
      min-height: 100%;
      display: flex;
      flex-direction: column;
    }
    /* skip-nav already in global.css; not duplicated */
    /* qr-header skeleton prevents layout shift before savetax.css loads */
    .qr-header {
      position: sticky;
      top: 0;
      z-index: 50;
      background: #FFFFFF;
      border-bottom: 1px solid #EEEEEE;
    }
    .qr-header__row {
      display: flex;
      align-items: center;
      height: 3.25rem;
      padding: 0 1rem;
      gap: 0.75rem;
    }
    .qr-card { display: flex; flex-direction: column; flex: 1; }
    .qr-main { flex: 1; position: relative; overflow: hidden; min-height: 0; }
  </style>

  <!-- Blocking CSS — same-server, negligible render penalty, no FOUC -->
  <link rel="stylesheet" href="/pages/css/global.css?v=113" />
  <link rel="stylesheet" href="/pages/css/savetax.css?v=15" />
</head>
<body>
  <a href="#main-content" class="skip-nav">Skip to main content</a>

  <div class="qr-card">

    <!-- Minimal funnel header — NOT the site nav partial -->
    <header class="qr-header" role="banner">
      <div class="qr-header__row">
        <button type="button" class="qr-back invisible" id="qr-back-btn" aria-label="Go back">
          <svg aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24" width="20" height="20">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
          </svg>
        </button>
        <a href="/" class="qr-logo" aria-label="Fynla home">
          <img src="/images/logos/LogoHiResFynlaDark.png" alt="Fynla" width="93" height="42" />
        </a>
        <div class="qr-step" id="qr-step-label" aria-live="polite" aria-atomic="true">1 of 6</div>
      </div>
      <div class="qr-progress" role="progressbar" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100" aria-label="Question progress">
        <div class="qr-progress__fill" id="qr-progress-fill"></div>
      </div>
    </header>

    <main id="main-content" class="qr-main">

      <!-- Q1: Employment status -->
      <section class="qr-screen is-active" id="s-employment" aria-labelledby="q1-heading">
        <h2 class="qr-q" id="q1-heading" tabindex="-1">What is your employment status?</h2>
        <p class="qr-q-sub">We use this to tailor your pension projection.</p>
        <div class="qr-options" role="group" aria-label="Employment status options">

          <button type="button" class="qr-opt" data-value="not-employed" aria-pressed="false">
            <span class="qr-opt__label">Not employed</span>
            <span class="qr-opt__check" aria-hidden="true">
              <svg width="14" height="14" fill="none" viewBox="0 0 24 24">
                <path stroke="#fff" stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/>
              </svg>
            </span>
          </button>

          <button type="button" class="qr-opt" data-value="part-time" aria-pressed="false">
            <span class="qr-opt__label">Part-time employed</span>
            <span class="qr-opt__check" aria-hidden="true">
              <svg width="14" height="14" fill="none" viewBox="0 0 24 24">
                <path stroke="#fff" stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/>
              </svg>
            </span>
          </button>

          <button type="button" class="qr-opt" data-value="full-time" aria-pressed="false">
            <span class="qr-opt__label">Full time employed</span>
            <span class="qr-opt__check" aria-hidden="true">
              <svg width="14" height="14" fill="none" viewBox="0 0 24 24">
                <path stroke="#fff" stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/>
              </svg>
            </span>
          </button>

          <button type="button" class="qr-opt" data-value="self-employed" aria-pressed="false">
            <span class="qr-opt__label">Self-employed</span>
            <span class="qr-opt__check" aria-hidden="true">
              <svg width="14" height="14" fill="none" viewBox="0 0 24 24">
                <path stroke="#fff" stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/>
              </svg>
            </span>
          </button>

          <button type="button" class="qr-opt" data-value="retired" aria-pressed="false">
            <span class="qr-opt__label">Retired</span>
            <span class="qr-opt__check" aria-hidden="true">
              <svg width="14" height="14" fill="none" viewBox="0 0 24 24">
                <path stroke="#fff" stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/>
              </svg>
            </span>
          </button>

        </div>
      </section>

      <!-- Q2: Annual income -->
      <section class="qr-screen" id="s-income" aria-labelledby="q2-heading">
        <h2 class="qr-q" id="q2-heading" tabindex="-1">What is your annual income?</h2>
        <p class="qr-q-sub">Your gross income before tax, including salary, self-employment, and pension income.</p>
        <div class="qr-options" role="group" aria-label="Annual income options">

          <button type="button" class="qr-opt" data-value="upto_50270" aria-pressed="false">
            <span class="qr-opt__label">Up to £50,270</span>
            <span class="qr-opt__check" aria-hidden="true">
              <svg width="14" height="14" fill="none" viewBox="0 0 24 24">
                <path stroke="#fff" stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/>
              </svg>
            </span>
          </button>

          <button type="button" class="qr-opt" data-value="50271_100000" aria-pressed="false">
            <span class="qr-opt__label">£50,271 to £100,000</span>
            <span class="qr-opt__check" aria-hidden="true">
              <svg width="14" height="14" fill="none" viewBox="0 0 24 24">
                <path stroke="#fff" stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/>
              </svg>
            </span>
          </button>

          <button type="button" class="qr-opt" data-value="100001_125140" aria-pressed="false">
            <span class="qr-opt__label">£100,001 to £125,140</span>
            <span class="qr-opt__badge">Tax-trap zone</span>
            <span class="qr-opt__check" aria-hidden="true">
              <svg width="14" height="14" fill="none" viewBox="0 0 24 24">
                <path stroke="#fff" stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/>
              </svg>
            </span>
          </button>

          <button type="button" class="qr-opt" data-value="over_125140" aria-pressed="false">
            <span class="qr-opt__label">Above £125,140</span>
            <span class="qr-opt__check" aria-hidden="true">
              <svg width="14" height="14" fill="none" viewBox="0 0 24 24">
                <path stroke="#fff" stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/>
              </svg>
            </span>
          </button>

        </div>
      </section>

      <!-- Q3: Age band -->
      <section class="qr-screen" id="s-age" aria-labelledby="q3-heading">
        <h2 class="qr-q" id="q3-heading" tabindex="-1">How old are you?</h2>
        <p class="qr-q-sub">We use this to estimate how many years of contributions remain before retirement.</p>
        <div class="qr-options" role="group" aria-label="Age band options">

          <button type="button" class="qr-opt" data-value="under_30" aria-pressed="false">
            <span class="qr-opt__label">Under 30</span>
            <span class="qr-opt__check" aria-hidden="true">
              <svg width="14" height="14" fill="none" viewBox="0 0 24 24">
                <path stroke="#fff" stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/>
              </svg>
            </span>
          </button>

          <button type="button" class="qr-opt" data-value="30s" aria-pressed="false">
            <span class="qr-opt__label">In my 30s</span>
            <span class="qr-opt__check" aria-hidden="true">
              <svg width="14" height="14" fill="none" viewBox="0 0 24 24">
                <path stroke="#fff" stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/>
              </svg>
            </span>
          </button>

          <button type="button" class="qr-opt" data-value="40s" aria-pressed="false">
            <span class="qr-opt__label">In my 40s</span>
            <span class="qr-opt__check" aria-hidden="true">
              <svg width="14" height="14" fill="none" viewBox="0 0 24 24">
                <path stroke="#fff" stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/>
              </svg>
            </span>
          </button>

          <button type="button" class="qr-opt" data-value="50s" aria-pressed="false">
            <span class="qr-opt__label">In my 50s</span>
            <span class="qr-opt__check" aria-hidden="true">
              <svg width="14" height="14" fill="none" viewBox="0 0 24 24">
                <path stroke="#fff" stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/>
              </svg>
            </span>
          </button>

          <button type="button" class="qr-opt" data-value="60_plus" aria-pressed="false">
            <span class="qr-opt__label">60 or over</span>
            <span class="qr-opt__check" aria-hidden="true">
              <svg width="14" height="14" fill="none" viewBox="0 0 24 24">
                <path stroke="#fff" stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/>
              </svg>
            </span>
          </button>

        </div>
      </section>

      <!-- Q4: Pensions (multi-select) -->
      <section class="qr-screen" id="s-pensions" aria-labelledby="q4-heading">
        <h2 class="qr-q" id="q4-heading" tabindex="-1">Which types of pension do you have?</h2>
        <p class="qr-q-sub">Select all that apply. This helps us understand your existing pension arrangements.</p>
        <div class="qr-options qr-options--grid" role="group" aria-label="Select all that apply">

          <button type="button" class="qr-opt qr-opt--multi" data-value="workplace" role="checkbox" aria-checked="false">
            <span class="qr-asset-label">A workplace pension</span>
            <span class="qr-opt__check" aria-hidden="true">
              <svg width="14" height="14" fill="none" viewBox="0 0 24 24">
                <path stroke="#fff" stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/>
              </svg>
            </span>
          </button>

          <button type="button" class="qr-opt qr-opt--multi" data-value="personal_sipp" role="checkbox" aria-checked="false">
            <span class="qr-asset-label">A personal pension or self-invested personal pension</span>
            <span class="qr-opt__check" aria-hidden="true">
              <svg width="14" height="14" fill="none" viewBox="0 0 24 24">
                <path stroke="#fff" stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/>
              </svg>
            </span>
          </button>

          <button type="button" class="qr-opt qr-opt--multi" data-value="final_salary" role="checkbox" aria-checked="false">
            <span class="qr-asset-label">A final salary or career average pension</span>
            <span class="qr-opt__check" aria-hidden="true">
              <svg width="14" height="14" fill="none" viewBox="0 0 24 24">
                <path stroke="#fff" stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/>
              </svg>
            </span>
          </button>

          <button type="button" class="qr-opt qr-opt--multi" data-value="none" role="checkbox" aria-checked="false">
            <span class="qr-asset-label">No pensions yet</span>
            <span class="qr-opt__check" aria-hidden="true">
              <svg width="14" height="14" fill="none" viewBox="0 0 24 24">
                <path stroke="#fff" stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/>
              </svg>
            </span>
          </button>

        </div>
      </section>

      <!-- Q5: Pension pot size -->
      <section class="qr-screen" id="s-pot" aria-labelledby="q5-heading">
        <h2 class="qr-q" id="q5-heading" tabindex="-1">What is the total value of your pension pot?</h2>
        <p class="qr-q-sub">An approximate figure is fine. Include all pensions you have.</p>
        <div class="qr-options" role="group" aria-label="Pension pot size options">

          <button type="button" class="qr-opt" data-value="none" aria-pressed="false">
            <span class="qr-opt__label">Nothing yet</span>
            <span class="qr-opt__check" aria-hidden="true">
              <svg width="14" height="14" fill="none" viewBox="0 0 24 24">
                <path stroke="#fff" stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/>
              </svg>
            </span>
          </button>

          <button type="button" class="qr-opt" data-value="under_25k" aria-pressed="false">
            <span class="qr-opt__label">Under £25,000</span>
            <span class="qr-opt__check" aria-hidden="true">
              <svg width="14" height="14" fill="none" viewBox="0 0 24 24">
                <path stroke="#fff" stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/>
              </svg>
            </span>
          </button>

          <button type="button" class="qr-opt" data-value="25k_100k" aria-pressed="false">
            <span class="qr-opt__label">£25,000 to £100,000</span>
            <span class="qr-opt__check" aria-hidden="true">
              <svg width="14" height="14" fill="none" viewBox="0 0 24 24">
                <path stroke="#fff" stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/>
              </svg>
            </span>
          </button>

          <button type="button" class="qr-opt" data-value="100k_250k" aria-pressed="false">
            <span class="qr-opt__label">£100,000 to £250,000</span>
            <span class="qr-opt__check" aria-hidden="true">
              <svg width="14" height="14" fill="none" viewBox="0 0 24 24">
                <path stroke="#fff" stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/>
              </svg>
            </span>
          </button>

          <button type="button" class="qr-opt" data-value="over_250k" aria-pressed="false">
            <span class="qr-opt__label">Over £250,000</span>
            <span class="qr-opt__check" aria-hidden="true">
              <svg width="14" height="14" fill="none" viewBox="0 0 24 24">
                <path stroke="#fff" stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/>
              </svg>
            </span>
          </button>

        </div>
      </section>

      <!-- Q6: Spouse -->
      <section class="qr-screen" id="s-spouse" aria-labelledby="q6-heading">
        <h2 class="qr-q" id="q6-heading" tabindex="-1">Do you have a spouse?</h2>
        <p class="qr-q-sub">Couples may be able to plan their pensions together to reduce their overall tax bill.</p>
        <div class="qr-options qr-options--pair" role="group" aria-label="Spouse options">

          <button type="button" class="qr-opt qr-opt--square" data-value="yes" aria-pressed="false">
            <span class="qr-opt__label">Yes</span>
            <span class="qr-opt__check" aria-hidden="true">
              <svg width="14" height="14" fill="none" viewBox="0 0 24 24">
                <path stroke="#fff" stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/>
              </svg>
            </span>
          </button>

          <button type="button" class="qr-opt qr-opt--square" data-value="no" aria-pressed="false">
            <span class="qr-opt__label">No</span>
            <span class="qr-opt__check" aria-hidden="true">
              <svg width="14" height="14" fill="none" viewBox="0 0 24 24">
                <path stroke="#fff" stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/>
              </svg>
            </span>
          </button>

        </div>
      </section>

    </main>

    <div class="qr-footer" id="qr-footer-area">
      <button type="button" class="qr-btn" id="qr-continue-btn" disabled>Continue</button>
    </div>

  </div><!-- /.qr-card -->

  <script src="/pages/js/pensioncheck.js?v=2" defer></script>
  <!-- Cookie consent — direct funnel entry must still surface the prompt here. -->
  <script src="/pages/js/cookie-consent.js?v=1" defer></script>
</body>
</html>
