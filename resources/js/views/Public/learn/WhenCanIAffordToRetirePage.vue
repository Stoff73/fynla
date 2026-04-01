<template>
  <PublicLayout>
    <!-- Hero -->
    <div class="relative flex items-center bg-gradient-to-r from-horizon-500 to-raspberry-500 overflow-hidden">
      <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10 text-left w-full">
        <h1 class="text-4xl md:text-6xl font-black text-white mb-4">When Can I Afford to Retire?</h1>
        <p class="text-lg text-white/70">
          A practical UK guide to calculating your retirement date based on pensions, savings, and spending.
        </p>
      </div>
    </div>

    <GuideNav />

    <!-- Content -->
    <section class="py-8 bg-eggshell-500">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="bg-white rounded-xl border border-light-gray p-6 sm:p-8 space-y-8">

        <!-- The Short Answer -->
        <div class="bg-eggshell-500 rounded-lg p-5">
          <h2 class="text-base font-bold text-horizon-500 mb-2">The Short Answer</h2>
          <p class="text-sm text-neutral-600 leading-relaxed">
            Your retirement date depends on three things: how much you'll need to spend, how much income your pensions
            and investments will generate, and how long your money needs to last. The government says 67 (rising to 68)
            &mdash; but your number might be very different.
          </p>
        </div>

        <!-- Step 1: Know What You'll Need -->
        <div>
          <h2 class="text-base font-bold text-horizon-500 mb-2">Step 1: Know What You'll Need</h2>
          <p class="text-sm text-neutral-600 leading-relaxed mb-3">
            Most people need 50&ndash;70% of their working income in retirement. The Pensions and Lifetime Savings
            Association (PLSA) sets benchmarks:
          </p>
          <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div class="bg-eggshell-500 rounded-lg p-5">
              <p class="text-sm font-semibold text-horizon-500 mb-1">Minimum</p>
              <p class="text-xs text-neutral-500 leading-relaxed">&pound;14,400/year (single)<br>&pound;22,400/year (couple)</p>
            </div>
            <div class="bg-eggshell-500 rounded-lg p-5">
              <p class="text-sm font-semibold text-horizon-500 mb-1">Moderate</p>
              <p class="text-xs text-neutral-500 leading-relaxed">&pound;31,300/year (single)<br>&pound;43,100/year (couple)</p>
            </div>
            <div class="bg-eggshell-500 rounded-lg p-5">
              <p class="text-sm font-semibold text-horizon-500 mb-1">Comfortable</p>
              <p class="text-xs text-neutral-500 leading-relaxed">&pound;43,100/year (single)<br>&pound;59,000/year (couple)</p>
            </div>
          </div>
        </div>

        <!-- Step 2: Add Up What You've Got -->
        <div>
          <h2 class="text-base font-bold text-horizon-500 mb-2">Step 2: Add Up What You've Got</h2>
          <ul class="space-y-1.5 text-sm text-neutral-600">
            <li class="flex items-start gap-2">
              <span class="w-1.5 h-1.5 rounded-full bg-spring-500 mt-1.5 flex-shrink-0"></span>
              <span><strong>State Pension</strong> (check your forecast at gov.uk).</span>
            </li>
            <li class="flex items-start gap-2">
              <span class="w-1.5 h-1.5 rounded-full bg-spring-500 mt-1.5 flex-shrink-0"></span>
              <span><strong>Defined Benefit pensions</strong> (guaranteed income).</span>
            </li>
            <li class="flex items-start gap-2">
              <span class="w-1.5 h-1.5 rounded-full bg-spring-500 mt-1.5 flex-shrink-0"></span>
              <span><strong>Defined Contribution pensions</strong> (pot to draw from).</span>
            </li>
            <li class="flex items-start gap-2">
              <span class="w-1.5 h-1.5 rounded-full bg-spring-500 mt-1.5 flex-shrink-0"></span>
              <span><strong>ISAs and investments.</strong></span>
            </li>
            <li class="flex items-start gap-2">
              <span class="w-1.5 h-1.5 rounded-full bg-spring-500 mt-1.5 flex-shrink-0"></span>
              <span><strong>Property equity</strong> (if downsizing).</span>
            </li>
            <li class="flex items-start gap-2">
              <span class="w-1.5 h-1.5 rounded-full bg-spring-500 mt-1.5 flex-shrink-0"></span>
              <span><strong>Other income</strong> (rental, part-time work).</span>
            </li>
          </ul>
        </div>

        <!-- Step 3: Bridge the Gap -->
        <div>
          <h2 class="text-base font-bold text-horizon-500 mb-2">Step 3: Bridge the Gap</h2>
          <p class="text-sm text-neutral-600 leading-relaxed mb-3">
            If you want to retire before State Pension age, you need private income to bridge the gap.
          </p>
          <div class="bg-eggshell-500 rounded-lg p-5">
            <p class="text-xs text-neutral-600 leading-relaxed">
              <span class="font-semibold text-horizon-500">Example:</span> Retire at 58, State Pension at 67 = 9 years
              to fund from private sources. At &pound;30,000/year, that's <strong>&pound;270,000</strong> needed just for
              the bridge.
            </p>
          </div>
        </div>

        <!-- Step 4: Stress-Test It -->
        <div>
          <h2 class="text-base font-bold text-horizon-500 mb-2">Step 4: Stress-Test It</h2>
          <p class="text-sm text-neutral-600 leading-relaxed mb-3">
            A single projection assumes everything goes to plan. But what if markets fall 30% in year one? What if
            inflation runs at 5% for a decade? What if you live to 100?
          </p>
          <p class="text-sm text-neutral-600 leading-relaxed">
            <strong>Monte Carlo simulations</strong> test thousands of scenarios and tell you the probability of your
            plan succeeding.
          </p>
        </div>

        <!-- The Levers You Can Pull -->
        <div>
          <h2 class="text-base font-bold text-horizon-500 mb-2">The Levers You Can Pull</h2>
          <ul class="space-y-1.5 text-sm text-neutral-600">
            <li class="flex items-start gap-2">
              <span class="w-1.5 h-1.5 rounded-full bg-violet-500 mt-1.5 flex-shrink-0"></span>
              <span><strong>Increase pension contributions</strong> &mdash; even 1% more makes a difference over decades.</span>
            </li>
            <li class="flex items-start gap-2">
              <span class="w-1.5 h-1.5 rounded-full bg-violet-500 mt-1.5 flex-shrink-0"></span>
              <span><strong>Delay retirement by 1&ndash;2 years</strong> &mdash; double benefit: more saving, less spending.</span>
            </li>
            <li class="flex items-start gap-2">
              <span class="w-1.5 h-1.5 rounded-full bg-violet-500 mt-1.5 flex-shrink-0"></span>
              <span><strong>Downsize property.</strong></span>
            </li>
            <li class="flex items-start gap-2">
              <span class="w-1.5 h-1.5 rounded-full bg-violet-500 mt-1.5 flex-shrink-0"></span>
              <span><strong>Work part-time</strong> in early retirement.</span>
            </li>
            <li class="flex items-start gap-2">
              <span class="w-1.5 h-1.5 rounded-full bg-violet-500 mt-1.5 flex-shrink-0"></span>
              <span><strong>Reduce planned spending.</strong></span>
            </li>
          </ul>
        </div>

        </div>
      </div>
    </section>

    <GuideArticleFooter />
  </PublicLayout>
</template>

<script>
import PublicLayout from '@/layouts/PublicLayout.vue';
import GuideNav from '@/components/Shared/GuideNav.vue';
import GuideArticleFooter from '@/components/Shared/GuideArticleFooter.vue';

export default {
  name: 'WhenCanIAffordToRetirePage',
  components: { PublicLayout, GuideNav, GuideArticleFooter },

  mounted() {
    document.title = 'When Can I Afford to Retire? — UK Retirement Guide | Fynla';
    const meta = document.querySelector('meta[name="description"]');
    if (meta) {
      meta.setAttribute('content', 'Find out when you can realistically stop working. A practical UK guide to calculating your retirement date based on pensions, savings, and spending.');
    }
  },
};
</script>
