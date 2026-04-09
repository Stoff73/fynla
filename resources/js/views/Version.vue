<template>
  <AppLayout>
    <div class="py-8">
      <!-- Header -->
      <div class="mb-8">
        <h1 class="text-h2 font-display text-horizon-500">Version Information</h1>
        <p class="mt-2 text-body-base text-neutral-500">
          Fynla - Financial Planning System version history and changelog
        </p>
      </div>

      <!-- Current Version Card -->
      <div class="bg-white rounded-lg shadow-sm mb-6">
        <div class="p-6 border-b border-light-gray">
          <div class="flex items-center justify-between">
            <div>
              <h2 class="text-h3 font-display text-horizon-500">Current Version</h2>
              <p class="mt-1 text-body-sm text-neutral-500">Latest release information</p>
            </div>
            <div class="text-right">
              <div class="text-3xl font-bold text-raspberry-500">v0.9.4</div>
              <div class="text-body-sm text-neutral-500 mt-1">Production Ready</div>
            </div>
          </div>
        </div>

        <div class="p-6">
          <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
            <div>
              <h3 class="text-body-sm font-semibold text-neutral-500 mb-2">Release Date</h3>
              <p class="text-body-base text-horizon-500">1 April 2026</p>
            </div>
            <div>
              <h3 class="text-body-sm font-semibold text-neutral-500 mb-2">Status</h3>
              <span class="inline-flex items-center px-3 py-1 rounded-full text-body-sm font-medium bg-green-100 text-green-800">
                Production Ready
              </span>
            </div>
          </div>

          <!-- v0.9.4 — Resource Pages Redesign -->
          <div class="mb-6">
            <h3 class="text-body-lg font-semibold text-horizon-500 mb-4">Resource Pages Redesign</h3>
            <p class="text-body-sm text-neutral-500 mb-4">Comprehensive redesign of all public-facing resource pages to match the design system palette. New advisors page and contact form with backend. Features page with harvey balls comparison table. FAQ page with centralised data and category filters. Security, glossary, our approach, one platform, financial companion, and getting started pages all redesigned. Guides &amp; Explainers hub replaces Learning Centre with category tabs, article layout, and shared navigation components. All 5 comparison pages renamed from competitor names to platform categories with redirects for old URLs.</p>
          </div>

          <div class="mb-6">
            <h3 class="text-body-lg font-semibold text-horizon-500 mb-4">Feature Gating</h3>
            <p class="text-body-sm text-neutral-500 mb-4">Tier-based feature access control across the application. Sidebar navigation items are greyed out for users on lower tiers with upgrade tooltips. Backend middleware enforces access restrictions per subscription plan. 10 automated tests covering all gating scenarios.</p>
          </div>

          <div class="mb-6">
            <h3 class="text-body-lg font-semibold text-horizon-500 mb-4">Journey Links</h3>
            <p class="text-body-sm text-neutral-500 mb-4">Stage page call-to-action buttons now pass the selected life stage through to the registration page, which auto-selects the corresponding stage during onboarding. Journey map displayed on stage-linked registration. Security fix ensures all Vuex stores are reset and auth tokens cleared on register and login to prevent data leakage between users.</p>
          </div>

          <div class="mb-6">
            <h3 class="text-body-lg font-semibold text-horizon-500 mb-4">Dashboard Improvements</h3>
            <p class="text-body-sm text-neutral-500 mb-4">Investment sparkline replaced with vertical bar chart per account. Empty state call-to-action buttons on Investment and Pension pages. Fyn chat button repositioned between Support and Account with toggle behaviour. Module status bar redesigned with three-column layout. Net worth donut hover tooltip. Sparkline hover tooltips with left marker clipping fix. Tax year label on Allowances card. Matched card heights per grid row.</p>
          </div>

          <div class="mb-6">
            <h3 class="text-body-lg font-semibold text-horizon-500 mb-4">Site-Wide Improvements</h3>
            <p class="text-body-sm text-neutral-500 mb-4">Sentence case applied across all titles and buttons. Demo modal opens in-place on the current page instead of redirecting to homepage. Centralised FAQ data provides a single source of truth for 50+ FAQs across the FAQ page, pricing page, and 7 feature pages. Google Analytics tag positioned in page head for full site coverage. SEO meta descriptions and structured data schemas added to learn pages. Sitemap updated with all 60+ public URLs. Content Security Policy updated for Google Analytics. Subscription upgrade with proration support. Tooltip clipping fix using Teleport.</p>
          </div>

          <div class="mb-6">
            <h3 class="text-body-lg font-semibold text-horizon-500 mb-4">New Pages</h3>
            <ul class="space-y-2">
              <li class="flex items-start">
                <svg class="w-5 h-5 text-raspberry-500 mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
                <span class="text-body-base text-neutral-500"><strong>Advisors page:</strong> Dedicated page for financial advisers explaining how Fynla complements their practice, with feature cards and sign-up call-to-action.</span>
              </li>
              <li class="flex items-start">
                <svg class="w-5 h-5 text-raspberry-500 mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
                <span class="text-body-base text-neutral-500"><strong>Contact page:</strong> Contact form with name, email, reason selector, message, and maths captcha. Backend controller with rate limiting routes messages to the appropriate email address. Three quick-link boxes for FAQ, demo, and Ask Fyn.</span>
              </li>
              <li class="flex items-start">
                <svg class="w-5 h-5 text-raspberry-500 mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
                <span class="text-body-base text-neutral-500"><strong>Guides &amp; Explainers hub:</strong> Replaces the Learning Centre with category filter tabs (Key Terms, Decision Support, Personal Journey Guides, Tax &amp; Allowances), shared navigation component, and consistent article layout across all 22 guide pages.</span>
              </li>
            </ul>
          </div>

          <!-- Version History Divider -->
          <div class="border-t border-light-gray my-8"></div>

          <!-- Previous Version: v0.9.3.2 -->
          <div class="mb-6">
            <div class="flex items-center gap-3 mb-4">
              <span class="text-xl font-bold text-horizon-400">v0.9.3.2</span>
              <span class="text-body-sm text-neutral-500">30 March 2026</span>
            </div>
          </div>

          <div class="mb-6">
            <h3 class="text-body-lg font-semibold text-horizon-500 mb-4">Website Redesign &amp; Content Refresh</h3>
            <p class="text-body-sm text-neutral-500 mb-4">Complete redesign of all 5 personal journey pages with consistent gradient hero template. New features comparison page with competitor analysis. Calculators page redesign with two-column sidebar layout. Preview banner, login/register, and pricing page improvements. Mega menu with eggshell backgrounds and renamed navigation ("Your personal journey", "Free calculators").</p>
          </div>

          <div class="mb-6">
            <h3 class="text-body-lg font-semibold text-horizon-500 mb-4">Security Hardening</h3>
            <p class="text-body-sm text-neutral-500 mb-4">National Insurance numbers masked in API responses. MFA token abilities enforced at middleware level. Exception messages sanitised across estate and protection controllers. User model mass assignment protection strengthened. Payment model soft deletes added for audit trail compliance. Admin seeder security improved.</p>
          </div>

          <div class="mb-6">
            <h3 class="text-body-lg font-semibold text-horizon-500 mb-4">Tax Compliance</h3>
            <p class="text-body-sm text-neutral-500 mb-4">Hardcoded tax thresholds replaced with TaxConfigService lookups across retirement, coordination, and investment services. Residence Nil Rate Band qualification checks added with property ownership verification, direct descendant checks, and taper relief calculations. All ISA and inheritance tax fallback values now use centralised TaxDefaults constants.</p>
          </div>

          <div class="mb-6">
            <h3 class="text-body-lg font-semibold text-horizon-500 mb-4">Design System Compliance</h3>
            <p class="text-body-sm text-neutral-500 mb-4">54 code review issues fixed across 103+ files. Off-palette colours replaced with design system tokens. Banned amber colour removed. Score gauge replaced with descriptive text. Console logging moved to structured logger. Timer leaks fixed. Acronyms spelled out in user-facing text. All spinner and tab colours standardised.</p>
          </div>

          <div class="mb-6">
            <h3 class="text-body-lg font-semibold text-horizon-500 mb-4">Bug Fixes</h3>
            <p class="text-body-sm text-neutral-500 mb-4">Protection dashboard card now correctly displays coverage amounts and includes disability policies. Payment and subscription amounts use decimal precision instead of integer truncation. Login and registration pages are mobile responsive. Pricing page FAQ correctly references Revolut payment processor.</p>
          </div>

          <!-- Version History Divider -->
          <div class="border-t border-light-gray my-8"></div>

          <!-- Previous Version: v0.9.3 -->
          <div class="mb-6">
            <div class="flex items-center gap-3 mb-4">
              <span class="text-xl font-bold text-horizon-400">v0.9.3</span>
              <span class="text-body-sm text-neutral-500">19 March 2026</span>
            </div>
          </div>

          <!-- v0.9.3 Changes -->
          <div class="mb-6">
            <h3 class="text-body-lg font-semibold text-horizon-500 mb-4">Admin Tax Configuration</h3>
            <p class="text-body-sm text-neutral-500 mb-4">Complete overhaul of the admin Tax Settings panel — all 568 TaxConfigService values now visible and editable across 10 tabs. Fixed NaN values in Inheritance Tax taper relief and trust charges. All rate displays corrected from decimals to percentages.</p>
            <ul class="space-y-2">
              <li class="flex items-start">
                <svg class="w-5 h-5 text-raspberry-500 mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
                <span class="text-body-base text-neutral-500"><strong>Guided Creation Wizard:</strong> Step-by-step wizard for both Lasting Power of Attorney types covering donor details, attorneys, replacement attorneys, decision type, preferences, instructions, certificate provider, and notification persons.</span>
              </li>
              <li class="flex items-start">
                <svg class="w-5 h-5 text-raspberry-500 mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
                <span class="text-body-base text-neutral-500"><strong>Compliance Checking:</strong> 10 automated checks against UK legal requirements — donor age, attorney count, decision type, certificate provider 2-year rule, notification person limit, registration status, life-sustaining treatment, and more.</span>
              </li>
              <li class="flex items-start">
                <svg class="w-5 h-5 text-raspberry-500 mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
                <span class="text-body-base text-neutral-500"><strong>Print &amp; Registration Tracking:</strong> View completed Lasting Powers of Attorney in Office of the Public Guardian format with print support. Track registration status with reference numbers.</span>
              </li>
              <li class="flex items-start">
                <svg class="w-5 h-5 text-raspberry-500 mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
                <span class="text-body-base text-neutral-500"><strong>Sidebar Navigation:</strong> New Power of Attorney item in the Family section of the sidebar for direct access to the Power of Attorney tab.</span>
              </li>
              <li class="flex items-start">
                <svg class="w-5 h-5 text-raspberry-500 mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
                <span class="text-body-base text-neutral-500"><strong>Document Upload:</strong> Upload existing Lasting Power of Attorney documents (PDF or image) and track them alongside newly created ones.</span>
              </li>
            </ul>
          </div>

          <!-- 18 March 2026 Patch -->
          <div class="mb-6">
            <h3 class="text-body-lg font-semibold text-horizon-500 mb-4">18 March 2026 Patch</h3>
            <ul class="space-y-2">
              <li class="flex items-start">
                <svg class="w-5 h-5 text-raspberry-500 mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
                <span class="text-body-base text-neutral-500"><strong>Fynla Brain Fix:</strong> Fixed an issue where the Fyn assistant would crash with a server error when sending messages. The prerequisite gate service was referencing a model in the wrong namespace, preventing all chat interactions.</span>
              </li>
              <li class="flex items-start">
                <svg class="w-5 h-5 text-raspberry-500 mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
                <span class="text-body-base text-neutral-500"><strong>Subscription Enforcement:</strong> Enabled subscription checking for all authenticated users. Users without an active subscription or trial now see appropriate messaging instead of accessing module data.</span>
              </li>
            </ul>
          </div>

          <!-- Fyn Assistant: Intelligent Prerequisite System -->
          <div class="mb-6">
            <h3 class="text-body-lg font-semibold text-horizon-500 mb-4">Fynla Brain: Intelligent Financial Guidance</h3>
            <p class="text-body-sm text-neutral-500 mb-4">Major upgrade to Fyn, your financial assistant — now powered by the Anthropic SDK with a new prerequisite system that ensures every piece of guidance is backed by your actual data. Fyn will never give vague or misleading advice when information is missing.</p>
            <ul class="space-y-2">
              <li class="flex items-start">
                <svg class="w-5 h-5 text-raspberry-500 mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
                <span class="text-body-base text-neutral-500"><strong>Prerequisite Gates:</strong> Fyn now checks your data before giving advice. If information is missing, you will see exactly what is needed, why it matters, and Fyn will navigate you directly to the right page to add it.</span>
              </li>
              <li class="flex items-start">
                <svg class="w-5 h-5 text-raspberry-500 mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
                <span class="text-body-base text-neutral-500"><strong>Richer Analysis:</strong> Fyn now uses 100% of the cross-module analysis when responding — including decision traces, cashflow allocation, conflict resolution, and cross-module strategies. Responses reference your specific figures, not generalities.</span>
              </li>
              <li class="flex items-start">
                <svg class="w-5 h-5 text-raspberry-500 mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
                <span class="text-body-base text-neutral-500"><strong>Smarter Navigation:</strong> Fyn can now navigate you to 26 different pages across the application — including income, expenditure, will builder, power of attorney, plans, actions, and what-if scenarios.</span>
              </li>
              <li class="flex items-start">
                <svg class="w-5 h-5 text-raspberry-500 mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
                <span class="text-body-base text-neutral-500"><strong>Anthropic SDK Integration:</strong> Upgraded from raw API calls to the official Anthropic PHP SDK for more reliable streaming, better error handling, and prompt caching for faster responses.</span>
              </li>
              <li class="flex items-start">
                <svg class="w-5 h-5 text-raspberry-500 mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
                <span class="text-body-base text-neutral-500"><strong>Simplified Architecture:</strong> Consolidated from 8 separate service files into a single, streamlined system. Both preview and registered users now use the same conversation engine.</span>
              </li>
              <li class="flex items-start">
                <svg class="w-5 h-5 text-raspberry-500 mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
                <span class="text-body-base text-neutral-500"><strong>User-Driven Conversations:</strong> Quick reply suggestion chips have been removed. Conversations are now entirely driven by you, with Fyn responding to your specific questions.</span>
              </li>
            </ul>
          </div>

          <!-- Will Builder -->
          <div class="mb-6">
            <h3 class="text-body-lg font-semibold text-horizon-500 mb-4">Will Builder</h3>
            <p class="text-body-sm text-neutral-500 mb-4">A guided 10-step wizard to create a legally-structured will for England and Wales — including executors, guardians for minor children, specific gifts, residuary estate distribution, funeral wishes, and digital assets.</p>
            <ul class="space-y-2">
              <li class="flex items-start">
                <svg class="w-5 h-5 text-raspberry-500 mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
                <span class="text-body-base text-neutral-500"><strong>Mirror Wills for Couples:</strong> Create matching wills for you and your spouse in a single wizard run. Beneficiaries are automatically swapped between the two documents.</span>
              </li>
              <li class="flex items-start">
                <svg class="w-5 h-5 text-raspberry-500 mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
                <span class="text-body-base text-neutral-500"><strong>Legal Document Format:</strong> Completed wills are displayed in formal legal language with proper attestation clauses, signatures in cursive script, and witness details — ready to print or save as PDF.</span>
              </li>
              <li class="flex items-start">
                <svg class="w-5 h-5 text-raspberry-500 mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
                <span class="text-body-base text-neutral-500"><strong>Pre-Population:</strong> Name, address, date of birth, occupation, spouse details, children, and existing executor are automatically filled from your profile.</span>
              </li>
              <li class="flex items-start">
                <svg class="w-5 h-5 text-raspberry-500 mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
                <span class="text-body-base text-neutral-500"><strong>Signing Guide:</strong> Step-by-step instructions for making your will legally valid with two witnesses, including what counts as valid witnessing under the Wills Act 1837.</span>
              </li>
            </ul>
          </div>

          <!-- Estate Navigation & Legal Documents -->
          <div class="mb-6">
            <h3 class="text-body-lg font-semibold text-horizon-500 mb-4">Estate Planning Improvements</h3>
            <ul class="space-y-2">
              <li class="flex items-start">
                <svg class="w-5 h-5 text-raspberry-500 mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
                <span class="text-body-base text-neutral-500"><strong>Lasting Power of Attorney Legal View:</strong> View your Lasting Powers of Attorney in formal Office of the Public Guardian format with signatures from donor, attorneys, and certificate provider, plus registration stamps for registered documents.</span>
              </li>
              <li class="flex items-start">
                <svg class="w-5 h-5 text-raspberry-500 mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
                <span class="text-body-base text-neutral-500"><strong>Standalone Power of Attorney Page:</strong> Power of Attorney has its own dedicated page accessible from the sidebar, with a navigation card on the Inheritance Tax calculation dashboard.</span>
              </li>
              <li class="flex items-start">
                <svg class="w-5 h-5 text-raspberry-500 mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
                <span class="text-body-base text-neutral-500"><strong>Consolidated Will Navigation:</strong> All will-related links now go directly to the Will Builder. The "Build Your Will" banner only appears when you haven't created a will yet.</span>
              </li>
            </ul>
          </div>

          <!-- Actions & Decision Trees -->
          <div class="mb-6">
            <h3 class="text-body-lg font-semibold text-horizon-500 mb-4">Actions Dashboard & Decision Trees</h3>
            <p class="text-body-sm text-neutral-500 mb-4">The Actions page has been rebuilt to show recommendations grouped by module, each with full decision traceability showing exactly how the Fynla Brain arrived at each recommendation.</p>
            <ul class="space-y-2">
              <li class="flex items-start">
                <svg class="w-5 h-5 text-raspberry-500 mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
                <span class="text-body-base text-neutral-500"><strong>Decision Tree Visualisation:</strong> Every recommendation includes a visual flowchart showing the decision path with your actual financial data at each step.</span>
              </li>
              <li class="flex items-start">
                <svg class="w-5 h-5 text-raspberry-500 mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
                <span class="text-body-base text-neutral-500"><strong>Decision Trace Timeline:</strong> A step-by-step timeline walking through every piece of data gathered, every check performed, and how each drove the final recommendation.</span>
              </li>
              <li class="flex items-start">
                <svg class="w-5 h-5 text-raspberry-500 mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
                <span class="text-body-base text-neutral-500"><strong>Savings Plan Integration:</strong> Savings module now included in the Plans system — savings recommendations appear alongside other module actions.</span>
              </li>
            </ul>
          </div>

          <!-- Dashboard Enhancements -->
          <div class="mb-6">
            <h3 class="text-body-lg font-semibold text-horizon-500 mb-4">Dashboard Enhancements</h3>
            <ul class="space-y-2">
              <li class="flex items-start">
                <svg class="w-5 h-5 text-raspberry-500 mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
                <span class="text-body-base text-neutral-500"><strong>Module Action Cards:</strong> New Protection, Cash & Savings, and Investment cards on the dashboard. Each shows key metrics plus up to 2 top-priority recommended actions from the Fynla Brain. When no actions exist, your accounts or policies are listed instead.</span>
              </li>
              <li class="flex items-start">
                <svg class="w-5 h-5 text-raspberry-500 mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
                <span class="text-body-base text-neutral-500"><strong>Real Dashboard Data:</strong> All module summaries now show your actual financial data — savings balances, portfolio values, retirement projections, estate net worth, and protection coverage — instead of placeholder values.</span>
              </li>
              <li class="flex items-start">
                <svg class="w-5 h-5 text-raspberry-500 mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
                <span class="text-body-base text-neutral-500"><strong>Docked Fyn Assistant:</strong> For logged-in users on desktop, Fyn now appears as a permanent panel on the right side of the dashboard with the side menu auto-collapsed for more space.</span>
              </li>
              <li class="flex items-start">
                <svg class="w-5 h-5 text-raspberry-500 mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
                <span class="text-body-base text-neutral-500"><strong>Retired User Support:</strong> Retirement analysis now works correctly for already-retired users, showing pension income from Defined Benefit and State Pension sources.</span>
              </li>
            </ul>
          </div>

          <!-- Stats -->
          <div class="mb-6">
            <h3 class="text-body-lg font-semibold text-horizon-500 mb-4">Release Statistics</h3>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
              <div class="bg-eggshell-500 rounded-lg p-4 text-center">
                <div class="text-2xl font-bold text-raspberry-500">213</div>
                <div class="text-body-sm text-neutral-500">Files Changed</div>
              </div>
              <div class="bg-eggshell-500 rounded-lg p-4 text-center">
                <div class="text-2xl font-bold text-raspberry-500">100</div>
                <div class="text-body-sm text-neutral-500">New Files</div>
              </div>
              <div class="bg-eggshell-500 rounded-lg p-4 text-center">
                <div class="text-2xl font-bold text-raspberry-500">74</div>
                <div class="text-body-sm text-neutral-500">New Tests</div>
              </div>
              <div class="bg-eggshell-500 rounded-lg p-4 text-center">
                <div class="text-2xl font-bold text-raspberry-500">18</div>
                <div class="text-body-sm text-neutral-500">New Endpoints</div>
              </div>
            </div>
          </div>
        </div>

          <div class="mb-6">
            <h3 class="text-body-lg font-semibold text-horizon-500 mb-4">Fyn Artificial Intelligence Assistant</h3>
            <p class="text-body-sm text-neutral-500 mb-4">Full create, update, and delete capabilities across all 18 entity types. Zero-token client-side navigation for instant page routing. Expanded tax information tool from 5 to 18 topics with caching. Investment knowledge nudge on dashboard.</p>
          </div>

          <div class="mb-6">
            <h3 class="text-body-lg font-semibold text-horizon-500 mb-4">Data Readiness &amp; Prerequisite Gates</h3>
            <p class="text-body-sm text-neutral-500 mb-4">PrerequisiteGateService refactored to delegate to DataReadiness services — single source of truth for module gates. Completeness endpoint returns field-level blocking, warnings, and completion percentages. Enriched Artificial Intelligence prompt context for accurate user guidance.</p>
          </div>

          <div class="mb-6">
            <h3 class="text-body-lg font-semibold text-horizon-500 mb-4">User Interface Improvements</h3>
            <p class="text-body-sm text-neutral-500 mb-4">Chat assistant renamed to Fyn with proper session lifecycle (close clears, open starts fresh). Info guide button moved from floating bottom-right to top navigation bar. Score displays replaced with descriptive text for Investment module compliance.</p>
          </div>

          <div class="mb-6">
            <h3 class="text-body-lg font-semibold text-horizon-500 mb-4">Security &amp; Code Quality</h3>
            <p class="text-body-sm text-neutral-500 mb-4">Code review remediation: UserResource enforced on all user data endpoints, advisor client assignment verified before access, hardcoded tax values replaced with TaxConfigService across 12 services, rate limiting added to admin routes, filter validation on goals endpoint.</p>
          </div>

          <div class="mb-6">
            <h3 class="text-body-lg font-semibold text-horizon-500 mb-4">March 18 Updates (included in v0.9.3)</h3>
            <p class="text-body-sm text-neutral-500 mb-4">Full codebase code review remediation (94 tasks), advisor dashboard with client management, soft deletes on key models, unique constraints and database indexes, Monte Carlo consolidation, financial health score removal, onboarding external resource links, income definitions with HMRC waterfall display.</p>
          </div>

        <!-- Version History Divider -->
        <div class="border-t border-light-gray my-8"></div>

        <!-- Previous Version: v0.9.2 -->
        <div class="mb-8 px-6">
          <h2 class="text-h3 font-display text-horizon-500 mb-4">Previous Release: v0.9.2</h2>
          <p class="text-body-sm text-neutral-500">16 March 2026 — Lasting Power of Attorney management, Will Builder enhancements, Expression of Wishes, estate planning compliance checking.</p>
        </div>

        <div class="border-t border-light-gray my-8"></div>

        <!-- Previous Version: v0.9.0 -->
        <div class="mb-8 px-6">
          <h2 class="text-h3 font-display text-horizon-500 mb-4">Previous Release: v0.9.0</h2>
          <p class="text-body-sm text-neutral-500 mb-4">Released: 15 March 2026</p>

          <!-- Decision Engine Upgrade -->
          <div class="mb-6">
            <h3 class="text-body-lg font-semibold text-horizon-500 mb-4">Fynla Brain Decision Engine (Complete Upgrade)</h3>
            <p class="text-body-sm text-neutral-500 mb-4">Transformed the Fynla Brain from static rule-based advice to a dynamic, data-aware decision engine across all 5 modules with 107 database-driven recommendation triggers.</p>
            <ul class="space-y-2">
              <li class="flex items-start">
                <svg class="w-5 h-5 text-raspberry-500 mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
                <span class="text-body-base text-neutral-500"><strong>Data Readiness Gates:</strong> All 5 modules validate data completeness before analysis. Users see exactly what information is missing with direct links to input forms — no more false results from incomplete data.</span>
              </li>
              <li class="flex items-start">
                <svg class="w-5 h-5 text-raspberry-500 mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
                <span class="text-body-base text-neutral-500"><strong>Investment Pipeline:</strong> 9-phase sequential engine covering safety checks, life event assessment, goal mapping, contribution waterfall (11 priority steps from Lifetime ISA to General Investment Account), transfer scans (13 types), spouse optimisation (7 strategies), and conflict resolution.</span>
              </li>
              <li class="flex items-start">
                <svg class="w-5 h-5 text-raspberry-500 mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
                <span class="text-body-base text-neutral-500"><strong>Savings Engine:</strong> 41 database-driven triggers covering emergency fund, ISA optimisation, rate comparison, Personal Savings Allowance breach, Financial Services Compensation Scheme exposure, children's savings, and debt comparison.</span>
              </li>
              <li class="flex items-start">
                <svg class="w-5 h-5 text-raspberry-500 mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
                <span class="text-body-base text-neutral-500"><strong>Protection Engine:</strong> 28 triggers with employer benefits integration, state benefits offset, and descriptive coverage ratings replacing numeric scores.</span>
              </li>
              <li class="flex items-start">
                <svg class="w-5 h-5 text-raspberry-500 mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
                <span class="text-body-base text-neutral-500"><strong>Centralised Tax Engine:</strong> Every UK tax value sourced from TaxConfigService. Tax year changes update everywhere automatically — no hardcoded values remain in the codebase.</span>
              </li>
            </ul>
          </div>

          <!-- Module-Specific Enhancements -->
          <div class="mb-6">
            <h3 class="text-body-lg font-semibold text-horizon-500 mb-4">Module-Specific Enhancements</h3>
            <ul class="space-y-2">
              <li class="flex items-start">
                <svg class="w-5 h-5 text-violet-500 mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
                <span class="text-body-base text-neutral-500"><strong>Estate Planning:</strong> 2027 pension Inheritance Tax amendment modelling with dual-scenario projections, 14-year gift rule enforcement, enhanced life insurance checks, liquidity reclassification, and trust Nil Rate Band projections.</span>
              </li>
              <li class="flex items-start">
                <svg class="w-5 h-5 text-violet-500 mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
                <span class="text-body-base text-neutral-500"><strong>Retirement:</strong> Salary sacrifice analysis with National Insurance savings calculator, auto-enrolment compliance checks, enhanced annuity assessment for smokers and health conditions, and 18 recommendation triggers.</span>
              </li>
              <li class="flex items-start">
                <svg class="w-5 h-5 text-violet-500 mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
                <span class="text-body-base text-neutral-500"><strong>Protection:</strong> Employer benefits (death-in-service, group income protection, group critical illness) deducted from coverage gaps. Statutory Sick Pay correctly calculated with self-employed exclusion.</span>
              </li>
              <li class="flex items-start">
                <svg class="w-5 h-5 text-violet-500 mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
                <span class="text-body-base text-neutral-500"><strong>Savings:</strong> Personal Savings Allowance analysis, Financial Services Compensation Scheme exposure warnings, employment-based emergency fund targets (9 months self-employed, 6 employed, 3 retired), and daily maturity/rate expiry alerts.</span>
              </li>
              <li class="flex items-start">
                <svg class="w-5 h-5 text-violet-500 mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
                <span class="text-body-base text-neutral-500"><strong>Plan Isolation:</strong> Individual module plans contain only their own recommendations. Cross-module optimisation (pension contributions, savings allowance, marriage allowance) appears only in the holistic plan.</span>
              </li>
            </ul>
          </div>

          <!-- Security Hardening and Code Quality -->
          <div class="mb-6">
            <h3 class="text-body-lg font-semibold text-horizon-500 mb-4">Security Hardening and Code Quality</h3>
            <ul class="space-y-2">
              <li class="flex items-start">
                <svg class="w-5 h-5 text-spring-500 mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
                <span class="text-body-base text-neutral-500"><strong>Login Security:</strong> Challenge tokens moved from cache to database — eliminates verification failures. User ID removed from pre-authentication API responses to prevent enumeration attacks.</span>
              </li>
              <li class="flex items-start">
                <svg class="w-5 h-5 text-spring-500 mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
                <span class="text-body-base text-neutral-500"><strong>Preview Mode Security:</strong> Sensitive fields (passwords, tokens, API keys) filtered from preview write interceptor responses.</span>
              </li>
              <li class="flex items-start">
                <svg class="w-5 h-5 text-spring-500 mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
                <span class="text-body-base text-neutral-500"><strong>Design System Compliance:</strong> All hardcoded hex colours replaced with design system constants. Hardcoded Inheritance Tax values replaced with tax configuration imports. Score badges replaced with descriptive text labels.</span>
              </li>
              <li class="flex items-start">
                <svg class="w-5 h-5 text-spring-500 mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
                <span class="text-body-base text-neutral-500"><strong>Code Quality:</strong> 97 lines of dead code removed, duplicate CSS keyframes extracted to global stylesheet, architecture tests added for all Models and Controllers, query limits added to all unbounded index endpoints.</span>
              </li>
            </ul>
          </div>

          <!-- Stats -->
          <div class="mb-6">
            <h3 class="text-body-lg font-semibold text-horizon-500 mb-4">Release Statistics</h3>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
              <div class="bg-eggshell-500 rounded-lg p-4 text-center">
                <div class="text-2xl font-bold text-raspberry-500">420+</div>
                <div class="text-body-sm text-neutral-500">Files Changed</div>
              </div>
              <div class="bg-eggshell-500 rounded-lg p-4 text-center">
                <div class="text-2xl font-bold text-raspberry-500">82</div>
                <div class="text-body-sm text-neutral-500">New Files</div>
              </div>
              <div class="bg-eggshell-500 rounded-lg p-4 text-center">
                <div class="text-2xl font-bold text-raspberry-500">1,873</div>
                <div class="text-body-sm text-neutral-500">Tests Passing</div>
              </div>
              <div class="bg-eggshell-500 rounded-lg p-4 text-center">
                <div class="text-2xl font-bold text-raspberry-500">16</div>
                <div class="text-body-sm text-neutral-500">Bugs Fixed</div>
              </div>
            </div>
          </div>
        </div>

        <!-- Version History Divider -->
        <div class="border-t border-light-gray my-8"></div>

        <!-- Previous Version: v0.8.3 -->
        <div class="mb-8 px-6">
          <h2 class="text-h3 font-display text-horizon-500 mb-4">Previous Release: v0.8.3</h2>
          <p class="text-body-sm text-neutral-500 mb-4">Released: 5 March 2026</p>

          <div class="mb-6">
            <h3 class="text-body-lg font-semibold text-horizon-500 mb-4">Financial Plans System (Complete Rebuild)</h3>
            <p class="text-body-sm text-neutral-500 mb-4">Comprehensive rebuild of the entire financial planning system across 6 phases with 263 implementation tasks and a full code audit fixing 25 issues.</p>
            <ul class="space-y-2">
              <li class="flex items-start">
                <svg class="w-5 h-5 text-raspberry-500 mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
                <span class="text-body-base text-neutral-500"><strong>Unified Plan Framework:</strong> 5 plan types (Investment, Protection, Retirement, Estate, Goal) with consistent 6-section structure: executive summary, current situation, toggleable actions, what-if scenarios, dynamic conclusion, and PDF export.</span>
              </li>
              <li class="flex items-start">
                <svg class="w-5 h-5 text-raspberry-500 mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
                <span class="text-body-base text-neutral-500"><strong>Structured Executive Summaries:</strong> Personalised greeting, key metrics tables, and prioritised actions tables for all plan types.</span>
              </li>
              <li class="flex items-start">
                <svg class="w-5 h-5 text-raspberry-500 mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
                <span class="text-body-base text-neutral-500"><strong>Per-Account Recommendations:</strong> Investment and retirement plans generate per-account fee and contribution recommendations with reactive line charts.</span>
              </li>
              <li class="flex items-start">
                <svg class="w-5 h-5 text-raspberry-500 mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
                <span class="text-body-base text-neutral-500"><strong>Admin-Configurable Values:</strong> PlanConfigService replaces 16+ hardcoded values (growth rates, fee benchmarks, thresholds) with database-driven admin configuration.</span>
              </li>
              <li class="flex items-start">
                <svg class="w-5 h-5 text-raspberry-500 mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
                <span class="text-body-base text-neutral-500"><strong>Goal Integration:</strong> Goals linked to savings, investment, or retirement accounts appear in the corresponding plan with progress tracking and priority-ordered recommendations.</span>
              </li>
            </ul>
          </div>

          <div class="mb-6">
            <h3 class="text-body-lg font-semibold text-horizon-500 mb-4">Also Included</h3>
            <ul class="space-y-1">
              <li class="text-body-sm text-neutral-500">• Holistic Plan rewrite with frontend-orchestrated aggregation</li>
              <li class="text-body-sm text-neutral-500">• AI Chat Assistant with 17 tools</li>
              <li class="text-body-sm text-neutral-500">• Side navigation menu with collapsible modes</li>
              <li class="text-body-sm text-neutral-500">• Student preview persona (Janice Taylor)</li>
              <li class="text-body-sm text-neutral-500">• Design System v1.2.0 overhaul</li>
              <li class="text-body-sm text-neutral-500">• 3 critical bugs fixed, 6 estate agent fixes, 12 code simplifications</li>
            </ul>
          </div>
        </div>

        <!-- Version History Divider -->
        <div class="border-t border-light-gray my-8"></div>

        <!-- Previous Version: v0.8.1 -->
        <div class="mb-8 px-6">
          <h2 class="text-h3 font-display text-horizon-500 mb-4">Previous Release: v0.8.1</h2>
          <p class="text-body-sm text-neutral-500 mb-4">Released: 22 February 2026</p>

          <div class="mb-6">
            <h3 class="text-body-lg font-semibold text-horizon-500 mb-4">Security Hardening (4 Phases)</h3>
            <p class="text-body-sm text-neutral-500 mb-4">Comprehensive security review addressing 46 findings across authentication, API controllers, models, and frontend configuration.</p>
            <ul class="space-y-2">
              <li class="flex items-start">
                <svg class="w-5 h-5 text-red-500 mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
                <span class="text-body-base text-neutral-500"><strong>Data Encryption at Rest:</strong> Account numbers, sort codes, mortgage account numbers, and National Insurance numbers now encrypted.</span>
              </li>
              <li class="flex items-start">
                <svg class="w-5 h-5 text-red-500 mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
                <span class="text-body-base text-neutral-500"><strong>Brute Force Protection:</strong> MFA verification, login email codes, and registration codes track failed attempts and lock after 5 failures.</span>
              </li>
              <li class="flex items-start">
                <svg class="w-5 h-5 text-red-500 mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
                <span class="text-body-base text-neutral-500"><strong>Account Enumeration Prevention, Challenge Token Authentication, Content Security Policy, Session Security, Source Maps Disabled.</strong></span>
              </li>
            </ul>
          </div>
        </div>

        <!-- Version History Divider -->
        <div class="border-t border-light-gray my-8"></div>

        <!-- Previous Version: v0.7.0 -->
        <div class="mb-8 px-6">
          <h2 class="text-h3 font-display text-horizon-500 mb-4">Previous Release: v0.7.0</h2>
          <p class="text-body-sm text-neutral-500 mb-4">Released: 6 February 2026</p>

          <!-- Laravel Best Practices & Code Quality -->
          <div class="mb-6">
            <h3 class="text-body-lg font-semibold text-horizon-500 mb-4">Laravel Best Practices Audit</h3>
            <ul class="space-y-2">
              <li class="flex items-start">
                <svg class="w-5 h-5 text-green-500 mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
                <span class="text-body-base text-neutral-500"><strong>Form Request Validation:</strong> Created 12 Form Request classes for Investment and Estate modules, replacing inline validation.</span>
              </li>
              <li class="flex items-start">
                <svg class="w-5 h-5 text-green-500 mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
                <span class="text-body-base text-neutral-500"><strong>API Resources:</strong> Created 10 API Resource classes for consistent response transformation across all endpoints.</span>
              </li>
              <li class="flex items-start">
                <svg class="w-5 h-5 text-green-500 mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
                <span class="text-body-base text-neutral-500"><strong>Controller Extraction:</strong> Extracted 470 lines from IHTController to IHTFormattingService. Code quality score improved from 85 to 94.</span>
              </li>
              <li class="flex items-start">
                <svg class="w-5 h-5 text-green-500 mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
                <span class="text-body-base text-neutral-500"><strong>PHP 8 Modernisation:</strong> Readonly constructor properties, PHP 8 property promotion, Builder return types on 39 query scopes.</span>
              </li>
            </ul>
          </div>

          <!-- Goals & Life Events -->
          <div class="mb-6">
            <h3 class="text-body-lg font-semibold text-horizon-500 mb-4">🎯 Goals & Life Events Improvements</h3>
            <ul class="space-y-2">
              <li class="flex items-start">
                <svg class="w-5 h-5 text-blue-500 mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
                <span class="text-body-base text-neutral-500"><strong>Realistic Projections:</strong> Replaced complex simulation with simple Future Value calculation using user's own growth and inflation assumptions.</span>
              </li>
              <li class="flex items-start">
                <svg class="w-5 h-5 text-blue-500 mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
                <span class="text-body-base text-neutral-500"><strong>Cash Flow Chart Fix:</strong> Income now correctly calculated using UK tax calculator instead of showing zero.</span>
              </li>
              <li class="flex items-start">
                <svg class="w-5 h-5 text-blue-500 mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
                <span class="text-body-base text-neutral-500"><strong>Contribution Streaks:</strong> Goal contribution streak tracking with longest streak badges and visual progress indicators.</span>
              </li>
              <li class="flex items-start">
                <svg class="w-5 h-5 text-blue-500 mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
                <span class="text-body-base text-neutral-500"><strong>Life Events Management:</strong> Full CRUD for life events with household view toggle and projection impact display.</span>
              </li>
            </ul>
          </div>

          <!-- Retirement Planner -->
          <div class="mb-6">
            <h3 class="text-body-lg font-semibold text-horizon-500 mb-4">🏦 Retirement Planner Enhancements</h3>
            <ul class="space-y-2">
              <li class="flex items-start">
                <svg class="w-5 h-5 text-purple-500 mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
                <span class="text-body-base text-neutral-500"><strong>Income Tax Slider:</strong> Interactive slider to model different retirement income levels and see the tax impact in real-time.</span>
              </li>
              <li class="flex items-start">
                <svg class="w-5 h-5 text-purple-500 mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
                <span class="text-body-base text-neutral-500"><strong>Decumulation Graph:</strong> Visual projection of pension fund drawdown over retirement years.</span>
              </li>
              <li class="flex items-start">
                <svg class="w-5 h-5 text-purple-500 mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
                <span class="text-body-base text-neutral-500"><strong>DB/State Pension Fallback:</strong> Dashboard retirement card now shows projected income for users with only DB or State pensions.</span>
              </li>
            </ul>
          </div>

          <!-- Net Worth & Wealth Summary -->
          <div class="mb-6">
            <h3 class="text-body-lg font-semibold text-horizon-500 mb-4">📊 Net Worth & Wealth Summary</h3>
            <ul class="space-y-2">
              <li class="flex items-start">
                <svg class="w-5 h-5 text-teal-500 mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
                <span class="text-body-base text-neutral-500"><strong>DB Pension Exclusion:</strong> Wealth summary now correctly excludes defined benefit pensions (not accessible capital) with explanatory note.</span>
              </li>
              <li class="flex items-start">
                <svg class="w-5 h-5 text-teal-500 mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
                <span class="text-body-base text-neutral-500"><strong>Responsive Values:</strong> Fixed currency values being cut off on smaller screens with flexible grid columns and responsive font sizing.</span>
              </li>
              <li class="flex items-start">
                <svg class="w-5 h-5 text-teal-500 mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
                <span class="text-body-base text-neutral-500"><strong>Joint Ownership Accuracy:</strong> Dashboard investment and savings cards now display ownership share instead of full account value.</span>
              </li>
              <li class="flex items-start">
                <svg class="w-5 h-5 text-teal-500 mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
                <span class="text-body-base text-neutral-500"><strong>Chattels Fix:</strong> Joint chattels now correctly show ownership type and percentage-based value share.</span>
              </li>
            </ul>
          </div>

          <!-- Bug Fixes -->
          <div class="mb-6">
            <h3 class="text-body-lg font-semibold text-horizon-500 mb-4">🐛 Bug Fixes & Data Accuracy</h3>
            <ul class="space-y-2">
              <li class="flex items-start">
                <svg class="w-5 h-5 text-red-500 mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
                <span class="text-body-base text-neutral-500"><strong>API Resource Null Safety:</strong> Fixed 500 errors across 8 API Resources when eager-loaded relationships resolve to null.</span>
              </li>
              <li class="flex items-start">
                <svg class="w-5 h-5 text-red-500 mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
                <span class="text-body-base text-neutral-500"><strong>Mortgage Display:</strong> Fixed mortgage values not appearing in property cards due to field name mismatch between API resource and frontend.</span>
              </li>
              <li class="flex items-start">
                <svg class="w-5 h-5 text-red-500 mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
                <span class="text-body-base text-neutral-500"><strong>Info Guide Fix:</strong> Fixed 500 error on requirements endpoint and corrected DC pension requirement logic for DB-only users.</span>
              </li>
              <li class="flex items-start">
                <svg class="w-5 h-5 text-red-500 mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
                <span class="text-body-base text-neutral-500"><strong>Occupation Lookup:</strong> Populated 406 ONS SOC 2020 occupation codes (table was empty after initial migration).</span>
              </li>
              <li class="flex items-start">
                <svg class="w-5 h-5 text-red-500 mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
                <span class="text-body-base text-neutral-500"><strong>DC Depletion Warning:</strong> Warning now only shows when user actually has DC pensions, not for DB/State-only users.</span>
              </li>
            </ul>
          </div>

          <!-- UI & Informational Updates -->
          <div class="mb-6">
            <h3 class="text-body-lg font-semibold text-horizon-500 mb-4">⚙️ Other Changes</h3>
            <ul class="space-y-2">
              <li class="flex items-start">
                <svg class="w-5 h-5 text-neutral-500 mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
                <span class="text-body-base text-neutral-500"><strong>Document Upload Disclaimer:</strong> Privacy banner on document upload modal explaining AI processing via Anthropic's Haiku 3.5.</span>
              </li>
              <li class="flex items-start">
                <svg class="w-5 h-5 text-neutral-500 mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
                <span class="text-body-base text-neutral-500"><strong>Postcode Lookup Removed:</strong> GetAddress.io provider shut down; manual address entry fields remain for all forms.</span>
              </li>
              <li class="flex items-start">
                <svg class="w-5 h-5 text-neutral-500 mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
                <span class="text-body-base text-neutral-500"><strong>Security Nav Link:</strong> Added Security page link to the main navigation sidebar.</span>
              </li>
              <li class="flex items-start">
                <svg class="w-5 h-5 text-neutral-500 mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
                <span class="text-body-base text-neutral-500"><strong>Lazy Loading Prevention:</strong> Enabled Model::preventLazyLoading() in development to catch N+1 query issues early.</span>
              </li>
            </ul>
          </div>
        </div>

        <!-- Version History Divider -->
        <div class="border-t border-light-gray my-8"></div>

        <!-- Previous Version: v0.6.2 -->
        <div class="mb-8 px-6">
          <h2 class="text-h3 font-display text-horizon-500 mb-4">Previous Release: v0.6.2</h2>
          <p class="text-body-sm text-neutral-500 mb-4">Released: 19 January 2026</p>

          <div class="mb-6">
            <h3 class="text-body-lg font-semibold text-horizon-500 mb-4">Security, Goals, Risk Calculator, Financial Statements, Investment Dashboard, Personas, Registration</h3>
            <ul class="space-y-2">
              <li class="flex items-start">
                <svg class="w-5 h-5 text-green-500 mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
                <span class="text-body-base text-neutral-500"><strong>TOTP MFA, Failed Login Tracking, Session Management, GDPR Compliance, Audit Logging, RBAC Authorisation</strong></span>
              </li>
              <li class="flex items-start">
                <svg class="w-5 h-5 text-green-500 mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
                <span class="text-body-base text-neutral-500"><strong>Goals-Based Planning Module:</strong> Centralised goal management, progress tracking, dashboard integration, 8 goal types.</span>
              </li>
              <li class="flex items-start">
                <svg class="w-5 h-5 text-green-500 mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
                <span class="text-body-base text-neutral-500"><strong>Automated Risk Calculator:</strong> 7-factor analysis with auto-recalculation via model observers.</span>
              </li>
              <li class="flex items-start">
                <svg class="w-5 h-5 text-green-500 mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
                <span class="text-body-base text-neutral-500"><strong>Financial Statements:</strong> Balance sheet, income statement, and cash flow views with joint ownership calculations.</span>
              </li>
              <li class="flex items-start">
                <svg class="w-5 h-5 text-green-500 mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
                <span class="text-body-base text-neutral-500"><strong>Investment Dashboard Redesign:</strong> Strategy terminology, sidebar card navigation, asset allocation bar, simplified Monte Carlo.</span>
              </li>
              <li class="flex items-start">
                <svg class="w-5 h-5 text-green-500 mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
                <span class="text-body-base text-neutral-500"><strong>New Personas:</strong> Young Adult Saver (John Morgan) and Retired Couple (Patricia & Harold Bennett).</span>
              </li>
            </ul>
          </div>
        </div>

        <!-- Version History Divider -->
        <div class="border-t border-light-gray my-8"></div>

        <!-- Previous Version: v0.5.1 -->
        <div class="mb-8 px-6">
          <h2 class="text-h3 font-display text-horizon-500 mb-4">Previous Release: v0.5.1</h2>
          <p class="text-body-sm text-neutral-500 mb-4">Released: 16 January 2026</p>

          <!-- What's New in v0.5.1 -->
          <div class="mb-6">
            <h3 class="text-body-lg font-semibold text-horizon-500 mb-4">🎨 UI Refresh & New Features in v0.5.1</h3>
            <ul class="space-y-2">
              <li class="flex items-start">
                <svg class="w-5 h-5 text-green-500 mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
                <span class="text-body-base text-neutral-500"><strong>Security & Privacy Page:</strong> New comprehensive page covering authentication, data protection, GDPR compliance, and business continuity.</span>
              </li>
              <li class="flex items-start">
                <svg class="w-5 h-5 text-green-500 mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
                <span class="text-body-base text-neutral-500"><strong>Landing Page Enhancements:</strong> Interactive hero stats cards, fixed rotating text spacing, optimised layout.</span>
              </li>
              <li class="flex items-start">
                <svg class="w-5 h-5 text-green-500 mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
                <span class="text-body-base text-neutral-500"><strong>Restyled Public Pages:</strong> Calculators and Learning Centre with glass-morphism effects and coloured icons.</span>
              </li>
              <li class="flex items-start">
                <svg class="w-5 h-5 text-green-500 mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
                <span class="text-body-base text-neutral-500"><strong>Other Trust Type:</strong> Support for offshore and foreign trusts with country selection field.</span>
              </li>
              <li class="flex items-start">
                <svg class="w-5 h-5 text-green-500 mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
                <span class="text-body-base text-neutral-500"><strong>Platform Fee Options:</strong> Investment accounts now support percentage or fixed fees with monthly/quarterly/annual frequency.</span>
              </li>
            </ul>
          </div>

          <!-- Estate & Retirement Module Updates -->
          <div class="mb-6">
            <h3 class="text-body-lg font-semibold text-horizon-500 mb-4">🏛️ Estate & Retirement Module Updates</h3>
            <ul class="space-y-2">
              <li class="flex items-start">
                <svg class="w-5 h-5 text-purple-500 mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
                <span class="text-body-base text-neutral-500"><strong>IHT → Inheritance Tax:</strong> Replaced acronym with full term throughout 19 components for clarity.</span>
              </li>
              <li class="flex items-start">
                <svg class="w-5 h-5 text-purple-500 mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
                <span class="text-body-base text-neutral-500"><strong>Tax Projection Columns:</strong> Added -5 and +5 year projections to Inheritance Tax calculation tables.</span>
              </li>
              <li class="flex items-start">
                <svg class="w-5 h-5 text-purple-500 mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
                <span class="text-body-base text-neutral-500"><strong>Estate Dashboard Redesign:</strong> Tab navigation removed, strategies accessible via clickable cards.</span>
              </li>
              <li class="flex items-start">
                <svg class="w-5 h-5 text-purple-500 mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
                <span class="text-body-base text-neutral-500"><strong>Retirement Dashboard:</strong> New 3-column layout with Monte Carlo projections, pension sidebar, and income summary.</span>
              </li>
              <li class="flex items-start">
                <svg class="w-5 h-5 text-purple-500 mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
                <span class="text-body-base text-neutral-500"><strong>Protection Gap Analysis:</strong> Moved into Policy Overview tab with 5 shortfall cards and affordability assessment.</span>
              </li>
            </ul>
          </div>

          <!-- Dashboard & UX Improvements -->
          <div class="mb-6">
            <h3 class="text-body-lg font-semibold text-horizon-500 mb-4">📊 Dashboard & UX Improvements</h3>
            <ul class="space-y-2">
              <li class="flex items-start">
                <svg class="w-5 h-5 text-blue-500 mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
                <span class="text-body-base text-neutral-500"><strong>Draggable Dashboard Widgets:</strong> Drag and drop to reorder widgets, order saved to database.</span>
              </li>
              <li class="flex items-start">
                <svg class="w-5 h-5 text-blue-500 mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
                <span class="text-body-base text-neutral-500"><strong>White Dashboard Background:</strong> Cleaner appearance with white instead of gray.</span>
              </li>
              <li class="flex items-start">
                <svg class="w-5 h-5 text-blue-500 mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
                <span class="text-body-base text-neutral-500"><strong>Removed Curved Borders:</strong> Info boxes updated to neutral gray background (92 files updated).</span>
              </li>
              <li class="flex items-start">
                <svg class="w-5 h-5 text-blue-500 mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
                <span class="text-body-base text-neutral-500"><strong>Auto-Collapse Sidebar:</strong> Cash and Retirement tabs auto-collapse sidebar for more screen space.</span>
              </li>
              <li class="flex items-start">
                <svg class="w-5 h-5 text-blue-500 mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
                <span class="text-body-base text-neutral-500"><strong>Clickable Wealth Summary:</strong> Each row in Net Worth summary navigates to the relevant section.</span>
              </li>
              <li class="flex items-start">
                <svg class="w-5 h-5 text-blue-500 mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
                <span class="text-body-base text-neutral-500"><strong>Investment Card:</strong> Account list with type badges and annualised returns per account.</span>
              </li>
              <li class="flex items-start">
                <svg class="w-5 h-5 text-blue-500 mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
                <span class="text-body-base text-neutral-500"><strong>Tax Allowances Card:</strong> Conditional display based on user situation (CGT only if GIA exists).</span>
              </li>
            </ul>
          </div>

          <!-- Forms & Data Entry -->
          <div class="mb-6">
            <h3 class="text-body-lg font-semibold text-horizon-500 mb-4">📝 Forms & Data Entry</h3>
            <ul class="space-y-2">
              <li class="flex items-start">
                <svg class="w-5 h-5 text-blue-500 mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
                <span class="text-body-base text-neutral-500"><strong>Optional Form Fields:</strong> All fields now optional to allow partial data entry and saving.</span>
              </li>
              <li class="flex items-start">
                <svg class="w-5 h-5 text-blue-500 mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
                <span class="text-body-base text-neutral-500"><strong>Red Asterisks Removed:</strong> No more required field indicators throughout the application.</span>
              </li>
              <li class="flex items-start">
                <svg class="w-5 h-5 text-blue-500 mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
                <span class="text-body-base text-neutral-500"><strong>Database Defaults:</strong> Sensible defaults added for NOT NULL columns via migration.</span>
              </li>
            </ul>
          </div>

          <!-- Bug Fixes -->
          <div class="mb-6">
            <h3 class="text-body-lg font-semibold text-horizon-500 mb-4">🐛 Bug Fixes</h3>
            <ul class="space-y-2">
              <li class="flex items-start">
                <svg class="w-5 h-5 text-red-500 mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
                <span class="text-body-base text-neutral-500"><strong>Life Expectancy Consolidated:</strong> Single source of truth (actuarial_life_tables database table).</span>
              </li>
              <li class="flex items-start">
                <svg class="w-5 h-5 text-red-500 mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
                <span class="text-body-base text-neutral-500"><strong>Spouse Data Visibility:</strong> Widowed and divorced users no longer see spouse-related UI elements.</span>
              </li>
              <li class="flex items-start">
                <svg class="w-5 h-5 text-red-500 mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
                <span class="text-body-base text-neutral-500"><strong>Router Scroll Behaviour:</strong> Pages now load at the top when navigating.</span>
              </li>
              <li class="flex items-start">
                <svg class="w-5 h-5 text-red-500 mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
                <span class="text-body-base text-neutral-500"><strong>Estate Card IHT Display:</strong> Fallback calculation when store data is null.</span>
              </li>
              <li class="flex items-start">
                <svg class="w-5 h-5 text-red-500 mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
                <span class="text-body-base text-neutral-500"><strong>Protection Shortfalls:</strong> Now matches GapAnalysis component calculations exactly.</span>
              </li>
            </ul>
          </div>

          <!-- Technical Changes -->
          <div class="mb-6">
            <h3 class="text-body-lg font-semibold text-horizon-500 mb-4">⚙️ Technical Changes</h3>
            <ul class="space-y-2">
              <li class="flex items-start">
                <svg class="w-5 h-5 text-neutral-500 mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
                <span class="text-body-base text-neutral-500"><strong>Vuex Store Refactor:</strong> Protection store reduced by 40% with action factory functions.</span>
              </li>
              <li class="flex items-start">
                <svg class="w-5 h-5 text-neutral-500 mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
                <span class="text-body-base text-neutral-500"><strong>Rate Limit Increase:</strong> API rate limit increased from 60 to 300 requests/minute.</span>
              </li>
              <li class="flex items-start">
                <svg class="w-5 h-5 text-neutral-500 mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
                <span class="text-body-base text-neutral-500"><strong>Email Branding:</strong> Spouse account emails updated from FPS to Fynla branding.</span>
              </li>
              <li class="flex items-start">
                <svg class="w-5 h-5 text-neutral-500 mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
                <span class="text-body-base text-neutral-500"><strong>Vuedraggable:</strong> Added vuedraggable@next for dashboard widget reordering.</span>
              </li>
              <li class="flex items-start">
                <svg class="w-5 h-5 text-neutral-500 mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
                <span class="text-body-base text-neutral-500"><strong>Database Migrations:</strong> Added dashboard_widget_order, platform fee fields, other trust type fields.</span>
              </li>
            </ul>
          </div>
        </div>

        <!-- Version History Divider -->
        <div class="border-t border-light-gray my-8"></div>

        <!-- Previous Version: v0.4.5 -->
        <div class="mb-8 px-6">
          <h2 class="text-h3 font-display text-horizon-500 mb-4">Previous Release: v0.4.5</h2>
          <p class="text-body-sm text-neutral-500 mb-4">Released: 1 January 2026</p>

          <div class="mb-6">
            <h3 class="text-body-lg font-semibold text-horizon-500 mb-4">💎 Personal Valuables Module with CGT Calculator</h3>
            <ul class="space-y-2">
              <li class="flex items-start">
                <svg class="w-5 h-5 text-green-500 mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
                <span class="text-body-base text-neutral-500"><strong>Full Personal Valuables Module:</strong> Track vehicles, art, antiques, jewellery, collectibles in Net Worth.</span>
              </li>
              <li class="flex items-start">
                <svg class="w-5 h-5 text-green-500 mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
                <span class="text-body-base text-neutral-500"><strong>CGT Calculator:</strong> UK-compliant with £6,000 threshold, marginal relief (5/3 rule), wasting asset exemption.</span>
              </li>
            </ul>
          </div>
        </div>

        <!-- Version History Divider -->
        <div class="border-t border-light-gray my-8"></div>

        <!-- Previous Version: v0.4.4 -->
        <div class="mb-8 px-6">
          <h2 class="text-h3 font-display text-horizon-500 mb-4">Previous Release: v0.4.4</h2>
          <p class="text-body-sm text-neutral-500 mb-4">Released: 31 December 2025</p>

          <div class="mb-6">
            <h3 class="text-body-lg font-semibold text-horizon-500 mb-4">🔧 Code Quality & Architecture Improvements</h3>
            <ul class="space-y-2">
              <li class="flex items-start">
                <svg class="w-5 h-5 text-green-500 mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
                <span class="text-body-base text-neutral-500"><strong>Investment Diversification Tab:</strong> New diversification analysis for investment accounts and DC pensions.</span>
              </li>
              <li class="flex items-start">
                <svg class="w-5 h-5 text-green-500 mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
                <span class="text-body-base text-neutral-500"><strong>EstateAgent Created:</strong> New EstateAgent for architectural consistency.</span>
              </li>
              <li class="flex items-start">
                <svg class="w-5 h-5 text-green-500 mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
                <span class="text-body-base text-neutral-500"><strong>Dynamic Tax Values:</strong> Removed hardcoded ISA allowances - uses API-backed TaxConfigService.</span>
              </li>
            </ul>
          </div>
        </div>

        <!-- Version History Divider -->
        <div class="border-t border-light-gray my-8"></div>

        <!-- Previous Version: v0.4.3 -->
        <div class="mb-8 px-6">
          <h2 class="text-h3 font-display text-horizon-500 mb-4">Previous Release: v0.4.3</h2>
          <p class="text-body-sm text-neutral-500 mb-4">Released: 31 December 2025</p>

          <div class="mb-6">
            <h3 class="text-body-lg font-semibold text-horizon-500 mb-4">📊 Investment Returns & Info Guide</h3>
            <ul class="space-y-2">
              <li class="flex items-start">
                <svg class="w-5 h-5 text-green-500 mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
                <span class="text-body-base text-neutral-500"><strong>Investment Account Panels:</strong> Holdings, Performance, Fees, and Rebalancing panels for detailed account analysis.</span>
              </li>
              <li class="flex items-start">
                <svg class="w-5 h-5 text-green-500 mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
                <span class="text-body-base text-neutral-500"><strong>Portfolio Strategy Tab:</strong> Tax, fee, and rebalancing recommendations aggregated by priority.</span>
              </li>
              <li class="flex items-start">
                <svg class="w-5 h-5 text-green-500 mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
                <span class="text-body-base text-neutral-500"><strong>Info Guide Feature:</strong> Floating help button showing data requirements per module with plain-language explanations.</span>
              </li>
            </ul>
          </div>
        </div>

        <!-- Version History Divider -->
        <div class="border-t border-light-gray my-8"></div>

        <!-- Previous Version: v0.4.2 -->
        <div class="mb-8 px-6">
          <h2 class="text-h3 font-display text-horizon-500 mb-4">Previous Release: v0.4.2</h2>
          <p class="text-body-sm text-neutral-500 mb-4">Released: 29 December 2025</p>

          <div class="mb-6">
            <h3 class="text-body-lg font-semibold text-horizon-500 mb-4">🏢 Business Interests Module</h3>
            <ul class="space-y-2">
              <li class="flex items-start">
                <svg class="w-5 h-5 text-green-500 mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
                <span class="text-body-base text-neutral-500"><strong>Full Business Interests Module:</strong> Track Sole Trader, Partnership, Limited Company, and LLP business structures.</span>
              </li>
              <li class="flex items-start">
                <svg class="w-5 h-5 text-green-500 mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
                <span class="text-body-base text-neutral-500"><strong>Tax Deadlines:</strong> Automatic calculation based on business type (Self Assessment, Corporation Tax, VAT, PAYE).</span>
              </li>
              <li class="flex items-start">
                <svg class="w-5 h-5 text-green-500 mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
                <span class="text-body-base text-neutral-500"><strong>Exit Planning:</strong> Capital Gains Tax calculations with Business Asset Disposal Relief eligibility.</span>
              </li>
            </ul>
          </div>
        </div>

        <!-- Version History Divider -->
        <div class="border-t border-light-gray my-8"></div>

        <!-- Previous Version: v0.4.1 -->
        <div class="mb-8 px-6">
          <h2 class="text-h3 font-display text-horizon-500 mb-4">Previous Release: v0.4.1</h2>
          <p class="text-body-sm text-neutral-500 mb-4">Released: 19 December 2025</p>

          <div class="mb-6">
            <h3 class="text-body-lg font-semibold text-horizon-500 mb-4">🛡️ Protection Module Enhancements</h3>
            <ul class="space-y-2">
              <li class="flex items-start">
                <svg class="w-5 h-5 text-green-500 mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
                <span class="text-body-base text-neutral-500"><strong>Simplified Policy Overview:</strong> Clean Coverage Summary with 5 key metrics.</span>
              </li>
              <li class="flex items-start">
                <svg class="w-5 h-5 text-green-500 mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
                <span class="text-body-base text-neutral-500"><strong>5 Protection Shortfall Cards:</strong> Debt Protection, Income Replacement, Critical Illness, Sickness Cover, Disability Cover.</span>
              </li>
              <li class="flex items-start">
                <svg class="w-5 h-5 text-green-500 mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
                <span class="text-body-base text-neutral-500"><strong>UK Statutory Sick Pay:</strong> SSP calculation for employees with self-employed eligibility check.</span>
              </li>
            </ul>
          </div>
        </div>

        <!-- Version History Divider -->
        <div class="border-t border-light-gray my-8"></div>

        <!-- Previous Version: v0.2.17 -->
        <div class="mb-8 px-6">
          <h2 class="text-h3 font-display text-horizon-500 mb-4">Previous Release: v0.2.17</h2>
          <p class="text-body-sm text-neutral-500 mb-4">Released: 8 December 2025</p>

          <div class="mb-6">
            <h3 class="text-body-lg font-semibold text-horizon-500 mb-4">📄 Document Upload with AI Extraction</h3>
            <ul class="space-y-2">
              <li class="flex items-start">
                <svg class="w-5 h-5 text-green-500 mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
                <span class="text-body-base text-neutral-500"><strong>AI-Powered Extraction:</strong> Upload PDFs, images, and Excel files to extract financial data using Claude Sonnet 4.5.</span>
              </li>
              <li class="flex items-start">
                <svg class="w-5 h-5 text-green-500 mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
                <span class="text-body-base text-neutral-500"><strong>Supported Documents:</strong> Pension statements, insurance policies, investment statements, mortgages, savings.</span>
              </li>
              <li class="flex items-start">
                <svg class="w-5 h-5 text-green-500 mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
                <span class="text-body-base text-neutral-500"><strong>Review & Confirm:</strong> Review extracted fields with confidence scores before saving to your profile.</span>
              </li>
            </ul>
          </div>
        </div>

        <!-- Previous Version: v0.2.16 -->
        <div class="mb-8 px-6">
          <h2 class="text-h3 font-display text-horizon-500 mb-4">v0.2.16</h2>
          <p class="text-body-sm text-neutral-500 mb-4">Released: 27 November 2025</p>

          <div class="mb-6">
            <h3 class="text-body-lg font-semibold text-horizon-500 mb-4">🚀 UI/UX Improvements</h3>
            <ul class="space-y-2">
              <li class="flex items-start">
                <svg class="w-5 h-5 text-green-500 mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
                <span class="text-body-base text-neutral-500">Income Protection benefit display fix, Investment & Savings Plan scaffold banner, Emergency Fund data fix.</span>
              </li>
              <li class="flex items-start">
                <svg class="w-5 h-5 text-green-500 mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
                <span class="text-body-base text-neutral-500">Onboarding UI improvements: Property, Retirement, Investment, and Savings cards now match module dashboards.</span>
              </li>
            </ul>
          </div>
        </div>

        <!-- Previous Version: v0.2.15 -->
        <div class="mb-8 px-6">
          <h2 class="text-h3 font-display text-horizon-500 mb-4">v0.2.15</h2>
          <p class="text-body-sm text-neutral-500 mb-4">Released: 26 November 2025</p>

          <div class="mb-6">
            <h3 class="text-body-lg font-semibold text-horizon-500 mb-4">🐛 Bug Fixes</h3>
            <ul class="space-y-2">
              <li class="flex items-start">
                <svg class="w-5 h-5 text-red-500 mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
                <span class="text-body-base text-neutral-500"><strong>Joint Expenditure 50/50 Split:</strong> Fixed expenditure not being halved when using joint mode during onboarding.</span>
              </li>
              <li class="flex items-start">
                <svg class="w-5 h-5 text-red-500 mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
                <span class="text-body-base text-neutral-500"><strong>Spouse Edit Sync:</strong> Fixed editing spouse family member not updating the linked User account.</span>
              </li>
              <li class="flex items-start">
                <svg class="w-5 h-5 text-red-500 mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
                <span class="text-body-base text-neutral-500"><strong>Estate Plan Spouse Children:</strong> Fixed Comprehensive Estate Plan not showing children added by spouse.</span>
              </li>
            </ul>
          </div>
        </div>

        <!-- Version History Divider -->
        <div class="border-t border-light-gray my-8"></div>

        <!-- Previous Version: v0.2.14 -->
        <div class="mb-8">
          <h2 class="text-h3 font-display text-horizon-500 mb-4">Previous Release: v0.2.14</h2>
          <p class="text-body-sm text-neutral-500 mb-4">Released: 25 November 2025</p>

          <div class="mb-6">
            <h3 class="text-body-lg font-semibold text-horizon-500 mb-4">🐛 10 Major Fixes in v0.2.14</h3>
            <ul class="space-y-2">
              <li class="flex items-start">
                <svg class="w-5 h-5 text-green-500 mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
                <span class="text-body-base text-neutral-500"><strong>Family Member/Spouse Account Linking:</strong> User Profile now uses same service as onboarding for consistent behaviour.</span>
              </li>
              <li class="flex items-start">
                <svg class="w-5 h-5 text-green-500 mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
                <span class="text-body-base text-neutral-500"><strong>Joint Property Sync:</strong> Joint property editing shows full values and syncs with audit trail.</span>
              </li>
              <li class="flex items-start">
                <svg class="w-5 h-5 text-green-500 mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
                <span class="text-body-base text-neutral-500"><strong>Joint Property Cost/Income Split:</strong> Monthly costs and rental income split by ownership percentage.</span>
              </li>
              <li class="flex items-start">
                <svg class="w-5 h-5 text-green-500 mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
                <span class="text-body-base text-neutral-500"><strong>Joint Account History UI:</strong> New "Joint History" tab in Net Worth dashboard.</span>
              </li>
              <li class="flex items-start">
                <svg class="w-5 h-5 text-green-500 mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
                <span class="text-body-base text-neutral-500"><strong>Code Quality Fixes:</strong> 11 issues resolved (1 critical: division by zero prevention).</span>
              </li>
            </ul>
          </div>
        </div>

        <!-- Previous Version: v0.2.8 -->
        <div class="mb-8">
          <h2 class="text-h3 font-display text-horizon-500 mb-4">v0.2.8</h2>
          <p class="text-body-sm text-neutral-500 mb-4">Released: 14-15 November 2025</p>

          <div class="mb-6">
            <h3 class="text-body-lg font-semibold text-horizon-500 mb-4">🐛 Critical Bug Fixes in v0.2.8</h3>
            <ul class="space-y-2">
              <li class="flex items-start">
                <svg class="w-5 h-5 text-red-500 mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
                <span class="text-body-base text-neutral-500"><strong>Joint Mortgage Reciprocal Creation (CRITICAL):</strong> Fixed joint properties with mortgages only creating ONE mortgage record instead of TWO. Added missing database columns (ownership_type, joint_owner_name) and Vue watchers to sync ownership data.</span>
              </li>
              <li class="flex items-start">
                <svg class="w-5 h-5 text-green-500 mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
                <span class="text-body-base text-neutral-500"><strong>Expenditure Form Defaulting:</strong> Fixed expenditure form defaulting to simple total instead of detailed breakdown during onboarding. Now correctly preserves user's entry mode choice.</span>
              </li>
              <li class="flex items-start">
                <svg class="w-5 h-5 text-green-500 mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
                <span class="text-body-base text-neutral-500"><strong>State Pension Field Mismatch:</strong> Fixed 422 validation errors caused by field name mismatch. Changed backend validation to accept weekly_amount instead of amount.</span>
              </li>
              <li class="flex items-start">
                <svg class="w-5 h-5 text-green-500 mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
                <span class="text-body-base text-neutral-500"><strong>Mortgage Fields Persistence:</strong> Fixed all 24 mortgage fields (repayment type, rate type, lender, start date, etc.) now persisting correctly. Added comprehensive validation.</span>
              </li>
              <li class="flex items-start">
                <svg class="w-5 h-5 text-green-500 mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
                <span class="text-body-base text-neutral-500"><strong>Invalid Mortgage Type Removed:</strong> Removed invalid 'part_and_part' mortgage type from enum. Only valid types: repayment, interest_only, mixed.</span>
              </li>
            </ul>
          </div>

          <!-- Module Improvements -->
          <div class="mb-6">
            <h3 class="text-body-lg font-semibold text-horizon-500 mb-4">⚙️ Module Improvements in v0.2.8</h3>
            <ul class="space-y-2">
              <li class="flex items-start">
                <svg class="w-5 h-5 text-blue-500 mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
                <span class="text-body-base text-neutral-500"><strong>Retirement Module Consolidation:</strong> Created unified pension form with visual type selection (DC/DB/State). Consolidated retirement access to /net-worth/retirement only. Removed standalone /retirement route.</span>
              </li>
              <li class="flex items-start">
                <svg class="w-5 h-5 text-blue-500 mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
                <span class="text-body-base text-neutral-500"><strong>Net Worth UI Enhancements:</strong> Card grid layouts for investments and pensions. Color coding (blue assets, red liabilities). Joint property/mortgage full amounts with user share display. Mixed mortgage type display with percentages.</span>
              </li>
              <li class="flex items-start">
                <svg class="w-5 h-5 text-blue-500 mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
                <span class="text-body-base text-neutral-500"><strong>Database Migrations:</strong> 10 new migrations adding DC pension types, joint mortgage ownership fields, state pension enhancements, and more.</span>
              </li>
            </ul>
          </div>

          <!-- Current Functionality -->
          <div>
            <h3 class="text-body-lg font-semibold text-horizon-500 mb-4">🚀 Current Functionality</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div class="border border-light-gray rounded-lg p-4">
                <h4 class="font-semibold text-horizon-500 mb-2">✅ Completed Modules</h4>
                <ul class="space-y-1 text-body-sm text-neutral-500">
                  <li><strong>Core Features:</strong></li>
                  <li>• User authentication & profile management</li>
                  <li>• Family members & spouse account linking</li>
                  <li>• Income, occupation, and assets tracking</li>
                  <li>• Joint ownership & trust ownership</li>
                  <li>• Main dashboard with quick actions</li>

                  <li class="mt-2"><strong>Investment Module:</strong></li>
                  <li>• Portfolio analysis with risk metrics (Alpha, Beta, Sharpe)</li>
                  <li>• Asset allocation & diversification scoring</li>
                  <li>• Fee analysis & optimisation suggestions</li>
                  <li>• Monte Carlo simulations (1,000 iterations)</li>
                  <li>• Efficient Frontier analysis</li>
                  <li>• Holdings management & rebalancing</li>

                  <li class="mt-2"><strong>Retirement Module:</strong></li>
                  <li>• DC/DB pension tracking & projections</li>
                  <li>• State pension integration</li>
                  <li>• Annual allowance monitoring (£60k + carry forward)</li>
                  <li>• DC pension portfolio optimisation</li>
                  <li>• Holdings management for pension pots</li>
                  <li>• Retirement readiness scoring</li>
                  <li>• Decumulation planning</li>

                  <li class="mt-2"><strong>Savings Module:</strong></li>
                  <li>• Emergency fund calculator & runway analysis</li>
                  <li>• Savings goals tracking & progress</li>
                  <li>• ISA allowance tracking (£20k annual limit)</li>
                  <li>• Cash accounts management</li>
                </ul>
              </div>
              <div class="border border-light-gray rounded-lg p-4">
                <h4 class="font-semibold text-horizon-500 mb-2">✅ Completed Modules (cont.)</h4>
                <ul class="space-y-1 text-body-sm text-neutral-500">
                  <li><strong>Estate Planning Module:</strong></li>
                  <li>• IHT calculation (NRB £325k + RNRB £175k)</li>
                  <li>• Second death IHT planning</li>
                  <li>• Net worth aggregation across all modules</li>
                  <li>• Gifting strategy (PET/CLT 7-year rules)</li>
                  <li>• Life policy strategy (WoL vs self-insurance)</li>
                  <li>• Will planning with bequests</li>
                  <li>• Letter to Spouse (emergency instructions)</li>
                  <li>• Trust tracking & management</li>

                  <li class="mt-2"><strong>Protection Module:</strong></li>
                  <li>• Life insurance coverage gap analysis</li>
                  <li>• Critical illness adequacy scoring</li>
                  <li>• Income protection recommendations</li>
                  <li>• Human capital calculations</li>
                  <li>• Protection plan generation</li>
                  <li>• Scenario analysis (death, CI, disability)</li>

                  <li class="mt-2"><strong>Plans Module:</strong></li>
                  <li>• Investment & Savings Plan (consolidated view)</li>
                  <li>• Risk metrics dashboard</li>
                  <li>• Goal progress tracking</li>
                  <li>• Asset allocation analysis</li>
                </ul>

                <h4 class="font-semibold text-horizon-500 mb-2 mt-4">🚧 In Development</h4>
                <ul class="space-y-1 text-body-sm text-neutral-500">
                  <li>• Holistic planning coordination (cross-module)</li>
                  <li>• Advanced scenario modeling</li>
                  <li>• Tax optimisation strategies</li>
                  <li>• Probate planning</li>
                  <li>• Comprehensive reporting & exports</li>
                </ul>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Version History -->
      <div class="bg-white rounded-lg shadow-sm mb-6">
        <div class="p-6 border-b border-light-gray">
          <h2 class="text-h3 font-display text-horizon-500">Version History</h2>
          <p class="mt-1 text-body-sm text-neutral-500">Previous releases and changes</p>
        </div>
        <div class="p-6">
          <!-- v0.2.1 -->
          <div class="mb-8 pb-8 border-b border-light-gray">
            <div class="flex items-center justify-between mb-4">
              <div>
                <h3 class="text-xl font-bold text-horizon-500">v0.2.1</h3>
                <p class="text-body-sm text-neutral-500">4 November 2025</p>
              </div>
              <span class="inline-flex items-center px-3 py-1 rounded-full text-body-sm font-medium bg-blue-100 text-blue-800">
                Beta Release
              </span>
            </div>
            <div class="space-y-4">
              <div>
                <h4 class="font-semibold text-horizon-500 mb-2">Features</h4>
                <ul class="space-y-1 text-body-sm text-neutral-500">
                  <li>• Investment & Savings Plans Module with consolidated goal tracking</li>
                  <li>• DC Pension Portfolio Optimisation with full holdings management</li>
                  <li>• Polymorphic Holdings System for Investment Accounts and DC Pensions</li>
                  <li>• Advanced Risk Metrics (Alpha, Beta, Sharpe Ratio, VaR, Max Drawdown)</li>
                  <li>• Portfolio Analysis & Fee Optimisation suggestions</li>
                  <li>• Monte Carlo Simulation and Efficient Frontier integration</li>
                </ul>
              </div>
              <div>
                <h4 class="font-semibold text-horizon-500 mb-2">Technical</h4>
                <ul class="space-y-1 text-body-sm text-neutral-500">
                  <li>• Service Reuse Architecture (shared Investment services in Retirement)</li>
                  <li>• Database migration with rollback for polymorphic holdings</li>
                  <li>• Enhanced RetirementAgent with 5 Investment module services</li>
                  <li>• 9 new API endpoints for holdings and portfolio analysis</li>
                </ul>
              </div>
            </div>
          </div>

          <!-- v0.1.2.13 -->
          <div class="mb-8 pb-8 border-b border-light-gray">
            <div class="flex items-center justify-between mb-4">
              <div>
                <h3 class="text-xl font-bold text-horizon-500">v0.1.2.13</h3>
                <p class="text-body-sm text-neutral-500">29 October 2025</p>
              </div>
              <span class="inline-flex items-center px-3 py-1 rounded-full text-body-sm font-medium bg-green-100 text-green-800">
                Beta Release
              </span>
            </div>
            <div class="space-y-4">
              <div>
                <h4 class="font-semibold text-horizon-500 mb-2">Features</h4>
                <ul class="space-y-1 text-body-sm text-neutral-500">
                  <li>• Letter to Spouse feature with comprehensive emergency instructions</li>
                  <li>• Dual view mode (edit own letter, view spouse's letter)</li>
                  <li>• Auto-population from all modules (Protection, Estate, Savings, etc.)</li>
                  <li>• Application rebranding from "FPS" to "Fynla"</li>
                </ul>
              </div>
              <div>
                <h4 class="font-semibold text-horizon-500 mb-2">Bug Fixes</h4>
                <ul class="space-y-1 text-body-sm text-neutral-500">
                  <li>• Fixed Letter to Spouse type errors (number_format, ucfirst)</li>
                  <li>• Fixed API authentication issues (401 errors)</li>
                  <li>• Fixed component lifecycle mounting issues</li>
                  <li>• Fixed User Profile layout tab overflow</li>
                </ul>
              </div>
            </div>
          </div>

          <!-- v0.1.2.3 -->
          <div class="mb-8 pb-8 border-b border-light-gray">
            <div class="flex items-center justify-between mb-4">
              <div>
                <h3 class="text-xl font-bold text-horizon-500">v0.1.2.3</h3>
                <p class="text-body-sm text-neutral-500">25 October 2025</p>
              </div>
              <span class="inline-flex items-center px-3 py-1 rounded-full text-body-sm font-medium bg-green-100 text-green-800">
                Beta Release
              </span>
            </div>
            <div class="space-y-4">
              <div>
                <h4 class="font-semibold text-horizon-500 mb-2">Features</h4>
                <ul class="space-y-1 text-body-sm text-neutral-500">
                  <li>• Comprehensive Protection Plan with professional report generation</li>
                  <li>• Executive Summary with coverage analysis and recommendations</li>
                  <li>• Coverage Gap Analysis for Life/CI/IP</li>
                  <li>• Optimised Strategy with prioritized recommendations</li>
                  <li>• Scenario Analysis (death, critical illness, disability)</li>
                  <li>• Print/PDF Export functionality</li>
                </ul>
              </div>
              <div>
                <h4 class="font-semibold text-horizon-500 mb-2">Bug Fixes</h4>
                <ul class="space-y-1 text-body-sm text-neutral-500">
                  <li>• Fixed Protection Plan array key mismatch errors</li>
                  <li>• Fixed type errors with str_replace() for nullable fields</li>
                  <li>• Fixed Income Protection data structure access issues</li>
                </ul>
              </div>
            </div>
          </div>

          <!-- v0.1.2.2 -->
          <div class="mb-8 pb-8 border-b border-light-gray">
            <div class="flex items-center justify-between mb-4">
              <div>
                <h3 class="text-xl font-bold text-horizon-500">v0.1.2.2</h3>
                <p class="text-body-sm text-neutral-500">21-23 October 2025</p>
              </div>
              <span class="inline-flex items-center px-3 py-1 rounded-full text-body-sm font-medium bg-green-100 text-green-800">
                Beta Release
              </span>
            </div>
            <div class="space-y-4">
              <div>
                <h4 class="font-semibold text-horizon-500 mb-2">Features</h4>
                <ul class="space-y-1 text-body-sm text-neutral-500">
                  <li>• Second Death IHT Planning with actuarial life expectancy calculations</li>
                  <li>• Life Policy Strategy (Whole of Life vs. Self-Insurance comparison)</li>
                  <li>• Cross-Module Data Integration for estate planning</li>
                  <li>• Dual Gifting Timelines for married couples</li>
                  <li>• NRB Transfer Tracking (up to £650k total NRB)</li>
                  <li>• IHT Mitigation Strategies with priority badges</li>
                  <li>• Spouse Account Management (auto-creation & linking)</li>
                  <li>• Joint Ownership across all asset types</li>
                  <li>• Trust Ownership tracking</li>
                  <li>• Spouse Data Sharing Permissions</li>
                  <li>• Email Notifications for spouse accounts</li>
                  <li>• Password Security (first-time login password change)</li>
                  <li>• Will Planning Module with death scenarios & bequests</li>
                </ul>
              </div>
              <div>
                <h4 class="font-semibold text-horizon-500 mb-2">Bug Fixes</h4>
                <ul class="space-y-1 text-body-sm text-neutral-500">
                  <li>• Fixed Second Death IHT calculation undefined property error</li>
                  <li>• Fixed type error with nullable arrays in mitigation strategies</li>
                  <li>• Fixed incorrect array access for different IHT data structures</li>
                  <li>• Fixed Investment Account Form 422 validation error</li>
                  <li>• Fixed Estate IHT calculation to include liabilities</li>
                  <li>• Fixed Property & Mortgage calculation errors</li>
                  <li>• Fixed Dashboard Estate display issues</li>
                  <li>• Fixed Will Planning authentication (401 errors)</li>
                </ul>
              </div>
            </div>
          </div>

          <!-- v0.1.1 -->
          <div class="mb-8 pb-8 border-b border-light-gray">
            <div class="flex items-center justify-between mb-4">
              <div>
                <h3 class="text-xl font-bold text-horizon-500">v0.1.1</h3>
                <p class="text-body-sm text-neutral-500">20 October 2025</p>
              </div>
              <span class="inline-flex items-center px-3 py-1 rounded-full text-body-sm font-medium bg-savannah-100 text-neutral-500">
                Internal Release
              </span>
            </div>
            <ul class="space-y-2 text-body-sm text-neutral-500">
              <li class="flex items-start">
                <svg class="w-4 h-4 text-horizon-400 mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
                Net Worth module improvements (property management, ISA tracking)
              </li>
              <li class="flex items-start">
                <svg class="w-4 h-4 text-horizon-400 mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
                Investment account management components
              </li>
              <li class="flex items-start">
                <svg class="w-4 h-4 text-horizon-400 mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
                Emergency fund tracking and analysis
              </li>
            </ul>
          </div>

          <!-- v0.1.0 -->
          <div class="mb-4">
            <div class="flex items-center justify-between mb-4">
              <div>
                <h3 class="text-xl font-bold text-horizon-500">v0.1.0</h3>
                <p class="text-body-sm text-neutral-500">18 October 2025</p>
              </div>
              <span class="inline-flex items-center px-3 py-1 rounded-full text-body-sm font-medium bg-yellow-100 text-yellow-800">
                Alpha Release
              </span>
            </div>
            <div class="space-y-4">
              <div>
                <h4 class="font-semibold text-horizon-500 mb-2">Features</h4>
                <ul class="space-y-1 text-body-sm text-neutral-500">
                  <li>• Laravel 10.x backend with Sanctum authentication</li>
                  <li>• Vue.js 3 frontend with full auth flow</li>
                  <li>• User Profile module (personal info, family, income, assets, liabilities)</li>
                  <li>• Main dashboard with module overview cards</li>
                  <li>• Settings page</li>
                  <li>• Pest testing framework (60+ tests)</li>
                </ul>
              </div>
              <div>
                <h4 class="font-semibold text-horizon-500 mb-2">Bug Fixes</h4>
                <ul class="space-y-1 text-body-sm text-neutral-500">
                  <li>• Fixed rate limiting issues (disabled throttle for development)</li>
                  <li>• Resolved infinite loop in FamilyMembers component</li>
                  <li>• Fixed missing axios import in TrustsDashboard</li>
                  <li>• Corrected router navigation warnings</li>
                  <li>• Restored missing navbar in NetWorthDashboard</li>
                  <li>• Fixed RecommendationsAggregatorService 500 error</li>
                </ul>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Technical Details -->
      <div class="bg-white rounded-lg shadow-sm">
        <div class="p-6 border-b border-light-gray">
          <h2 class="text-h3 font-display text-horizon-500">Technical Stack</h2>
          <p class="mt-1 text-body-sm text-neutral-500">Technology and frameworks used in this version</p>
        </div>
        <div class="p-6">
          <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            <div>
              <h4 class="font-semibold text-horizon-500 mb-2">Backend</h4>
              <ul class="space-y-1 text-body-sm text-neutral-500">
                <li>• Laravel 10.x</li>
                <li>• PHP 8.2+</li>
                <li>• MySQL 8.0+</li>
                <li>• Laravel Sanctum (auth)</li>
                <li>• Pest (testing)</li>
              </ul>
            </div>
            <div>
              <h4 class="font-semibold text-horizon-500 mb-2">Frontend</h4>
              <ul class="space-y-1 text-body-sm text-neutral-500">
                <li>• Vue.js 3</li>
                <li>• Vuex 4.x (state management)</li>
                <li>• Vue Router 4.x</li>
                <li>• Vite 5.x (build tool)</li>
                <li>• Tailwind CSS 3.x</li>
              </ul>
            </div>
            <div>
              <h4 class="font-semibold text-horizon-500 mb-2">Development</h4>
              <ul class="space-y-1 text-body-sm text-neutral-500">
                <li>• Git version control</li>
                <li>• Laravel Pint (code formatting)</li>
                <li>• PSR-12 coding standards</li>
                <li>• Hot Module Replacement (HMR)</li>
              </ul>
            </div>
          </div>
        </div>
      </div>

      <!-- Footer Note -->
      <div class="mt-8 text-center text-body-sm text-neutral-500">
        <p>For detailed changelog and bug fixes, see the <a href="/OCTOBER_2025_FEATURES_UPDATE.md" class="text-raspberry-500 hover:text-raspberry-600 underline">October 2025 Features Update</a> document.</p>
        <p class="mt-2">Built with Claude Code</p>
      </div>
    </div>
  </AppLayout>
</template>

<script>
import AppLayout from '@/layouts/AppLayout.vue';

export default {
  name: 'VersionHistory',

  components: {
    AppLayout,
  },
};
</script>
