<template>
  <PublicLayout>
    <!-- Hero -->
    <div class="relative flex items-center bg-gradient-to-r from-horizon-500 to-raspberry-500 overflow-hidden">
      <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10 text-left w-full">
        <h1 class="text-4xl md:text-6xl font-black text-white mb-4">Planning Your Future &mdash; Your Retirement Roadmap</h1>
        <p class="text-lg text-white/70">
          A step-by-step retirement planning guide for UK workers. Covers pension consolidation, drawdown strategy, Monte Carlo testing, and the State Pension.
        </p>
      </div>
    </div>

    <GuideNav />

    <!-- Content -->
    <section class="py-8 bg-eggshell-500">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="bg-white rounded-xl border border-light-gray p-6 sm:p-8 space-y-8">

        <!-- This Guide is For You If... -->
        <div class="bg-eggshell-500 rounded-lg p-5">
          <h2 class="text-base font-bold text-horizon-500 mb-2">This Guide is For You If&hellip;</h2>
          <ul class="space-y-1.5 text-sm text-neutral-600">
            <li class="flex items-start gap-2">
              <span class="w-1.5 h-1.5 rounded-full bg-violet-500 mt-1.5 flex-shrink-0"></span>
              <span>Retirement is 5&ndash;20 years away.</span>
            </li>
            <li class="flex items-start gap-2">
              <span class="w-1.5 h-1.5 rounded-full bg-violet-500 mt-1.5 flex-shrink-0"></span>
              <span>You have multiple pension pots from different employers.</span>
            </li>
            <li class="flex items-start gap-2">
              <span class="w-1.5 h-1.5 rounded-full bg-violet-500 mt-1.5 flex-shrink-0"></span>
              <span>You&rsquo;re wondering if early retirement is realistic.</span>
            </li>
            <li class="flex items-start gap-2">
              <span class="w-1.5 h-1.5 rounded-full bg-violet-500 mt-1.5 flex-shrink-0"></span>
              <span>You want to make the most of your peak earning years.</span>
            </li>
          </ul>
        </div>

        <!-- Step 1 -->
        <div>
          <div class="flex items-center gap-3 mb-2">
            <span class="flex items-center justify-center w-7 h-7 rounded-full bg-spring-500 text-white text-xs font-bold flex-shrink-0">1</span>
            <h2 class="text-base font-bold text-horizon-500">Gather Everything</h2>
          </div>
          <p class="text-sm text-neutral-600 leading-relaxed mb-3">
            You cannot plan what you cannot see. Start by getting a complete picture of where you stand.
          </p>
          <ul class="space-y-1.5 text-sm text-neutral-600">
            <li class="flex items-start gap-2">
              <span class="w-1.5 h-1.5 rounded-full bg-violet-500 mt-1.5 flex-shrink-0"></span>
              <span>List every pension: workplace (current and old), personal, Self-Invested Personal Pension.</span>
            </li>
            <li class="flex items-start gap-2">
              <span class="w-1.5 h-1.5 rounded-full bg-violet-500 mt-1.5 flex-shrink-0"></span>
              <span>Get valuations from each provider.</span>
            </li>
            <li class="flex items-start gap-2">
              <span class="w-1.5 h-1.5 rounded-full bg-violet-500 mt-1.5 flex-shrink-0"></span>
              <span>Check your State Pension forecast at gov.uk.</span>
            </li>
            <li class="flex items-start gap-2">
              <span class="w-1.5 h-1.5 rounded-full bg-violet-500 mt-1.5 flex-shrink-0"></span>
              <span>Add ISAs, investments, and property equity.</span>
            </li>
            <li class="flex items-start gap-2">
              <span class="w-1.5 h-1.5 rounded-full bg-violet-500 mt-1.5 flex-shrink-0"></span>
              <span>Calculate your total retirement pot.</span>
            </li>
          </ul>
        </div>

        <!-- Step 2 -->
        <div>
          <div class="flex items-center gap-3 mb-2">
            <span class="flex items-center justify-center w-7 h-7 rounded-full bg-spring-500 text-white text-xs font-bold flex-shrink-0">2</span>
            <h2 class="text-base font-bold text-horizon-500">Define Your Income Need</h2>
          </div>
          <p class="text-sm text-neutral-600 leading-relaxed mb-3">
            What will you actually spend in retirement? Be honest. The Pension and Lifetime Savings Association benchmarks are a useful starting point.
          </p>
          <ul class="space-y-1.5 text-sm text-neutral-600">
            <li class="flex items-start gap-2">
              <span class="w-1.5 h-1.5 rounded-full bg-violet-500 mt-1.5 flex-shrink-0"></span>
              <span><strong>Essential costs:</strong> housing, food, bills, insurance.</span>
            </li>
            <li class="flex items-start gap-2">
              <span class="w-1.5 h-1.5 rounded-full bg-violet-500 mt-1.5 flex-shrink-0"></span>
              <span><strong>Lifestyle costs:</strong> holidays, hobbies, eating out.</span>
            </li>
            <li class="flex items-start gap-2">
              <span class="w-1.5 h-1.5 rounded-full bg-violet-500 mt-1.5 flex-shrink-0"></span>
              <span><strong>One-off costs:</strong> home repairs, car replacement.</span>
            </li>
            <li class="flex items-start gap-2">
              <span class="w-1.5 h-1.5 rounded-full bg-violet-500 mt-1.5 flex-shrink-0"></span>
              <span><strong>Later-life costs:</strong> care, mobility.</span>
            </li>
          </ul>
        </div>

        <!-- Step 3 -->
        <div>
          <div class="flex items-center gap-3 mb-2">
            <span class="flex items-center justify-center w-7 h-7 rounded-full bg-spring-500 text-white text-xs font-bold flex-shrink-0">3</span>
            <h2 class="text-base font-bold text-horizon-500">Model Your Retirement Date</h2>
          </div>
          <p class="text-sm text-neutral-600 leading-relaxed mb-2">
            Take your total pot, add the State Pension and any Defined Benefit pensions. Model whether this can sustain your spending from your target retirement age to age 90&ndash;95.
          </p>
          <p class="text-sm text-neutral-600 leading-relaxed">
            Don&rsquo;t forget inflation &mdash; &pound;30,000 today is very different from &pound;30,000 in 20 years.
          </p>
        </div>

        <!-- Step 4 -->
        <div>
          <div class="flex items-center gap-3 mb-2">
            <span class="flex items-center justify-center w-7 h-7 rounded-full bg-spring-500 text-white text-xs font-bold flex-shrink-0">4</span>
            <h2 class="text-base font-bold text-horizon-500">Stress-Test with Monte Carlo</h2>
          </div>
          <p class="text-sm text-neutral-600 leading-relaxed mb-2">
            A single projection assumes average returns every year. Real markets don&rsquo;t work that way.
          </p>
          <p class="text-sm text-neutral-600 leading-relaxed">
            Monte Carlo simulations test thousands of scenarios &mdash; good markets, bad markets, crashes early in retirement.
            Aim for <strong>80%+ probability of success</strong>.
          </p>
        </div>

        <!-- Step 5 -->
        <div>
          <div class="flex items-center gap-3 mb-2">
            <span class="flex items-center justify-center w-7 h-7 rounded-full bg-spring-500 text-white text-xs font-bold flex-shrink-0">5</span>
            <h2 class="text-base font-bold text-horizon-500">Pull the Levers</h2>
          </div>
          <p class="text-sm text-neutral-600 leading-relaxed mb-3">
            If the numbers don&rsquo;t work yet, you have options.
          </p>
          <ul class="space-y-1.5 text-sm text-neutral-600">
            <li class="flex items-start gap-2">
              <span class="w-1.5 h-1.5 rounded-full bg-violet-500 mt-1.5 flex-shrink-0"></span>
              <span><strong>Increase contributions</strong> &mdash; even 2% more has a big impact over time.</span>
            </li>
            <li class="flex items-start gap-2">
              <span class="w-1.5 h-1.5 rounded-full bg-violet-500 mt-1.5 flex-shrink-0"></span>
              <span><strong>Delay retirement by 1&ndash;3 years</strong> &mdash; a huge double benefit (more saving, less drawdown).</span>
            </li>
            <li class="flex items-start gap-2">
              <span class="w-1.5 h-1.5 rounded-full bg-violet-500 mt-1.5 flex-shrink-0"></span>
              <span><strong>Plan a phased retirement</strong> &mdash; part-time work bridging the gap.</span>
            </li>
            <li class="flex items-start gap-2">
              <span class="w-1.5 h-1.5 rounded-full bg-violet-500 mt-1.5 flex-shrink-0"></span>
              <span>Reduce target spending.</span>
            </li>
            <li class="flex items-start gap-2">
              <span class="w-1.5 h-1.5 rounded-full bg-violet-500 mt-1.5 flex-shrink-0"></span>
              <span>Consider downsizing.</span>
            </li>
          </ul>
        </div>

        <!-- Step 6 -->
        <div>
          <div class="flex items-center gap-3 mb-2">
            <span class="flex items-center justify-center w-7 h-7 rounded-full bg-spring-500 text-white text-xs font-bold flex-shrink-0">6</span>
            <h2 class="text-base font-bold text-horizon-500">Review Annually</h2>
          </div>
          <p class="text-sm text-neutral-600 leading-relaxed">
            Your retirement date isn&rsquo;t fixed &mdash; it moves as markets, contributions, and plans change.
            Check in yearly. Adjust as needed. The earlier you spot a problem, the easier it is to fix.
          </p>
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
  name: 'PlanningYourFutureGuidePage',
  components: { PublicLayout, GuideNav },

  mounted() {
    document.title = 'Retirement Planning Roadmap UK — Your Complete Guide | Fynla';
    const meta = document.querySelector('meta[name="description"]');
    if (meta) {
      meta.setAttribute('content', 'A step-by-step retirement planning guide for UK workers aged 45-60. Covers pension consolidation, drawdown strategy, Monte Carlo testing, and the State Pension.');
    }
  },
};
</script>
