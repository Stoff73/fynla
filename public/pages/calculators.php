<?php
/**
 * Fynla — Free UK Financial Calculators
 * Converted from resources/js/views/Public/CalculatorsPage.vue
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <!-- Favicon -->
  <link rel="icon" type="image/png" href="/images/logos/favicon.png" />
  <link rel="icon" type="image/x-icon" href="/images/logos/favicon.ico" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Free UK Financial Calculators — Income Tax, Mortgage, Pension | Fynla</title>
  <meta name="description" content="Free UK financial calculators for income tax, mortgage repayments, pension growth, stamp duty, student loans, and more. No sign-up required." />
  <link rel="canonical" href="https://fynla.org/calculators" />

  <!-- Open Graph -->
  <meta property="og:type" content="website" />
  <meta property="og:title" content="Free UK Financial Calculators — Income Tax, Mortgage, Pension | Fynla" />
  <meta property="og:description" content="Free UK financial calculators for income tax, mortgage repayments, pension growth, stamp duty, student loans, and more. No sign-up required." />
  <meta property="og:image" content="https://fynla.org/images/og/calculators.jpg" />
  <meta property="og:url" content="https://fynla.org/calculators" />

  <!-- Twitter Card -->
  <meta name="twitter:card" content="summary_large_image" />
  <meta name="twitter:title" content="Free UK Financial Calculators — Income Tax, Mortgage, Pension | Fynla" />
  <meta name="twitter:description" content="Free UK financial calculators for income tax, mortgage repayments, pension growth, stamp duty, student loans, and more. No sign-up required." />
  <meta name="twitter:image" content="https://fynla.org/images/og/calculators.jpg" />

  <!-- hreflang -->
  <link rel="alternate" hreflang="en-GB" href="https://fynla.org/calculators" />
  <link rel="alternate" hreflang="x-default" href="https://fynla.org/calculators" />

  <!-- JSON-LD -->
  <script type="application/ld+json">
  {
    "@context": "https://schema.org",
    "@type": "WebPage",
    "name": "Free UK Financial Calculators",
    "url": "https://fynla.org/calculators",
    "description": "Free UK financial calculators for income tax, mortgage repayments, pension growth, stamp duty, student loans, and more.",
    "publisher": {
      "@type": "Organization",
      "name": "Fynla",
      "url": "https://fynla.org"
    }
  }
  </script>

  <!-- Chart.js self-hosted (same-origin, satisfies CSP script-src 'self'; base-path rewritten on subdir deploys) -->
  <script src="/pages/js/vendor/chart.umd.min.js" defer></script>

  <!-- Blocking CSS — same-server, negligible render penalty, prevents FOUC -->
  <link rel="stylesheet" href="/pages/css/global.css?v=2" />
  <link rel="stylesheet" href="/pages/css/calculators.css?v=1" />
</head>
<body class="calc-page-body">

<a href="#main-content" class="skip-nav">Skip to main content</a>

<?php include __DIR__.'/partials/nav.php'; ?>

<main id="main-content">

  <!-- ================================================================
       HERO
  ================================================================ -->
  <section class="calc-hero" aria-labelledby="calc-hero-heading">
    <div class="calc-hero__inner">
      <h1 class="calc-hero__title" id="calc-hero-heading">
        Free Financial
        <span class="calc-hero__title-accent">Calculators</span>
      </h1>
      <p class="calc-hero__subtext">Free tools to help you understand your finances. Planning tools require a free Fynla account.</p>
    </div>
  </section>

  <!-- ================================================================
       TWO-COLUMN LAYOUT
  ================================================================ -->
  <div class="calc-layout">

    <!-- ============================================================
         MOBILE NAVIGATION (pill nav — hidden at 1024px+)
    ============================================================ -->
    <nav class="calc-mobile-nav scrollbar-hide" aria-label="Calculator categories">
      <!-- Stage pills -->
      <div class="calc-stage-pills scrollbar-hide" role="tablist" aria-label="Financial life stages">

        <button type="button" class="calc-stage-pill is-active" data-stage="Planning Your Future" role="tab" aria-selected="true"
                aria-label="Planning Your Future calculators">
          <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
          </svg>
          Planning Your Future
        </button>

        <button type="button" class="calc-stage-pill" data-stage="Starting Out" role="tab" aria-selected="false"
                aria-label="Starting Out calculators">
          <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
          </svg>
          Starting Out
        </button>

        <button type="button" class="calc-stage-pill" data-stage="Building Foundations" role="tab" aria-selected="false"
                aria-label="Building Foundations calculators">
          <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
          </svg>
          Building Foundations
        </button>

        <button type="button" class="calc-stage-pill" data-stage="Protecting and Growing" role="tab" aria-selected="false"
                aria-label="Protecting and Growing calculators">
          <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
          </svg>
          Protecting and Growing
        </button>

        <button type="button" class="calc-stage-pill" data-stage="Enjoying Your Wealth" role="tab" aria-selected="false"
                aria-label="Enjoying Your Wealth calculators">
          <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m4.22 1.78l-.71.71M20 12h1M4 12H3m3.34-5.66l-.71-.71M15.54 8.46A5.99 5.99 0 0112 7a5.99 5.99 0 00-3.54 1.46M12 14a2 2 0 100-4 2 2 0 000 4zm0 0v7"/>
          </svg>
          Enjoying Your Wealth
        </button>

      </div>

      <!-- Calculator item pills (populated by JS based on selected stage) -->
      <div id="mobile-item-pills" class="calc-item-pills scrollbar-hide" role="tablist" aria-label="Calculators in selected stage"></div>
    </nav>

    <!-- ============================================================
         SIDEBAR (hidden below 1024px)
    ============================================================ -->
    <aside class="calc-sidebar" aria-label="Calculator navigation">

      <!-- Starting Out -->
      <div class="calc-stage-group" data-stage="Starting Out">
        <button type="button" class="calc-stage-btn" aria-expanded="true" aria-controls="stage-items-starting-out">
          <span class="calc-stage-btn__left">
            <svg class="calc-stage-btn__icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
            </svg>
            <span class="calc-stage-btn__label">Starting Out</span>
          </span>
          <svg class="calc-stage-chevron is-open" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
          </svg>
        </button>
        <div class="calc-stage-items" id="stage-items-starting-out">
          <button type="button" class="calc-sidebar-btn" data-calc="student-loan" data-type="free" aria-label="Student Loan Repayment calculator">
            <svg class="calc-sidebar-btn__icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 14l9-5-9-5-9 5 9 5z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 14l6.16-3.422a12.083 12.083 0 01.665 6.479A11.952 11.952 0 0012 20.055a11.952 11.952 0 00-6.824-2.998 12.078 12.078 0 01.665-6.479L12 14z"/></svg>
            <span class="calc-sidebar-btn__name">Student Loan Repayment</span>
          </button>
          <button type="button" class="calc-sidebar-btn" data-calc="savings-goal" data-type="free" aria-label="Savings Goal calculator">
            <svg class="calc-sidebar-btn__icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z"/></svg>
            <span class="calc-sidebar-btn__name">Savings Goal</span>
          </button>
          <button type="button" class="calc-sidebar-btn" data-calc="emergency-fund" data-type="free" aria-label="Emergency Fund calculator">
            <svg class="calc-sidebar-btn__icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
            <span class="calc-sidebar-btn__name">Emergency Fund</span>
          </button>
        </div>
      </div>

      <!-- Building Foundations -->
      <div class="calc-stage-group" data-stage="Building Foundations">
        <button type="button" class="calc-stage-btn" aria-expanded="true" aria-controls="stage-items-building">
          <span class="calc-stage-btn__left">
            <svg class="calc-stage-btn__icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
            </svg>
            <span class="calc-stage-btn__label">Building Foundations</span>
          </span>
          <svg class="calc-stage-chevron is-open" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
          </svg>
        </button>
        <div class="calc-stage-items" id="stage-items-building">
          <button type="button" class="calc-sidebar-btn" data-calc="mortgage" data-type="free" aria-label="Mortgage Repayment calculator">
            <svg class="calc-sidebar-btn__icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
            <span class="calc-sidebar-btn__name">Mortgage Repayment</span>
          </button>
          <button type="button" class="calc-sidebar-btn" data-calc="mortgage-afford" data-type="free" aria-label="Mortgage Affordability calculator">
            <svg class="calc-sidebar-btn__icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"/></svg>
            <span class="calc-sidebar-btn__name">Mortgage Affordability</span>
          </button>
          <button type="button" class="calc-sidebar-btn" data-calc="stamp-duty" data-type="free" aria-label="Stamp Duty calculator">
            <svg class="calc-sidebar-btn__icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 14l6-6m-5.5.5h.01m4.99 5h.01M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16l3.5-2 3.5 2 3.5-2 3.5 2z"/></svg>
            <span class="calc-sidebar-btn__name">Stamp Duty</span>
          </button>
          <button type="button" class="calc-sidebar-btn" data-calc="personal-loan" data-type="free" aria-label="Personal Loan calculator">
            <svg class="calc-sidebar-btn__icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
            <span class="calc-sidebar-btn__name">Personal Loan</span>
          </button>
          <button type="button" class="calc-sidebar-btn" data-calc="compound-interest" data-type="free" aria-label="Compound Interest calculator">
            <svg class="calc-sidebar-btn__icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/></svg>
            <span class="calc-sidebar-btn__name">Compound Interest</span>
          </button>
        </div>
      </div>

      <!-- Protecting and Growing -->
      <div class="calc-stage-group" data-stage="Protecting and Growing">
        <button type="button" class="calc-stage-btn" aria-expanded="false" aria-controls="stage-items-protecting">
          <span class="calc-stage-btn__left">
            <svg class="calc-stage-btn__icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
            </svg>
            <span class="calc-stage-btn__label">Protecting and Growing</span>
          </span>
          <svg class="calc-stage-chevron" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
          </svg>
        </button>
        <div class="calc-stage-items" id="stage-items-protecting" hidden>
          <button type="button" class="calc-sidebar-btn is-gated" data-calc="life-insurance" data-type="gated-free" aria-label="Life Insurance Needs calculator — requires free trial" aria-disabled="true">
            <svg class="calc-sidebar-btn__icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
            <span class="calc-sidebar-btn__name">Life Insurance Needs</span>
            <span class="calc-badge-trial">Free trial</span>
          </button>
          <button type="button" class="calc-sidebar-btn is-gated" data-calc="income-protection" data-type="gated-free" aria-label="Income Protection calculator — requires free trial" aria-disabled="true">
            <svg class="calc-sidebar-btn__icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
            <span class="calc-sidebar-btn__name">Income Protection</span>
            <span class="calc-badge-trial">Free trial</span>
          </button>
        </div>
      </div>

      <!-- Planning Your Future -->
      <div class="calc-stage-group" data-stage="Planning Your Future">
        <button type="button" class="calc-stage-btn" aria-expanded="true" aria-controls="stage-items-planning">
          <span class="calc-stage-btn__left">
            <svg class="calc-stage-btn__icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            <span class="calc-stage-btn__label">Planning Your Future</span>
          </span>
          <svg class="calc-stage-chevron is-open" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
          </svg>
        </button>
        <div class="calc-stage-items" id="stage-items-planning">
          <button type="button" class="calc-sidebar-btn is-active" data-calc="income-tax" data-type="free" aria-label="Income Tax calculator" aria-current="true">
            <svg class="calc-sidebar-btn__icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
            <span class="calc-sidebar-btn__name">Income Tax</span>
          </button>
          <button type="button" class="calc-sidebar-btn" data-calc="pension" data-type="free" aria-label="Pension Growth calculator">
            <svg class="calc-sidebar-btn__icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/></svg>
            <span class="calc-sidebar-btn__name">Pension Growth</span>
          </button>
          <button type="button" class="calc-sidebar-btn" data-calc="pension-relief" data-type="free" aria-label="Pension Tax Relief calculator">
            <svg class="calc-sidebar-btn__icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 14l6-6m-5.5.5h.01m4.99 5h.01M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16l3.5-2 3.5 2 3.5-2 3.5 2z"/></svg>
            <span class="calc-sidebar-btn__name">Pension Tax Relief</span>
          </button>
          <button type="button" class="calc-sidebar-btn is-gated" data-calc="salary-sacrifice" data-type="gated-free" aria-label="Salary Sacrifice calculator — requires free trial" aria-disabled="true">
            <svg class="calc-sidebar-btn__icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
            <span class="calc-sidebar-btn__name">Salary Sacrifice</span>
            <span class="calc-badge-trial">Free trial</span>
          </button>
          <button type="button" class="calc-sidebar-btn is-gated" data-calc="retirement-budget" data-type="gated-paid" aria-label="Retirement Budget Planner — requires paid subscription" aria-disabled="true">
            <svg class="calc-sidebar-btn__icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
            <span class="calc-sidebar-btn__name">Retirement Budget Planner</span>
            <span class="calc-badge-trial">Free trial</span>
          </button>
        </div>
      </div>

      <!-- Enjoying Your Wealth -->
      <div class="calc-stage-group" data-stage="Enjoying Your Wealth">
        <button type="button" class="calc-stage-btn" aria-expanded="false" aria-controls="stage-items-wealth">
          <span class="calc-stage-btn__left">
            <svg class="calc-stage-btn__icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m4.22 1.78l-.71.71M20 12h1M4 12H3m3.34-5.66l-.71-.71M15.54 8.46A5.99 5.99 0 0112 7a5.99 5.99 0 00-3.54 1.46M12 14a2 2 0 100-4 2 2 0 000 4zm0 0v7"/>
            </svg>
            <span class="calc-stage-btn__label">Enjoying Your Wealth</span>
          </span>
          <svg class="calc-stage-chevron" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
          </svg>
        </button>
        <div class="calc-stage-items" id="stage-items-wealth" hidden>
          <button type="button" class="calc-sidebar-btn" data-calc="pension-withdrawal" data-type="free" aria-label="Pension Withdrawal Tax calculator">
            <svg class="calc-sidebar-btn__icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            <span class="calc-sidebar-btn__name">Pension Withdrawal Tax</span>
          </button>
          <button type="button" class="calc-sidebar-btn is-gated" data-calc="iht-checker" data-type="gated-free" aria-label="Inheritance Tax Exposure Checker — requires free trial" aria-disabled="true">
            <svg class="calc-sidebar-btn__icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
            <span class="calc-sidebar-btn__name">Inheritance Tax Checker</span>
            <span class="calc-badge-trial">Free trial</span>
          </button>
          <button type="button" class="calc-sidebar-btn is-gated" data-calc="drawdown-runway" data-type="gated-free" aria-label="Pension Drawdown Runway calculator — requires free trial" aria-disabled="true">
            <svg class="calc-sidebar-btn__icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            <span class="calc-sidebar-btn__name">Pension Drawdown / Runway</span>
            <span class="calc-badge-trial">Free trial</span>
          </button>
          <button type="button" class="calc-sidebar-btn is-gated" data-calc="annuity-vs-drawdown" data-type="gated-paid" aria-label="Annuity vs Drawdown Comparison — requires paid subscription" aria-disabled="true">
            <svg class="calc-sidebar-btn__icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
            <span class="calc-sidebar-btn__name">Annuity vs Drawdown</span>
            <span class="calc-badge-trial">Free trial</span>
          </button>
        </div>
      </div>

    </aside><!-- /sidebar -->

    <!-- ============================================================
         CALCULATOR PANEL
    ============================================================ -->
    <div class="calc-panel" id="calculator-panel">

      <noscript>
        <div class="calc-noscript">
          <p>These calculators require JavaScript to function. Please enable JavaScript in your browser settings to use them.</p>
        </div>
      </noscript>

      <!-- ============================================================
           INCOME TAX CALCULATOR
      ============================================================ -->
      <section class="calc-card is-active" data-calc="income-tax" aria-labelledby="it-heading">
        <div class="calc-header">
          <h2 class="calc-header__title" id="it-heading">Income Tax Calculator</h2>
          <p class="calc-header__sub">Calculate your UK income tax and National Insurance contributions</p>
        </div>
        <div class="calc-form-card">
          <div class="calc-grid">
            <div class="calc-inputs">
              <div>
                <label class="calc-label" for="it-income">Annual Gross Income</label>
                <div class="calc-input-wrap">
                  <span class="calc-input-prefix" aria-hidden="true">&pound;</span>
                  <input id="it-income" type="text" inputmode="numeric" class="calc-input calc-input--pl" placeholder="50,000" autocomplete="off" />
                </div>
              </div>
              <div>
                <label class="calc-label" for="it-pension">Pension Contributions</label>
                <div class="calc-input-wrap">
                  <span class="calc-input-prefix" aria-hidden="true">&pound;</span>
                  <input id="it-pension" type="text" inputmode="numeric" class="calc-input calc-input--pl" placeholder="0" autocomplete="off" />
                </div>
                <p class="calc-input-hint">Pension contributions reduce your taxable income</p>
              </div>
              <button id="it-calc-btn" type="button" class="calc-submit-btn">Calculate Tax</button>
            </div>

            <!-- Results -->
            <div id="income-tax-result" class="calc-result-box" hidden>
              <h3 class="calc-result-heading">Results</h3>
              <div class="calc-result-rows">
                <div class="calc-result-row">
                  <span class="calc-result-row__label">Gross Income</span>
                  <span class="calc-result-row__value" id="it-gross"></span>
                </div>
                <div class="calc-result-row">
                  <span class="calc-result-row__label">Pension Contributions</span>
                  <span class="calc-result-row__value" id="it-pension-val" aria-live="polite"></span>
                </div>
                <div class="calc-result-row">
                  <span class="calc-result-row__label">Taxable Income</span>
                  <span class="calc-result-row__value" id="it-taxable"></span>
                </div>
                <hr class="calc-result-divider" />
                <div class="calc-result-row">
                  <span class="calc-result-row__label">Income Tax</span>
                  <span class="calc-result-row__value calc-result-row__value--raspberry" id="it-tax"></span>
                </div>
                <div class="calc-result-row">
                  <span class="calc-result-row__label">National Insurance</span>
                  <span class="calc-result-row__value calc-result-row__value--raspberry" id="it-ni"></span>
                </div>
                <hr class="calc-result-divider" />
                <div class="calc-result-total calc-result-total--spring">
                  <span class="calc-result-total__label">Net Income</span>
                  <span class="calc-result-total__value" id="it-net" aria-live="polite"></span>
                </div>
                <div class="calc-result-row">
                  <span class="calc-result-row__label">Effective Tax Rate</span>
                  <span class="calc-result-row__value" id="it-rate"></span>
                </div>
              </div>
            </div>
            <div id="income-tax-placeholder" class="calc-result-placeholder">
              <div class="calc-result-placeholder__text">
                <p>Enter your income and click Calculate to see results</p>
              </div>
            </div>
          </div>
        </div>
      </section>

      <!-- ============================================================
           MORTGAGE REPAYMENT CALCULATOR
      ============================================================ -->
      <section class="calc-card" data-calc="mortgage" aria-labelledby="m-heading">
        <div class="calc-header">
          <span class="calc-header__badge">Building Foundations</span>
          <h2 class="calc-header__title" id="m-heading">Mortgage Repayment Calculator</h2>
          <p class="calc-header__sub">Calculate your monthly payments, total cost, and amortisation breakdown</p>
        </div>
        <div class="calc-form-card">
          <div class="calc-grid">
            <div class="calc-inputs">
              <div>
                <label class="calc-label" for="m-property">Property Price</label>
                <div class="calc-input-wrap">
                  <span class="calc-input-prefix" aria-hidden="true">&pound;</span>
                  <input id="m-property" type="text" inputmode="numeric" class="calc-input calc-input--pl" placeholder="300,000" autocomplete="off" />
                </div>
              </div>
              <div>
                <label class="calc-label" for="m-deposit">Deposit</label>
                <div class="calc-input-wrap">
                  <span class="calc-input-prefix" aria-hidden="true">&pound;</span>
                  <input id="m-deposit" type="text" inputmode="numeric" class="calc-input calc-input--pl" placeholder="30,000" autocomplete="off" />
                </div>
                <div id="m-deposit-hint" class="calc-deposit-hint" hidden>
                  <span id="m-dep-pct"></span>
                  <span id="m-dep-ltv"></span>
                </div>
              </div>
              <div>
                <label class="calc-label" for="m-term-range">Mortgage Term: <span id="m-term-display">25</span> years</label>
                <input id="m-term-range" type="range" min="5" max="40" step="1" class="calc-range" aria-label="Mortgage term in years" />
                <div class="calc-range-labels"><span>5 yrs</span><span>40 yrs</span></div>
              </div>
              <div>
                <label class="calc-label" for="m-rate">Interest Rate (%)</label>
                <input id="m-rate" type="number" step="0.1" class="calc-input" placeholder="4.5" />
              </div>
              <div>
                <label class="calc-label">Repayment Type</label>
                <div class="calc-toggle-pair" role="group" aria-label="Repayment type">
                  <button type="button" class="calc-toggle-btn is-active" data-mortgage-type="repayment" aria-pressed="true">Repayment</button>
                  <button type="button" class="calc-toggle-btn" data-mortgage-type="interest_only" aria-pressed="false">Interest Only</button>
                </div>
              </div>
              <button id="m-calc-btn" type="button" class="calc-submit-btn">Calculate</button>
            </div>

            <!-- Results -->
            <div id="mortgage-result" class="calc-results" hidden>
              <div class="calc-result-hero">
                <p class="calc-result-hero__label">Monthly Payment</p>
                <p class="calc-result-hero__value" id="m-monthly" aria-live="polite"></p>
                <p class="calc-result-hero__sub" id="m-repayment-sub">Capital and interest</p>
              </div>
              <div class="calc-result-grid">
                <div class="calc-metric-tile">
                  <p class="calc-metric-tile__label">Loan Amount</p>
                  <p class="calc-metric-tile__value" id="m-loan"></p>
                </div>
                <div class="calc-metric-tile">
                  <p class="calc-metric-tile__label">Loan to Value</p>
                  <p class="calc-metric-tile__value" id="m-ltv"></p>
                </div>
                <div class="calc-metric-tile">
                  <p class="calc-metric-tile__label">Total Repaid</p>
                  <p class="calc-metric-tile__value" id="m-total"></p>
                </div>
                <div class="calc-metric-tile">
                  <p class="calc-metric-tile__label">Total Interest</p>
                  <p class="calc-metric-tile__value calc-metric-tile__value--raspberry" id="m-interest"></p>
                </div>
              </div>
              <div id="m-ltv-warn" class="calc-alert calc-alert--info" hidden>
                <div class="calc-alert__body">
                  <svg class="calc-alert__icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                  <p class="calc-alert__text">Your Loan to Value is above 90%. Fewer mortgage deals are available at this level and rates tend to be higher. A larger deposit would improve your options.</p>
                </div>
              </div>
              <div id="m-io-warn" class="calc-alert calc-alert--info" hidden>
                <div class="calc-alert__body">
                  <svg class="calc-alert__icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                  <p class="calc-alert__text">With interest-only, you still owe the full <strong id="m-io-loan-amount"></strong> at the end of the term. You'll need a repayment strategy.</p>
                </div>
              </div>
              <div id="m-chart-box" class="calc-chart-box" hidden>
                <p class="calc-chart-label">Capital vs Interest Over Term</p>
                <canvas id="mortgage-chart" height="220" aria-label="Mortgage amortisation chart" role="img"></canvas>
              </div>
              <div>
                <a href="/register" class="calc-inline-cta">
                  <span class="calc-inline-cta__inner">
                    <span>Model your mortgage alongside your full financial picture in Fynla</span>
                    <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"/></svg>
                  </span>
                </a>
              </div>
            </div>
            <div id="mortgage-placeholder" class="calc-result-placeholder">
              <div class="calc-result-placeholder__text">
                <p>Enter your mortgage details to see your repayment breakdown</p>
              </div>
            </div>
          </div>
        </div>
      </section>

      <!-- ============================================================
           LOAN REPAYMENT CALCULATOR
      ============================================================ -->
      <section class="calc-card" data-calc="loan" aria-labelledby="l-heading">
        <div class="calc-header">
          <h2 class="calc-header__title" id="l-heading">Loan Repayment Calculator</h2>
          <p class="calc-header__sub">Calculate monthly payments and total interest on personal loans</p>
        </div>
        <div class="calc-form-card">
          <div class="calc-grid">
            <div class="calc-inputs">
              <div>
                <label class="calc-label" for="l-amount">Loan Amount</label>
                <div class="calc-input-wrap">
                  <span class="calc-input-prefix" aria-hidden="true">&pound;</span>
                  <input id="l-amount" type="text" inputmode="numeric" class="calc-input calc-input--pl" placeholder="10,000" autocomplete="off" />
                </div>
              </div>
              <div>
                <label class="calc-label" for="l-rate">Interest Rate (% APR)</label>
                <input id="l-rate" type="number" step="0.1" class="calc-input" placeholder="8.9" />
              </div>
              <div>
                <label class="calc-label" for="l-term">Loan Term (months)</label>
                <input id="l-term" type="number" class="calc-input" placeholder="36" />
              </div>
              <button id="l-calc-btn" type="button" class="calc-submit-btn">Calculate Loan</button>
            </div>
            <div id="loan-result" class="calc-result-box" hidden>
              <h3 class="calc-result-heading">Results</h3>
              <div class="calc-result-rows">
                <div class="calc-result-row"><span class="calc-result-row__label">Loan Amount</span><span class="calc-result-row__value" id="l-amount-val"></span></div>
                <div class="calc-result-row"><span class="calc-result-row__label">Interest Rate</span><span class="calc-result-row__value" id="l-rate-val"></span></div>
                <div class="calc-result-row"><span class="calc-result-row__label">Term</span><span class="calc-result-row__value" id="l-term-val"></span></div>
                <hr class="calc-result-divider" />
                <div class="calc-result-total calc-result-total--violet">
                  <span class="calc-result-total__label">Monthly Payment</span>
                  <span class="calc-result-total__value calc-result-total__value--violet" id="l-monthly" aria-live="polite"></span>
                </div>
                <div class="calc-result-row"><span class="calc-result-row__label">Total Interest</span><span class="calc-result-row__value calc-result-row__value--raspberry" id="l-interest"></span></div>
                <div class="calc-result-row"><span class="calc-result-row__label">Total Repayment</span><span class="calc-result-row__value" id="l-total"></span></div>
              </div>
            </div>
            <div id="loan-placeholder" class="calc-result-placeholder">
              <div class="calc-result-placeholder__text"><p>Enter your loan details and click Calculate to see results</p></div>
            </div>
          </div>
        </div>
      </section>

      <!-- ============================================================
           EMERGENCY FUND CALCULATOR
      ============================================================ -->
      <section class="calc-card" data-calc="emergency-fund" aria-labelledby="ef-heading">
        <div class="calc-header">
          <h2 class="calc-header__title" id="ef-heading">Emergency Fund Calculator</h2>
          <p class="calc-header__sub">Calculate how much you should save for emergencies (3&ndash;6 months of expenses)</p>
        </div>
        <div class="calc-form-card">
          <div class="calc-grid">
            <div class="calc-inputs">
              <div>
                <label class="calc-label" for="ef-expenses">Monthly Expenses</label>
                <div class="calc-input-wrap">
                  <span class="calc-input-prefix" aria-hidden="true">&pound;</span>
                  <input id="ef-expenses" type="text" inputmode="numeric" class="calc-input calc-input--pl" placeholder="2,500" autocomplete="off" />
                </div>
              </div>
              <div>
                <label class="calc-label" for="ef-savings">Current Savings</label>
                <div class="calc-input-wrap">
                  <span class="calc-input-prefix" aria-hidden="true">&pound;</span>
                  <input id="ef-savings" type="text" inputmode="numeric" class="calc-input calc-input--pl" placeholder="5,000" autocomplete="off" />
                </div>
              </div>
              <div>
                <label class="calc-label" for="ef-target-months">Target Months</label>
                <select id="ef-target-months" class="calc-select">
                  <option value="3">3 months (minimum recommended)</option>
                  <option value="6" selected>6 months (standard recommendation)</option>
                  <option value="9">9 months (conservative)</option>
                  <option value="12">12 months (very conservative)</option>
                </select>
              </div>
              <button id="ef-calc-btn" type="button" class="calc-submit-btn">Calculate Fund</button>
            </div>
            <div id="ef-result" class="calc-result-box" hidden>
              <h3 class="calc-result-heading">Results</h3>
              <div class="calc-result-rows">
                <div class="calc-result-row"><span class="calc-result-row__label">Monthly Expenses</span><span class="calc-result-row__value" id="ef-expenses-val"></span></div>
                <div class="calc-result-row"><span class="calc-result-row__label">Target Coverage</span><span class="calc-result-row__value" id="ef-target-months-val"></span></div>
                <hr class="calc-result-divider" />
                <div class="calc-result-total calc-result-total--spring">
                  <span class="calc-result-total__label">Target Fund</span>
                  <span class="calc-result-total__value" id="ef-target" aria-live="polite"></span>
                </div>
                <div class="calc-result-row"><span class="calc-result-row__label">Current Savings</span><span class="calc-result-row__value" id="ef-savings-val"></span></div>
                <div class="calc-result-row"><span class="calc-result-row__label">Shortfall</span><span id="ef-shortfall"></span></div>
                <div class="calc-result-row"><span class="calc-result-row__label">Current Runway</span><span class="calc-result-row__value" id="ef-runway"></span></div>
              </div>
              <div id="ef-status" class="calc-status-badge">
                <p class="calc-status-badge__title"></p>
                <p class="calc-status-badge__msg"></p>
              </div>
            </div>
            <div id="ef-placeholder" class="calc-result-placeholder">
              <div class="calc-result-placeholder__text"><p>Enter your expenses and savings to see your emergency fund status</p></div>
            </div>
          </div>
        </div>
      </section>

      <!-- ============================================================
           PENSION GROWTH CALCULATOR
      ============================================================ -->
      <section class="calc-card" data-calc="pension" aria-labelledby="p-heading">
        <div class="calc-header">
          <span class="calc-header__badge">Planning Your Future</span>
          <h2 class="calc-header__title" id="p-heading">Pension Growth Calculator</h2>
          <p class="calc-header__sub">Project your pension pot at retirement with regular contributions</p>
        </div>
        <div class="calc-form-card">
          <div class="calc-grid">
            <div class="calc-inputs">
              <div>
                <label class="calc-label" for="p-current">Current Pension Value</label>
                <div class="calc-input-wrap">
                  <span class="calc-input-prefix" aria-hidden="true">&pound;</span>
                  <input id="p-current" type="text" inputmode="numeric" class="calc-input calc-input--pl" placeholder="50,000" autocomplete="off" />
                </div>
              </div>
              <div>
                <label class="calc-label" for="p-contribution">Monthly Contribution</label>
                <div class="calc-input-wrap">
                  <span class="calc-input-prefix" aria-hidden="true">&pound;</span>
                  <input id="p-contribution" type="text" inputmode="numeric" class="calc-input calc-input--pl" placeholder="500" autocomplete="off" />
                </div>
                <p class="calc-input-hint">Including employer contributions and tax relief</p>
              </div>
              <div class="calc-result-grid">
                <div>
                  <label class="calc-label" for="p-age">Current Age</label>
                  <input id="p-age" type="number" class="calc-input" placeholder="35" />
                </div>
                <div>
                  <label class="calc-label" for="p-retire-age">Retirement Age</label>
                  <input id="p-retire-age" type="number" class="calc-input" placeholder="65" />
                </div>
              </div>
              <div>
                <label class="calc-label" for="p-rate">Expected Growth Rate (%)</label>
                <input id="p-rate" type="number" step="0.1" class="calc-input" placeholder="5.0" />
                <p class="calc-input-hint">Typical range: 4&ndash;7% for balanced portfolios</p>
              </div>
              <button id="p-calc-btn" type="button" class="calc-submit-btn">Calculate Projection</button>
            </div>
            <div id="pension-result" class="calc-result-box" hidden>
              <h3 class="calc-result-heading">Projection</h3>
              <div class="calc-result-rows">
                <div class="calc-result-row"><span class="calc-result-row__label">Years to Retirement</span><span class="calc-result-row__value" id="p-years"></span></div>
                <div class="calc-result-row"><span class="calc-result-row__label">Current Value</span><span class="calc-result-row__value" id="p-current-val"></span></div>
                <div class="calc-result-row"><span class="calc-result-row__label">Total Contributions</span><span class="calc-result-row__value" id="p-contributions"></span></div>
                <hr class="calc-result-divider" />
                <div class="calc-result-total calc-result-total--pink">
                  <span class="calc-result-total__label" id="p-projected-label">Projected Pot at 65</span>
                  <span class="calc-result-total__value calc-result-total__value--violet" id="p-projected-hero" aria-live="polite"></span>
                </div>
                <div class="calc-result-row"><span class="calc-result-row__label">Investment Growth</span><span class="calc-result-row__value calc-result-row__value--spring" id="p-growth"></span></div>
              </div>
              <div class="calc-pension-detail">
                <p class="calc-pension-detail__title">At 4% withdrawal rate:</p>
                <div class="calc-pension-detail__grid">
                  <div>
                    <p class="calc-pension-detail__label">Annual Income</p>
                    <p class="calc-pension-detail__value" id="p-annual"></p>
                  </div>
                  <div>
                    <p class="calc-pension-detail__label">Monthly Income</p>
                    <p class="calc-pension-detail__value" id="p-monthly"></p>
                  </div>
                </div>
              </div>
            </div>
            <div id="pension-placeholder" class="calc-result-placeholder">
              <div class="calc-result-placeholder__text"><p>Enter your pension details to see your retirement projection</p></div>
            </div>
          </div>
        </div>
      </section>

      <!-- ============================================================
           STUDENT LOAN REPAYMENT CALCULATOR
      ============================================================ -->
      <section class="calc-card" data-calc="student-loan" aria-labelledby="sl-heading">
        <div class="calc-header">
          <span class="calc-header__badge">Starting Out</span>
          <h2 class="calc-header__title" id="sl-heading">Student Loan Repayment Calculator</h2>
          <p class="calc-header__sub">Estimate your monthly repayments, total cost, and when your loan will be cleared</p>
        </div>
        <div class="calc-form-card">
          <div class="calc-grid">
            <div class="calc-inputs">
              <div>
                <label class="calc-label" for="sl-plan">Loan Plan</label>
                <select id="sl-plan" class="calc-select">
                  <option value="plan1">Plan 1</option>
                  <option value="plan2" selected>Plan 2</option>
                  <option value="plan4">Plan 4 (Scotland)</option>
                  <option value="plan5">Plan 5</option>
                </select>
                <div id="sl-plan-info" class="calc-plan-info"></div>
              </div>
              <div>
                <label class="calc-label" for="sl-balance">Current Loan Balance</label>
                <div class="calc-input-wrap">
                  <span class="calc-input-prefix" aria-hidden="true">&pound;</span>
                  <input id="sl-balance" type="text" inputmode="numeric" class="calc-input calc-input--pl" placeholder="45,000" autocomplete="off" />
                </div>
              </div>
              <div>
                <label class="calc-label" for="sl-salary">Current Annual Salary</label>
                <div class="calc-input-wrap">
                  <span class="calc-input-prefix" aria-hidden="true">&pound;</span>
                  <input id="sl-salary" type="text" inputmode="numeric" class="calc-input calc-input--pl" placeholder="30,000" autocomplete="off" />
                </div>
              </div>
              <div>
                <label class="calc-label" for="sl-growth-range">Expected Salary Growth: <span id="sl-growth-display">3</span>% per year</label>
                <input id="sl-growth-range" type="range" min="0" max="10" step="0.5" class="calc-range calc-range--spring" aria-label="Expected salary growth percentage per year" />
                <div class="calc-range-labels"><span>0%</span><span>10%</span></div>
              </div>
              <button id="sl-calc-btn" type="button" class="calc-submit-btn">Calculate Repayments</button>
            </div>
            <div id="sl-result" class="calc-results" hidden>
              <div class="calc-result-hero">
                <p class="calc-result-hero__label">Monthly Repayment</p>
                <p class="calc-result-hero__value" id="sl-monthly" aria-live="polite"></p>
                <p class="calc-result-hero__sub">at your current salary</p>
              </div>
              <div class="calc-result-grid">
                <div class="calc-metric-tile"><p class="calc-metric-tile__label">Time to Repay</p><p class="calc-metric-tile__value" id="sl-years"></p></div>
                <div class="calc-metric-tile"><p class="calc-metric-tile__label">Total Repaid</p><p class="calc-metric-tile__value" id="sl-repaid"></p></div>
                <div class="calc-metric-tile"><p class="calc-metric-tile__label">Total Interest</p><p class="calc-metric-tile__value" id="sl-interest"></p></div>
                <div class="calc-metric-tile"><p class="calc-metric-tile__label">Write-off Date</p><p class="calc-metric-tile__value" id="sl-writeoff"></p></div>
              </div>
              <div id="sl-written-off-warn" class="calc-alert calc-alert--info" hidden>
                <div class="calc-alert__body">
                  <svg class="calc-alert__icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                  <div>
                    <p class="calc-alert__text"><strong>Loan likely to be written off</strong></p>
                    <p class="calc-alert__text calc-alert__text--neutral">Based on your salary and growth projections, your loan balance of <span id="sl-remaining"></span> would be written off after <span id="sl-writeoff-years"></span> years. You would repay <span id="sl-total-repaid-warn"></span> of the original <span id="sl-balance-orig"></span> balance.</p>
                  </div>
                </div>
              </div>
              <div id="sl-fully-repaid" class="calc-alert calc-alert--spring" hidden>
                <div class="calc-alert__body">
                  <svg class="calc-alert__icon calc-alert__icon--spring" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                  <div>
                    <p class="calc-alert__text calc-alert__text--spring"><strong>Loan fully repaid</strong></p>
                    <p class="calc-alert__text calc-alert__text--neutral">You would clear your loan in <span id="sl-repaid-years"></span> before the <span id="sl-writeoff-period"></span>-year write-off period.</p>
                  </div>
                </div>
              </div>
              <div><a href="/register" class="calc-inline-cta"><span class="calc-inline-cta__inner"><span>Factor your student loan into your full financial plan in Fynla</span><svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"/></svg></span></a></div>
            </div>
            <div id="sl-placeholder" class="calc-result-placeholder">
              <div class="calc-result-placeholder__text"><p>Enter your loan details to see your repayment projection</p></div>
            </div>
          </div>
        </div>
      </section>

      <!-- ============================================================
           SAVINGS GOAL CALCULATOR
      ============================================================ -->
      <section class="calc-card" data-calc="savings-goal" aria-labelledby="sg-heading">
        <div class="calc-header">
          <span class="calc-header__badge">Starting Out</span>
          <h2 class="calc-header__title" id="sg-heading">Savings Goal Calculator</h2>
          <p class="calc-header__sub">See how long it takes to reach your savings target with compound interest</p>
        </div>
        <div class="calc-form-card">
          <div class="calc-grid">
            <div class="calc-inputs">
              <div>
                <label class="calc-label" for="sg-target">Target Amount</label>
                <div class="calc-input-wrap"><span class="calc-input-prefix" aria-hidden="true">&pound;</span><input id="sg-target" type="text" inputmode="numeric" class="calc-input calc-input--pl" placeholder="10,000" autocomplete="off" /></div>
              </div>
              <div>
                <label class="calc-label" for="sg-current">Current Savings</label>
                <div class="calc-input-wrap"><span class="calc-input-prefix" aria-hidden="true">&pound;</span><input id="sg-current" type="text" inputmode="numeric" class="calc-input calc-input--pl" placeholder="1,000" autocomplete="off" /></div>
              </div>
              <div>
                <label class="calc-label" for="sg-monthly">Monthly Contribution</label>
                <div class="calc-input-wrap"><span class="calc-input-prefix" aria-hidden="true">&pound;</span><input id="sg-monthly" type="text" inputmode="numeric" class="calc-input calc-input--pl" placeholder="200" autocomplete="off" /></div>
              </div>
              <div>
                <label class="calc-label" for="sg-rate-range">Interest Rate / Expected Return: <span id="sg-rate-display">4</span>% per year</label>
                <input id="sg-rate-range" type="range" min="0" max="8" step="0.25" class="calc-range calc-range--raspberry" aria-label="Annual interest rate percentage" />
                <div class="calc-range-labels"><span>0%</span><span>8%</span></div>
              </div>
              <button id="sg-calc-btn" type="button" class="calc-submit-btn">Calculate</button>
            </div>
            <div id="sg-result" class="calc-results" hidden>
              <div class="calc-result-hero">
                <p class="calc-result-hero__label calc-result-hero__label--raspberry">Time to Reach Goal</p>
                <p class="calc-result-hero__value" id="sg-time" aria-live="polite"></p>
                <p class="calc-result-hero__sub calc-result-hero__sub--raspberry" id="sg-target-label"></p>
              </div>
              <div class="calc-result-grid">
                <div class="calc-metric-tile"><p class="calc-metric-tile__label">Total Contributions</p><p class="calc-metric-tile__value" id="sg-contributions"></p></div>
                <div class="calc-metric-tile"><p class="calc-metric-tile__label">Interest Earned</p><p class="calc-metric-tile__value calc-metric-tile__value--spring" id="sg-interest"></p></div>
              </div>
              <div class="calc-chart-box">
                <p class="calc-chart-label">Savings Growth Over Time</p>
                <canvas id="savings-goal-chart" height="200" aria-label="Savings growth chart" role="img"></canvas>
              </div>
              <div><a href="/register" class="calc-inline-cta"><span class="calc-inline-cta__inner"><span>Set and track your savings goals in Fynla</span><svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"/></svg></span></a></div>
            </div>
            <div id="sg-placeholder" class="calc-result-placeholder">
              <div class="calc-result-placeholder__text"><p>Enter your savings details to see your projection</p></div>
            </div>
          </div>
        </div>
      </section>

      <!-- ============================================================
           MORTGAGE AFFORDABILITY CALCULATOR
      ============================================================ -->
      <section class="calc-card" data-calc="mortgage-afford" aria-labelledby="ma-heading">
        <div class="calc-header">
          <span class="calc-header__badge">Building Foundations</span>
          <h2 class="calc-header__title" id="ma-heading">Mortgage Affordability Calculator</h2>
          <p class="calc-header__sub">Estimate how much you could borrow based on your income</p>
        </div>
        <div class="calc-form-card">
          <div class="calc-grid">
            <div class="calc-inputs">
              <div>
                <label class="calc-label" for="ma-salary1">Annual Salary (Applicant 1)</label>
                <div class="calc-input-wrap"><span class="calc-input-prefix" aria-hidden="true">&pound;</span><input id="ma-salary1" type="text" inputmode="numeric" class="calc-input calc-input--pl" placeholder="45,000" autocomplete="off" /></div>
              </div>
              <div>
                <label class="calc-label" for="ma-salary2">Annual Salary (Applicant 2, optional)</label>
                <div class="calc-input-wrap"><span class="calc-input-prefix" aria-hidden="true">&pound;</span><input id="ma-salary2" type="text" inputmode="numeric" class="calc-input calc-input--pl" placeholder="0" autocomplete="off" /></div>
              </div>
              <div>
                <label class="calc-label" for="ma-debts">Other Monthly Debt Commitments</label>
                <div class="calc-input-wrap"><span class="calc-input-prefix" aria-hidden="true">&pound;</span><input id="ma-debts" type="text" inputmode="numeric" class="calc-input calc-input--pl" placeholder="200" autocomplete="off" /></div>
                <p class="calc-input-hint">Loans, car finance, credit card minimums, etc.</p>
              </div>
              <div>
                <label class="calc-label" for="ma-deposit">Deposit Available</label>
                <div class="calc-input-wrap"><span class="calc-input-prefix" aria-hidden="true">&pound;</span><input id="ma-deposit" type="text" inputmode="numeric" class="calc-input calc-input--pl" placeholder="25,000" autocomplete="off" /></div>
              </div>
              <button id="ma-calc-btn" type="button" class="calc-submit-btn">Calculate Affordability</button>
            </div>
            <div id="ma-result" class="calc-results" hidden>
              <div class="calc-result-hero">
                <p class="calc-result-hero__label">Estimated Maximum Borrowing</p>
                <div class="calc-borrow-flex">
                  <div>
                    <p class="calc-borrow-primary" id="ma-max4x" aria-live="polite"></p>
                    <p class="calc-borrow-label">at 4x income</p>
                  </div>
                  <div>
                    <p class="calc-borrow-secondary" id="ma-max45x"></p>
                    <p class="calc-borrow-label">at 4.5x income</p>
                  </div>
                </div>
              </div>
              <div>
                <p class="calc-chart-label">Maximum Property Price by Deposit</p>
                <div class="calc-result-grid calc-result-grid--3">
                  <div class="calc-metric-tile" style="text-align:center">
                    <p class="calc-metric-tile__label">10% deposit</p>
                    <p class="calc-metric-tile__value" id="ma-p10"></p>
                  </div>
                  <div class="calc-metric-tile" style="text-align:center">
                    <p class="calc-metric-tile__label">15% deposit</p>
                    <p class="calc-metric-tile__value" id="ma-p15"></p>
                  </div>
                  <div class="calc-metric-tile" style="text-align:center">
                    <p class="calc-metric-tile__label">20% deposit</p>
                    <p class="calc-metric-tile__value" id="ma-p20"></p>
                  </div>
                </div>
              </div>
              <div class="calc-alert calc-alert--info">
                <p class="calc-alert__text">Lenders assess affordability differently &mdash; this is a guide only. Your actual borrowing will depend on credit history, outgoings, and lender criteria.</p>
              </div>
              <div><a href="/register" class="calc-inline-cta"><span class="calc-inline-cta__inner"><span>See how a mortgage fits into your full financial plan in Fynla</span><svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"/></svg></span></a></div>
            </div>
            <div id="ma-placeholder" class="calc-result-placeholder">
              <div class="calc-result-placeholder__text"><p>Enter your income details to see what you could borrow</p></div>
            </div>
          </div>
        </div>
      </section>

      <!-- ============================================================
           STAMP DUTY CALCULATOR
      ============================================================ -->
      <section class="calc-card" data-calc="stamp-duty" aria-labelledby="sd-heading">
        <div class="calc-header">
          <span class="calc-header__badge">Building Foundations</span>
          <h2 class="calc-header__title" id="sd-heading">Stamp Duty Calculator</h2>
          <p class="calc-header__sub">Calculate Stamp Duty Land Tax, Land and Buildings Transaction Tax, or Land Transaction Tax</p>
        </div>
        <div class="calc-form-card">
          <div class="calc-grid">
            <div class="calc-inputs">
              <div>
                <label class="calc-label" for="sd-price">Property Price</label>
                <div class="calc-input-wrap"><span class="calc-input-prefix" aria-hidden="true">&pound;</span><input id="sd-price" type="text" inputmode="numeric" class="calc-input calc-input--pl" placeholder="300,000" autocomplete="off" /></div>
              </div>
              <div>
                <label class="calc-label" for="sd-buyer">Buyer Type</label>
                <select id="sd-buyer" class="calc-select">
                  <option value="first-time">First-time buyer</option>
                  <option value="home-mover" selected>Home mover</option>
                  <option value="additional">Additional property</option>
                  <option value="non-uk">Non-UK resident</option>
                </select>
              </div>
              <div>
                <label class="calc-label" for="sd-country">Country</label>
                <select id="sd-country" class="calc-select">
                  <option value="england" selected>England &amp; Northern Ireland (SDLT)</option>
                  <option value="scotland">Scotland (LBTT)</option>
                  <option value="wales">Wales (LTT)</option>
                </select>
              </div>
              <button id="sd-calc-btn" type="button" class="calc-submit-btn">Calculate Stamp Duty</button>
            </div>
            <div id="sd-result" class="calc-results" hidden>
              <div class="calc-result-hero">
                <p class="calc-result-hero__label calc-result-hero__label--horizon" id="sd-tax-name"></p>
                <p class="calc-result-hero__value" id="sd-total" aria-live="polite"></p>
                <p class="calc-result-hero__sub" id="sd-rate"></p>
              </div>
              <div class="calc-chart-box">
                <p class="calc-chart-label">Breakdown by Band</p>
                <div id="sd-bands" class="calc-band-list"></div>
              </div>
              <div id="sd-saving" class="calc-alert calc-alert--spring" hidden>
                <div class="calc-alert__body">
                  <svg class="calc-alert__icon calc-alert__icon--spring" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                  <p class="calc-alert__text calc-alert__text--spring">First-time buyer relief saves you <strong id="sd-saving-amount"></strong> compared to standard rates.</p>
                </div>
              </div>
              <div id="sd-additional-warn" class="calc-alert calc-alert--info" hidden>
                <p class="calc-alert__text">Additional property surcharge of 5% has been applied to all bands.</p>
              </div>
              <div id="sd-nonuk-warn" class="calc-alert calc-alert--info" hidden>
                <p class="calc-alert__text">Non-UK resident surcharge of 2% has been applied to all bands.</p>
              </div>
              <div><a href="/register" class="calc-inline-cta"><span class="calc-inline-cta__inner"><span>Factor stamp duty into your home buying plan in Fynla</span><svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"/></svg></span></a></div>
            </div>
            <div id="sd-placeholder" class="calc-result-placeholder">
              <div class="calc-result-placeholder__text"><p>Enter a property price to see the stamp duty breakdown</p></div>
            </div>
          </div>
        </div>
      </section>

      <!-- ============================================================
           COMPOUND INTEREST CALCULATOR
      ============================================================ -->
      <section class="calc-card" data-calc="compound-interest" aria-labelledby="ci-heading">
        <div class="calc-header">
          <span class="calc-header__badge">Building Foundations</span>
          <h2 class="calc-header__title" id="ci-heading">Compound Interest Calculator</h2>
          <p class="calc-header__sub">See how your money grows over time with the power of compound interest</p>
        </div>
        <div class="calc-form-card">
          <div class="calc-grid">
            <div class="calc-inputs">
              <div>
                <label class="calc-label" for="ci-lump">Initial Lump Sum</label>
                <div class="calc-input-wrap"><span class="calc-input-prefix" aria-hidden="true">&pound;</span><input id="ci-lump" type="text" inputmode="numeric" class="calc-input calc-input--pl" placeholder="5,000" autocomplete="off" /></div>
              </div>
              <div>
                <label class="calc-label" for="ci-monthly">Monthly Contribution</label>
                <div class="calc-input-wrap"><span class="calc-input-prefix" aria-hidden="true">&pound;</span><input id="ci-monthly" type="text" inputmode="numeric" class="calc-input calc-input--pl" placeholder="200" autocomplete="off" /></div>
              </div>
              <div>
                <label class="calc-label" for="ci-rate-range">Annual Return: <span id="ci-rate-display">6</span>%</label>
                <input id="ci-rate-range" type="range" min="0" max="12" step="0.5" class="calc-range calc-range--spring" aria-label="Annual return percentage" />
                <div class="calc-range-labels"><span>0%</span><span>12%</span></div>
              </div>
              <div>
                <label class="calc-label" for="ci-term-range">Investment Term: <span id="ci-term-display">20</span> years</label>
                <input id="ci-term-range" type="range" min="1" max="40" step="1" class="calc-range calc-range--spring" aria-label="Investment term in years" />
                <div class="calc-range-labels"><span>1 yr</span><span>40 yrs</span></div>
              </div>
              <div>
                <label class="calc-label">Compound Frequency</label>
                <div class="calc-toggle-pair" role="group" aria-label="Compounding frequency">
                  <button type="button" class="calc-toggle-btn is-active is-active--spring" data-compound-freq="monthly" aria-pressed="true">Monthly</button>
                  <button type="button" class="calc-toggle-btn" data-compound-freq="annually" aria-pressed="false">Annually</button>
                </div>
              </div>
              <button id="ci-calc-btn" type="button" class="calc-submit-btn">Calculate Growth</button>
            </div>
            <div id="ci-result" class="calc-results" hidden>
              <div class="calc-result-hero">
                <p class="calc-result-hero__label">Final Value</p>
                <p class="calc-result-hero__value" id="ci-final" aria-live="polite"></p>
                <p class="calc-result-hero__sub" id="ci-term-label"></p>
              </div>
              <div class="calc-result-grid">
                <div class="calc-metric-tile"><p class="calc-metric-tile__label">Total Contributions</p><p class="calc-metric-tile__value" id="ci-contributions"></p></div>
                <div class="calc-metric-tile"><p class="calc-metric-tile__label">Growth Earned</p><p class="calc-metric-tile__value calc-metric-tile__value--spring" id="ci-growth"></p></div>
              </div>
              <div class="calc-chart-box">
                <p class="calc-chart-label">Growth Over Time</p>
                <canvas id="compound-chart" height="220" aria-label="Compound interest growth chart" role="img"></canvas>
              </div>
              <div><a href="/register" class="calc-inline-cta"><span class="calc-inline-cta__inner"><span>See your investments in the context of your full financial picture in Fynla</span><svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"/></svg></span></a></div>
            </div>
            <div id="ci-placeholder" class="calc-result-placeholder">
              <div class="calc-result-placeholder__text"><p>Enter your investment details to see projected growth</p></div>
            </div>
          </div>
        </div>
      </section>

      <!-- ============================================================
           PERSONAL LOAN CALCULATOR
      ============================================================ -->
      <section class="calc-card" data-calc="personal-loan" aria-labelledby="pl-heading">
        <div class="calc-header">
          <span class="calc-header__badge">Building Foundations</span>
          <h2 class="calc-header__title" id="pl-heading">Personal Loan Calculator</h2>
          <p class="calc-header__sub">Calculate your monthly repayments and total cost of borrowing</p>
        </div>
        <div class="calc-form-card">
          <div class="calc-grid">
            <div class="calc-inputs">
              <div>
                <label class="calc-label" for="pl-amount">Loan Amount</label>
                <div class="calc-input-wrap"><span class="calc-input-prefix" aria-hidden="true">&pound;</span><input id="pl-amount" type="text" inputmode="numeric" class="calc-input calc-input--pl" placeholder="10,000" autocomplete="off" /></div>
              </div>
              <div>
                <label class="calc-label" for="pl-rate">Annual Interest Rate (%)</label>
                <input id="pl-rate" type="number" step="0.1" class="calc-input" placeholder="6.9" />
              </div>
              <div>
                <label class="calc-label" for="pl-term-range">Loan Term: <span id="pl-term-display">5</span> years</label>
                <input id="pl-term-range" type="range" min="1" max="10" step="1" class="calc-range" aria-label="Loan term in years" />
                <div class="calc-range-labels"><span>1 yr</span><span>10 yrs</span></div>
              </div>
              <button id="pl-calc-btn" type="button" class="calc-submit-btn">Calculate Repayments</button>
            </div>
            <div id="pl-result" class="calc-results" hidden>
              <div class="calc-result-hero">
                <p class="calc-result-hero__label">Monthly Repayment</p>
                <p class="calc-result-hero__value" id="pl-monthly" aria-live="polite"></p>
                <p class="calc-result-hero__sub" id="pl-term-label"></p>
              </div>
              <div class="calc-result-grid">
                <div class="calc-metric-tile"><p class="calc-metric-tile__label">Total Repaid</p><p class="calc-metric-tile__value" id="pl-total"></p></div>
                <div class="calc-metric-tile"><p class="calc-metric-tile__label">Total Interest</p><p class="calc-metric-tile__value calc-metric-tile__value--raspberry" id="pl-interest"></p></div>
              </div>
              <div class="calc-alert calc-alert--info">
                <p class="calc-alert__text">The APR (Annual Percentage Rate) may differ from the interest rate as it includes any fees. Always check the APR when comparing loan offers.</p>
              </div>
              <div><a href="/register" class="calc-inline-cta"><span class="calc-inline-cta__inner"><span>See how debt fits into your financial picture in Fynla</span><svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"/></svg></span></a></div>
            </div>
            <div id="pl-placeholder" class="calc-result-placeholder">
              <div class="calc-result-placeholder__text"><p>Enter your loan details to see your repayment breakdown</p></div>
            </div>
          </div>
        </div>
      </section>

      <!-- ============================================================
           PENSION WITHDRAWAL TAX CALCULATOR
      ============================================================ -->
      <section class="calc-card" data-calc="pension-withdrawal" aria-labelledby="pw-heading">
        <div class="calc-header">
          <span class="calc-header__badge">Enjoying Your Wealth</span>
          <h2 class="calc-header__title" id="pw-heading">Pension Withdrawal Tax Calculator</h2>
          <p class="calc-header__sub">See how much tax you'll pay when withdrawing from your pension</p>
        </div>
        <div class="calc-form-card">
          <div class="calc-grid">
            <div class="calc-inputs">
              <div>
                <label class="calc-label" for="pw-pot">Total Pension Pot</label>
                <div class="calc-input-wrap"><span class="calc-input-prefix" aria-hidden="true">&pound;</span><input id="pw-pot" type="text" inputmode="numeric" class="calc-input calc-input--pl" placeholder="250,000" autocomplete="off" /></div>
              </div>
              <div>
                <label class="calc-label" for="pw-withdrawal">Withdrawal Amount</label>
                <div class="calc-input-wrap"><span class="calc-input-prefix" aria-hidden="true">&pound;</span><input id="pw-withdrawal" type="text" inputmode="numeric" class="calc-input calc-input--pl" placeholder="50,000" autocomplete="off" /></div>
              </div>
              <div>
                <label class="calc-label">Withdrawal Type</label>
                <div class="calc-toggle-pair" role="group" aria-label="Withdrawal type">
                  <button type="button" class="calc-toggle-btn is-active is-active--horizon" data-pw-type="lump" aria-pressed="true">Lump Sum</button>
                  <button type="button" class="calc-toggle-btn" data-pw-type="annual" aria-pressed="false">Annual Income</button>
                </div>
              </div>
              <div>
                <label class="calc-label" for="pw-other">Other Annual Income</label>
                <div class="calc-input-wrap"><span class="calc-input-prefix" aria-hidden="true">&pound;</span><input id="pw-other" type="text" inputmode="numeric" class="calc-input calc-input--pl" placeholder="12,570" autocomplete="off" /></div>
                <p class="calc-input-hint">State pension, employment, rental income, etc.</p>
              </div>
              <div>
                <label class="calc-label">Tax-Free Cash Already Taken?</label>
                <div class="calc-toggle-pair" role="group" aria-label="Has tax-free cash been taken">
                  <button type="button" class="calc-toggle-btn is-active is-active--horizon" data-pw-taxfree="false" aria-pressed="true">No</button>
                  <button type="button" class="calc-toggle-btn" data-pw-taxfree="true" aria-pressed="false">Yes</button>
                </div>
              </div>
              <button id="pw-calc-btn" type="button" class="calc-submit-btn">Calculate Tax</button>
            </div>
            <div id="pw-result" class="calc-results" hidden>
              <div class="calc-result-hero">
                <p class="calc-result-hero__label calc-result-hero__label--horizon">Net Amount Received</p>
                <p class="calc-result-hero__value" id="pw-net" aria-live="polite"></p>
                <p class="calc-result-hero__sub" id="pw-withdrawal-label"></p>
              </div>
              <div class="calc-result-grid">
                <div class="calc-metric-tile"><p class="calc-metric-tile__label">Tax-Free Portion</p><p class="calc-metric-tile__value calc-metric-tile__value--spring" id="pw-taxfree"></p></div>
                <div class="calc-metric-tile"><p class="calc-metric-tile__label">Taxable Portion</p><p class="calc-metric-tile__value" id="pw-taxable"></p></div>
                <div class="calc-metric-tile"><p class="calc-metric-tile__label">Income Tax Due</p><p class="calc-metric-tile__value calc-metric-tile__value--raspberry" id="pw-taxdue"></p></div>
                <div class="calc-metric-tile"><p class="calc-metric-tile__label">Effective Tax Rate</p><p class="calc-metric-tile__value" id="pw-rate"></p></div>
              </div>
              <div id="pw-higher-warn" class="calc-alert calc-alert--info" hidden>
                <div class="calc-alert__body">
                  <svg class="calc-alert__icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                  <p class="calc-alert__text">Taking large lump sums can push you into higher tax bands. Consider spreading withdrawals across tax years to reduce the overall tax burden.</p>
                </div>
              </div>
              <div><a href="/register" class="calc-inline-cta"><span class="calc-inline-cta__inner"><span>Plan your pension withdrawals in Fynla</span><svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"/></svg></span></a></div>
            </div>
            <div id="pw-placeholder" class="calc-result-placeholder">
              <div class="calc-result-placeholder__text"><p>Enter your pension withdrawal details to see the tax impact</p></div>
            </div>
          </div>
        </div>
      </section>

      <!-- ============================================================
           PENSION TAX RELIEF CALCULATOR
      ============================================================ -->
      <section class="calc-card" data-calc="pension-relief" aria-labelledby="pr-heading">
        <div class="calc-header">
          <span class="calc-header__badge">Planning Your Future</span>
          <h2 class="calc-header__title" id="pr-heading">Pension Tax Relief Calculator</h2>
          <p class="calc-header__sub">See how much tax relief you get on your pension contributions</p>
        </div>
        <div class="calc-form-card">
          <div class="calc-grid">
            <div class="calc-inputs">
              <div>
                <label class="calc-label" for="pr-contribution">Annual Pension Contribution</label>
                <div class="calc-input-wrap"><span class="calc-input-prefix" aria-hidden="true">&pound;</span><input id="pr-contribution" type="text" inputmode="numeric" class="calc-input calc-input--pl" placeholder="5,000" autocomplete="off" /></div>
              </div>
              <div>
                <label class="calc-label" for="pr-band">Tax Band</label>
                <select id="pr-band" class="calc-select">
                  <option value="basic">Basic rate (20%)</option>
                  <option value="higher">Higher rate (40%)</option>
                  <option value="additional">Additional rate (45%)</option>
                </select>
              </div>
              <div>
                <label class="calc-label">Pension Type</label>
                <div class="calc-toggle-pair" role="group" aria-label="Pension type">
                  <button type="button" class="calc-toggle-btn is-active" data-pr-type="relief-at-source" aria-pressed="true">Relief at Source</button>
                  <button type="button" class="calc-toggle-btn" data-pr-type="net-pay" aria-pressed="false">Net Pay</button>
                </div>
                <p id="pr-type-info" class="calc-pension-type-info"></p>
              </div>
              <button id="pr-calc-btn" type="button" class="calc-submit-btn">Calculate Relief</button>
            </div>
            <div id="pr-result" class="calc-results" hidden>
              <div class="calc-result-hero">
                <p class="calc-result-hero__label">Total Pension Contribution (Gross)</p>
                <p class="calc-result-hero__value" id="pr-gross" aria-live="polite"></p>
                <p class="calc-result-hero__sub" id="pr-cost-label"></p>
              </div>
              <div class="calc-result-grid">
                <div class="calc-metric-tile"><p class="calc-metric-tile__label">Your Contribution (Net)</p><p class="calc-metric-tile__value" id="pr-net"></p></div>
                <div class="calc-metric-tile"><p class="calc-metric-tile__label">Government Top-Up (20%)</p><p class="calc-metric-tile__value calc-metric-tile__value--spring" id="pr-basic-relief"></p></div>
              </div>
              <div id="pr-additional-relief" class="calc-relief-box" hidden>
                <div class="calc-alert__body">
                  <svg class="calc-alert__icon calc-alert__icon--spring" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                  <div>
                    <p class="calc-alert__text calc-alert__text--spring"><strong>Additional relief: <span id="pr-additional-amount"></span></strong></p>
                    <p id="pr-ras-msg" class="calc-alert__text calc-alert__text--neutral"></p>
                    <p id="pr-np-msg" class="calc-alert__text calc-alert__text--neutral" hidden>With net pay, your full relief is applied automatically through your payslip. No self-assessment claim needed.</p>
                  </div>
                </div>
              </div>
              <div class="calc-result-box">
                <div class="calc-pw-summary">
                  <span class="calc-result-row__label">Effective cost to you</span>
                  <span class="calc-result-row__value" id="pr-cost"></span>
                </div>
                <div class="calc-pw-summary">
                  <span class="calc-result-row__label">Total relief received</span>
                  <span class="calc-result-row__value calc-result-row__value--spring" id="pr-total-relief"></span>
                </div>
              </div>
              <div><a href="/register" class="calc-inline-cta"><span class="calc-inline-cta__inner"><span>Track your pension contributions in Fynla</span><svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"/></svg></span></a></div>
            </div>
            <div id="pr-placeholder" class="calc-result-placeholder">
              <div class="calc-result-placeholder__text"><p>Enter your contribution details to see your tax relief</p></div>
            </div>
          </div>
        </div>
      </section>

    </div><!-- /calc-panel -->

  </div><!-- /calc-layout -->

  <!-- ================================================================
       CTA SECTION
  ================================================================ -->
  <section class="calc-cta-section" aria-labelledby="calc-cta-heading">
    <div class="calc-cta-section__inner">
      <h2 class="calc-cta-section__heading" id="calc-cta-heading">Ready for comprehensive financial planning?</h2>
      <p class="calc-cta-section__sub">Create a free account to access all planning tools and get a complete view of your finances.</p>
      <a href="/register" class="calc-cta-btn">
        Register for free
        <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"/>
        </svg>
      </a>
    </div>
  </section>

</main>

<?php include __DIR__.'/partials/footer.php'; ?>

<script src="/pages/js/site.js?v=5" defer></script>
<script src="/pages/js/calculators.js?v=1" defer></script>
</body>
</html>
