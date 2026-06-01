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

  <!-- Blocking CSS — base styles then v3 overrides (green register button) -->
  <link rel="stylesheet" href="/pages/css/global.css?v=113" />
  <link rel="stylesheet" href="/pages/css/savetax-plan.css?v=3" />
  <link rel="stylesheet" href="/pages/css/savetax-plan-v3.css?v=1" />

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

  <?php include __DIR__ . '/partials/nav.php'; ?>

  <main id="main-content" class="campaign-body">

    <!-- ================================================================
         HERO  [id=hero]
         Two-column on desktop: copy (left) + savings card (right).
         JS swaps heading + subtext when ?from=savetax is present.
         ================================================================ -->
    <section id="hero" class="campaign-hero sp-hero" aria-labelledby="hero-heading">
      <div class="campaign-inner sp-hero__inner">

        <!-- Left col: heading + subtext + organic CTA -->
        <div class="sp-hero__copy">
          <h1 id="hero-heading" class="campaign-hero__heading">
            Save more on <span class="campaign-hero__heading-accent" id="hero-accent">tax</span>
          </h1>
          <p class="campaign-hero__subtext" id="hero-subtext">
            Understand your tax position, maximise your allowances, and keep more of what you earn with Fynla's complete financial planning platform.
          </p>
          <!-- v3: organic CTA removed — savings card on right is the sole hero CTA -->
        </div>

        <!-- Right col: savings amount + CTA + expandable Fyn chat -->
        <div class="sp-hero__savings-card" aria-label="Your potential tax saving">

          <div class="sp-savings-amount">
            <p class="sp-savings-amount__up-to">Up to</p>
            <p class="sp-savings-amount__figure" id="savings-figure">£103,330</p>
            <p class="sp-savings-amount__label">in available annual tax allowances</p>
          </div>

          <div class="sp-cta-block">
            <p class="sp-cta-block__copy">To find out exactly how you could save</p>
            <a href="/register?from=savetax" class="sp-cta-block__register-btn">Register for free</a>
            <button
              type="button"
              class="sp-fyn-toggle"
              id="fyn-toggle"
              aria-expanded="false"
              aria-controls="fyn-chat-card"
            >Or find out more with Fyn</button>
          </div>

          <!-- Inline Fyn chat — expands on toggle click -->
          <div class="sp-fyn-chat" id="fyn-chat-card" hidden>
            <div class="sp-fyn-chat__messages" aria-live="polite">
              <div class="sp-fyn-chat__bubble sp-fyn-chat__bubble--fyn">
                <p>Hi! I'm Fyn. Register for free and I'll build a personalised tax strategy based on your situation.</p>
              </div>
              <div class="sp-fyn-chat__bubble sp-fyn-chat__bubble--fyn">
                <p>I can help you:</p>
                <ul class="sp-fyn-chat__list">
                  <li>See which allowances apply to you</li>
                  <li>Build a personalised tax strategy</li>
                  <li>Answer all your financial questions</li>
                </ul>
              </div>
              <div class="sp-fyn-chat__cta-row">
                <a href="/register?from=savetax" class="sp-fyn-chat__cta-link">Register now to ask Fyn</a>
              </div>
            </div>
            <div class="sp-fyn-chat__compose">
              <label for="fyn-input-card" class="visually-hidden">Ask Fyn a question</label>
              <input
                type="text"
                id="fyn-input-card"
                class="sp-fyn-chat__input"
                placeholder="Ask Fyn anything..."
                autocomplete="off"
                readonly
              />
              <button type="button" class="sp-fyn-chat__send" id="fyn-send-card" aria-label="Get started with Fyn">
                Get started
              </button>
            </div>
          </div>

        </div>
      </div>
    </section>

    <!-- ================================================================
         ALLOWANCES  [id=allowances]
         bg-eggshell-500
         ================================================================ -->
    <section id="allowances" class="allowances-section" aria-labelledby="allowances-heading">
      <div class="campaign-inner">
        <div class="allowances-section__intro">
          <span class="allowances-section__label">Tax year <span id="tax-year">2026/27</span></span>
          <h2 id="allowances-heading" class="allowances-section__heading">Your allowances</h2>
        </div>

        <div class="allowances-grid">

          <!-- Income column -->
          <div class="allowances-col">
            <div class="allowances-col__header allowances-col__header--horizon">
              <h3 class="allowances-col__title">Income</h3>
            </div>
            <ul id="income-allowances" class="allowances-list" aria-label="Income allowances">
              <li class="allowance-item">
                <div class="allowance-item__text">
                  <p class="allowance-item__label">Personal Allowance</p>
                  <p class="allowance-item__note">Tax-free income each year</p>
                </div>
                <span class="allowance-item__amount">£12,570</span>
              </li>
              <li class="allowance-item">
                <div class="allowance-item__text">
                  <p class="allowance-item__label">Savings Allowance</p>
                  <p class="allowance-item__note">Basic-rate taxpayers</p>
                </div>
                <span class="allowance-item__amount">£1,000</span>
              </li>
              <li class="allowance-item">
                <div class="allowance-item__text">
                  <p class="allowance-item__label">Starting Rate for Savings</p>
                  <p class="allowance-item__note">If non-savings income &lt; £17,570</p>
                </div>
                <span class="allowance-item__amount">£5,000</span>
              </li>
              <li class="allowance-item">
                <div class="allowance-item__text">
                  <p class="allowance-item__label">Marriage Allowance</p>
                  <p class="allowance-item__note">Transferable to spouse</p>
                </div>
                <span class="allowance-item__amount">£1,260</span>
              </li>
            </ul>
          </div>

          <!-- Investment & Cash column -->
          <div class="allowances-col">
            <div class="allowances-col__header allowances-col__header--raspberry">
              <h3 class="allowances-col__title">Investment &amp; Cash</h3>
            </div>
            <ul id="investment-allowances" class="allowances-list" aria-label="Investment and cash allowances">
              <li class="allowance-item">
                <div class="allowance-item__text">
                  <p class="allowance-item__label">ISA Allowance</p>
                  <p class="allowance-item__note">Tax-free savings &amp; investing</p>
                </div>
                <span class="allowance-item__amount">£20,000</span>
              </li>
              <li class="allowance-item">
                <div class="allowance-item__text">
                  <p class="allowance-item__label">Capital Gains Tax Allowance</p>
                  <p class="allowance-item__note">Capital gains exempt amount</p>
                </div>
                <span class="allowance-item__amount">£3,000</span>
              </li>
              <li class="allowance-item">
                <div class="allowance-item__text">
                  <p class="allowance-item__label">Dividend Allowance</p>
                  <p class="allowance-item__note">Tax-free dividend income</p>
                </div>
                <span class="allowance-item__amount">£500</span>
              </li>
              <li class="allowance-item">
                <div class="allowance-item__text">
                  <p class="allowance-item__label">Pension Annual Allowance</p>
                  <p class="allowance-item__note">Tax-relievable contributions</p>
                </div>
                <span class="allowance-item__amount">£60,000</span>
              </li>
            </ul>
          </div>

        </div>

        <p class="allowances-section__footer-text">
          Knowing how to get the most out of, and use your allowances can be tricky.
          <strong>Fyn can help.</strong>
          Here are a few common situations where the right allowance — or the right account — can save you thousands.
        </p>
      </div>
    </section>

    <!-- ================================================================
         WHAT DOES THIS MEAN?  [id=meaning]
         bg-horizon-500
         ================================================================ -->
    <section id="meaning" class="meaning-section" aria-labelledby="meaning-heading">
      <div class="campaign-inner">
        <h2 id="meaning-heading" class="meaning-section__heading">What does this mean?</h2>
        <div class="meaning-section__grid">
          <div class="meaning-section__total-col">
            <p class="meaning-section__total-label">Total available allowances 2026/27</p>
            <p class="meaning-section__total" id="allowances-total">£96,330</p>
          </div>
          <div class="meaning-section__text-col">
            <p class="meaning-section__body">
              That's the combined value of the tax-free and tax-relievable allowances available to every UK taxpayer each year. From income you can earn free of tax, to ISA savings sheltered from capital gains and dividends, to pension contributions that attract full tax relief — the system offers significant scope to keep more of what you earn. The challenge is knowing which allowances apply to your situation and how to use them together effectively.
            </p>
            <a href="/register?from=savetax" class="meaning-section__cta">Register to save tax</a>
          </div>
        </div>
      </div>
    </section>

    <!-- ================================================================
         EXAMPLES  [id=examples]
         bg-light-pink-100
         ================================================================ -->
    <section id="examples" class="examples-section" aria-labelledby="examples-heading">
      <div class="campaign-inner">
        <div class="examples-section__intro">
          <span class="examples-section__label">Real-life examples</span>
          <h2 id="examples-heading" class="examples-section__heading">Could this be you?</h2>
        </div>

        <div class="examples-grid">

          <article class="example-card">
            <h3 class="example-card__heading">Non-working spouse</h3>
            <p class="example-card__body">If no income is earned, a non-earning spouse can still receive up to <strong>£18,750</strong> per year of income tax-free by combining the Personal Allowance, Starting Rate for Savings and Personal Savings Allowance.</p>
          </article>

          <article class="example-card">
            <h3 class="example-card__heading">High income tax trap</h3>
            <p class="example-card__body">If you earn above <strong>£100,000</strong> per year, you may have some of your income taxed at an effective rate of <strong>60%</strong> due to the tapered withdrawal of your Personal Allowance.</p>
          </article>

          <article class="example-card">
            <h3 class="example-card__heading">Investment accounts</h3>
            <p class="example-card__body">Just using these, you can take up to <strong>£3,000</strong> per year of tax-free gains and <strong>£500</strong> per year of tax-free dividend income — on top of your ISA.</p>
          </article>

          <article class="example-card">
            <h3 class="example-card__heading">National Insurance contributions</h3>
            <p class="example-card__body">When you pay into your pension, both you and your employer pay <strong>National Insurance contributions</strong> on those contributions. But if your employer pays directly into your pension, neither side pays them at all. A <strong>salary sacrifice</strong> scheme makes tax-efficient use of this difference.</p>
          </article>

        </div>

        <div class="examples-section__footer">
          <p class="examples-section__footer-text">Get started now</p>
          <a href="/register?from=savetax" class="examples-section__cta">Register now to ask Fyn</a>
        </div>
      </div>
    </section>

  </main>

  <!-- ================================================================
       FYN CHAT PANEL (desktop only)
       Starts hidden — JS removes [hidden] when it is available.
       ================================================================ -->
  <aside class="fyn-panel" id="fyn-panel" aria-label="Chat with Fyn" hidden>
    <div class="fyn-panel__inner">

      <div class="fyn-panel__header">
        <div class="fyn-panel__avatar" aria-hidden="true">F</div>
        <div class="fyn-panel__header-text">
          <p class="fyn-panel__name">Fyn</p>
          <p class="fyn-panel__status">Your financial companion</p>
        </div>
      </div>

      <div class="fyn-panel__messages" id="fyn-messages" aria-live="polite" aria-label="Chat messages">

        <div class="fyn-panel__message fyn-panel__message--fyn">
          <p>Hi! I'm Fyn, your financial companion. I can answer questions about tax allowances, pensions, ISAs, and your overall financial plan.</p>
        </div>

        <div class="fyn-panel__message fyn-panel__message--fyn">
          <div class="fyn-panel__bubble">
            <p style="background:none;padding:0;margin-bottom:0.5rem;">Register for free to start chatting with me:</p>
            <ul class="fyn-panel__register-list">
              <li>Get a personalised tax strategy</li>
              <li>See which allowances apply to you</li>
              <li>Ask unlimited questions</li>
            </ul>
          </div>
          <a href="/register?from=savetax" class="fyn-panel__register-cta">Register now to ask Fyn</a>
        </div>

      </div>

      <div class="fyn-panel__prompts" aria-label="Suggested questions">
        <p class="fyn-panel__prompts-label">Suggested questions</p>
        <ul class="fyn-panel__prompts-list">
          <li class="fyn-panel__prompt">How much do I need to retire?</li>
          <li class="fyn-panel__prompt">Am I using my ISA allowance?</li>
          <li class="fyn-panel__prompt">What is my net worth?</li>
        </ul>
      </div>

      <div class="fyn-panel__compose">
        <label for="fyn-input" class="visually-hidden">Ask Fyn a question</label>
        <input
          type="text"
          id="fyn-input"
          class="fyn-panel__input"
          placeholder="Ask Fyn a question..."
          autocomplete="off"
          readonly
          aria-describedby="fyn-input-hint"
        />
        <p id="fyn-input-hint" class="visually-hidden">Register to unlock chat with Fyn.</p>
        <button type="button" class="fyn-panel__send" id="fyn-send" aria-label="Get started with Fyn">
          Get started
        </button>
      </div>

    </div>
  </aside>

  <?php include __DIR__ . '/partials/footer.php'; ?>

  <script src="/pages/js/site.js?v=3" defer></script>
  <script src="/pages/js/savetax-plan.js?v=2" defer></script>

</body>
</html>
