<?php

use App\Services\Marketing\SaveTaxEstimateService;
use App\Services\TaxConfigService;

// Compute the personalised tax estimate server-side from the funnel answers
// (passed as query params by /savetax). All tax values come from
// TaxConfigService via SaveTaxEstimateService — never hard-coded here.
// Read through the request, not $_GET, so a test request sees its own answers.
$savetaxQuery = request()->query();
$savetaxAssets = (string) ($savetaxQuery['assets'] ?? '');
$savetaxAnswers = [
    'employment' => $savetaxQuery['employment'] ?? null,
    'income' => $savetaxQuery['income'] ?? null,
    'spouse' => $savetaxQuery['spouse'] ?? null,
    'spouseIncome' => $savetaxQuery['spouseIncome'] ?? null,
    'spouseEmployment' => $savetaxQuery['spouseEmployment'] ?? null,
    'assets' => $savetaxAssets !== ''
        ? array_slice(array_map('trim', explode(',', $savetaxAssets)), 0, 12)
        : [],
];
// Representative default for direct visits (no funnel params) so the page is
// never empty for SEO / shared links.
if (empty($savetaxAnswers['income'])) {
    $savetaxAnswers = [
        'employment' => 'full-time',
        'income' => '50271_100000',
        'spouse' => 'no',
        'spouseIncome' => null,
        'spouseEmployment' => null,
        'assets' => ['savings', 'pension', 'isa'],
    ];
}
try {
    $savetaxEstimate = app(SaveTaxEstimateService::class)->estimate($savetaxAnswers);
} catch (Throwable $e) {
    $savetaxEstimate = null;
}
$savetaxEstimateAvailable = is_array($savetaxEstimate);
$savetaxWithPartner = ($savetaxAnswers['spouse'] ?? null) === 'yes';
// The headline says what it assumes: the top of the chosen income band, and
// how much of it is the partner's (only when there is any).
$savetaxBasis = '';
if ($savetaxEstimateAvailable) {
    $savetaxBasis = match ($savetaxEstimate['assumed_income_basis'] ?? null) {
        'band_top' => 'Worked out for an income of £'.number_format((int) $savetaxEstimate['assumed_income']).', the top of the band you chose',
        'example' => 'Worked out for an example income of £'.number_format((int) $savetaxEstimate['assumed_income']).' in the band you chose',
        default => 'Worked out for no income of your own',
    };
    $savetaxPartnerTotal = (int) ($savetaxEstimate['partner_savings_total'] ?? 0);
    $savetaxBasis .= $savetaxPartnerTotal > 0
        ? ', and it includes £'.number_format($savetaxPartnerTotal).' from your partner\'s side. '
        : '. ';
    // The plan engine prices England, Wales and Northern Ireland rates and does
    // not model Scottish Income Tax bands, so neither does this (CSJ 2026-09-29).
    $savetaxBasis .= 'It uses England, Wales and Northern Ireland Income Tax rates, not Scottish rates. ';
}
$savetaxAllowanceCount = $savetaxEstimateAvailable
    ? (int) ($savetaxEstimate['allowances']['available_count'] ?? 0).' of '.(int) ($savetaxEstimate['allowances']['count'] ?? 0)
    : 'Unavailable';
try {
    $savetaxTaxYear = (string) ($savetaxEstimate['tax_year'] ?? app(TaxConfigService::class)->getTaxYear());
} catch (Throwable $e) {
    $savetaxTaxYear = 'Current tax year';
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
  <title>Save More on Tax — Your UK Tax Allowances Guide | Fynla</title>
  <meta name="description" content="See the key UK tax allowances considered by this estimate for <?= htmlspecialchars($savetaxTaxYear, ENT_QUOTES) ?> — Personal Allowance, ISA, pension, and more. Understand your position and keep more of what you earn with Fynla." />
  <link rel="canonical" href="https://fynla.org/savetax/plan" />

  <!-- Open Graph -->
  <meta property="og:type" content="website" />
  <meta property="og:title" content="Save More on Tax — Your UK Tax Allowances Guide | Fynla" />
  <meta property="og:description" content="See the key UK tax allowances considered by this estimate for <?= htmlspecialchars($savetaxTaxYear, ENT_QUOTES) ?> — Personal Allowance, ISA, pension, and more. Understand your position and keep more of what you earn with Fynla." />
  <meta property="og:image" content="https://fynla.org/images/og/savetax-plan.jpg" />
  <meta property="og:url" content="https://fynla.org/savetax/plan" />

  <!-- Twitter Card -->
  <meta name="twitter:card" content="summary_large_image" />
  <meta name="twitter:title" content="Save More on Tax — Your UK Tax Allowances Guide | Fynla" />
  <meta name="twitter:description" content="See the key UK tax allowances considered by this estimate for <?= htmlspecialchars($savetaxTaxYear, ENT_QUOTES) ?> — Personal Allowance, ISA, pension, and more. Understand your position and keep more of what you earn with Fynla." />
  <meta name="twitter:image" content="https://fynla.org/images/og/savetax-plan.jpg" />

  <!-- hreflang -->
  <link rel="alternate" hreflang="en-GB" href="https://fynla.org/savetax/plan" />
  <link rel="alternate" hreflang="x-default" href="https://fynla.org/savetax/plan" />

  <!-- Blocking CSS — same-server files, no FOUC risk -->
  <link rel="stylesheet" href="/pages/css/global.css?v=113" />
  <link rel="stylesheet" href="/pages/css/savetax-plan.css?v=4" />
  <link rel="stylesheet" href="/pages/css/savetax-plan-v4.css?v=12" />

  <!-- JSON-LD structured data -->
  <script type="application/ld+json">
  {
    "@context": "https://schema.org",
    "@type": "WebPage",
    "name": "Save More on Tax — Your UK Tax Allowances Guide",
    "url": "https://fynla.org/savetax/plan",
    "description": "See the key UK tax allowances considered by this estimate for <?= htmlspecialchars($savetaxTaxYear, ENT_QUOTES) ?> and understand how to keep more of what you earn.",
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
          <?php $savetaxFigure = $savetaxEstimateAvailable ? '£'.number_format((int) $savetaxEstimate['savings_total']) : null; ?>
          <div class="sp4-savings sp4-savings--hero">
            <?php if ($savetaxFigure !== null) { ?>
              <p class="sp4-savings__claim" id="savings-claim" aria-label="An estimated saving of up to <?= htmlspecialchars($savetaxFigure, ENT_QUOTES) ?> each year">
                An estimated saving of up to <span class="sp4-savings__figure" id="savings-figure"><?= htmlspecialchars($savetaxFigure, ENT_QUOTES) ?></span> each year
              </p>
            <?php } else { ?>
              <p class="sp4-savings__claim" id="savings-claim">Your estimate is temporarily unavailable</p>
            <?php } ?>
          </div>

          <p class="campaign-hero__subtext" id="hero-subtext">
            <?= htmlspecialchars($savetaxBasis, ENT_QUOTES) ?>Register for free and get your personalised tax strategy, worked out from your real figures.
          </p>
          <p class="campaign-hero__subtext">This is an illustrative estimate, not personal financial advice.</p>
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
         Meaning intro + total at the top, with "Find out how" registration
         actions immediately before and after the personalised allowances.
         ================================================================ -->
    <section id="allowances" class="sp4-combined" aria-labelledby="allowances-heading">
      <div class="campaign-inner">

        <!-- Meaning intro -->
        <div class="sp4-combined__intro">
          <span class="allowances-section__label">Tax year <span id="tax-year"><?= htmlspecialchars($savetaxTaxYear, ENT_QUOTES) ?></span></span>
          <h2 id="allowances-heading" class="sp4-combined__heading"><?= $savetaxWithPartner ? 'Your allowances, and your partner\'s' : 'Your allowances' ?></h2>
          <div class="sp4-combined__meaning">
            <div class="sp4-combined__total">
              <p class="sp4-combined__total-label">Allowances available to you<?= $savetaxWithPartner ? ' and your partner' : '' ?></p>
              <p class="sp4-combined__total-figure" id="allowances-total"><?= htmlspecialchars($savetaxAllowanceCount, ENT_QUOTES) ?></p>
              <p class="sp4-combined__body">The amounts have different tax meanings and are not added together.</p>
            </div>
            <details class="sp4-combined__meaning-detail">
              <summary class="sp4-combined__meaning-summary">What does this mean?</summary>
              <p class="sp4-combined__body" id="meaning-body">
                Each allowance below says whether it is available to act on, used automatically, or not applicable to the answers supplied. Open one to see why. Fyn can turn the relevant opportunities into a personalised tax strategy.
              </p>
            </details>
          </div>
        </div>

        <div class="sp4-combined__cta sp4-combined__cta--top">
          <p class="sp4-combined__cta-text">Find out how</p>
          <a href="#register-form" class="sp4-combined__cta-btn">Register for free</a>
        </div>

        <!-- Personalised allowances grid -->
        <div class="sp4-allowances__grid" id="allowances-render" aria-live="polite">
          <!-- JS renders two columns of allowance items here -->
        </div>

        <div class="sp4-combined__cta">
          <p class="sp4-combined__cta-text">Find out how</p>
          <a href="#register-form" class="sp4-combined__cta-btn">Register for free</a>
        </div>
      </div>
    </section>

  </main>

  <?php include __DIR__.'/partials/footer.php'; ?>

  <script>window.SAVETAX_ESTIMATE = <?= json_encode($savetaxEstimate, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;</script>
  <script src="/pages/js/site.js?v=3" defer></script>
  <script src="/pages/js/savetax-plan-v4.js?v=17" defer></script>
  <!-- Cookie consent — persisted via localStorage; the SPA register step reuses it. -->
  <script src="/pages/js/cookie-consent.js?v=2" defer></script>

</body>
</html>
