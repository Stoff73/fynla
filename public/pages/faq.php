<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <!-- Favicon -->
  <link rel="icon" type="image/png" href="/images/logos/favicon.png" />
  <link rel="icon" type="image/x-icon" href="/images/logos/favicon.ico" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Frequently Asked Questions | Fynla</title>
  <meta name="description" content="Find answers to common questions about Fynla, UK financial planning, pensions, investments, savings, and how to get the most from the platform." />
  <link rel="canonical" href="https://fynla.org/faq" />

  <!-- Open Graph -->
  <meta property="og:type"        content="website" />
  <meta property="og:title"       content="Frequently Asked Questions | Fynla" />
  <meta property="og:description" content="Find answers to common questions about Fynla, UK financial planning, pensions, investments, savings, and how to get the most from the platform." />
  <meta property="og:image"       content="https://fynla.org/images/og/faq.jpg" />
  <meta property="og:url"         content="https://fynla.org/faq" />

  <!-- Twitter Card -->
  <meta name="twitter:card"        content="summary_large_image" />
  <meta name="twitter:title"       content="Frequently Asked Questions | Fynla" />
  <meta name="twitter:description" content="Find answers to common questions about Fynla, UK financial planning, pensions, investments, savings, and how to get the most from the platform." />
  <meta name="twitter:image"       content="https://fynla.org/images/og/faq.jpg" />

  <!-- hreflang -->
  <link rel="alternate" hreflang="en-GB"    href="https://fynla.org/faq" />
  <link rel="alternate" hreflang="x-default" href="https://fynla.org/faq" />

  <!-- JSON-LD structured data -->
  <script type="application/ld+json">
  {
    "@context": "https://schema.org",
    "@type": "FAQPage",
    "name": "Frequently Asked Questions",
    "url": "https://fynla.org/faq",
    "description": "Find answers to common questions about Fynla, UK financial planning, pensions, investments, and savings.",
    "publisher": {
      "@type": "Organization",
      "name": "Fynla",
      "url": "https://fynla.org"
    }
  }
  </script>

  <!-- Critical CSS â€” above-fold: tokens, reset, skip-nav, nav skeleton, hero -->
  <style>
    :root{--raspberry-300:#F472B6;--raspberry-400:#EC4899;--raspberry-500:#E83E6D;--raspberry-600:#DB2777;--horizon-100:#F1F5F9;--horizon-200:#E2E8F0;--horizon-300:#CBD5E1;--horizon-400:#94A3B8;--horizon-500:#1F2A44;--horizon-600:#0F172A;--horizon-700:#020617;--spring-500:#20B486;--violet-500:#5854E6;--savannah-100:#FDFAF7;--eggshell-500:#F7F6F4;--neutral-500:#717171;--light-pink-100:#FAD6E0;--light-pink-200:#F5B3C5;--light-gray:#EEEEEE;--white:#FFFFFF;--white-70:rgba(255,255,255,0.70);--black-05:rgba(0,0,0,0.05);--font-primary:'Segoe UI','Inter',-apple-system,BlinkMacSystemFont,sans-serif;--radius-md:0.5rem;--radius-lg:0.75rem;--radius-2xl:1rem;--radius-button:0.5rem;--radius-full:9999px;--shadow-sm:0 1px 2px 0 rgba(0,0,0,0.05);}
    *,*::before,*::after{box-sizing:border-box;margin:0;padding:0;}
    [hidden]{display:none !important;}
    html{scroll-behavior:smooth;}
    body{font-family:var(--font-primary);background:var(--eggshell-500);color:var(--horizon-500);line-height:1.5;min-height:100vh;}
    img{display:block;max-width:100%;}
    a{color:inherit;text-decoration:none;}
    ul{list-style:none;}
    button{cursor:pointer;font-family:inherit;}
    .skip-nav{position:absolute;top:-100%;left:1rem;background:var(--raspberry-500);color:var(--white);padding:0.5rem 1rem;border-radius:var(--radius-md);font-weight:600;z-index:9999;transition:top 0.2s;}
    .skip-nav:focus{top:1rem;}
    .site-header{position:sticky;top:0;z-index:50;background:var(--white);box-shadow:var(--shadow-sm);border-bottom:1px solid var(--light-gray);}
    .nav-primary__inner{max-width:80rem;margin:0 auto;padding:0 1rem;display:flex;align-items:center;justify-content:flex-start;height:4rem;position:relative;}
    /* Hero critical */
    .faq-hero{background:linear-gradient(to right,var(--horizon-500),var(--raspberry-500));overflow:hidden;}
    .faq-hero__inner{max-width:80rem;margin:0 auto;padding:2.5rem 1rem;}
    .faq-hero__heading{font-size:clamp(2.25rem,8vw,4.5rem);line-height:1;font-weight:900;color:var(--white);margin-bottom:1rem;}
    .faq-hero__accent{color:var(--raspberry-300);}
    .faq-hero__lead{font-size:1.125rem;color:var(--white-70);max-width:42rem;line-height:1.625;}
    @media(min-width:1024px){.faq-hero__inner{padding-left:2rem;padding-right:2rem;}}
  </style>

  <link rel="stylesheet" href="/pages/css/global.css?v=113" />
  <link rel="stylesheet" href="/pages/css/faq.css?v=1"    />
</head>
<body>

  <a href="#main-content" class="skip-nav">Skip to main content</a>

  <?php include __DIR__ . '/partials/nav.php'; ?>

  <main id="main-content">

    <!-- ================================================================
         HERO  [id=hero]
         ================================================================ -->
    <section id="hero" class="faq-hero" aria-labelledby="hero-heading">
      <div class="faq-hero__inner">
        <h1 id="hero-heading" class="faq-hero__heading">
          Frequently asked <span class="faq-hero__accent">questions</span>
        </h1>
        <p class="faq-hero__lead">
          Everything you need to know about Fynla â€” from what it does, to how it works, to whether it's right for you.
        </p>
      </div>
    </section>

    <!-- ================================================================
         INTRO + CATEGORY FILTER  [id=faq-filter]
         ================================================================ -->
    <section id="faq-filter" class="faq-filter" aria-label="Filter FAQ categories">
      <div class="faq-filter__inner">
        <p class="faq-filter__intro">
          Answers to the most common questions about Fynla â€” what it does, how it works, pricing, security, and whether it's the right tool for your financial planning needs.
        </p>
        <div class="faq-filter__tabs" role="tablist" aria-label="FAQ categories">
          <button class="faq-filter__tab is-active" data-faq-filter="all" role="tab" aria-selected="true">All</button>
          <button class="faq-filter__tab" data-faq-filter="about" role="tab" aria-selected="false">About Fynla</button>
          <button class="faq-filter__tab" data-faq-filter="getting-started" role="tab" aria-selected="false">Getting Started</button>
          <button class="faq-filter__tab" data-faq-filter="features" role="tab" aria-selected="false">Features &amp; Capabilities</button>
          <button class="faq-filter__tab" data-faq-filter="pricing" role="tab" aria-selected="false">Pricing &amp; Plans</button>
          <button class="faq-filter__tab" data-faq-filter="security" role="tab" aria-selected="false">Security &amp; Privacy</button>
          <button class="faq-filter__tab" data-faq-filter="technical" role="tab" aria-selected="false">Technical</button>
        </div>
      </div>
    </section>

    <!-- ================================================================
         FAQ CONTENT  [id=faq-content]
         FAQ accordion markup is written manually here (not using the
         partials/modules/faq.php partial) because this page has
         multiple categories that need to be filterable by JS. The
         faq.php partial renders one monolithic accordion â€” here we need
         individually hideable category groups. The faq__* CSS and JS
         from site.js are reused; only the grouping wrapper is new.
         ================================================================ -->
    <section id="faq-content" class="faq-content" aria-live="polite" aria-label="FAQ answers">
      <div class="faq-content__inner">

        <!-- Category: About Fynla -->
        <div class="faq-group" data-faq-category="about">
          <h2 class="faq-group__label">About Fynla</h2>
          <dl class="faq__list">

            <div class="faq__item" data-faq-item>
              <dt class="faq__question">
                <button class="faq__toggle" aria-expanded="false" aria-controls="faq-about-0">What is Fynla? <span class="faq__icon" aria-hidden="true"></span></button>
              </dt>
              <dd class="faq__answer" id="faq-about-0" hidden>
                <div class="faq__answer-inner">Fynla is a UK financial planning platform that helps you see your complete financial picture â€” pensions, property, investments, protection, tax, and retirement planning â€” in one place. It's designed for people who want to plan their finances properly but don't want to pay thousands for an independent financial adviser.</div>
              </dd>
            </div>

            <div class="faq__item" data-faq-item>
              <dt class="faq__question">
                <button class="faq__toggle" aria-expanded="false" aria-controls="faq-about-1">Is Fynla a financial adviser? <span class="faq__icon" aria-hidden="true"></span></button>
              </dt>
              <dd class="faq__answer" id="faq-about-1" hidden>
                <div class="faq__answer-inner">No. Fynla is a planning tool, not a regulated financial adviser. We help you understand your financial position, model scenarios, and make informed decisions â€” but we don't recommend specific financial products or give personalised financial advice. For complex situations, we'd encourage you to use Fynla alongside a qualified adviser.</div>
              </dd>
            </div>

            <div class="faq__item" data-faq-item>
              <dt class="faq__question">
                <button class="faq__toggle" aria-expanded="false" aria-controls="faq-about-2">Who is Fynla for? <span class="faq__icon" aria-hidden="true"></span></button>
              </dt>
              <dd class="faq__answer" id="faq-about-2" hidden>
                <div class="faq__answer-inner">Anyone in the UK who wants to understand and plan their finances. We have features for every stage of life â€” from students building their first savings habits to pre-retirees planning drawdown strategies. Most of our users are people who know they should be planning but haven't had the right tools to do it properly.</div>
              </dd>
            </div>

            <div class="faq__item" data-faq-item>
              <dt class="faq__question">
                <button class="faq__toggle" aria-expanded="false" aria-controls="faq-about-3">How is Fynla different from budgeting apps like Emma or Plum? <span class="faq__icon" aria-hidden="true"></span></button>
              </dt>
              <dd class="faq__answer" id="faq-about-3" hidden>
                <div class="faq__answer-inner">Budgeting apps track where your money went (backward-looking). Fynla plans where your money is going (forward-looking). Fynla covers pensions, retirement projections, Inheritance Tax planning, protection analysis, and long-term financial modelling â€” things budgeting apps don't touch.</div>
              </dd>
            </div>

            <div class="faq__item" data-faq-item>
              <dt class="faq__question">
                <button class="faq__toggle" aria-expanded="false" aria-controls="faq-about-4">How is Fynla different from an independent financial adviser or wealth manager? <span class="faq__icon" aria-hidden="true"></span></button>
              </dt>
              <dd class="faq__answer" id="faq-about-4" hidden>
                <div class="faq__answer-inner">An independent financial adviser provides personalised advice and can execute transactions (buy products, transfer pensions). Fynla provides the planning, projections, and analysis â€” the part most people actually need â€” at a fraction of the cost. Many users find that Fynla either replaces their need for an adviser or makes their sessions shorter and more productive.</div>
              </dd>
            </div>

            <div class="faq__item" data-faq-item>
              <dt class="faq__question">
                <button class="faq__toggle" aria-expanded="false" aria-controls="faq-about-5">Is Fynla giving me tax advice? <span class="faq__icon" aria-hidden="true"></span></button>
              </dt>
              <dd class="faq__answer" id="faq-about-5" hidden>
                <div class="faq__answer-inner">No. Fynla is a planning tool that helps you understand your position based on current UK tax rules. For complex estate planning, we'd recommend consulting a qualified adviser â€” and Fynla gives you the numbers to make that conversation productive.</div>
              </dd>
            </div>

          </dl>
        </div>

        <!-- Category: Getting Started -->
        <div class="faq-group" data-faq-category="getting-started">
          <h2 class="faq-group__label">Getting Started</h2>
          <dl class="faq__list">

            <div class="faq__item" data-faq-item>
              <dt class="faq__question">
                <button class="faq__toggle" aria-expanded="false" aria-controls="faq-gs-0">How long does it take to set up? <span class="faq__icon" aria-hidden="true"></span></button>
              </dt>
              <dd class="faq__answer" id="faq-gs-0" hidden>
                <div class="faq__answer-inner">Most people complete their initial setup in 15â€“20 minutes. You'll add your key financial data â€” pensions, property, savings, insurance â€” and immediately see your dashboard. You can add more detail over time.</div>
              </dd>
            </div>

            <div class="faq__item" data-faq-item>
              <dt class="faq__question">
                <button class="faq__toggle" aria-expanded="false" aria-controls="faq-gs-1">Can I try it before signing up? <span class="faq__icon" aria-hidden="true"></span></button>
              </dt>
              <dd class="faq__answer" id="faq-gs-1" hidden>
                <div class="faq__answer-inner">Yes. Our interactive demo lets you explore every feature using pre-built personas with realistic sample data. No account needed, no personal data required.</div>
              </dd>
            </div>

            <div class="faq__item" data-faq-item>
              <dt class="faq__question">
                <button class="faq__toggle" aria-expanded="false" aria-controls="faq-gs-2">Do I need to connect my bank accounts? <span class="faq__icon" aria-hidden="true"></span></button>
              </dt>
              <dd class="faq__answer" id="faq-gs-2" hidden>
                <div class="faq__answer-inner">No. Fynla doesn't require bank connections. You enter your data manually, which means you stay in full control and don't need to share banking credentials. We're exploring optional open banking integrations for the future.</div>
              </dd>
            </div>

            <div class="faq__item" data-faq-item>
              <dt class="faq__question">
                <button class="faq__toggle" aria-expanded="false" aria-controls="faq-gs-3">What data do I need to get started? <span class="faq__icon" aria-hidden="true"></span></button>
              </dt>
              <dd class="faq__answer" id="faq-gs-3" hidden>
                <div class="faq__answer-inner">At minimum: your pension values (check provider statements or online portals), property value estimate, savings and investment balances, and any outstanding debts. The more complete your data, the more accurate your plan â€” but you can start with the basics and add detail over time.</div>
              </dd>
            </div>

            <div class="faq__item" data-faq-item>
              <dt class="faq__question">
                <button class="faq__toggle" aria-expanded="false" aria-controls="faq-gs-4">How do I value my property? <span class="faq__icon" aria-hidden="true"></span></button>
              </dt>
              <dd class="faq__answer" id="faq-gs-4" hidden>
                <div class="faq__answer-inner">Enter your best estimate of current market value. You can update this periodically â€” many people check Zoopla or Rightmove for comparable sales. Fynla doesn't auto-value property, so you stay in control of the estimate.</div>
              </dd>
            </div>

            <div class="faq__item" data-faq-item>
              <dt class="faq__question">
                <button class="faq__toggle" aria-expanded="false" aria-controls="faq-gs-5">How often should I update my data? <span class="faq__icon" aria-hidden="true"></span></button>
              </dt>
              <dd class="faq__answer" id="faq-gs-5" hidden>
                <div class="faq__answer-inner">Property and pension values: every 3â€“6 months. Savings and investment balances: monthly if you want precision, quarterly if you want simplicity. Fynla prompts you when data is getting stale.</div>
              </dd>
            </div>

          </dl>
        </div>

        <!-- Category: Features & Capabilities -->
        <div class="faq-group" data-faq-category="features">
          <h2 class="faq-group__label">Features &amp; Capabilities</h2>
          <dl class="faq__list">

            <div class="faq__item" data-faq-item>
              <dt class="faq__question">
                <button class="faq__toggle" aria-expanded="false" aria-controls="faq-feat-0">What features does Fynla include? <span class="faq__icon" aria-hidden="true"></span></button>
              </dt>
              <dd class="faq__answer" id="faq-feat-0" hidden>
                <div class="faq__answer-inner">Fynla has 32 features across financial planning, retirement, pensions, property, investments, protection, tax, and estate planning. Key highlights include: net worth dashboard, pension tracker, "When Can I Retire?" calculator, Inheritance Tax planning, protection gap analysis, Monte Carlo simulations, In Case of Emergency letters, and Fyn AI assistant.</div>
              </dd>
            </div>

            <div class="faq__item" data-faq-item>
              <dt class="faq__question">
                <button class="faq__toggle" aria-expanded="false" aria-controls="faq-feat-1">What is Fyn? <span class="faq__icon" aria-hidden="true"></span></button>
              </dt>
              <dd class="faq__answer" id="faq-feat-1" hidden>
                <div class="faq__answer-inner">Fyn is our AI assistant built into the platform. Ask Fyn any financial planning question in plain English â€” from "what's an ISA?" to "how does taper relief work?" â€” and get a clear, jargon-free answer. Fyn also helps you navigate the platform and understand your plan.</div>
              </dd>
            </div>

            <div class="faq__item" data-faq-item>
              <dt class="faq__question">
                <button class="faq__toggle" aria-expanded="false" aria-controls="faq-feat-2">Does Fynla do Monte Carlo simulations? <span class="faq__icon" aria-hidden="true"></span></button>
              </dt>
              <dd class="faq__answer" id="faq-feat-2" hidden>
                <div class="faq__answer-inner">Yes (Premium tier). Fynla runs thousands of market scenarios against your financial plan to give you a probability-based confidence level rather than a single projection. This is the same approach used by professional financial planners.</div>
              </dd>
            </div>

            <div class="faq__item" data-faq-item>
              <dt class="faq__question">
                <button class="faq__toggle" aria-expanded="false" aria-controls="faq-feat-3">Can I plan as a couple? <span class="faq__icon" aria-hidden="true"></span></button>
              </dt>
              <dd class="faq__answer" id="faq-feat-3" hidden>
                <div class="faq__answer-inner">Yes. A single Fynla subscription supports both individual and joint planning. See combined net worth, joint retirement income projections, household protection needs, and Inheritance Tax for your combined estate.</div>
              </dd>
            </div>

            <div class="faq__item" data-faq-item>
              <dt class="faq__question">
                <button class="faq__toggle" aria-expanded="false" aria-controls="faq-feat-4">Does the retirement calculator include the state pension? <span class="faq__icon" aria-hidden="true"></span></button>
              </dt>
              <dd class="faq__answer" id="faq-feat-4" hidden>
                <div class="faq__answer-inner">Yes. Enter your state pension forecast from gov.uk and Fynla incorporates it into your total retirement income projection.</div>
              </dd>
            </div>

            <div class="faq__item" data-faq-item>
              <dt class="faq__question">
                <button class="faq__toggle" aria-expanded="false" aria-controls="faq-feat-5">Does Fynla cover the April 2027 pension Inheritance Tax changes? <span class="faq__icon" aria-hidden="true"></span></button>
              </dt>
              <dd class="faq__answer" id="faq-feat-5" hidden>
                <div class="faq__answer-inner">Yes. Our Inheritance Tax planning suite (Premium tier) lets you model your estate under both current rules and the post-April 2027 rules where unused pension pots are included in your estate for Inheritance Tax purposes.</div>
              </dd>
            </div>

            <div class="faq__item" data-faq-item>
              <dt class="faq__question">
                <button class="faq__toggle" aria-expanded="false" aria-controls="faq-feat-6">What is included in the In Case of Emergency letter? <span class="faq__icon" aria-hidden="true"></span></button>
              </dt>
              <dd class="faq__answer" id="faq-feat-6" hidden>
                <div class="faq__answer-inner">The In Case of Emergency letter is a comprehensive summary of your entire financial life â€” accounts, policies, pensions, property, debts, contacts, and instructions â€” designed so your family can quickly understand and access everything if the worst happens.</div>
              </dd>
            </div>

            <div class="faq__item" data-faq-item>
              <dt class="faq__question">
                <button class="faq__toggle" aria-expanded="false" aria-controls="faq-feat-7">Does Fynla sell insurance? <span class="faq__icon" aria-hidden="true"></span></button>
              </dt>
              <dd class="faq__answer" id="faq-feat-7" hidden>
                <div class="faq__answer-inner">No. Fynla calculates your protection gap â€” we don't sell insurance products, earn commissions, or recommend specific providers.</div>
              </dd>
            </div>

          </dl>
        </div>

        <!-- Category: Pricing & Plans -->
        <div class="faq-group" data-faq-category="pricing">
          <h2 class="faq-group__label">Pricing &amp; Plans</h2>
          <dl class="faq__list">

            <div class="faq__item" data-faq-item>
              <dt class="faq__question">
                <button class="faq__toggle" aria-expanded="false" aria-controls="faq-price-0">How much does Fynla cost? <span class="faq__icon" aria-hidden="true"></span></button>
              </dt>
              <dd class="faq__answer" id="faq-price-0" hidden>
                <div class="faq__answer-inner">Student tier from approximately &pound;3/month, Standard at &pound;8.50/month, and Premium at &pound;20/month. Annual billing gives you a discount. No hidden fees, no commission, no lock-in. See our pricing page for full details.</div>
              </dd>
            </div>

            <div class="faq__item" data-faq-item>
              <dt class="faq__question">
                <button class="faq__toggle" aria-expanded="false" aria-controls="faq-price-1">What is the difference between the plans? <span class="faq__icon" aria-hidden="true"></span></button>
              </dt>
              <dd class="faq__answer" id="faq-price-1" hidden>
                <div class="faq__answer-inner">Each tier adds more depth. Student covers budgeting, savings, and basic pension tracking. Standard adds full retirement projections, property, investments, and protection analysis. Premium adds Monte Carlo simulations, Inheritance Tax planning, In Case of Emergency letters, and advanced scenario modelling.</div>
              </dd>
            </div>

            <div class="faq__item" data-faq-item>
              <dt class="faq__question">
                <button class="faq__toggle" aria-expanded="false" aria-controls="faq-price-2">Is there a free trial? <span class="faq__icon" aria-hidden="true"></span></button>
              </dt>
              <dd class="faq__answer" id="faq-price-2" hidden>
                <div class="faq__answer-inner">Yes. Try Fynla free with full access to all features in your chosen tier. No credit card required to start.</div>
              </dd>
            </div>

            <div class="faq__item" data-faq-item>
              <dt class="faq__question">
                <button class="faq__toggle" aria-expanded="false" aria-controls="faq-price-3">What happens when my free trial ends? <span class="faq__icon" aria-hidden="true"></span></button>
              </dt>
              <dd class="faq__answer" id="faq-price-3" hidden>
                <div class="faq__answer-inner">You'll be prompted to choose a plan. If you don't subscribe, your account remains accessible in read-only mode â€” you won't lose any data. You can subscribe at any time to regain full access.</div>
              </dd>
            </div>

            <div class="faq__item" data-faq-item>
              <dt class="faq__question">
                <button class="faq__toggle" aria-expanded="false" aria-controls="faq-price-4">Can I change plans? <span class="faq__icon" aria-hidden="true"></span></button>
              </dt>
              <dd class="faq__answer" id="faq-price-4" hidden>
                <div class="faq__answer-inner">Yes, upgrade or downgrade at any time. Changes take effect from your next billing cycle.</div>
              </dd>
            </div>

            <div class="faq__item" data-faq-item>
              <dt class="faq__question">
                <button class="faq__toggle" aria-expanded="false" aria-controls="faq-price-5">What payment methods do you accept? <span class="faq__icon" aria-hidden="true"></span></button>
              </dt>
              <dd class="faq__answer" id="faq-price-5" hidden>
                <div class="faq__answer-inner">We accept all major credit and debit cards. Payments are processed securely through Stripe.</div>
              </dd>
            </div>

            <div class="faq__item" data-faq-item>
              <dt class="faq__question">
                <button class="faq__toggle" aria-expanded="false" aria-controls="faq-price-6">What if I cancel? <span class="faq__icon" aria-hidden="true"></span></button>
              </dt>
              <dd class="faq__answer" id="faq-price-6" hidden>
                <div class="faq__answer-inner">You can export your data at any time. Cancel monthly plans any time; annual plans run to the end of the 12-month period. Your data is retained for 30 days after cancellation, then permanently deleted.</div>
              </dd>
            </div>

          </dl>
        </div>

        <!-- Category: Security & Privacy -->
        <div class="faq-group" data-faq-category="security">
          <h2 class="faq-group__label">Security &amp; Privacy</h2>
          <dl class="faq__list">

            <div class="faq__item" data-faq-item>
              <dt class="faq__question">
                <button class="faq__toggle" aria-expanded="false" aria-controls="faq-sec-0">Is my financial data safe? <span class="faq__icon" aria-hidden="true"></span></button>
              </dt>
              <dd class="faq__answer" id="faq-sec-0" hidden>
                <div class="faq__answer-inner">Yes. Your data is encrypted in transit and at rest using industry-standard encryption. We don't share your data with third parties, don't sell data to advertisers, and don't earn commission from financial product providers.</div>
              </dd>
            </div>

            <div class="faq__item" data-faq-item>
              <dt class="faq__question">
                <button class="faq__toggle" aria-expanded="false" aria-controls="faq-sec-1">Where is my data stored? <span class="faq__icon" aria-hidden="true"></span></button>
              </dt>
              <dd class="faq__answer" id="faq-sec-1" hidden>
                <div class="faq__answer-inner">Fynla data is stored on secure UK-hosted servers.</div>
              </dd>
            </div>

            <div class="faq__item" data-faq-item>
              <dt class="faq__question">
                <button class="faq__toggle" aria-expanded="false" aria-controls="faq-sec-2">Does Fynla sell my data? <span class="faq__icon" aria-hidden="true"></span></button>
              </dt>
              <dd class="faq__answer" id="faq-sec-2" hidden>
                <div class="faq__answer-inner">Never. Your subscription is our only revenue source. We don't sell data, don't share it with product providers, and don't display advertising.</div>
              </dd>
            </div>

            <div class="faq__item" data-faq-item>
              <dt class="faq__question">
                <button class="faq__toggle" aria-expanded="false" aria-controls="faq-sec-3">Can I export my data? <span class="faq__icon" aria-hidden="true"></span></button>
              </dt>
              <dd class="faq__answer" id="faq-sec-3" hidden>
                <div class="faq__answer-inner">Yes. You can export your data at any time in standard formats.</div>
              </dd>
            </div>

          </dl>
        </div>

        <!-- Category: Technical -->
        <div class="faq-group" data-faq-category="technical">
          <h2 class="faq-group__label">Technical</h2>
          <dl class="faq__list">

            <div class="faq__item" data-faq-item>
              <dt class="faq__question">
                <button class="faq__toggle" aria-expanded="false" aria-controls="faq-tech-0">Does Fynla work on mobile? <span class="faq__icon" aria-hidden="true"></span></button>
              </dt>
              <dd class="faq__answer" id="faq-tech-0" hidden>
                <div class="faq__answer-inner">Yes. Fynla is fully responsive and works on smartphones, tablets, and desktops.</div>
              </dd>
            </div>

            <div class="faq__item" data-faq-item>
              <dt class="faq__question">
                <button class="faq__toggle" aria-expanded="false" aria-controls="faq-tech-1">Which browsers are supported? <span class="faq__icon" aria-hidden="true"></span></button>
              </dt>
              <dd class="faq__answer" id="faq-tech-1" hidden>
                <div class="faq__answer-inner">Fynla works on all modern browsers â€” Chrome, Firefox, Safari, and Edge.</div>
              </dd>
            </div>

            <div class="faq__item" data-faq-item>
              <dt class="faq__question">
                <button class="faq__toggle" aria-expanded="false" aria-controls="faq-tech-2">Do I need to install anything? <span class="faq__icon" aria-hidden="true"></span></button>
              </dt>
              <dd class="faq__answer" id="faq-tech-2" hidden>
                <div class="faq__answer-inner">No. Fynla is a web application â€” just sign up and start using it in your browser.</div>
              </dd>
            </div>

          </dl>
        </div>

      </div>
    </section>

    <!-- ================================================================
         STILL HAVE QUESTIONS  [id=still-questions]
         ================================================================ -->
    <?php
    $module = [
      'id'      => 'still-questions',
      'heading' => 'Still have questions?',
      'subtext' => 'Can\'t find what you\'re looking for? Get in touch and we\'ll help.',
      'actions' => [
        ['text' => 'Contact us',  'href' => '/contact',  'primary' => true],
        ['text' => 'Try the demo', 'href' => '/?demo=true', 'primary' => false],
      ],
    ];
    include __DIR__ . '/partials/modules/cta-band.php';
    ?>

  </main>

  <?php include __DIR__ . '/partials/footer.php'; ?>

  <script src="/pages/js/site.js?v=1" defer></script>
  <script src="/pages/js/faq.js?v=1" defer></script>

</body>
</html>
