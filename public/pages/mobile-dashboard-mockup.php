<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover" />
  <meta name="theme-color" content="#1F2A44" />
  <title>Mobile Dashboard Mockup — Fynla</title>
  <meta name="description" content="Design mockup for the redesigned mobile dashboard." />
  <meta name="robots" content="noindex,nofollow" />

  <!-- Critical inline CSS — above-fold skeleton -->
  <style>
    html { height: 100%; }
    body {
      font-family: 'Segoe UI', 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
      background: #1F2A44;
      color: #1F2A44;
      min-height: 100svh;
      margin: 0;
      display: flex;
      align-items: stretch;
      justify-content: center;
    }
    .phone-frame {
      width: 100%;
      max-width: 430px;
      background: #F7F6F4;
      height: 100vh;
      height: 100svh;
      display: flex;
      flex-direction: column;
      position: relative;
      overflow: hidden;
    }
  </style>

  <link rel="stylesheet" href="/pages/css/global.css?v=113" />
  <link rel="stylesheet" href="/pages/css/mobile-dashboard-mockup.css?v=49" />
</head>
<body>
  <a href="#main-content" class="skip-nav">Skip to main content</a>

  <div class="phone-frame">

    <!-- ============================================================
         FIXED HEADER — hamburger + greeting. Stays put while the
         body (including the progress wheel) scrolls beneath it.
         ============================================================ -->
    <header class="md-header" role="banner">
      <button type="button" class="md-hamburger" id="md-hamburger" aria-label="Open menu" aria-expanded="false" aria-controls="md-drawer">
        <svg aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24" width="22" height="22">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
        </svg>
      </button>
      <div class="md-header__greeting">
        <p class="md-header__hello">Hello, Roxanne</p>
      </div>
      <span class="md-header__spacer" aria-hidden="true"></span>
    </header>

    <main id="main-content" class="md-main">

      <!-- Gradient backdrop + level card — scrolls with the rest of the page -->
      <div class="md-scroll-hero">
        <section class="md-level" aria-labelledby="md-level-heading">
          <div class="md-level__pie" role="img" aria-label="Level 2, 60 percent complete">
            <svg class="md-level__pie-svg" viewBox="0 0 100 100" aria-hidden="true">
              <circle class="md-level__pie-track" cx="50" cy="50" r="44" />
              <circle class="md-level__pie-arc" id="md-level-arc" cx="50" cy="50" r="44" style="--progress: 0;" data-target="60" />
            </svg>
            <div class="md-level__pie-inner">
              <p class="md-level__pie-label">Level</p>
              <p class="md-level__pie-num" id="md-level-num">1</p>
            </div>
          </div>
          <div class="md-level__copy">
            <h2 class="md-level__heading" id="md-level-heading">0 of 3 actions complete</h2>
            <p class="md-level__sub" id="md-level-sub">Complete actions to reach <strong>Level 2</strong>.</p>
          </div>
        </section>
      </div>

      <!-- ============================================================
           CALLOUT — two halves: rank statement (top) + light-pink
           carousel + per-category recommendations (bottom).
           ============================================================ -->
      <div class="md-callout" role="note">

        <!-- Top half: LEVEL UP heading (left) + rank statement (right) -->
        <div class="md-callout__top">
          <p class="md-callout__levelup">LEVEL<br>UP</p>
          <div class="md-callout__top-copy">
            <p class="md-callout__lead">You're ahead of <strong id="md-percentile">57%</strong> of people</p>
            <p class="md-callout__sub">Complete your actions to get further ahead, level up and change your financial future</p>
          </div>
        </div>

        <!-- Bottom half: light-pink horizontal accordion + recommendations -->
        <div class="md-callout__carousel" id="md-carousel">

          <!-- Horizontal accordion — active card expands (light pink),
               inactive cards collapse to a grey icon (eggshell). -->
          <div class="md-accordion" id="md-accordion" role="tablist" aria-label="Focus areas">

            <button type="button" class="md-accordion__card is-active" data-index="0" role="tab" aria-selected="true" aria-label="Save tax">
              <span class="md-accordion__icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="22" height="22"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg></span>
              <span class="md-accordion__content">
                <span class="md-callout__slide-stat">£2,400</span>
                <span class="md-callout__slide-area">Save tax</span>
                <span class="md-callout__slide-info">You could save this much a year by using your full ISA and pension allowances.</span>
              </span>
            </button>

            <button type="button" class="md-accordion__card" data-index="1" role="tab" aria-selected="false" aria-label="Retirement">
              <span class="md-accordion__icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="22" height="22"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg></span>
              <span class="md-accordion__content">
                <span class="md-callout__slide-stat">£4,200</span>
                <span class="md-callout__slide-area">Retirement</span>
                <span class="md-callout__slide-info">Your projected monthly income gap. Small increases now close it faster.</span>
              </span>
            </button>

            <button type="button" class="md-accordion__card" data-index="2" role="tab" aria-selected="false" aria-label="Savings">
              <span class="md-accordion__icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="22" height="22"><path stroke-linecap="round" stroke-linejoin="round" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/></svg></span>
              <span class="md-accordion__content">
                <span class="md-callout__slide-stat">4.2 months</span>
                <span class="md-callout__slide-area">Savings</span>
                <span class="md-callout__slide-info">Your emergency fund covers this long. Aim for six months of essential spending.</span>
              </span>
            </button>

          </div>

          <!-- Dots — alternative navigation for the accordion -->
          <div class="md-callout__dots" id="md-carousel-dots" role="tablist" aria-label="Focus area navigation">
            <button type="button" class="md-callout__dot is-active" data-index="0" aria-label="Save tax" aria-selected="true"></button>
            <button type="button" class="md-callout__dot" data-index="1" aria-label="Retirement" aria-selected="false"></button>
            <button type="button" class="md-callout__dot" data-index="2" aria-label="Savings" aria-selected="false"></button>
          </div>

          <!-- Recommendations — collapsible, list rendered by JS per category -->
          <section class="md-recs is-open" aria-labelledby="md-recs-heading" id="md-recs-section">
            <button type="button" class="md-section-head md-section-head--toggle" id="md-recs-toggle" aria-expanded="true" aria-controls="md-recs-body">
              <h3 class="md-section-head__title" id="md-recs-heading">Recommendations</h3>
              <span class="md-section-head__right">
                <span class="md-section-head__count" id="md-recs-count">0 / 0</span>
                <svg class="md-section-head__chevron" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24" width="18" height="18"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
              </span>
            </button>

            <div class="md-recs__body" id="md-recs-body">
              <ul class="md-recs__list" id="md-recs-list" aria-live="polite"></ul>
              <a href="#" class="md-recs__view-all">View all recommendations</a>
            </div>
          </section>

        </div><!-- /.md-callout__carousel -->

      </div><!-- /.md-callout -->

      <!-- ============================================================
           4-PANEL GRID — key dashboard attributes
           ============================================================ -->
      <section class="md-panels" aria-labelledby="md-panels-heading">
        <div class="md-section-head">
          <h3 class="md-section-head__title" id="md-panels-heading">Your finances</h3>
        </div>

        <div class="md-panels__list">

          <a href="#net-worth" class="md-panel md-panel--horizon">
            <div class="md-panel__viz" style="--progress: 72;" aria-hidden="true">
              <div class="md-panel__viz-inner">
                <span class="md-panel__viz-num">+7%</span>
                <span class="md-panel__viz-cap">YTD</span>
              </div>
            </div>
            <div class="md-panel__body">
              <p class="md-panel__label"><span class="md-panel__title-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14"><path stroke-linecap="round" stroke-linejoin="round" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg></span>Net worth</p>
              <p class="md-panel__value">£482,300</p>
              <p class="md-panel__caption">+£3,400 this month</p>
            </div>
          </article>

          <a href="#protection" class="md-panel md-panel--raspberry">
            <div class="md-panel__viz" style="--progress: 85;" aria-hidden="true">
              <div class="md-panel__viz-inner">
                <span class="md-panel__viz-num">85%</span>
                <span class="md-panel__viz-cap">Covered</span>
              </div>
            </div>
            <div class="md-panel__body">
              <p class="md-panel__label"><span class="md-panel__title-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg></span>Protection</p>
              <p class="md-panel__value">£350,000</p>
              <p class="md-panel__caption">3 policies active</p>
            </div>
          </a>

          <a href="#savings" class="md-panel md-panel--spring">
            <div class="md-panel__viz md-panel__viz--bar" aria-hidden="true">
              <div class="md-panel__bar">
                <div class="md-panel__bar-fill" style="width: 70%;"></div>
              </div>
              <p class="md-panel__bar-label"><strong>4.2</strong> / 6 months</p>
            </div>
            <div class="md-panel__body">
              <p class="md-panel__label"><span class="md-panel__title-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14"><path stroke-linecap="round" stroke-linejoin="round" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/></svg></span>Savings</p>
              <p class="md-panel__value">£18,900</p>
              <p class="md-panel__caption">Emergency fund on track</p>
            </div>
          </a>

          <a href="#retirement" class="md-panel md-panel--violet">
            <div class="md-panel__viz md-panel__viz--bar" aria-hidden="true">
              <div class="md-panel__bar">
                <div class="md-panel__bar-fill" style="width: 58%;"></div>
              </div>
              <p class="md-panel__bar-label"><strong>58%</strong> of target</p>
            </div>
            <div class="md-panel__body">
              <p class="md-panel__label"><span class="md-panel__title-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg></span>Retirement</p>
              <p class="md-panel__value">£4,200</p>
              <p class="md-panel__caption">Monthly income gap</p>
            </div>
          </a>

        </div>
      </section>

      <div class="md-bottom-pad" aria-hidden="true"></div>

    </main>

    <!-- ============================================================
         FYN DOCK — docked chat input (matches authenticated Fyn chat).
         Tapping the input (or send) opens the full overlay.
         ============================================================ -->
    <button type="button" class="md-fyn-dock md-fyn-dock--bar" id="md-fyn-open" aria-label="Chat with Fyn">
      <span class="md-fyn-dock__avatar" aria-hidden="true">F</span>
      <span class="md-fyn-dock__text">
        <span class="md-fyn-dock__name">Fyn</span>
        <span class="md-fyn-dock__status">Your financial companion</span>
      </span>
      <span class="md-fyn-dock__action" aria-hidden="true">
        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" width="22" height="22"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 15l7-7 7 7"/></svg>
      </span>
    </button>

    <!-- ============================================================
         HAMBURGER DRAWER — slide-in from left, dim backdrop
         ============================================================ -->
    <div class="md-drawer-backdrop" id="md-drawer-backdrop" hidden></div>
    <aside class="md-drawer" id="md-drawer" aria-label="Menu" hidden>
      <div class="md-drawer__head">
        <div class="md-drawer__head-text">
          <p class="md-drawer__user">Roxanne Harley</p>
          <p class="md-drawer__email">roxanne@example.com</p>
        </div>
        <button type="button" class="md-drawer__close" id="md-drawer-close" aria-label="Close menu">
          <svg aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24" width="22" height="22">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12" />
          </svg>
        </button>
      </div>

      <nav class="md-drawer__nav" aria-label="Primary navigation">

        <!-- Primary -->
        <div class="md-drawer__section">
          <a href="#" class="md-drawer__link is-active" data-nav="dashboard">
            <span class="md-drawer__icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="20" height="20"><path stroke-linecap="round" stroke-linejoin="round" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg></span>
            <span class="md-drawer__label">Dashboard</span>
          </a>
        </div>

        <!-- Cash Management -->
        <div class="md-drawer__section">
          <p class="md-drawer__group">Cash Management</p>
          <a href="#" class="md-drawer__link" data-nav="net-worth">
            <span class="md-drawer__icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="20" height="20"><path stroke-linecap="round" stroke-linejoin="round" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg></span>
            <span class="md-drawer__label">Net Worth</span>
          </a>
          <a href="#" class="md-drawer__link" data-nav="bank-accounts">
            <span class="md-drawer__icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="20" height="20"><path stroke-linecap="round" stroke-linejoin="round" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/></svg></span>
            <span class="md-drawer__label">Bank Accounts</span>
          </a>
          <a href="#" class="md-drawer__link" data-nav="income">
            <span class="md-drawer__icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="20" height="20"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg></span>
            <span class="md-drawer__label">Income</span>
          </a>
          <a href="#" class="md-drawer__link" data-nav="expenditure">
            <span class="md-drawer__icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="20" height="20"><path stroke-linecap="round" stroke-linejoin="round" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/></svg></span>
            <span class="md-drawer__label">Expenditure</span>
          </a>
        </div>

        <!-- Finances -->
        <div class="md-drawer__section">
          <p class="md-drawer__group">Finances</p>
          <a href="#" class="md-drawer__link" data-nav="protection">
            <span class="md-drawer__icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="20" height="20"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg></span>
            <span class="md-drawer__label">Protection</span>
          </a>
          <a href="#" class="md-drawer__link" data-nav="savings">
            <span class="md-drawer__icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="20" height="20"><path stroke-linecap="round" stroke-linejoin="round" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/></svg></span>
            <span class="md-drawer__label">Savings</span>
          </a>
          <a href="#" class="md-drawer__link" data-nav="investment">
            <span class="md-drawer__icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="20" height="20"><path stroke-linecap="round" stroke-linejoin="round" d="M7 12l3-3 3 3 4-4M8 21l4-4 4 4M3 4h18M4 4h16v12a1 1 0 01-1 1H5a1 1 0 01-1-1V4z"/></svg></span>
            <span class="md-drawer__label">Investment</span>
          </a>
          <a href="#" class="md-drawer__link" data-nav="retirement">
            <span class="md-drawer__icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="20" height="20"><path stroke-linecap="round" stroke-linejoin="round" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg></span>
            <span class="md-drawer__label">Retirement</span>
          </a>
          <a href="#" class="md-drawer__link" data-nav="estate">
            <span class="md-drawer__icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="20" height="20"><path stroke-linecap="round" stroke-linejoin="round" d="M3 21h18M3 10h18M5 6l7-3 7 3M4 10v11m16-11v11M8 14v3m4-3v3m4-3v3"/></svg></span>
            <span class="md-drawer__label">Estate</span>
          </a>
        </div>

        <!-- Planning -->
        <div class="md-drawer__section">
          <p class="md-drawer__group">Planning</p>
          <a href="#" class="md-drawer__link" data-nav="goals">
            <span class="md-drawer__icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="20" height="20"><path stroke-linecap="round" stroke-linejoin="round" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.196-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118L2.36 10.8c-.783-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.518-4.674z"/></svg></span>
            <span class="md-drawer__label">Goals &amp; Life Events</span>
          </a>
          <a href="#" class="md-drawer__link" data-nav="plans">
            <span class="md-drawer__icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="20" height="20"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg></span>
            <span class="md-drawer__label">Plans</span>
          </a>
        </div>

        <!-- Account -->
        <div class="md-drawer__section md-drawer__section--account">
          <a href="#" class="md-drawer__link" data-nav="account">
            <span class="md-drawer__icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="20" height="20"><path stroke-linecap="round" stroke-linejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg></span>
            <span class="md-drawer__label">Account &amp; settings</span>
          </a>
          <a href="#" class="md-drawer__link md-drawer__link--signout" data-nav="signout">
            <span class="md-drawer__icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="20" height="20"><path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg></span>
            <span class="md-drawer__label">Sign out</span>
          </a>
        </div>

      </nav>
    </aside>

    <!-- ============================================================
         FYN CHAT OVERLAY — full-screen takeover with close button
         ============================================================ -->
    <section class="md-fyn" id="md-fyn-overlay" aria-label="Chat with Fyn" hidden>
      <header class="md-fyn__head">
        <div class="md-fyn__title">
          <span class="md-fyn__avatar" aria-hidden="true">F</span>
          <div>
            <p class="md-fyn__name">Fyn</p>
            <p class="md-fyn__status">Your financial companion</p>
          </div>
        </div>
        <button type="button" class="md-fyn__close" id="md-fyn-close" aria-label="Close Fyn chat">
          <svg aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24" width="22" height="22">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12" />
          </svg>
        </button>
      </header>

      <div class="md-fyn__messages" aria-live="polite">
        <div class="md-fyn__msg md-fyn__msg--fyn">
          <p>Hi Roxanne. You're 60% of the way to Level 3 — finish two more recommendations to get there.</p>
        </div>
        <div class="md-fyn__msg md-fyn__msg--fyn">
          <p>What would you like to look at?</p>
        </div>
      </div>

      <div class="md-fyn__prompts" aria-label="Suggested questions">
        <button type="button" class="md-fyn__prompt">How am I tracking for retirement?</button>
        <button type="button" class="md-fyn__prompt">Am I using my ISA allowance?</button>
        <button type="button" class="md-fyn__prompt">What should I do next?</button>
      </div>

      <!-- Chat input — only present in the expanded overlay (matches the
           authenticated Fyn composer). The collapsed state is just the dock bar. -->
      <form class="md-fyn__compose" onsubmit="event.preventDefault();">
        <span class="md-fyn-dock__avatar" aria-hidden="true">F</span>
        <label for="md-fyn-input" class="visually-hidden">Ask Fyn a question</label>
        <input type="text" id="md-fyn-input" class="md-fyn-dock__input" placeholder="Ask Fyn anything..." autocomplete="off" />
        <button type="submit" class="md-fyn-dock__send" aria-label="Send to Fyn">
          <svg aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24" width="20" height="20"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.2" d="M5 12h14M13 5l7 7-7 7"/></svg>
        </button>
      </form>
    </section>

  </div><!-- /.phone-frame -->

  <script src="/pages/js/mobile-dashboard-mockup.js?v=19" defer></script>
</body>
</html>
