<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Your Tax Plan (v4 mockup) — Fynla</title>
  <meta name="description" content="Mockup v4 — personalised UK tax allowances based on your answers, with social proof and a compact register flow." />
  <meta name="robots" content="noindex,nofollow" />

  <!-- Blocking CSS — same-server files, no FOUC risk -->
  <link rel="stylesheet" href="/pages/css/global.css?v=113" />
  <link rel="stylesheet" href="/pages/css/savetax-plan.css?v=4" />
  <link rel="stylesheet" href="/pages/css/savetax-plan-v4.css?v=8" />
</head>
<body>

  <a href="#main-content" class="skip-nav">Skip to main content</a>

  <?php include __DIR__ . '/partials/nav.php'; ?>

  <main id="main-content" class="campaign-body sp4-body">

    <!-- ================================================================
         HERO — single column (Fyn chat removed). Savings figure + compact
         register CTA. JS personalises the figure from saved answers.
         ================================================================ -->
    <section id="hero" class="campaign-hero sp4-hero" aria-labelledby="hero-heading">
      <div class="campaign-inner sp4-hero__inner">

        <div class="sp4-hero__copy">
          <h1 id="hero-heading" class="campaign-hero__heading">
            Great news,<br />you could <span class="campaign-hero__heading-accent">save tax</span>
          </h1>

          <!-- Savings figure — moved directly under the title -->
          <div class="sp4-savings sp4-savings--hero" aria-label="Your potential tax saving">
            <p class="sp4-savings__row">
              <span class="sp4-savings__up-to">Up to</span>
              <span class="sp4-savings__figure" id="savings-figure">£3,100</span>
            </p>
            <p class="sp4-savings__label">estimated tax savings each year</p>
          </div>

          <p class="campaign-hero__subtext" id="hero-subtext">
            This is what you could save based on your situation. Register for free and Fyn will build your personal tax strategy, and remember what you've told us so far.
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
         "What does this mean" intro + total sit at the top, then the
         personalised allowances grid directly below.
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
         COULD THIS BE YOU? — replaced example panels with SOCIAL PROOF
         relevant to the user's answers. Rendered by JS.
         (Sample/illustrative figures — mockup only.)
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
          <a href="#hero" class="examples-section__cta">Register &amp; meet Fyn</a>
        </div>
      </div>
    </section>

  </main>

  <?php include __DIR__ . '/partials/footer.php'; ?>

  <script src="/pages/js/site.js?v=3" defer></script>
  <script src="/pages/js/savetax-plan-v4.js?v=5" defer></script>

</body>
</html>
