<?php

use App\Services\Marketing\PensionEstimateService;

// Compute the personalised pension estimate server-side from the funnel answers
// (passed as query params by /pensioncheck). All tax values come from
// TaxConfigService via PensionEstimateService — never hard-coded here.
$pensioncheckAnswers = [
    'employment' => $_GET['employment'] ?? null,
    'income' => $_GET['income'] ?? null,
    'age' => $_GET['age'] ?? null,
    'pensions' => (isset($_GET['pensions']) && $_GET['pensions'] !== '')
        ? array_slice(array_map('trim', explode(',', (string) $_GET['pensions'])), 0, 8)
        : [],
    'pot' => $_GET['pot'] ?? null,
    'spouse' => $_GET['spouse'] ?? null,
];
// Representative default for direct visits (no funnel params) so the page is
// never empty for SEO / shared links.
if (empty($pensioncheckAnswers['income'])) {
    $pensioncheckAnswers = [
        'employment' => 'full-time',
        'income' => 'upto_50270',
        'age' => '40s',
        'pensions' => ['workplace'],
        'pot' => '25k_100k',
        'spouse' => 'no',
    ];
}
try {
    $pensioncheckEstimate = app(PensionEstimateService::class)->estimate($pensioncheckAnswers);
} catch (Throwable $e) {
    $pensioncheckEstimate = null;
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
  <title>Your Pension Projection — Pension Check Results | Fynla</title>
  <meta name="description" content="See where your pension pot could be by retirement. Understand your position and take action with Fynla's free pension planning tools." />
  <link rel="canonical" href="https://fynla.org/pensioncheck/plan" />

  <!-- Open Graph -->
  <meta property="og:type" content="website" />
  <meta property="og:title" content="Your Pension Projection — Pension Check Results | Fynla" />
  <meta property="og:description" content="See where your pension pot could be by retirement. Understand your position and take action with Fynla's free pension planning tools." />
  <meta property="og:image" content="https://fynla.org/images/og/pensioncheck-plan.jpg" />
  <meta property="og:url" content="https://fynla.org/pensioncheck/plan" />

  <!-- Twitter Card -->
  <meta name="twitter:card" content="summary_large_image" />
  <meta name="twitter:title" content="Your Pension Projection — Pension Check Results | Fynla" />
  <meta name="twitter:description" content="See where your pension pot could be by retirement. Understand your position and take action with Fynla's free pension planning tools." />
  <meta name="twitter:image" content="https://fynla.org/images/og/pensioncheck-plan.jpg" />

  <!-- hreflang -->
  <link rel="alternate" hreflang="en-GB" href="https://fynla.org/pensioncheck/plan" />
  <link rel="alternate" hreflang="x-default" href="https://fynla.org/pensioncheck/plan" />

  <!-- Blocking CSS — same-server files, no FOUC risk -->
  <link rel="stylesheet" href="/pages/css/global.css?v=113" />
  <link rel="stylesheet" href="/pages/css/savetax-plan.css?v=4" />
  <link rel="stylesheet" href="/pages/css/savetax-plan-v4.css?v=9" />

  <!-- JSON-LD structured data -->
  <script type="application/ld+json">
  {
    "@context": "https://schema.org",
    "@type": "WebPage",
    "name": "Your Pension Projection — Pension Check Results",
    "url": "https://fynla.org/pensioncheck/plan",
    "description": "See where your pension pot could be by retirement and understand how to build the retirement you want.",
    "publisher": {
      "@type": "Organization",
      "name": "Fynla",
      "url": "https://fynla.org"
    }
  }
  </script>
</head>
<body>

  <a href="#main-content" class="skip-nav">Skip to main content</a>

  <?php include __DIR__.'/partials/nav.php'; ?>

  <main id="main-content" class="campaign-body sp4-body">

    <!-- ================================================================
         HERO — single column. Pension pot projection under the title +
         compact register card. JS personalises the figures from the
         server-injected estimate and saved funnel answers.
         ================================================================ -->
    <section id="hero" class="campaign-hero sp4-hero" aria-labelledby="hero-heading">
      <div class="campaign-inner sp4-hero__inner">

        <div class="sp4-hero__copy">

          <!-- Heading switches between projection and already-retired variants in JS -->
          <h1 id="hero-heading" class="campaign-hero__heading">
            <span id="hero-heading-text">On track for a <span class="campaign-hero__heading-accent">pension pot</span> of roughly</span>
          </h1>

          <!-- Pension pot projection figure — JS updates from PENSIONCHECK_ESTIMATE -->
          <div class="sp4-savings sp4-savings--hero" aria-label="Your projected pension pot">
            <p class="sp4-savings__row">
              <span class="sp4-savings__figure" id="pot-figure">£140,000</span>
            </p>
            <p class="sp4-savings__label" id="pot-label">by age <span id="retirement-age">67</span></p>
          </div>

          <!-- Supporting lines — JS updates from PENSIONCHECK_ESTIMATE -->
          <p class="campaign-hero__subtext" id="contribution-note">
            <!-- Contribution assumption and tax relief note rendered by JS -->
          </p>

          <p class="campaign-hero__subtext" id="tax-relief-note">
            <!-- Tax relief note rendered by JS -->
          </p>

          <p class="campaign-hero__subtext sp4-disclaimer">
            This is a <strong>projection based on your answers</strong>, not a guaranteed outcome or regulated financial forecast. Register free and continue with Fyn to get a full, personalised pension plan.
          </p>
        </div>

        <!-- Compact register card -->
        <div class="sp4-hero__card" aria-label="Create your account">

          <!-- Compact register form: first name, last name, email, password -->
          <form class="sp4-register" id="register-form" novalidate>
            <p class="sp4-register__heading">Create your free account</p>
            <div class="sp4-register__row">
              <div class="sp4-register__col">
                <label class="visually-hidden" for="reg-first-name">First name</label>
                <input class="sp4-register__field" id="reg-first-name" name="first_name" type="text" placeholder="First name" autocomplete="given-name" />
              </div>
              <div class="sp4-register__col">
                <label class="visually-hidden" for="reg-last-name">Last name</label>
                <input class="sp4-register__field" id="reg-last-name" name="last_name" type="text" placeholder="Last name" autocomplete="family-name" />
              </div>
            </div>
            <label class="visually-hidden" for="reg-email">Email address</label>
            <input class="sp4-register__field" id="reg-email" name="email" type="email" placeholder="Email address" autocomplete="email" />
            <label class="visually-hidden" for="reg-password">Password</label>
            <input class="sp4-register__field" id="reg-password" name="password" type="password" placeholder="Create a password" autocomplete="new-password" />
            <button type="submit" class="sp4-register__btn" id="register-btn">Start my pension plan — free</button>
            <p class="sp4-register__note">
              Takes you straight to your dashboard with Fyn open, ready to build your pension plan.
            </p>
            <!-- Login link href is constructed by JS using window.FYNLA_BASE -->
            <p class="sp4-register__login">
              Already with Fynla? <a id="login-link" href="/login">Log in</a>
            </p>
          </form>
        </div>
      </div>
    </section>

    <!-- ================================================================
         WHAT THIS MEANS — key stats explained.
         JS populates the breakdown from PENSIONCHECK_ESTIMATE.
         ================================================================ -->
    <section id="breakdown" class="sp4-combined" aria-labelledby="breakdown-heading">
      <div class="campaign-inner">

        <div class="sp4-combined__intro">
          <h2 id="breakdown-heading" class="sp4-combined__heading">What goes into this projection?</h2>
          <div class="sp4-combined__meaning">
            <div class="sp4-combined__total">
              <p class="sp4-combined__total-label">Years to retirement</p>
              <p class="sp4-combined__total-figure" id="years-figure">22</p>
            </div>
            <details class="sp4-combined__meaning-detail">
              <summary class="sp4-combined__meaning-summary">How is this calculated?</summary>
              <p class="sp4-combined__body">
                This projection assumes contributions continue at the current rate until State Pension age, with a conservative real-terms growth rate applied. It is a marketing-grade estimate — not a regulated financial forecast. Your actual pot will depend on contributions, investment returns, charges, and any gaps in saving. Fyn builds a full personalised plan once you register.
              </p>
            </details>
          </div>
        </div>

        <!-- Personalised stats grid — JS renders rows here -->
        <div class="sp4-allowances__grid" id="stats-render" aria-live="polite">
          <!-- JS renders pension breakdown items here -->
        </div>

        <!-- Register CTA -->
        <div class="sp4-combined__cta">
          <p class="sp4-combined__cta-text">Build your full pension plan</p>
          <a href="#hero" class="sp4-combined__cta-btn">Register for free</a>
        </div>
      </div>
    </section>

    <!-- ================================================================
         COULD THIS BE YOU? — illustrative social proof.
         ================================================================ -->
    <section id="examples" class="examples-section sp4-proof" aria-labelledby="examples-heading">
      <div class="campaign-inner">
        <div class="examples-section__intro sp4-proof__intro">
          <h2 id="examples-heading" class="examples-section__heading">Could this be you?</h2>
        </div>

        <!-- Headline social-proof stat -->
        <div class="sp4-proof__headline" id="proof-headline"></div>

        <!-- Testimonials relevant to the persona -->
        <div class="sp4-proof__grid" id="proof-grid" aria-live="polite"></div>

        <div class="examples-section__footer">
          <p class="examples-section__footer-text sp4-proof__join">Join them — it's free</p>
          <a href="#hero" class="examples-section__cta">Register for free</a>
        </div>
      </div>
    </section>

  </main>

  <?php include __DIR__.'/partials/footer.php'; ?>

  <script>window.PENSIONCHECK_ESTIMATE = <?= json_encode($pensioncheckEstimate, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;</script>
  <script src="/pages/js/site.js?v=3" defer></script>
  <script src="/pages/js/pensioncheck-plan.js?v=2" defer></script>
  <!-- Cookie consent — persisted via localStorage; the SPA register step reuses it. -->
  <script src="/pages/js/cookie-consent.js?v=1" defer></script>

</body>
</html>
