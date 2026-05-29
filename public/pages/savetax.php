<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Save Tax — Find Your Tax Saving Opportunities — Fynla</title>
  <meta name="description" content="Answer 5 quick questions and discover the UK tax reliefs and allowances you may be missing. Personalised tax insights from Fynla in under a minute." />
  <link rel="canonical" href="https://fynla.org/savetax" />

  <!-- Open Graph -->
  <meta property="og:type" content="website" />
  <meta property="og:title" content="Save Tax — Find Your Tax Saving Opportunities — Fynla" />
  <meta property="og:description" content="Answer 5 quick questions and discover the UK tax reliefs and allowances you may be missing. Personalised tax insights from Fynla in under a minute." />
  <meta property="og:image" content="https://fynla.org/images/og/savetax.jpg" />
  <meta property="og:url" content="https://fynla.org/savetax" />

  <!-- Twitter Card -->
  <meta name="twitter:card" content="summary_large_image" />
  <meta name="twitter:title" content="Save Tax — Find Your Tax Saving Opportunities — Fynla" />
  <meta name="twitter:description" content="Answer 5 quick questions and discover the UK tax reliefs and allowances you may be missing. Personalised tax insights from Fynla in under a minute." />
  <meta name="twitter:image" content="https://fynla.org/images/og/savetax.jpg" />

  <!-- hreflang -->
  <link rel="alternate" hreflang="en-GB" href="https://fynla.org/savetax" />
  <link rel="alternate" hreflang="x-default" href="https://fynla.org/savetax" />

  <!-- JSON-LD -->
  <script type="application/ld+json">
  {
    "@context": "https://schema.org",
    "@type": "WebPage",
    "name": "Save Tax — Find Your Tax Saving Opportunities",
    "description": "Answer 5 quick questions and discover the UK tax reliefs and allowances you may be missing.",
    "url": "https://fynla.org/savetax",
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
  <link rel="stylesheet" href="/pages/css/global.css?v=3" />
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
        <div class="qr-step" id="qr-step-label" aria-live="polite" aria-atomic="true">1 of 4</div>
      </div>
      <div class="qr-progress" role="progressbar" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100" aria-label="Question progress">
        <div class="qr-progress__fill" id="qr-progress-fill"></div>
      </div>
    </header>

    <main id="main-content" class="qr-main">

      <!-- Q1: Employment status -->
      <section class="qr-screen is-active" id="s-employment" aria-labelledby="q1-heading">
        <h2 class="qr-q" id="q1-heading" tabindex="-1">What is your employment status?</h2>
        <p class="qr-q-sub">We use this to identify the tax reliefs most relevant to you.</p>
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

          <button type="button" class="qr-opt" data-value="personal-allowance" aria-pressed="false">
            <span class="qr-opt__label">Up to £12,570</span>
            <span class="qr-opt__badge">Up to personal allowance</span>
            <span class="qr-opt__check" aria-hidden="true">
              <svg width="14" height="14" fill="none" viewBox="0 0 24 24">
                <path stroke="#fff" stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/>
              </svg>
            </span>
          </button>

          <button type="button" class="qr-opt" data-value="basic" aria-pressed="false">
            <span class="qr-opt__label">£12,571 – £50,270</span>
            <span class="qr-opt__badge">Basic rate</span>
            <span class="qr-opt__check" aria-hidden="true">
              <svg width="14" height="14" fill="none" viewBox="0 0 24 24">
                <path stroke="#fff" stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/>
              </svg>
            </span>
          </button>

          <button type="button" class="qr-opt" data-value="higher" aria-pressed="false">
            <span class="qr-opt__label">£50,271 – £100,000</span>
            <span class="qr-opt__badge">Higher rate</span>
            <span class="qr-opt__check" aria-hidden="true">
              <svg width="14" height="14" fill="none" viewBox="0 0 24 24">
                <path stroke="#fff" stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/>
              </svg>
            </span>
          </button>

          <button type="button" class="qr-opt" data-value="additional" aria-pressed="false">
            <span class="qr-opt__label">£100,001 or more</span>
            <span class="qr-opt__badge">Additional rate</span>
            <span class="qr-opt__check" aria-hidden="true">
              <svg width="14" height="14" fill="none" viewBox="0 0 24 24">
                <path stroke="#fff" stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/>
              </svg>
            </span>
          </button>

        </div>
      </section>

      <!-- Q3: Spouse or partner -->
      <section class="qr-screen" id="s-spouse" aria-labelledby="q3-heading">
        <h2 class="qr-q" id="q3-heading" tabindex="-1">Do you have a spouse or partner?</h2>
        <p class="qr-q-sub">Couples may be able to transfer allowances and split income to reduce their overall tax bill.</p>
        <div class="qr-options qr-options--pair" role="group" aria-label="Spouse or partner options">

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

      <!-- Q4: Spouse income (conditional — shown only if Q3=Yes) -->
      <section class="qr-screen" id="s-spouse-income" aria-labelledby="q4-heading">
        <h2 class="qr-q" id="q4-heading" tabindex="-1">What is your spouse or partner's annual income?</h2>
        <p class="qr-q-sub">Their gross income before tax. This helps us identify allowance transfer opportunities.</p>
        <div class="qr-options" role="group" aria-label="Spouse or partner annual income options">

          <button type="button" class="qr-opt" data-value="personal-allowance" aria-pressed="false">
            <span class="qr-opt__label">Up to £12,570</span>
            <span class="qr-opt__badge">Up to personal allowance</span>
            <span class="qr-opt__check" aria-hidden="true">
              <svg width="14" height="14" fill="none" viewBox="0 0 24 24">
                <path stroke="#fff" stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/>
              </svg>
            </span>
          </button>

          <button type="button" class="qr-opt" data-value="basic" aria-pressed="false">
            <span class="qr-opt__label">£12,571 – £50,270</span>
            <span class="qr-opt__badge">Basic rate</span>
            <span class="qr-opt__check" aria-hidden="true">
              <svg width="14" height="14" fill="none" viewBox="0 0 24 24">
                <path stroke="#fff" stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/>
              </svg>
            </span>
          </button>

          <button type="button" class="qr-opt" data-value="higher" aria-pressed="false">
            <span class="qr-opt__label">£50,271 – £100,000</span>
            <span class="qr-opt__badge">Higher rate</span>
            <span class="qr-opt__check" aria-hidden="true">
              <svg width="14" height="14" fill="none" viewBox="0 0 24 24">
                <path stroke="#fff" stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/>
              </svg>
            </span>
          </button>

          <button type="button" class="qr-opt" data-value="additional" aria-pressed="false">
            <span class="qr-opt__label">£100,001 or more</span>
            <span class="qr-opt__badge">Additional rate</span>
            <span class="qr-opt__check" aria-hidden="true">
              <svg width="14" height="14" fill="none" viewBox="0 0 24 24">
                <path stroke="#fff" stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/>
              </svg>
            </span>
          </button>

        </div>
      </section>

      <!-- Q5: Assets (multi-select) -->
      <section class="qr-screen" id="s-assets" aria-labelledby="q5-heading">
        <h2 class="qr-q" id="q5-heading" tabindex="-1">Which of these do you have?</h2>
        <p class="qr-q-sub">Select all that apply. Each one may unlock additional tax saving opportunities.</p>
        <div class="qr-options qr-options--grid" role="group" aria-label="Select all that apply">

          <button type="button" class="qr-opt qr-opt--multi" data-value="bank" role="checkbox" aria-checked="false">
            <span class="qr-asset-label">Bank accounts</span>
            <span class="qr-opt__check" aria-hidden="true">
              <svg width="14" height="14" fill="none" viewBox="0 0 24 24">
                <path stroke="#fff" stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/>
              </svg>
            </span>
          </button>

          <button type="button" class="qr-opt qr-opt--multi" data-value="savings" role="checkbox" aria-checked="false">
            <span class="qr-asset-label">Savings accounts</span>
            <span class="qr-opt__check" aria-hidden="true">
              <svg width="14" height="14" fill="none" viewBox="0 0 24 24">
                <path stroke="#fff" stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/>
              </svg>
            </span>
          </button>

          <button type="button" class="qr-opt qr-opt--multi" data-value="pension" role="checkbox" aria-checked="false">
            <span class="qr-asset-label">Pension</span>
            <span class="qr-opt__check" aria-hidden="true">
              <svg width="14" height="14" fill="none" viewBox="0 0 24 24">
                <path stroke="#fff" stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/>
              </svg>
            </span>
          </button>

          <button type="button" class="qr-opt qr-opt--multi" data-value="property" role="checkbox" aria-checked="false">
            <span class="qr-asset-label">Property</span>
            <span class="qr-opt__check" aria-hidden="true">
              <svg width="14" height="14" fill="none" viewBox="0 0 24 24">
                <path stroke="#fff" stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/>
              </svg>
            </span>
          </button>

          <button type="button" class="qr-opt qr-opt--multi" data-value="isa" role="checkbox" aria-checked="false">
            <span class="qr-asset-label">ISA</span>
            <span class="qr-opt__check" aria-hidden="true">
              <svg width="14" height="14" fill="none" viewBox="0 0 24 24">
                <path stroke="#fff" stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/>
              </svg>
            </span>
          </button>

          <button type="button" class="qr-opt qr-opt--multi" data-value="investments" role="checkbox" aria-checked="false">
            <span class="qr-asset-label">Investments</span>
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

  <script src="/pages/js/savetax.js?v=5" defer></script>
</body>
</html>
