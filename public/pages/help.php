<?php

use App\Services\TaxConfigService;

// Every figure on this page comes from TaxConfigService (Rule 2); each rule
// names its source beside it (Rule 23). Audit: docs/help-audit-2026-09-26.md.
$helpTaxConfig = app(TaxConfigService::class);
$helpPounds = static fn ($v): string => '£'.number_format((int) $v);

// ISAs: gov.uk "How ISAs work" (https://www.gov.uk/individual-savings-accounts/how-isas-work)
// and "Junior ISAs" (https://www.gov.uk/junior-individual-savings-accounts).
$helpIsa = $helpTaxConfig->getISAAllowances();
$helpIsaAllowance = $helpPounds($helpIsa['annual_allowance'] ?? 0);
$helpJisaAllowance = $helpPounds($helpIsa['junior_isa']['annual_allowance'] ?? 0);

// Inheritance Tax: gov.uk "How Inheritance Tax works" (https://www.gov.uk/inheritance-tax),
// "Inheritance Tax: residence nil rate band"
// (https://www.gov.uk/guidance/inheritance-tax-residence-nil-rate-band) and
// "Gifts" (https://www.gov.uk/inheritance-tax/gifts).
$helpIht = $helpTaxConfig->getInheritanceTax();
$helpIhtRate = (int) round(((float) ($helpIht['standard_rate'] ?? 0)) * 100).'%';
$helpNrb = $helpPounds($helpIht['nil_rate_band'] ?? 0);
$helpRnrb = $helpPounds($helpIht['residence_nil_rate_band'] ?? 0);
$helpRnrbTaper = $helpPounds($helpIht['rnrb_taper_threshold'] ?? 0);
$helpRnrbTaperPer = $helpPounds(1 / max(0.0001, (float) ($helpIht['rnrb_taper_rate'] ?? 0.5)));
$helpCltRate = (int) round($helpTaxConfig->getCLTLifetimeRate() * 100).'%';
// Gifts to people: exempt after the taper table's last band; taper starts
// where full tax ends (IHTA 1984 s7, https://www.gov.uk/inheritance-tax/gifts).
$helpTaperBands = $helpTaxConfig->getTaperRelief('pet');
$helpGiftExemptYears = (int) (collect($helpTaperBands)->firstWhere('tax_rate', 0)['min_years'] ?? 0);
$helpTaperStartYears = (int) (collect($helpTaperBands)->firstWhere('min_years', 0)['max_years'] ?? 0);

// Long-term UK residence decides Inheritance Tax scope from 6 April 2025:
// IHTA 1984 s6A, HMRC IHTM47020
// (https://www.gov.uk/hmrc-internal-manuals/inheritance-tax-manual/ihtm47020).
$helpResidence = $helpTaxConfig->getDomicile()['long_term_residence'] ?? [];
$helpResidenceYears = (int) ($helpResidence['qualifying_years'] ?? 0);
$helpResidenceLookback = (int) ($helpResidence['lookback_years'] ?? 0);

// Pensions: gov.uk "Tax on your private pension: annual allowance"
// (https://www.gov.uk/tax-on-your-private-pension/annual-allowance) and
// Finance Act 2004 s190 (https://www.legislation.gov.uk/ukpga/2004/12/section/190).
$helpPension = $helpTaxConfig->getPensionAllowances();
$helpAnnualAllowance = $helpPounds($helpPension['annual_allowance'] ?? 0);
$helpCarryForwardYears = (int) ($helpPension['carry_forward_years'] ?? 0);
$helpTaper = $helpPension['tapered_annual_allowance'] ?? [];
$helpTaperThreshold = $helpPounds($helpTaper['threshold_income'] ?? 0);
$helpTaperAdjusted = $helpPounds($helpTaper['adjusted_income_threshold'] ?? 0);
$helpTaperMinimum = $helpPounds($helpTaper['minimum_allowance'] ?? 0);
$helpMpaa = $helpPounds($helpPension['money_purchase_annual_allowance'] ?? 0);
$helpBasicAmount = $helpPounds($helpPension['relevant_earnings_minimum'] ?? 0);

// Protection shortfall assumptions: Fynla's own planning assumptions, from
// config (CoverageGapAnalyzer reads the same keys).
$helpProtection = $helpTaxConfig->getProtectionConfig();
$helpFinalExpenses = $helpPounds($helpProtection['final_expenses'] ?? 0);
$helpEducationPerYear = $helpPounds($helpProtection['education_cost_per_year'] ?? 0);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <!-- Favicon -->
  <link rel="icon" type="image/png" href="/images/logos/favicon.png" />
  <link rel="icon" type="image/x-icon" href="/images/logos/favicon.ico" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Help &amp; Documentation — Using Fynla | Fynla</title>
  <meta name="description" content="How to use Fynla: getting started with Fyn, your dashboard and actions, tax strategy, protection, estate, pensions, investments, savings, family and troubleshooting." />
  <link rel="canonical" href="https://fynla.org/help" />

  <!-- Open Graph -->
  <meta property="og:type"        content="website" />
  <meta property="og:title"       content="Help &amp; Documentation — Using Fynla | Fynla" />
  <meta property="og:description" content="Comprehensive guide to using Fynla — getting started, dashboard overview, protection module, estate planning, retirement, investments, savings, family management, and troubleshooting." />
  <meta property="og:image"       content="https://fynla.org/images/og/help.jpg" />
  <meta property="og:url"         content="https://fynla.org/help" />

  <!-- Twitter Card -->
  <meta name="twitter:card"        content="summary_large_image" />
  <meta name="twitter:title"       content="Help &amp; Documentation — Using Fynla | Fynla" />
  <meta name="twitter:description" content="Comprehensive guide to using Fynla — getting started, dashboard overview, protection module, estate planning, retirement, investments, savings, family management, and troubleshooting." />
  <meta name="twitter:image"       content="https://fynla.org/images/og/help.jpg" />

  <!-- hreflang -->
  <link rel="alternate" hreflang="en-GB"    href="https://fynla.org/help" />
  <link rel="alternate" hreflang="x-default" href="https://fynla.org/help" />

  <!-- JSON-LD structured data -->
  <script type="application/ld+json">
  {
    "@context": "https://schema.org",
    "@type": "WebPage",
    "name": "Help & Documentation — Using Fynla",
    "url": "https://fynla.org/help",
    "description": "Comprehensive guide to using Fynla financial planning platform — getting started, modules, family management, and troubleshooting.",
    "publisher": {
      "@type": "Organization",
      "name": "Fynla",
      "url": "https://fynla.org"
    }
  }
  </script>

  <!-- Critical CSS — above-fold: tokens, reset, skip-nav, nav skeleton, hero -->
  <style>
    :root{--raspberry-300:#F472B6;--raspberry-400:#EC4899;--raspberry-500:#E83E6D;--raspberry-600:#DB2777;--horizon-100:#F1F5F9;--horizon-200:#E2E8F0;--horizon-300:#CBD5E1;--horizon-400:#94A3B8;--horizon-500:#1F2A44;--horizon-600:#0F172A;--horizon-700:#020617;--spring-500:#20B486;--violet-500:#5854E6;--savannah-100:#FDFAF7;--eggshell-500:#F7F6F4;--neutral-400:#9CA3AF;--neutral-500:#717171;--neutral-600:#4B5563;--light-pink-100:#FAD6E0;--light-pink-200:#F5B3C5;--light-gray:#EEEEEE;--white:#FFFFFF;--white-70:rgba(255,255,255,0.70);--black-05:rgba(0,0,0,0.05);--font-primary:'Segoe UI','Inter',-apple-system,BlinkMacSystemFont,sans-serif;--radius-sm:0.375rem;--radius-md:0.5rem;--radius-lg:0.75rem;--radius-2xl:1rem;--radius-button:0.5rem;--radius-full:9999px;--shadow-sm:0 1px 2px 0 rgba(0,0,0,0.05);--shadow-lg:0 10px 15px -3px rgba(0,0,0,0.10),0 4px 6px -4px rgba(0,0,0,0.10);}
    *,*::before,*::after{box-sizing:border-box;margin:0;padding:0;}
    [hidden]{display:none !important;}
    html{scroll-behavior:smooth;}
    body{font-family:var(--font-primary);background:var(--eggshell-500);color:var(--horizon-500);line-height:1.5;min-height:100vh;}
    img{display:block;max-width:100%;}
    a{color:inherit;text-decoration:none;}
    ul,ol{list-style:none;}
    button{cursor:pointer;font-family:inherit;}
    .skip-nav{position:absolute;top:-100%;left:1rem;background:var(--raspberry-500);color:var(--white);padding:0.5rem 1rem;border-radius:var(--radius-md);font-weight:600;z-index:9999;transition:top 0.2s;}
    .skip-nav:focus{top:1rem;}
    .site-header{position:sticky;top:0;z-index:50;background:var(--white);box-shadow:var(--shadow-sm);border-bottom:1px solid var(--light-gray);}
    .nav-primary__inner{max-width:80rem;margin:0 auto;padding:0 1rem;display:flex;align-items:center;justify-content:flex-start;height:4rem;position:relative;}
    /* Hero critical */
    .help-hero{background:linear-gradient(to right,var(--horizon-500),var(--raspberry-500));overflow:hidden;}
    .help-hero__inner{max-width:80rem;margin:0 auto;padding:2.5rem 1rem;}
    .help-hero__heading{font-size:clamp(2.25rem,8vw,4.5rem);line-height:1;font-weight:900;color:var(--white);margin-bottom:1rem;}
    .help-hero__accent{color:var(--raspberry-300);}
    .help-hero__lead{font-size:1.125rem;color:var(--white-70);max-width:42rem;line-height:1.625;}
    @media(min-width:1024px){.help-hero__inner{padding-left:2rem;padding-right:2rem;}}
  </style>

  <link rel="stylesheet" href="/pages/css/global.css?v=113" />
  <link rel="stylesheet" href="/pages/css/help.css?v=2"   />
</head>
<body>

  <a href="#main-content" class="skip-nav">Skip to main content</a>

  <?php include __DIR__.'/partials/nav.php'; ?>

  <main id="main-content">

    <!-- ================================================================
         HERO  [id=hero]
         ================================================================ -->
    <section id="hero" class="help-hero" aria-labelledby="hero-heading">
      <div class="help-hero__inner">
        <h1 id="hero-heading" class="help-hero__heading">
          Help &amp; <span class="help-hero__accent">documentation</span>
        </h1>
        <p class="help-hero__lead">
          How to use Fynla, screen by screen.
        </p>
      </div>
    </section>

    <!-- ================================================================
         SEARCH + CONTENT LAYOUT  [id=help-body]
         ================================================================ -->
    <section id="help-body" class="help-body" aria-label="Help documentation">
      <div class="help-body__inner">

        <!-- Sidebar TOC -->
        <aside class="help-toc" aria-label="Table of contents">
          <div class="help-toc__card">
            <h2 class="help-toc__heading">Table of Contents</h2>
            <nav aria-label="Help sections">
              <ul class="help-toc__list">
                <li><a href="#getting-started"    class="help-toc__link" data-help-toc>Getting Started</a></li>
                <li><a href="#dashboard"           class="help-toc__link" data-help-toc>Dashboard and Actions</a></li>
                <li><a href="#tax-strategy"        class="help-toc__link" data-help-toc>Tax Strategy</a></li>
                <li><a href="#user-profile"        class="help-toc__link" data-help-toc>Your Details and Settings</a></li>
                <li><a href="#protection"          class="help-toc__link" data-help-toc>Protection</a></li>
                <li><a href="#estate"              class="help-toc__link" data-help-toc>Estate Planning</a></li>
                <li><a href="#retirement"          class="help-toc__link" data-help-toc>Retirement and Pensions</a></li>
                <li><a href="#investment-savings"  class="help-toc__link" data-help-toc>Investments and Savings</a></li>
                <li><a href="#planning"            class="help-toc__link" data-help-toc>Plans, Goals and What If</a></li>
                <li><a href="#family-spouse"       class="help-toc__link" data-help-toc>Family and Your Spouse</a></li>
                <li><a href="#mobile"              class="help-toc__link" data-help-toc>Fynla on Your Phone</a></li>
                <li><a href="#help-faqs"           class="help-toc__link" data-help-toc>Common Questions</a></li>
                <li><a href="#troubleshooting"     class="help-toc__link" data-help-toc>Troubleshooting</a></li>
                <li><a href="#contact-support"     class="help-toc__link" data-help-toc>Contact Support</a></li>
              </ul>
            </nav>
          </div>
        </aside>

        <!-- Main content sections -->
        <div class="help-sections">

          <!-- Getting Started -->
          <section id="getting-started" class="help-section" aria-labelledby="gs-heading">
            <h2 id="gs-heading" class="help-section__heading">Getting Started</h2>

            <div class="help-section__body">
              <h3 class="help-section__subheading">Welcome to Fynla</h3>
              <p class="help-section__text">
                Fynla is a financial planning app for people in the UK. It brings your tax, savings, investments, pensions, protection, estate and goals into one place, shows where you could save tax or close a gap, and tells you how to do it.
              </p>
            </div>

            <div class="help-section__body">
              <h3 class="help-section__subheading">Your first few minutes</h3>
              <ol class="help-section__list help-section__list--ordered">
                <li>Create your account on the registration page and enter the code we email you.</li>
                <li>Your dashboard opens with Fyn, Fynla's assistant, ready to ask a few quick questions: your work and income, your ISAs, your pensions, and your spouse or civil partner if you have one.</li>
                <li>After each set of details, Fyn takes you to the page it has just filled in and asks whether it looks right.</li>
                <li>At the end, Fyn builds your tax plan and takes you to it. Your actions are waiting on the Actions page.</li>
              </ol>
            </div>

            <div class="help-section__body">
              <h3 class="help-section__subheading">Meet Fyn</h3>
              <p class="help-section__text">Fyn is the assistant on every page. Open it with Chat with Fyn at the top of the page on a computer, or the Fyn button on your phone. Fyn can add or update your details, explain a figure, or answer a question about your plan. Fyn does not give regulated financial advice.</p>
            </div>
          </section>

          <!-- Dashboard and Actions -->
          <section id="dashboard" class="help-section" aria-labelledby="db-heading">
            <h2 id="db-heading" class="help-section__heading">Dashboard and Actions</h2>

            <div class="help-section__body">
              <h3 class="help-section__subheading">Your dashboard</h3>
              <ul class="help-section__list help-section__list--disc">
                <li><strong>Your level:</strong> how many actions you need to complete to reach the next level, and how you compare with other people using Fynla.</li>
                <li><strong>Focus areas:</strong> tabs for Save tax, Retirement, Protection, Savings, Investment, Estate and Goals. Each has a list of recommendations you can tick off or skip. Get more recommendations opens Fyn.</li>
                <li><strong>Your finances:</strong> your net worth, the cover you have in place, your emergency fund, your retirement savings against your target, and your investments. Select any of them to open the full page.</li>
              </ul>
            </div>

            <div class="help-section__body">
              <h3 class="help-section__subheading">The Actions page</h3>
              <p class="help-section__text">Every action Fynla has for you, grouped by when it needs doing:</p>
              <ul class="help-section__list help-section__list--disc">
                <li><strong>Before 5 April:</strong> allowances that do not carry over to the next tax year, with the number of days left.</li>
                <li><strong>Worth doing soon:</strong> no deadline, but waiting still costs you.</li>
                <li><strong>Waiting on you:</strong> details Fynla needs before its figures can be right. Each missing detail appears once.</li>
              </ul>
              <p class="help-section__text">Open any action to see why it matters for you, the steps to take with your own accounts and figures, and what it changes, such as your Income Tax before and after.</p>
            </div>
          </section>

          <!-- Tax Strategy -->
          <section id="tax-strategy" class="help-section" aria-labelledby="tax-heading">
            <h2 id="tax-heading" class="help-section__heading">Tax Strategy</h2>

            <div class="help-section__body">
              <h3 class="help-section__subheading">What it shows</h3>
              <p class="help-section__text">Tax Strategy is your tax plan. It shows the allowances you have used this tax year and what is left, including your ISA allowance of <?= $helpIsaAllowance ?>, and the strategies that would save you tax in the order to tackle them. For couples, it shows what you could save by sharing allowances or moving savings between you.</p>
            </div>

            <div class="help-section__body">
              <h3 class="help-section__subheading">When a strategy is missing</h3>
              <p class="help-section__text">Some strategies need a detail Fynla does not have yet, such as your savings balances or your spouse's income. They appear under Waiting on you on the Actions page. Add the detail, and the strategy appears with its figures.</p>
            </div>
          </section>

          <!-- Your Details and Settings -->
          <section id="user-profile" class="help-section" aria-labelledby="profile-heading">
            <h2 id="profile-heading" class="help-section__heading">Your Details and Settings</h2>

            <div class="help-section__body">
              <h3 class="help-section__subheading">Personal information</h3>
              <p class="help-section__text">In Settings, then Personal Info, you can update your name, email, date of birth, gender, marital status, phone, address, education, job, employment status and planned retirement age.</p>
            </div>

            <div class="help-section__body">
              <h3 class="help-section__subheading">Income</h3>
              <p class="help-section__text">Your income is under Cash Management, then Income. Add each job or source of income, including self-employment, rental, dividend and other income. Your spending is on the same page.</p>
            </div>

            <div class="help-section__body">
              <h3 class="help-section__subheading">Health</h3>
              <p class="help-section__text">Settings, then Health, holds your health and smoking status.</p>
            </div>

            <div class="help-section__body">
              <h3 class="help-section__subheading">Where you live, and Inheritance Tax</h3>
              <p class="help-section__text">Since 6 April 2025, whether Inheritance Tax applies to your assets worldwide depends on long-term UK residence, not domicile: you are a long-term UK resident if you have been UK resident for at least <?= $helpResidenceYears ?> of the previous <?= $helpResidenceLookback ?> tax years. See <a href="https://www.gov.uk/hmrc-internal-manuals/inheritance-tax-manual/ihtm47020" class="help-link">HMRC's Inheritance Tax Manual (IHTM47020)</a>.</p>
            </div>

            <div class="help-section__body">
              <h3 class="help-section__subheading">Security, privacy and your subscription</h3>
              <ul class="help-section__list help-section__list--disc">
                <li><strong>Security:</strong> turn on two-factor authentication with an authenticator app.</li>
                <li><strong>Privacy and Data:</strong> export all your data, or delete your account.</li>
                <li><strong>Subscription:</strong> see your plan and change it.</li>
              </ul>
            </div>
          </section>

          <!-- Protection -->
          <section id="protection" class="help-section" aria-labelledby="prot-heading">
            <h2 id="prot-heading" class="help-section__heading">Protection</h2>

            <div class="help-section__body">
              <h3 class="help-section__subheading">Your policies</h3>
              <p class="help-section__text">The Protection page lists your cover: life insurance, critical illness, income protection, disability, and sickness or illness cover. Choose Add New Policy to add one, or Upload Document to add it from a policy document. Open any policy to see or edit its details.</p>
            </div>

            <div class="help-section__body">
              <h3 class="help-section__subheading">Protection Shortfall</h3>
              <p class="help-section__text">Fynla compares the cover your family would need with the cover you have:</p>
              <ul class="help-section__list help-section__list--disc">
                <li><strong>Debt protection:</strong> your mortgage and other debts.</li>
                <li><strong>Income replacement:</strong> a lump sum large enough to keep paying your family's yearly income need.</li>
                <li><strong>Final expenses:</strong> Fynla assumes <?= $helpFinalExpenses ?> for funeral and immediate costs.</li>
                <li><strong>Education:</strong> Fynla assumes <?= $helpEducationPerYear ?> a year for each child's education.</li>
                <li><strong>Critical illness, sickness and disability cover:</strong> what you have against what you would need.</li>
              </ul>
              <p class="help-section__note">Your spouse's income reduces the cover you need once your spouse's account is linked and you both share your details.</p>
            </div>
          </section>

          <!-- Estate Planning -->
          <section id="estate" class="help-section" aria-labelledby="estate-heading">
            <h2 id="estate-heading" class="help-section__heading">Estate Planning</h2>

            <div class="help-section__body">
              <h3 class="help-section__subheading">Your estate page</h3>
              <p class="help-section__text">The Estate page shows your Inheritance Tax position, with cards for your will, power of attorney, gifts, life policies and trusts. Open the Inheritance Tax summary for the full calculation. On the free plan you see your estimated Inheritance Tax and a summary.</p>
            </div>

            <div class="help-section__body">
              <h3 class="help-section__subheading">How Inheritance Tax is worked out</h3>
              <p class="help-section__text">Inheritance Tax is charged at <?= $helpIhtRate ?> on the part of your estate above your tax-free allowance of <?= $helpNrb ?>. Leaving your home to your children or grandchildren adds a home allowance of up to <?= $helpRnrb ?>, which falls by £1 for every <?= $helpRnrbTaperPer ?> that your estate is worth over <?= $helpRnrbTaper ?>. For married couples and civil partners, the calculation page shows both deaths together: any allowance unused on the first death passes to the survivor.</p>
            </div>

            <div class="help-section__body">
              <h3 class="help-section__subheading">Gifts</h3>
              <ul class="help-section__list help-section__list--disc">
                <li><strong>Gifts to people:</strong> free of Inheritance Tax if you live for <?= $helpGiftExemptYears ?> years after making them. If you die <?= $helpTaperStartYears ?> to <?= $helpGiftExemptYears ?> years after a gift, taper relief reduces the tax on it.</li>
                <li><strong>Gifts into most trusts:</strong> Inheritance Tax of <?= $helpCltRate ?> is due straight away on the part above your tax-free allowance.</li>
              </ul>
              <p class="help-section__text">Source: <a href="https://www.gov.uk/inheritance-tax/gifts" class="help-link">GOV.UK, Inheritance Tax on gifts</a> and <a href="https://www.gov.uk/guidance/trusts-and-inheritance-tax" class="help-link">trusts and Inheritance Tax</a>.</p>
            </div>

            <div class="help-section__body">
              <h3 class="help-section__subheading">Will, power of attorney and trusts</h3>
              <ul class="help-section__list help-section__list--disc">
                <li><strong>Will:</strong> the Will Builder takes you through ten steps, from executors and guardians to gifts and signing. It is for England and Wales only. If you already have a will, you see its summary instead.</li>
                <li><strong>Power of attorney:</strong> record your lasting powers of attorney, or prepare one with the step-by-step form.</li>
                <li><strong>Trusts:</strong> record trusts you have set up or benefit from.</li>
                <li><strong>Letter to Spouse:</strong> a letter of practical instructions for your spouse. Without a spouse, it is called Expression of Wishes.</li>
              </ul>
            </div>
          </section>

          <!-- Retirement and Pensions -->
          <section id="retirement" class="help-section" aria-labelledby="ret-heading">
            <h2 id="ret-heading" class="help-section__heading">Retirement and Pensions</h2>

            <div class="help-section__body">
              <h3 class="help-section__subheading">Your retirement page</h3>
              <p class="help-section__text">The Retirement page shows your pensions and answers three questions: will I have enough income for retirement, am I saving enough, and, within 10 years of retiring, how should I draw down my pension.</p>
            </div>

            <div class="help-section__body">
              <h3 class="help-section__subheading">Types of pension</h3>
              <ul class="help-section__list help-section__list--disc">
                <li><strong>Defined contribution pensions:</strong> you and your employer pay into a pot that is invested. Workplace pensions, personal pensions and self-invested personal pensions (SIPPs) are all this type.</li>
                <li><strong>Defined benefit pensions:</strong> pay an income based on your salary and years of service, sometimes called final salary or career average pensions.</li>
                <li><strong>State Pension:</strong> based on your National Insurance record.</li>
              </ul>
            </div>

            <div class="help-section__body" id="avcs">
              <h3 class="help-section__subheading">Paying more in alongside a defined benefit pension</h3>
              <p class="help-section__text">If you are in a defined benefit scheme, you may be able to pay in more in one of two ways. Ask your scheme administrator which your scheme offers:</p>
              <ul class="help-section__list help-section__list--disc">
                <li><strong>Buying extra pension:</strong> some schemes let you buy more guaranteed income from the scheme itself.</li>
                <li><strong>Additional voluntary contributions (AVCs):</strong> payments into a separate defined contribution pot linked to your scheme, often run by another provider. The pot is invested and is yours to draw on at retirement.</li>
              </ul>
              <p class="help-section__text">AVCs get tax relief like any other pension payment and count towards your annual allowance. If your scheme offers neither, a personal pension works the same way. Source: <a href="https://helpfiles.thepensionsregulator.gov.uk/members/dbschememembership" class="help-link">The Pensions Regulator, defined benefit scheme membership</a>.</p>
            </div>

            <div class="help-section__body">
              <h3 class="help-section__subheading">Annual allowance</h3>
              <p class="help-section__text">You can pay up to <?= $helpAnnualAllowance ?> a year into pensions with tax relief, and no more than you earn (or <?= $helpBasicAmount ?> if you earn less). Unused allowance from the previous <?= $helpCarryForwardYears ?> tax years can be carried forward. The allowance is lower if your threshold income is over <?= $helpTaperThreshold ?> and your adjusted income is over <?= $helpTaperAdjusted ?>, down to <?= $helpTaperMinimum ?>, and it is <?= $helpMpaa ?> once you have taken money flexibly from a pension. Your progress is under Am I saving enough for retirement. Source: <a href="https://www.gov.uk/tax-on-your-private-pension/annual-allowance" class="help-link">GOV.UK, pension annual allowance</a>.</p>
            </div>
          </section>

          <!-- Investments and Savings -->
          <section id="investment-savings" class="help-section" aria-labelledby="inv-heading">
            <h2 id="inv-heading" class="help-section__heading">Investments and Savings</h2>

            <div class="help-section__body">
              <h3 class="help-section__subheading">Investments</h3>
              <ul class="help-section__list help-section__list--disc">
                <li><strong>Account types:</strong> Stocks and Shares ISAs, General Investment Accounts, onshore and offshore bonds, Venture Capital Trusts, Enterprise Investment Schemes, private company shares, crowdfunding and employee share schemes.</li>
                <li><strong>Holdings:</strong> add the funds and shares in each account, with their International Securities Identification Number if you have it.</li>
                <li><strong>Projections:</strong> Fynla runs many possible market outcomes to show a range for what your investments could be worth.</li>
              </ul>
            </div>

            <div class="help-section__body">
              <h3 class="help-section__subheading">Bank accounts and savings</h3>
              <ul class="help-section__list help-section__list--disc">
                <li><strong>Account types:</strong> current accounts, easy access, instant access, notice and fixed term savings, National Savings and Investments, Cash ISAs and Junior ISAs.</li>
                <li><strong>Emergency fund:</strong> Fynla aims for 6 months of spending if you are employed, 9 if you are self-employed or a contractor, and 3 if you are retired, and shows how many months your savings cover.</li>
              </ul>
            </div>

            <div class="help-section__body">
              <h3 class="help-section__subheading">ISA allowance</h3>
              <p class="help-section__text">You can pay up to <?= $helpIsaAllowance ?> into ISAs each tax year (6 April to 5 April), across Cash ISAs and Stocks and Shares ISAs together. A child can have up to <?= $helpJisaAllowance ?> a year paid into their Junior ISAs. Tax Strategy shows how much of your allowance is left. Source: <a href="https://www.gov.uk/individual-savings-accounts/how-isas-work" class="help-link">GOV.UK, how ISAs work</a>.</p>
            </div>
          </section>

          <!-- Plans, Goals and What If -->
          <section id="planning" class="help-section" aria-labelledby="plan-heading">
            <h2 id="plan-heading" class="help-section__heading">Plans, Goals and What If</h2>

            <div class="help-section__body">
              <ul class="help-section__list help-section__list--disc">
                <li><strong>Plans:</strong> a written plan for each area (investment, protection, retirement and estate, and each goal), ready to print. Find them under Planning, then Plans.</li>
                <li><strong>Holistic Plan:</strong> one plan across every area of your finances.</li>
                <li><strong>Goals and Life Events:</strong> set your goals and add events coming up in your life, so Fynla plans around them.</li>
                <li><strong>What If:</strong> see how a change would affect you, such as the death of a spouse.</li>
                <li><strong>Net Worth:</strong> your assets and debts, including property, businesses and valuables, with how your net worth has changed over time.</li>
              </ul>
            </div>
          </section>

          <!-- Family and Your Spouse -->
          <section id="family-spouse" class="help-section" aria-labelledby="fam-heading">
            <h2 id="fam-heading" class="help-section__heading">Family and Your Spouse</h2>

            <div class="help-section__body">
              <h3 class="help-section__subheading">Adding family members</h3>
              <p class="help-section__text">Go to Settings, then Family, and choose Add Family Member. You can add a spouse, partner, child, stepchild, parent or other dependant, with their name, date of birth, gender and whether they depend on you.</p>
            </div>

            <div class="help-section__body">
              <h3 class="help-section__subheading">Linking your spouse's account</h3>
              <p class="help-section__text">When you add your spouse with their email address, Fynla sends them an invitation. Nothing is linked or shared until they accept it, whether or not they already have a Fynla account.</p>
            </div>

            <div class="help-section__body">
              <h3 class="help-section__subheading">Sharing your details</h3>
              <p class="help-section__text">Sharing is one switch, in Settings, then Family. One of you asks to share, and the other accepts or declines. Once accepted, you each see the other's assets, debts and income, and your accounts are treated as one household. Either of you can stop sharing at any time.</p>
            </div>

            <div class="help-section__body">
              <h3 class="help-section__subheading">Joint ownership</h3>
              <p class="help-section__text">An account or property can be owned by you alone, jointly with your spouse, or in a trust. Property can also be owned as tenants in common, in shares you choose. A joint record is kept once, with your share of it. An ISA can only ever be in one person's name (<a href="https://www.gov.uk/individual-savings-accounts" class="help-link">GOV.UK</a>).</p>
            </div>
          </section>

          <!-- Fynla on Your Phone -->
          <section id="mobile" class="help-section" aria-labelledby="mobile-heading">
            <h2 id="mobile-heading" class="help-section__heading">Fynla on Your Phone</h2>

            <div class="help-section__body">
              <p class="help-section__text">On your phone, Fynla opens a version made for small screens, with your dashboard, actions, tax strategy, net worth, each module, goals, achievements and your conversations with Fyn. A few detailed tools, such as the full Inheritance Tax table and powers of attorney, open in the full web app.</p>
            </div>
          </section>

          <!-- Common Questions (FAQs) -->
          <section id="help-faqs" class="help-section" aria-labelledby="hfaq-heading">
            <h2 id="hfaq-heading" class="help-section__heading">Common Questions</h2>

            <?php
            $module = [
                'id' => 'help-page-faq',
                'heading' => '',
                'heading_tag' => 'h3',
                'items' => [
                    ['q' => 'How do I add a protection policy?',
                        'a' => 'On the Protection page, choose Add New Policy. Select the type (Life Insurance, Critical Illness, Income Protection, Disability, or Sickness or Illness) and fill in the details. You can also ask Fyn to add it.'],
                    ['q' => 'Why is my spouse\'s income not reducing my protection need?',
                        'a' => 'Fynla uses your spouse\'s income from their own account. Link your accounts in Settings, then Family, and turn on sharing; once your spouse accepts, their income is included.'],
                    ['q' => 'How is Inheritance Tax calculated?',
                        'a' => 'Inheritance Tax is charged at '.$helpIhtRate.' on the part of your estate above your tax-free allowance of '.$helpNrb.'. If you leave your home to your direct descendants (children, including adopted, foster and stepchildren, and grandchildren), a home allowance of up to '.$helpRnrb.' is added; it reduces by £1 for every '.$helpRnrbTaperPer.' that your estate is worth over '.$helpRnrbTaper.'. If you are married or in a civil partnership, any allowance left unused on the first death can be added to the survivor\'s.'],
                    ['q' => 'What is the difference between defined contribution and defined benefit pensions?',
                        'a' => 'A defined contribution (money purchase) pension is a pot: you and your employer pay in, it is invested, and you draw from it. A defined benefit (final salary or career average) pension pays a guaranteed income based on your salary and years of service.'],
                    ['q' => 'Can I have more than one ISA?',
                        'a' => 'Yes. You can pay into more than one ISA of the same type in a tax year, except a Lifetime ISA. Your total across all your ISAs cannot be more than '.$helpIsaAllowance.' per tax year (6 April to 5 April). A child can have one Cash Junior ISA and one Stocks and Shares Junior ISA, with up to '.$helpJisaAllowance.' a year paid in across them.'],
                    ['q' => 'How do I link my spouse\'s account?',
                        'a' => 'Go to Settings, then Family, and choose Add Family Member. Select spouse and enter their email address. We send them an invitation, and nothing is shared or linked until they accept it.'],
                    ['q' => 'How does the emergency fund work?',
                        'a' => 'Fynla aims for 6 months of spending if you are employed, 9 if you are self-employed or a contractor, and 3 if you are retired. Your savings page shows how many months your easy-to-reach savings cover.'],
                    ['q' => 'Can I export or print my data?',
                        'a' => 'Yes. Settings, then Privacy and Data, lets you download all your data as a spreadsheet file or in a format another app can read. Every plan has a Print button.'],
                    ['q' => 'Is my data secure?',
                        'a' => 'Your data is encrypted when it travels between your device and Fynla. You can turn on two-factor authentication in Settings, then Security, and download or delete your data at any time.'],
                ],
            ];
// Render FAQ items directly (not via partial) since heading is empty
// and this is embedded within an existing section.
?>
            <dl class="faq__list help-inline-faq">
              <?php foreach ($module['items'] as $i => $item) { ?>
              <div class="faq__item" data-faq-item>
                <dt class="faq__question">
                  <button class="faq__toggle" aria-expanded="false" aria-controls="hfaq-answer-<?= (int) $i ?>">
                    <?= htmlspecialchars($item['q'], ENT_QUOTES, 'UTF-8') ?>
                    <span class="faq__icon" aria-hidden="true"></span>
                  </button>
                </dt>
                <dd class="faq__answer" id="hfaq-answer-<?= (int) $i ?>" hidden>
                  <div class="faq__answer-inner"><?= nl2br(htmlspecialchars($item['a'], ENT_QUOTES, 'UTF-8')) ?></div>
                </dd>
              </div>
              <?php } ?>
            </dl>
          </section>

          <!-- Troubleshooting -->
          <section id="troubleshooting" class="help-section" aria-labelledby="trouble-heading">
            <h2 id="trouble-heading" class="help-section__heading">Troubleshooting</h2>

            <div class="help-section__body">
              <h3 class="help-section__subheading">A policy is missing from Protection Shortfall</h3>
              <ul class="help-section__list help-section__list--disc">
                <li>Check the policy is listed on the Protection page. If it is not, add it again with Add New Policy.</li>
                <li>Reload the page to fetch your latest details.</li>
              </ul>
            </div>

            <div class="help-section__body">
              <h3 class="help-section__subheading">My Inheritance Tax figure looks wrong</h3>
              <ul class="help-section__list help-section__list--disc">
                <li>Check your assets are all entered: property, investments, pensions and savings are added under their own pages, not on the Estate page.</li>
                <li>Check your mortgage and other debts are entered, as they reduce your estate.</li>
                <li>The home allowance only applies if you leave your home to your children or grandchildren.</li>
              </ul>
            </div>

            <div class="help-section__body">
              <h3 class="help-section__subheading">My changes are not saving</h3>
              <ul class="help-section__list help-section__list--disc">
                <li>Look for a message on the form saying which field needs attention.</li>
                <li>Check your internet connection, then try again.</li>
                <li>Sign out and back in.</li>
              </ul>
            </div>

            <div class="help-section__body">
              <h3 class="help-section__subheading">I could not link my spouse</h3>
              <ul class="help-section__list help-section__list--disc">
                <li>"That email address cannot be linked to your household" means the address is already part of another household, or cannot be used.</li>
                <li>You cannot add your own email address as your spouse.</li>
                <li>You can send up to 5 invitations an hour. If you see "Too many household invitations", wait an hour.</li>
              </ul>
            </div>
          </section>

          <!-- Contact Support -->
          <section id="contact-support" class="help-section" aria-labelledby="support-heading">
            <h2 id="support-heading" class="help-section__heading">Contact Support</h2>

            <div class="help-section__body">
              <p class="help-section__text">Need more help? Ask Fyn, or report a problem from the app: on a computer, choose Support, then Bug Report; on your phone, choose Report a problem. You can also contact our support team.</p>
            </div>

            <div class="help-support-card">
              <h3 class="help-support-card__heading">Support Information</h3>
              <ul class="help-section__list">
                <li><strong>Email:</strong> <a href="mailto:support@fynla.org" class="help-link">support@fynla.org</a></li>
              </ul>
            </div>

            <div class="help-notice-card">
              <h3 class="help-notice-card__heading">Important Note</h3>
              <p class="help-section__text">
                Fynla is a financial planning tool. It is <strong>not</strong> a regulated financial advice service. For advice personal to your circumstances, speak to a qualified financial adviser regulated by the Financial Conduct Authority (FCA).
              </p>
            </div>
          </section>

        </div>
      </div>
    </section>

    <!-- ================================================================
         CTA  [id=help-cta]
         ================================================================ -->
    <?php
    $module = [
        'id' => 'help-cta',
        'heading' => 'Ready to get started?',
        'subtext' => 'Create your free account and see your complete financial picture.',
        'actions' => [
            ['text' => 'Create your free account', 'href' => '/register', 'primary' => true],
            ['text' => 'Contact support',       'href' => '/contact',  'primary' => false],
        ],
    ];
include __DIR__.'/partials/modules/cta-band.php';
?>

  </main>

  <?php include __DIR__.'/partials/footer.php'; ?>

  <script src="/pages/js/site.js?v=1" defer></script>
  <script src="/pages/js/help.js?v=1" defer></script>

</body>
</html>
