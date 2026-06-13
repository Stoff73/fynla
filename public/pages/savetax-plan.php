<?php

use App\Services\Marketing\SaveTaxEstimateService;

// Compute the personalised tax estimate server-side from the funnel answers
// (passed as query params by /savetax). All tax values come from
// TaxConfigService via SaveTaxEstimateService — never hard-coded here.
$savetaxAnswers = [
    'income' => $_GET['income'] ?? null,
    'spouse' => $_GET['spouse'] ?? null,
    'spouseIncome' => $_GET['spouseIncome'] ?? null,
    'assets' => (isset($_GET['assets']) && $_GET['assets'] !== '')
        ? array_slice(array_map('trim', explode(',', (string) $_GET['assets'])), 0, 12)
        : [],
];
// Representative default for direct visits (no funnel params) so the page is
// never empty for SEO / shared links.
if (empty($savetaxAnswers['income'])) {
    $savetaxAnswers = [
        'income' => '50271_100000',
        'spouse' => 'no',
        'spouseIncome' => null,
        'assets' => ['savings', 'pension', 'isa'],
    ];
}
try {
    $savetaxEstimate = app(SaveTaxEstimateService::class)->estimate($savetaxAnswers);
} catch (Throwable $e) {
    $savetaxEstimate = null;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Save More on Tax — Your UK Tax Allowances Guide | Fynla</title>
  <meta name="description" content="See every UK tax allowance for 2026/27 — Personal Allowance, ISA, pension, and more. Understand your position and keep more of what you earn with Fynla." />
  <link rel="canonical" href="https://fynla.org/savetax/plan" />

  <!-- Open Graph -->
  <meta property="og:type" content="website" />
  <meta property="og:title" content="Save More on Tax — Your UK Tax Allowances Guide | Fynla" />
  <meta property="og:description" content="See every UK tax allowance for 2026/27 — Personal Allowance, ISA, pension, and more. Understand your position and keep more of what you earn with Fynla." />
  <meta property="og:image" content="https://fynla.org/images/og/savetax-plan.jpg" />
  <meta property="og:url" content="https://fynla.org/savetax/plan" />

  <!-- Twitter Card -->
  <meta name="twitter:card" content="summary_large_image" />
  <meta name="twitter:title" content="Save More on Tax — Your UK Tax Allowances Guide | Fynla" />
  <meta name="twitter:description" content="See every UK tax allowance for 2026/27 — Personal Allowance, ISA, pension, and more. Understand your position and keep more of what you earn with Fynla." />
  <meta name="twitter:image" content="https://fynla.org/images/og/savetax-plan.jpg" />

  <!-- hreflang -->
  <link rel="alternate" hreflang="en-GB" href="https://fynla.org/savetax/plan" />
  <link rel="alternate" hreflang="x-default" href="https://fynla.org/savetax/plan" />

  <!-- Blocking CSS — same-server files, no FOUC risk -->
  <link rel="stylesheet" href="/pages/css/global.css?v=113" />
  <link rel="stylesheet" href="/pages/css/savetax-plan.css?v=4" />
  <link rel="stylesheet" href="/pages/css/savetax-plan-v4.css?v=8" />

  <!-- JSON-LD structured data -->
  <script type="application/ld+json">
  {
    "@context": "https://schema.org",
    "@type": "WebPage",
    "name": "Save More on Tax — Your UK Tax Allowances Guide",
    "url": "https://fynla.org/savetax/plan",
    "description": "See every UK tax allowance for 2026/27 and understand how to keep more of what you earn.",
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
         HERO — single column. Savings figure under the title + compact
         register card. JS personalises the figure from saved answers.
         ================================================================ -->
    <section id="hero" class="campaign-hero sp4-hero" aria-labelledby="hero-heading">
      <div class="campaign-inner sp4-hero__inner">

        <div class="sp4-hero__copy">
          <h1 id="hero-heading" class="campaign-hero__heading">
            Great news,<br />you could <span class="campaign-hero__heading-accent">save tax</span>
          </h1>

          <!-- Savings figure — directly under the title -->
          <div class="sp4-savings sp4-savings--hero" aria-label="Your potential tax saving">
            <p class="sp4-savings__row">
              <span class="sp4-savings__up-to">Up to</span>
              <span class="sp4-savings__figure" id="savings-figure">£3,100</span>
            </p>
            <p class="sp4-savings__label">average estimated saving each year</p>
          </div>

          <p class="campaign-hero__subtext" id="hero-subtext">
            This is an <strong>average</strong> based on the answers you gave — not your personal tax saving. To get your personal tax strategy, register for free and continue with Fyn, who'll work it out from your full situation and remember everything you've told us so far.
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
            <button type="submit" class="sp4-register__btn" id="register-btn">Register for free</button>
            <p class="sp4-register__note">
              Takes you straight to your dashboard with Fyn open, ready to guide your onboarding.
            </p>
          </form>
        </div>
      </div>
    </section>

    <!-- ================================================================
         YOUR ALLOWANCES + WHAT DOES THIS MEAN — combined into one section.
         Meaning intro + total at the top, then the personalised allowances
         grid, then a "Find out how" register CTA.
         ================================================================ -->
    <section id="allowances" class="sp4-combined" aria-labelledby="allowances-heading">
      <div class="campaign-inner">

        <!-- Meaning intro -->
        <div class="sp4-combined__intro">
          <span class="allowances-section__label">Tax year <span id="tax-year">2026/27</span></span>
          <h2 id="allowances-heading" class="sp4-combined__heading">Your allowances</h2>
          <div class="sp4-combined__meaning">
            <div class="sp4-combined__total">
              <p class="sp4-combined__total-label">Allowances relevant to you 2026/27</p>
              <p class="sp4-combined__total-figure" id="allowances-total">£96,330</p>
            </div>
            <p class="sp4-combined__body" id="meaning-body">
              <strong>What does this mean?</strong> These are the tax-free and tax-relievable allowances that apply to your situation this year. Below, we've highlighted the ones you're entitled to and greyed out those that don't apply — so you can see exactly where your opportunities are. Fyn can help you use them together to keep more of what you earn.
            </p>
          </div>
        </div>

        <!-- Personalised allowances grid -->
        <div class="sp4-allowances__grid" id="allowances-render" aria-live="polite">
          <!-- JS renders two columns of allowance items here -->
        </div>

        <!-- Find out how + register CTA -->
        <div class="sp4-combined__cta">
          <p class="sp4-combined__cta-text">Find out how</p>
          <a href="#hero" class="sp4-combined__cta-btn">Register for free</a>
        </div>
      </div>
    </section>

    <!-- ================================================================
         COULD THIS BE YOU? — social proof relevant to the user's answers.
         Rendered by JS. (Illustrative sample figures.)
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

  <script>window.SAVETAX_ESTIMATE = <?= json_encode($savetaxEstimate, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;</script>
  <script src="/pages/js/site.js?v=3" defer></script>
  <script src="/pages/js/savetax-plan-v4.js?v=10" defer></script>

</body>
</html>
