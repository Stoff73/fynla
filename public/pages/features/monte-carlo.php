<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Monte Carlo Simulations — Model Investment Outcomes | Fynla</title>
  <meta name="description" content="Run Monte Carlo simulations on your investment portfolio with Fynla. Model thousands of market scenarios to understand the probability of reaching your financial goals." />
  <link rel="canonical" href="https://fynla.org/features/monte-carlo" />

  <!-- Open Graph -->
  <meta property="og:type"        content="article" />
  <meta property="og:title"       content="Monte Carlo Simulations — Model Investment Outcomes | Fynla" />
  <meta property="og:description" content="Run Monte Carlo simulations on your investment portfolio with Fynla. Model thousands of market scenarios to understand the probability of reaching your financial goals." />
  <meta property="og:image"       content="https://fynla.org/images/og/feat-monte-carlo.jpg" />
  <meta property="og:url"         content="https://fynla.org/features/monte-carlo" />

  <!-- Twitter Card -->
  <meta name="twitter:card"        content="summary_large_image" />
  <meta name="twitter:title"       content="Monte Carlo Simulations — Model Investment Outcomes | Fynla" />
  <meta name="twitter:description" content="Run Monte Carlo simulations on your investment portfolio with Fynla. Model thousands of market scenarios to understand the probability of reaching your financial goals." />
  <meta name="twitter:image"       content="https://fynla.org/images/og/feat-monte-carlo.jpg" />

  <!-- hreflang -->
  <link rel="alternate" hreflang="en-GB"    href="https://fynla.org/features/monte-carlo" />
  <link rel="alternate" hreflang="x-default" href="https://fynla.org/features/monte-carlo" />

  <!-- JSON-LD structured data -->
  <script type="application/ld+json">
  {
    "@context": "https://schema.org",
    "@type": "Article",
    "headline": "Monte Carlo Simulations — Model Investment Outcomes",
    "description": "Run Monte Carlo simulations on your investment portfolio with Fynla. Model thousands of market scenarios to understand the probability of reaching your financial goals.",
    "url": "https://fynla.org/features/monte-carlo",
    "publisher": {
      "@type": "Organization",
      "name": "Fynla",
      "url": "https://fynla.org"
    }
  }
  </script>

  <!-- Critical CSS -->
  <style>
    :root{--raspberry-300:#F472B6;--raspberry-400:#EC4899;--raspberry-500:#E83E6D;--raspberry-600:#DB2777;--horizon-100:#F1F5F9;--horizon-200:#E2E8F0;--horizon-300:#CBD5E1;--horizon-400:#94A3B8;--horizon-500:#1F2A44;--horizon-600:#0F172A;--horizon-700:#020617;--spring-400:#34D399;--spring-500:#20B486;--spring-600:#059669;--violet-500:#5854E6;--savannah-100:#FDFAF7;--eggshell-500:#F7F6F4;--neutral-400:#9CA3AF;--neutral-500:#717171;--neutral-600:#4B5563;--light-pink-50:#FDF0F4;--light-pink-100:#FAD6E0;--light-pink-200:#F5B3C5;--light-gray:#EEEEEE;--white:#FFFFFF;--white-70:rgba(255,255,255,0.70);--white-80:rgba(255,255,255,0.80);--black-05:rgba(0,0,0,0.05);--font-primary:'Segoe UI','Inter',-apple-system,BlinkMacSystemFont,sans-serif;--radius-md:0.5rem;--radius-lg:0.75rem;--radius-2xl:1rem;--radius-button:0.5rem;--radius-full:9999px;--shadow-sm:0 1px 2px 0 rgba(0,0,0,0.05);}
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
    .feat-hero{background:linear-gradient(to right,var(--horizon-500),var(--raspberry-500));overflow:hidden;}
    .feat-hero__inner{max-width:80rem;margin:0 auto;padding:2.5rem 1rem;}
    .feat-hero__heading{font-size:clamp(1.75rem,6vw,3.5rem);font-weight:900;color:var(--white);margin-bottom:1rem;line-height:1.1;}
    .feat-hero__subheadline{font-size:1.125rem;color:var(--white-70);margin-bottom:1.5rem;line-height:1.625;max-width:42rem;}
    .feat-hero__actions{display:flex;flex-direction:column;align-items:flex-start;gap:0.75rem;}
    @media(min-width:640px){.feat-hero__actions{flex-direction:row;align-items:center;}}
    @media(min-width:1024px){.feat-hero__inner{padding-left:2rem;padding-right:2rem;}}
  </style>
  <link rel="stylesheet" href="/pages/css/global.css?v=3"             />
  <link rel="stylesheet" href="/pages/css/feat-monte-carlo.css?v=1"   />
</head>
<body>

  <a href="#main-content" class="skip-nav">Skip to main content</a>

  <?php include __DIR__ . '/../partials/nav.php'; ?>

  <main id="main-content">

    <!-- ================================================================
         HERO  [id=hero]
         ================================================================ -->
    <section id="hero" class="feat-hero" aria-labelledby="hero-heading">
      <div class="feat-hero__inner">
        <h1 id="hero-heading" class="feat-hero__heading">
          One Projection Is a Guess.<br />A Thousand Is a Plan.
        </h1>
        <p class="feat-hero__subheadline">
          Markets don&rsquo;t move in straight lines. Fynla runs thousands of simulated scenarios &mdash; booms, crashes, and everything between &mdash; to show you the real probability of your financial plan succeeding.
        </p>
        <div class="feat-hero__actions">
          <a href="/register" class="feat-btn-primary">Start your free trial</a>
          <a href="/how-it-works" class="feat-btn-secondary">See how it works</a>
        </div>
      </div>
    </section>

    <!-- ================================================================
         PROBLEM  [id=problem]
         ================================================================ -->
    <section id="problem" class="feat-problem" aria-labelledby="problem-heading">
      <div class="feat-section__inner">
        <h2 id="problem-heading" class="feat-section__heading feat-section__heading--center">Your Plan Probably Assumes Everything Goes to Plan</h2>
        <div class="feat-cards-3">
          <article class="feat-card feat-card--pink" aria-labelledby="prob1-heading">
            <h3 id="prob1-heading" class="feat-card__title">Straight-line projections lie</h3>
            <p class="feat-card__body">A 6% average return sounds fine. But what if you get &minus;20% in year one of retirement? The order of returns matters enormously, and most tools ignore it.</p>
          </article>
          <article class="feat-card feat-card--pink" aria-labelledby="prob2-heading">
            <h3 id="prob2-heading" class="feat-card__title">One number isn&rsquo;t a plan</h3>
            <p class="feat-card__body">&ldquo;You&rsquo;ll have &pound;450,000 at retirement&rdquo; tells you nothing about how likely that is, or what happens if markets underperform for a decade.</p>
          </article>
          <article class="feat-card feat-card--pink" aria-labelledby="prob3-heading">
            <h3 id="prob3-heading" class="feat-card__title">False confidence is dangerous</h3>
            <p class="feat-card__body">A plan that works in the average case but fails in 30% of scenarios isn&rsquo;t a plan &mdash; it&rsquo;s a bet.</p>
          </article>
        </div>
      </div>
    </section>

    <!-- ================================================================
         MOCK-UP UI  [id=mockup]
         ================================================================ -->
    <section id="mockup" class="feat-mockup" aria-label="Monte Carlo fan chart visual example">
      <div class="feat-section__inner">
        <div class="feat-mc-card" role="img" aria-label="Illustration of Monte Carlo simulation results showing plan confidence level and outcome distribution">
          <div class="feat-mc-card__header">
            <span class="feat-mc-card__label">Plan confidence level</span>
            <span class="feat-mc-card__confidence">84%</span>
          </div>
          <div class="feat-mc-fan" aria-hidden="true">
            <div class="feat-mc-fan__band feat-mc-fan__band--best"></div>
            <div class="feat-mc-fan__band feat-mc-fan__band--upper"></div>
            <div class="feat-mc-fan__band feat-mc-fan__band--median"></div>
            <div class="feat-mc-fan__band feat-mc-fan__band--lower"></div>
            <div class="feat-mc-fan__band feat-mc-fan__band--worst"></div>
          </div>
          <div class="feat-mc-legend">
            <div class="feat-mc-legend__item"><span class="feat-mc-dot feat-mc-dot--best"></span>Best case &pound;920,000</div>
            <div class="feat-mc-legend__item"><span class="feat-mc-dot feat-mc-dot--median"></span>Median &pound;610,000</div>
            <div class="feat-mc-legend__item"><span class="feat-mc-dot feat-mc-dot--worst"></span>Worst case &pound;280,000</div>
          </div>
          <div class="feat-mc-card__footer">Based on 10,000 simulated scenarios &mdash; retirement age 63, drawdown &pound;28,000/yr</div>
        </div>
      </div>
    </section>

    <!-- ================================================================
         SOLUTION  [id=solution]
         ================================================================ -->
    <section id="solution" class="feat-solution" aria-labelledby="solution-heading">
      <div class="feat-section__inner">
        <h2 id="solution-heading" class="feat-section__heading feat-section__heading--white feat-section__heading--center">Real Confidence, Not False Precision</h2>
        <div class="feat-cards-3">
          <article class="feat-card feat-card--dark" aria-labelledby="sol1-heading">
            <h3 id="sol1-heading" class="feat-card__title feat-card__title--white">Thousands of Scenarios, One Confidence Level</h3>
            <p class="feat-card__body feat-card__body--faded">Fynla runs Monte Carlo simulations across your entire financial plan and gives you a clear confidence level: your plan succeeds in a given percentage of scenarios.</p>
          </article>
          <article class="feat-card feat-card--dark" aria-labelledby="sol2-heading">
            <h3 id="sol2-heading" class="feat-card__title feat-card__title--white">Visual Outcome Distribution</h3>
            <p class="feat-card__body feat-card__body--faded">See the range of possible outcomes as a fan chart. The best case, worst case, and most likely case &mdash; all visible at once.</p>
          </article>
          <article class="feat-card feat-card--dark" aria-labelledby="sol3-heading">
            <h3 id="sol3-heading" class="feat-card__title feat-card__title--white">Stress Test Your Assumptions</h3>
            <p class="feat-card__body feat-card__body--faded">What happens if inflation runs at 5% for a decade? What if you face a 2008-style crash in year one of retirement? Test specific scenarios.</p>
          </article>
          <article class="feat-card feat-card--dark" aria-labelledby="sol4-heading">
            <h3 id="sol4-heading" class="feat-card__title feat-card__title--white">Sequence of Returns Risk</h3>
            <p class="feat-card__body feat-card__body--faded">The biggest risk retirees face isn&rsquo;t low average returns &mdash; it&rsquo;s bad returns at the wrong time. Fynla explicitly models this risk.</p>
          </article>
          <article class="feat-card feat-card--dark" aria-labelledby="sol5-heading">
            <h3 id="sol5-heading" class="feat-card__title feat-card__title--white">Actionable Recommendations</h3>
            <p class="feat-card__body feat-card__body--faded">Confidence level below 80%? Fynla shows you which levers to pull &mdash; increase contributions, delay retirement, reduce drawdown &mdash; and how each change helps.</p>
          </article>
          <article class="feat-card feat-card--dark" aria-labelledby="sol6-heading">
            <h3 id="sol6-heading" class="feat-card__title feat-card__title--white">Updates as Life Changes</h3>
            <p class="feat-card__body feat-card__body--faded">Your confidence level updates automatically as you add data, change plans, or as markets move. It&rsquo;s a living dashboard, not a one-off report.</p>
          </article>
        </div>
      </div>
    </section>

    <!-- ================================================================
         EXPLAINER  [id=explainer]
         ================================================================ -->
    <section id="explainer" class="feat-explainer" aria-labelledby="explainer-heading">
      <div class="feat-section__inner feat-section__inner--narrow">
        <h2 id="explainer-heading" class="feat-section__heading feat-section__heading--center">What Is Monte Carlo Simulation? (In Plain English)</h2>
        <div class="feat-explainer__box">
          <p class="feat-explainer__body">Imagine you&rsquo;re planning a road trip. You could assume the motorway is always clear and you&rsquo;ll average 60mph. But in reality, you might hit traffic, roadworks, or detours.</p>
          <p class="feat-explainer__body">Monte Carlo simulation is like running that road trip 10,000 times with random traffic conditions each time. Instead of one estimated arrival time, you get a range: you&rsquo;ll arrive between 2pm and 4pm, with 85% confidence you&rsquo;ll be there by 3pm.</p>
          <p class="feat-explainer__body">Fynla does the same thing with your finances. Instead of one projection with one assumed growth rate, we run thousands of scenarios using historical market data &mdash; including crashes, booms, and flat periods. The result isn&rsquo;t a single number. It&rsquo;s a probability.</p>
        </div>
      </div>
    </section>

    <!-- ================================================================
         FAQ  [id=faq]
         ================================================================ -->
    <?php
    $module = [
      'id'       => 'faq',
      'modifier' => 'faq--dark',
      'heading'  => 'Frequently asked questions',
      'items'    => [
        [
          'q' => 'Does Fynla do Monte Carlo simulations?',
          'a' => 'Yes (Premium tier). Fynla runs thousands of market scenarios against your financial plan to give you a probability-based confidence level rather than a single projection. This is the same approach used by professional financial planners.',
        ],
        [
          'q' => 'What confidence level should I aim for?',
          'a' => 'Financial planners generally consider 80%+ a solid plan and 90%+ a very robust one. Below 70% suggests meaningful risk. Fynla shows you what changes would improve your level.',
        ],
        [
          'q' => 'Is the Monte Carlo analysis the same as what an IFA would do?',
          'a' => 'Many IFAs use similar Monte Carlo tools — but charge £1,000+ for the privilege. Fynla puts the same analytical power in your hands for a fraction of the cost.',
        ],
        [
          'q' => 'Can I see individual scenario paths?',
          'a' => 'Yes. The fan chart shows the distribution, and you can explore specific scenario paths including worst-case, best-case, and median outcomes.',
        ],
      ],
    ];
    include __DIR__ . '/../partials/modules/faq.php';
    ?>

    <!-- ================================================================
         FINAL CTA  [id=final-cta]
         ================================================================ -->
    <?php
    $module = [
      'id'      => 'final-cta',
      'heading' => 'How Confident Is Your Plan? Find Out in Minutes.',
      'subtext' => 'The demo runs Monte Carlo simulations on sample data so you can see exactly how it works — no maths degree required.',
      'actions' => [
        ['text' => 'Start your free trial', 'href' => '/register', 'primary' => true],
        ['text' => 'Try the demo',          'href' => '/register', 'primary' => false],
      ],
    ];
    include __DIR__ . '/../partials/modules/cta-band.php';
    ?>

    <!-- ================================================================
         RELATED LINKS  [id=related]
         ================================================================ -->
    <nav id="related" class="feat-related" aria-label="Related Fynla features">
      <div class="feat-section__inner">
        <h2 class="feat-related__heading">Explore more features</h2>
        <ul class="feat-related__list">
          <li><a href="/features/when-can-i-retire"   class="feat-related__link">When Can I Retire?</a></li>
          <li><a href="/features/pension-tracker"      class="feat-related__link">Pension Tracker</a></li>
          <li><a href="/features/net-worth-dashboard"  class="feat-related__link">Net Worth Dashboard</a></li>
          <li><a href="/features"                      class="feat-related__link">All features</a></li>
        </ul>
      </div>
    </nav>

  </main>

  <?php include __DIR__ . '/../partials/footer.php'; ?>

  <script src="/pages/js/site.js?v=3" defer></script>
  <script src="/pages/js/feat-monte-carlo.js?v=1" defer></script>

</body>
</html>
