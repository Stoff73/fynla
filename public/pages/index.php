<?php

use App\Services\Marketing\SaveTaxEstimateService;

// Headline "save tax" figure for the How-Fyn-can-help teaser. Uses the same
// representative default persona as /savetax/plan for a direct visit, so the
// number shown here matches the savetax landing page. All tax values come from
// TaxConfigService via SaveTaxEstimateService — never hard-coded.
$homeSaveTaxFigure = null;
try {
    $homeSaveTaxEstimate = app(SaveTaxEstimateService::class)->estimate([
        'income' => '50271_100000',
        'spouse' => 'no',
        'spouseIncome' => null,
        'assets' => ['savings', 'pension', 'isa'],
    ]);
    if (! empty($homeSaveTaxEstimate['savings_total'])) {
        $homeSaveTaxFigure = '£'.number_format((int) $homeSaveTaxEstimate['savings_total']);
    }
} catch (Throwable $e) {
    $homeSaveTaxFigure = null;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <!-- Favicon -->
  <link rel="icon" type="image/png" href="/images/logos/favicon.png" />
  <link rel="icon" type="image/x-icon" href="/images/logos/favicon.ico" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />

  <!-- Preload LCP hero image - must be as early as possible in <head> so
       the browser discovers and fetches it before any blocking resources. -->
  <link rel="preload" as="image" href="/images/Website/Homepage-Header-Desktopv3.png"
        fetchpriority="high" media="(min-width: 1024px)" />

  <title>Fyn, your financial companion | Fynla</title>
  <meta name="description" content="Fynla is a UK personal finance platform that helps you plan savings, investments, pensions, retirement and estate. See your complete financial picture in one place." />
  <link rel="canonical" href="https://fynla.org/" />

  <!-- Open Graph -->
  <meta property="og:type" content="website" />
  <meta property="og:title" content="Fyn, your financial companion | Fynla" />
  <meta property="og:description" content="Fynla is a UK personal finance platform that helps you plan savings, investments, pensions, retirement and estate. See your complete financial picture in one place." />
  <meta property="og:image" content="https://fynla.org/images/og/index.jpg" />
  <meta property="og:url" content="https://fynla.org/" />

  <!-- Twitter Card -->
  <meta name="twitter:card" content="summary_large_image" />
  <meta name="twitter:title" content="Fyn, your financial companion | Fynla" />
  <meta name="twitter:description" content="Fynla is a UK personal finance platform that helps you plan savings, investments, pensions, retirement and estate. See your complete financial picture in one place." />
  <meta name="twitter:image" content="https://fynla.org/images/og/index.jpg" />

  <!-- hreflang -->
  <link rel="alternate" hreflang="en-GB" href="https://fynla.org/" />
  <link rel="alternate" hreflang="x-default" href="https://fynla.org/" />

  <!-- External stylesheets (synchronous - render-blocking is intentional;
       all styles live in these files, no inline fallback needed) -->
  <link rel="stylesheet" href="/pages/css/global.css?v=113" />
  <link rel="stylesheet" href="/pages/css/index.css?v=124" />

  <!-- JSON-LD structured data -->
  <script type="application/ld+json">
  {
    "@context": "https://schema.org",
    "@type": "WebPage",
    "name": "Fyn, your financial companion",
    "url": "https://fynla.org/",
    "description": "Fynla is a UK personal finance platform that helps you plan savings, investments, pensions, retirement and estate.",
    "publisher": {
      "@type": "Organization",
      "name": "Fynla",
      "url": "https://fynla.org"
    }
  }
  </script>

  <!-- All styles live in /pages/css/global.css and /pages/css/index.css.
       No inline style block. -->

</head>
<body>

  <a href="#main-content" class="skip-nav">Skip to main content</a>

  <?php include __DIR__.'/partials/nav.php'; ?>

  <main id="main-content">

    <!-- ================================================================
         HERO MODULE  [id=hero]
         ================================================================ -->
    <section id="hero" class="hero" aria-labelledby="hero-heading">
      <div class="hero__inner">
        <h1 id="hero-heading" class="hero__heading">
          Change your<br />
          <span class="hero__heading-accent">financial future</span>
        </h1>
        <p class="hero__body">
          We help create your path to financial freedom<br />
          with clear recommendations from our proprietary Fynla Brain&reg;
        </p>
        <div class="hero__cta">
          <a href="/register" class="btn-cta-primary">Get started for free</a>
          <p class="hero__sublinks">
            <a href="#meet-fyn" class="hero__sublink" id="scroll-meet-fyn">Meet Fyn</a>
            <span class="hero__sublink-sep" aria-hidden="true">|</span>
            <a href="#dashboard" class="hero__sublink" id="scroll-dashboard">View the video</a>
            <span class="hero__sublink-sep" aria-hidden="true">|</span>
            <a href="#" class="hero__sublink open-demo-modal">See our demo</a>
          </p>
        </div>

        <!-- Mobile hero panel (hidden above 640px by CSS).
             Translucent white card holding the Fynla Brain, mirroring the level
             card on /m-mockup/dashboard — it overlaps down onto the eggshell
             tray below for the same straddle effect. -->
        <div class="hero__mobile-panels" aria-hidden="true">
          <div class="hero__brain-card">
            <img src="/images/Website/Fyn-Brain-Animation-Whitev2M.gif" alt="" width="176" height="176" loading="lazy" />
          </div>
        </div>

        <!-- Desktop composite (hidden below 1024px by CSS) -->
        <div class="hero__desktop-composite" aria-hidden="true">
          <img
            src="/images/Website/Homepage-Header-Desktopv3.png"
            alt="Fynla Brain - your financial planning intelligence"
            class="hero__desktop-img"
            width="1315" height="489"
            loading="eager" fetchpriority="high"
          />
          <div class="hero__caption hero__caption--left">
            <p class="hero__caption-title">One financial view.</p>
            <p class="hero__caption-body">Use Fynla to securely centralise and view all your financial data.</p>
          </div>
          <div class="hero__caption hero__caption--center">
            <div>
              <p class="hero__caption-title">One financial brain.</p>
              <p class="hero__caption-body">Our proprietary brain does the calculations<br />so you don't have to.</p>
            </div>
            <div class="hero__caption-brain">
              <img src="/images/Website/Fyn-Brain-Animation-Whitev2M.gif" alt="" width="200" height="200" loading="lazy" />
            </div>
          </div>
          <div class="hero__caption hero__caption--right">
            <p class="hero__caption-title">One financial voice.</p>
            <p class="hero__caption-body">We will give you clear, simple and tailored advice to help your financial freedom.</p>
          </div>
        </div>
      </div>

      <!-- Eggshell tray (mobile only) — sits at the bottom of the hero so the
           translucent brain card above overlaps onto it. Holds the three value
           props on an eggshell background, matching /m-mockup/dashboard. -->
      <div class="hero__mobile-eggshell" aria-hidden="true">
        <div class="hero__panel">
          <p class="hero__panel-title">One financial view.</p>
          <p class="hero__panel-body">Use Fynla to securely centralise and view all your financial data.</p>
        </div>
        <div class="hero__panel">
          <p class="hero__panel-title">One financial brain.</p>
          <p class="hero__panel-body">Our proprietary brain does the calculations so you don't have to.</p>
        </div>
        <div class="hero__panel">
          <p class="hero__panel-title">One financial voice.</p>
          <p class="hero__panel-body">We will give you clear, simple and tailored advice to help your financial freedom.</p>
        </div>
      </div>
    </section>

    <!-- ================================================================
         MEET FYN MODULE  [id=meet-fyn]
         ================================================================ -->
    <section id="meet-fyn" class="meet-fyn" aria-labelledby="meet-fyn-heading">
      <div class="meet-fyn__inner">
        <div class="meet-fyn__content">
          <!-- Mobile: heading + character side by side -->
          <div class="meet-fyn__mobile-header">
            <h2 id="meet-fyn-heading" class="meet-fyn__heading">Meet Fyn</h2>
            <img src="/images/Fyn/Design Character 001a.webp" alt="Fyn, your AI financial companion" class="meet-fyn__mobile-char" width="324" height="427" loading="lazy" />
          </div>
          <p class="meet-fyn__subheading">Your financial companion for life</p>
          <p class="meet-fyn__body">
            Need help? Fyn is the face of our Fynla brain and will help you with your finance goals by giving you financial clarity. Fyn will help with everything from planning to saving and investments, through to your net worth and real estate. Tell Fyn what you'd like to do and he'll walk you through your dashboard step-by-step.
          </p>
          <div class="fyn-accordion">
            <button type="button" class="fyn-accordion__trigger" aria-expanded="false" aria-controls="fyn-accordion-panel" id="fyn-accordion-btn">
              <span>What can Fyn help you with?</span>
              <svg class="fyn-accordion__chevron" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24" width="16" height="16">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
              </svg>
            </button>
            <div class="fyn-accordion__panel" id="fyn-accordion-panel" role="region" aria-labelledby="fyn-accordion-btn">
              <div class="fyn-accordion__item">
                <svg class="fyn-accordion__check" aria-hidden="true" fill="currentColor" viewBox="0 0 20 20" width="16" height="16"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" /></svg>
                Your financial companion to answer any questions you have
              </div>
              <div class="fyn-accordion__item">
                <svg class="fyn-accordion__check" aria-hidden="true" fill="currentColor" viewBox="0 0 20 20" width="16" height="16"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" /></svg>
                Help understand and complete your dashboard
              </div>
              <div class="fyn-accordion__item">
                <svg class="fyn-accordion__check" aria-hidden="true" fill="currentColor" viewBox="0 0 20 20" width="16" height="16"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" /></svg>
                Support you to achieve your financial goals
              </div>
            </div>
          </div>
          <a href="/register?from=fyn" class="btn-fyn-start">Quick start with Fyn</a>
        </div>
        <!-- Desktop character -->
        <div class="meet-fyn__character" aria-hidden="true">
          <img src="/images/Fyn/Design Character 001a.webp" alt="" width="324" height="427" loading="lazy" />
        </div>
      </div>
    </section>

    <!-- ================================================================
         FEATURE GRID MODULE  [id=features]
         ================================================================ -->
    <section id="features" class="feature-grid" aria-labelledby="features-heading">
      <div class="feature-grid__inner">
        <h2 id="features-heading" class="feature-grid__heading">How Fyn can help you</h2>
        <p class="feature-grid__intro">We leverage tools designed for individuals and families to plan savings, investments, retirement and estate with confidence and within local regulations.</p>

        <!-- Save-tax highlight — headline saving + CTA into the savetax funnel.
             The figure counts up to its value when scrolled into view (JS). -->
        <div class="feature-savetax">
          <p class="feature-savetax__headline">You could save tax today</p>
          <?php if ($homeSaveTaxFigure) { ?>
            <p
              class="feature-savetax__figure"
              id="savetax-counter"
              data-count-to="<?= (int) ($homeSaveTaxEstimate['savings_total'] ?? 0) ?>"
              data-count-prefix="£"
            >£0</p>
          <?php } ?>
          <p class="feature-savetax__sub">
            <?php if ($homeSaveTaxFigure) { ?>You can save up to <strong><?= htmlspecialchars($homeSaveTaxFigure, ENT_QUOTES) ?></strong> in tax. <?php } ?>Answer a few quick questions and Fyn will show the UK tax allowances you could be missing. Find out how much tax you can save.
          </p>
          <a href="/savetax" class="feature-savetax__cta">Save tax now</a>
        </div>

        <h3 class="feature-grid__subheading">Other ways Fyn can help you</h3>

        <div class="feature-grid__cards">
          <article class="feature-card" aria-label="Protection">
            <div class="feature-card__icon-wrap feature-card__icon-wrap--raspberry" aria-hidden="true">
              <svg class="feature-card__icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" width="20" height="20"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" /></svg>
            </div>
            <h3 class="feature-card__title">Protection</h3>
            <p class="feature-card__body">Analyse life insurance, critical illness, and income protection coverage gaps to ensure your family is fully protected.</p>
            <div class="feature-card__tags"><span class="feature-card__tag">Life Cover</span><span class="feature-card__tag">Income Protection</span></div>
          </article>
          <article class="feature-card" aria-label="Savings">
            <div class="feature-card__icon-wrap feature-card__icon-wrap--spring" aria-hidden="true">
              <svg class="feature-card__icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" width="20" height="20"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
            </div>
            <h3 class="feature-card__title">Savings</h3>
            <p class="feature-card__body">Track emergency funds, ISA allowances, and savings goals across all your accounts with smart benchmarking.</p>
            <div class="feature-card__tags"><span class="feature-card__tag">Emergency Fund</span><span class="feature-card__tag">ISA</span></div>
          </article>
          <article class="feature-card" aria-label="Investment">
            <div class="feature-card__icon-wrap feature-card__icon-wrap--violet" aria-hidden="true">
              <svg class="feature-card__icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" width="20" height="20"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" /></svg>
            </div>
            <h3 class="feature-card__title">Investment</h3>
            <p class="feature-card__body">Portfolio analysis, risk profiling, and Monte Carlo projections to optimise your investment strategy.</p>
            <div class="feature-card__tags"><span class="feature-card__tag">Portfolio</span><span class="feature-card__tag">Risk Profile</span></div>
          </article>
          <article class="feature-card" aria-label="Retirement">
            <div class="feature-card__icon-wrap feature-card__icon-wrap--blue" aria-hidden="true">
              <svg class="feature-card__icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" width="20" height="20"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
            </div>
            <h3 class="feature-card__title">Retirement</h3>
            <p class="feature-card__body">Defined Contribution, Defined Benefit, and State Pension tracking with retirement income projections.</p>
            <div class="feature-card__tags"><span class="feature-card__tag">Pension</span><span class="feature-card__tag">State Pension</span></div>
          </article>
          <article class="feature-card" aria-label="Estate planning">
            <div class="feature-card__icon-wrap feature-card__icon-wrap--savannah" aria-hidden="true">
              <svg class="feature-card__icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" width="20" height="20"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" /></svg>
            </div>
            <h3 class="feature-card__title">Estate</h3>
            <p class="feature-card__body">Inheritance Tax calculations, gifting strategies, and estate value projections for effective planning.</p>
            <div class="feature-card__tags"><span class="feature-card__tag">Inheritance Tax</span><span class="feature-card__tag">Trusts</span></div>
          </article>
          <article class="feature-card" aria-label="Net worth">
            <div class="feature-card__icon-wrap feature-card__icon-wrap--slate" aria-hidden="true">
              <svg class="feature-card__icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" width="20" height="20"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" /></svg>
            </div>
            <h3 class="feature-card__title">Net Worth</h3>
            <p class="feature-card__body">Complete balance sheet with properties, assets, and liabilities tracking for a clear financial picture.</p>
            <div class="feature-card__tags"><span class="feature-card__tag">Assets</span><span class="feature-card__tag">Liabilities</span></div>
          </article>
        </div>
        <div class="feature-grid__footer">
          <a href="/features" class="feature-grid__footer-link">View detailed list of features &rsaquo;</a>
          <span class="feature-grid__footer-sep" aria-hidden="true">|</span>
          <a href="#" class="feature-grid__footer-link open-demo-modal">View demos &rsaquo;</a>
        </div>
      </div>
    </section>

    <!-- ================================================================
         DASHBOARD VIDEO MODULE  [id=dashboard]
         ================================================================ -->
    <section id="dashboard" class="dashboard-preview" aria-labelledby="dashboard-heading">
      <div class="dashboard-preview__inner">
        <h2 id="dashboard-heading" class="dashboard-preview__heading">Your Fynla dashboard</h2>
        <div class="dashboard-preview__video-wrap" id="video-wrap" role="button" tabindex="0" aria-label="Play Fynla dashboard product tour video" aria-pressed="false">
          <video id="product-video" src="/images/Homepage-Fynla-ProductVideov2.mp4" playsinline class="dashboard-preview__video">
            Your browser does not support the video tag.
          </video>
          <div class="dashboard-preview__overlay" id="video-overlay" aria-hidden="true">
            <div class="dashboard-preview__play-btn">
              <svg class="dashboard-preview__play-icon" aria-hidden="true" fill="currentColor" viewBox="0 0 24 24" width="40" height="40"><path d="M8 5v14l11-7z" /></svg>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- ================================================================
         REVIEW CAROUSEL MODULE  [id=reviews]
         ================================================================ -->
    <section id="reviews" class="review-carousel" aria-labelledby="reviews-heading">
      <div class="review-carousel__inner">
        <h2 id="reviews-heading" class="review-carousel__heading">What our customers say</h2>
        <div class="review-carousel__track-wrapper" aria-live="polite" aria-atomic="false">
          <div class="review-carousel__track-desktop" id="carousel-desktop-track"></div>
          <div class="review-carousel__track-mobile"  id="carousel-mobile-track"></div>
        </div>
        <div class="review-carousel__nav" role="group" aria-label="Carousel navigation">
          <button class="review-carousel__arrow" id="carousel-prev" aria-label="Previous reviews">
            <svg aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24" width="16" height="16"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" /></svg>
          </button>
          <div class="review-carousel__dots" id="carousel-dots" role="tablist" aria-label="Review pages"></div>
          <button class="review-carousel__arrow" id="carousel-next" aria-label="Next reviews">
            <svg aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24" width="16" height="16"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" /></svg>
          </button>
        </div>
      </div>
    </section>

    <!-- ================================================================
         PERSONAL JOURNEY MODULE  [id=solutions]
         ================================================================ -->
    <section id="solutions" class="journey-stages" aria-labelledby="journey-heading">
      <div class="journey-stages__inner">
        <h2 id="journey-heading" class="journey-stages__heading">Your personal journey</h2>
        <div class="journey-stages__grid">
          <a href="/stage/starting-out" class="stage-card">
            <p class="stage-card__title"><span class="stage-card__accent">Starting</span><br />out</p>
            <p class="stage-card__sub">Build strong financial habits for your future.</p>
          </a>
          <a href="/stage/building-foundations" class="stage-card">
            <p class="stage-card__title"><span class="stage-card__accent">Building</span><br />foundations</p>
            <p class="stage-card__sub">Save, invest, and grow your wealth with confidence.</p>
          </a>
          <a href="/stage/protecting-and-growing" class="stage-card">
            <p class="stage-card__title"><span class="stage-card__accent">Protecting</span><br />and growing</p>
            <p class="stage-card__sub">Ensure your family and assets are fully covered.</p>
          </a>
          <a href="/stage/planning-your-future" class="stage-card">
            <p class="stage-card__title"><span class="stage-card__accent">Planning</span><br />your future</p>
            <p class="stage-card__sub">Retirement projections, pension tracking, and estate planning.</p>
          </a>
          <a href="/stage/enjoying-your-wealth" class="stage-card">
            <p class="stage-card__title"><span class="stage-card__accent">Enjoying</span><br />your wealth</p>
            <p class="stage-card__sub">Make the most of your financial success.</p>
          </a>
        </div>
        <div class="journey-stages__cta">
          <a href="#" class="btn-demo open-demo-modal">View demo</a>
        </div>
      </div>
    </section>

    <!-- ================================================================
         LATEST INSIGHTS MODULE  [id=insights]
         ================================================================ -->
    <section id="insights" class="latest-insights" aria-labelledby="insights-heading">
      <div class="latest-insights__inner">
        <h2 id="insights-heading" class="latest-insights__heading">Latest insights</h2>

        <!-- Dynamic: populated by JS fetch (hidden until API responds) -->
        <div id="insights-dynamic" class="insights-dynamic" hidden aria-live="polite">
          <a id="insight-featured" class="insights-featured" href="/insights" aria-label="Featured insight">
            <img id="insight-featured-img" src="" alt="" class="insights-featured__img" width="800" height="420" loading="eager" />
            <div class="insights-featured__overlay" aria-hidden="true"></div>
            <div class="insights-featured__body">
              <span class="insights-featured__badge">Featured</span>
              <h3 id="insight-featured-title" class="insights-featured__title"></h3>
              <p  id="insight-featured-summary" class="insights-featured__summary"></p>
            </div>
          </a>
          <div class="insights-supporting" id="insights-supporting"></div>
        </div>

        <!-- Static fallback: same featured+supporting layout as dynamic, visible until API succeeds -->
        <div id="insights-static" class="insights-dynamic">
          <a href="/insights/how-much-to-retire-uk" class="insights-featured" aria-label="How Much Do I Need to Retire in the UK? A Realistic Guide">
            <img src="/images/insights/pension-contribution-limits.jpg" alt="" class="insights-featured__img" width="800" height="420" loading="eager" />
            <div class="insights-featured__overlay" aria-hidden="true"></div>
            <div class="insights-featured__body">
              <span class="insights-featured__badge">Featured</span>
              <h3 class="insights-featured__title">How Much Do I Need to Retire in the UK? A Realistic Guide</h3>
              <p class="insights-featured__summary">Calculate your UK retirement number using 2026 PLSA living standards. Pension pot sizes needed and how to bridge the State Pension gap.</p>
            </div>
          </a>
          <div class="insights-supporting">
            <a href="/insights/stocks-shares-isa-uk" class="insights-support-card">
              <div class="insights-support-card__thumb">
                <img src="/images/insights/isa-allowance.jpg" alt="" width="300" height="200" loading="lazy" />
              </div>
              <div class="insights-support-card__body">
                <h4 class="insights-support-card__title">What Is a Stocks and Shares ISA? How It Works, Benefits &amp; Risks</h4>
              </div>
            </a>
            <a href="/insights/isa-guide-uk" class="insights-support-card">
              <div class="insights-support-card__thumb">
                <img src="/images/insights/pension-iht-changes.jpg" alt="" width="300" height="200" loading="lazy" />
              </div>
              <div class="insights-support-card__body">
                <h4 class="insights-support-card__title">The Ultimate Guide to ISAs in the UK</h4>
              </div>
            </a>
          </div>
        </div>

        <!-- Latest news bar — newest article from /api/news. Hidden until the
             fetch succeeds (graceful degradation); plain text, no icons. -->
        <a id="latest-news" class="latest-news" href="/news" hidden>
          <span class="latest-news__badge">Latest news</span>
          <span id="latest-news-text" class="latest-news__text"></span>
        </a>

        <p class="insights-footer">
          <a href="/insights" class="insights-footer__link">See all insights &rarr;</a>
        </p>
      </div>
    </section>

    <!-- ================================================================
         STATS BAR MODULE  [id=stats]
         ================================================================ -->
    <section id="stats" class="stats-bar" aria-label="Fynla key statistics">
      <div class="stats-bar__inner">
        <div class="stats-bar__card">
          <div class="stats-bar__stat">
            <div class="stats-bar__number">91%</div>
            <div class="stats-bar__label">UK adults don't get financial advice</div>
          </div>
          <div class="stats-bar__divider" aria-hidden="true"></div>
          <div class="stats-bar__stat">
            <div class="stats-bar__number">1000's</div>
            <div class="stats-bar__label">of financial plans<br />created for people like you</div>
          </div>
          <div class="stats-bar__divider" aria-hidden="true"></div>
          <div class="stats-bar__stat">
            <div class="stats-bar__number">30+</div>
            <div class="stats-bar__label">Fynla features for<br />financial planning</div>
          </div>
        </div>
      </div>
    </section>

  </main>

  <!-- ================================================================
       DEMO LIGHTBOX MODAL
       Hidden by default; opened by .open-demo-modal clicks.
       JS in index.js POSTs to /api/preview/login/{personaId} and
       redirects to /dashboard on success.
       ================================================================ -->
  <div id="demo-modal" class="demo-modal" role="dialog" aria-modal="true"
       aria-labelledby="demo-modal-title" hidden>
    <div id="demo-modal-backdrop" class="demo-modal__backdrop" aria-hidden="true"></div>
    <div class="demo-modal__panel">
      <div class="demo-modal__header">
        <h2 id="demo-modal-title" class="demo-modal__title">Choose your demo</h2>
        <p class="demo-modal__subtitle">Each demo is pre-loaded with realistic UK financial data - no sign-up needed.</p>
        <button id="demo-modal-close" class="demo-modal__close" aria-label="Close demo chooser">
          <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor"
               stroke-width="2" width="18" height="18">
            <path d="M18 6 6 18M6 6l12 12"/>
          </svg>
        </button>
      </div>

      <div class="demo-modal__body">
        <!-- 5-group layout matching live site: Starting Out | Protecting and Growing (×2) | Building Foundations | Planning Your Future | Enjoying Your Wealth -->
        <div class="demo-modal__grid">

          <!-- STARTING OUT — col 1 at desktop -->
          <div class="demo-group">
            <div class="demo-group__header demo-group__header--pink">
              <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14">
                <path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z"/>
              </svg>
              Starting out
            </div>
            <div class="demo-group__cards">
              <button class="demo-persona-card demo-persona-card--pink" data-persona="student" type="button">
                <div class="demo-persona-card__meta">
                  <span class="demo-persona-card__tag">Student</span>
                  <span class="demo-persona-card__income">~&pound;9k/yr</span>
                </div>
                <span class="demo-persona-card__name">Janice Taylor</span>
                <span class="demo-persona-card__detail">21, Economics student saving for a first home with a Lifetime ISA.</span>
                <div class="demo-persona-card__focus-tags">
                  <span>Lifetime ISA</span><span>First Home</span><span>Student Loan</span>
                </div>
                <span class="demo-persona-card__cta">View demo &rarr;</span>
              </button>
            </div>
          </div>

          <!-- PROTECTING AND GROWING — spans cols 2+3 at desktop, 2 cards side by side -->
          <div class="demo-group demo-group--wide">
            <div class="demo-group__header demo-group__header--horizon">
              <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
              </svg>
              Protecting and growing
            </div>
            <div class="demo-group__cards">
              <button class="demo-persona-card" data-persona="young_family" type="button">
                <div class="demo-persona-card__meta">
                  <span class="demo-persona-card__tag">Family</span>
                  <span class="demo-persona-card__income">~&pound;85k household</span>
                </div>
                <span class="demo-persona-card__name">Emily &amp; James Carter</span>
                <span class="demo-persona-card__detail">Young family in their 30s with a mortgage, workplace pensions, and two children.</span>
                <div class="demo-persona-card__focus-tags">
                  <span>Mortgage</span><span>Pensions</span><span>Protection</span>
                </div>
                <span class="demo-persona-card__cta">View demo &rarr;</span>
              </button>
              <button class="demo-persona-card demo-persona-card--purple" data-persona="entrepreneur" type="button">
                <div class="demo-persona-card__meta">
                  <span class="demo-persona-card__tag">Business owner</span>
                  <span class="demo-persona-card__income">~&pound;120k variable</span>
                </div>
                <span class="demo-persona-card__name">Alex Chen</span>
                <span class="demo-persona-card__detail">42, business owner with complex income streams and succession planning needs.</span>
                <div class="demo-persona-card__focus-tags">
                  <span>Business</span><span>SIPP</span><span>Succession</span>
                </div>
                <span class="demo-persona-card__cta">View demo &rarr;</span>
              </button>
            </div>
          </div>

          <!-- BUILDING FOUNDATIONS — col 1 at desktop (row 2) -->
          <div class="demo-group">
            <div class="demo-group__header demo-group__header--spring">
              <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14">
                <path stroke-linecap="round" stroke-linejoin="round" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
              </svg>
              Building foundations
            </div>
            <div class="demo-group__cards">
              <button class="demo-persona-card demo-persona-card--green" data-persona="young_saver" type="button">
                <div class="demo-persona-card__meta">
                  <span class="demo-persona-card__tag">First-time buyer</span>
                  <span class="demo-persona-card__income">~&pound;38k/yr</span>
                </div>
                <span class="demo-persona-card__name">John Morgan</span>
                <span class="demo-persona-card__detail">24, junior analyst renting and saving for a deposit with a Lifetime ISA.</span>
                <div class="demo-persona-card__focus-tags">
                  <span>Savings</span><span>Mortgage</span><span>Lifetime ISA</span>
                </div>
                <span class="demo-persona-card__cta">View demo &rarr;</span>
              </button>
            </div>
          </div>

          <!-- PLANNING YOUR FUTURE — col 2 at desktop (row 2) -->
          <div class="demo-group">
            <div class="demo-group__header demo-group__header--violet">
              <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
              </svg>
              Planning your future
            </div>
            <div class="demo-group__cards">
              <button class="demo-persona-card demo-persona-card--green" data-persona="peak_earners" type="button">
                <div class="demo-persona-card__meta">
                  <span class="demo-persona-card__tag">Peak earners</span>
                  <span class="demo-persona-card__income">~&pound;220k household</span>
                </div>
                <span class="demo-persona-card__name">David &amp; Sarah Mitchell</span>
                <span class="demo-persona-card__detail">Late 40s, substantial assets, multiple properties, SIPP and NHS pension.</span>
                <div class="demo-persona-card__focus-tags">
                  <span>Properties</span><span>Pensions</span><span>Tax Planning</span>
                </div>
                <span class="demo-persona-card__cta">View demo &rarr;</span>
              </button>
            </div>
          </div>

          <!-- ENJOYING YOUR WEALTH — col 3 at desktop (row 2) -->
          <div class="demo-group">
            <div class="demo-group__header demo-group__header--spring">
              <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 3v1m4.22 1.78l-.71.71M20 12h1M4 12H3m3.34-5.66l-.71-.71M15.54 8.46A5.99 5.99 0 0112 7a5.99 5.99 0 00-3.54 1.46M12 14a2 2 0 100-4 2 2 0 000 4zm0 0v7"/>
              </svg>
              Enjoying your wealth
            </div>
            <div class="demo-group__cards">
              <button class="demo-persona-card demo-persona-card--pink" data-persona="retired_couple" type="button">
                <div class="demo-persona-card__meta">
                  <span class="demo-persona-card__tag">Retirement</span>
                  <span class="demo-persona-card__income">~&pound;52k pensions</span>
                </div>
                <span class="demo-persona-card__name">Patricia &amp; Harold Bennett</span>
                <span class="demo-persona-card__detail">Early 70s, drawing defined-benefit pensions, focused on estate planning and gifting.</span>
                <div class="demo-persona-card__focus-tags">
                  <span>Estate</span><span>Inheritance Tax</span><span>Gifting</span>
                </div>
                <span class="demo-persona-card__cta">View demo &rarr;</span>
              </button>
            </div>
          </div>

        </div>

        <p id="demo-modal-status" class="demo-modal__note" aria-live="polite"></p>
      </div>
    </div>
  </div>

  <?php include __DIR__.'/partials/footer.php'; ?>

  <!-- Shared interactive wiring (nav active state, menus, etc.) -->
  <script src="/pages/js/site.js?v=112" defer></script>
  <!-- Page-specific interactions (carousel, video, accordion, insights, demo modal) -->
  <script src="/pages/js/index.js?v=114" defer></script>
  <!-- Cookie consent — server-rendered pages don't mount the SPA banner, so the
       prompt must appear here at the landing, persisted via localStorage. -->
  <script src="/pages/js/cookie-consent.js?v=1" defer></script>

</body>
</html>