<template>
  <PublicLayout>
    <!-- Hero -->
    <div class="relative flex items-center bg-gradient-to-r from-horizon-500 to-raspberry-500 overflow-hidden">
      <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10 text-left w-full">
        <h1 class="text-4xl md:text-6xl font-black text-white mb-4">Building Foundations &mdash; Your First Home and Beyond</h1>
        <p class="text-lg text-white/70">
          A practical guide to buying your first home, building savings, managing debt, and laying strong financial foundations.
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
              <span>You&rsquo;re saving for a house deposit.</span>
            </li>
            <li class="flex items-start gap-2">
              <span class="w-1.5 h-1.5 rounded-full bg-violet-500 mt-1.5 flex-shrink-0"></span>
              <span>You&rsquo;ve got a workplace pension running but aren&rsquo;t sure if it&rsquo;s enough.</span>
            </li>
            <li class="flex items-start gap-2">
              <span class="w-1.5 h-1.5 rounded-full bg-violet-500 mt-1.5 flex-shrink-0"></span>
              <span>You&rsquo;re juggling debt and savings.</span>
            </li>
            <li class="flex items-start gap-2">
              <span class="w-1.5 h-1.5 rounded-full bg-violet-500 mt-1.5 flex-shrink-0"></span>
              <span>You want to know you&rsquo;re on the right track.</span>
            </li>
          </ul>
        </div>

        <!-- Getting on the Property Ladder -->
        <div>
          <h2 class="text-base font-bold text-horizon-500 mb-2">Getting on the Property Ladder</h2>
          <p class="text-sm text-neutral-600 leading-relaxed mb-3">
            Buying your first home is one of the biggest financial steps you will take. Getting the basics right makes a real difference.
          </p>
          <ul class="space-y-1.5 text-sm text-neutral-600">
            <li class="flex items-start gap-2">
              <span class="w-1.5 h-1.5 rounded-full bg-violet-500 mt-1.5 flex-shrink-0"></span>
              <span>Save into a <strong>Lifetime ISA</strong> for the 25% government bonus (maximum &pound;4,000 per year, property up to &pound;450,000).</span>
            </li>
            <li class="flex items-start gap-2">
              <span class="w-1.5 h-1.5 rounded-full bg-violet-500 mt-1.5 flex-shrink-0"></span>
              <span>Understand mortgage affordability &mdash; lenders typically offer 4&ndash;4.5 times your income.</span>
            </li>
            <li class="flex items-start gap-2">
              <span class="w-1.5 h-1.5 rounded-full bg-violet-500 mt-1.5 flex-shrink-0"></span>
              <span>Budget for stamp duty, solicitor fees, and surveys &mdash; add 3&ndash;5% to the purchase price.</span>
            </li>
            <li class="flex items-start gap-2">
              <span class="w-1.5 h-1.5 rounded-full bg-violet-500 mt-1.5 flex-shrink-0"></span>
              <span>Don&rsquo;t drain your emergency fund for a deposit.</span>
            </li>
          </ul>
        </div>

        <!-- ISA Strategy -->
        <div>
          <h2 class="text-base font-bold text-horizon-500 mb-2">ISA Strategy</h2>
          <p class="text-sm text-neutral-600 leading-relaxed mb-3">
            ISAs are your most powerful tax-free savings and investment tool. Use them in order of priority.
          </p>
          <ul class="space-y-1.5 text-sm text-neutral-600">
            <li class="flex items-start gap-2">
              <span class="w-1.5 h-1.5 rounded-full bg-violet-500 mt-1.5 flex-shrink-0"></span>
              <span>Max the <strong>Lifetime ISA first</strong> (&pound;4,000 for your house deposit).</span>
            </li>
            <li class="flex items-start gap-2">
              <span class="w-1.5 h-1.5 rounded-full bg-violet-500 mt-1.5 flex-shrink-0"></span>
              <span>Remaining allowance: <strong>Cash ISA</strong> for short-term goals, <strong>Stocks and Shares ISA</strong> for goals 5+ years away.</span>
            </li>
            <li class="flex items-start gap-2">
              <span class="w-1.5 h-1.5 rounded-full bg-violet-500 mt-1.5 flex-shrink-0"></span>
              <span>Couples: &pound;40,000 combined ISA allowance per year.</span>
            </li>
          </ul>
        </div>

        <!-- Pension Check -->
        <div>
          <h2 class="text-base font-bold text-horizon-500 mb-2">Pension Check</h2>
          <p class="text-sm text-neutral-600 leading-relaxed mb-3">
            Your workplace pension is one of the best benefits you have. Make sure you are getting the most from it.
          </p>
          <ul class="space-y-1.5 text-sm text-neutral-600">
            <li class="flex items-start gap-2">
              <span class="w-1.5 h-1.5 rounded-full bg-violet-500 mt-1.5 flex-shrink-0"></span>
              <span>Are you getting the <strong>full employer match</strong>? If your employer matches up to 5%, contribute at least 5%.</span>
            </li>
            <li class="flex items-start gap-2">
              <span class="w-1.5 h-1.5 rounded-full bg-violet-500 mt-1.5 flex-shrink-0"></span>
              <span>Consider <strong>salary sacrifice</strong> if your employer offers it &mdash; you save on National Insurance too.</span>
            </li>
            <li class="flex items-start gap-2">
              <span class="w-1.5 h-1.5 rounded-full bg-violet-500 mt-1.5 flex-shrink-0"></span>
              <span>Check the charges on your workplace pension fund.</span>
            </li>
          </ul>
        </div>

        <!-- Debt Strategy -->
        <div>
          <h2 class="text-base font-bold text-horizon-500 mb-2">Debt Strategy</h2>
          <p class="text-sm text-neutral-600 leading-relaxed mb-3">
            Not all debt is equal. Prioritise the expensive stuff and be strategic about the rest.
          </p>
          <ul class="space-y-1.5 text-sm text-neutral-600">
            <li class="flex items-start gap-2">
              <span class="w-1.5 h-1.5 rounded-full bg-violet-500 mt-1.5 flex-shrink-0"></span>
              <span><strong>Pay off high-interest debt first</strong> &mdash; credit cards, overdrafts.</span>
            </li>
            <li class="flex items-start gap-2">
              <span class="w-1.5 h-1.5 rounded-full bg-violet-500 mt-1.5 flex-shrink-0"></span>
              <span><strong>Student loan:</strong> don&rsquo;t overpay &mdash; it&rsquo;s written off eventually.</span>
            </li>
            <li class="flex items-start gap-2">
              <span class="w-1.5 h-1.5 rounded-full bg-violet-500 mt-1.5 flex-shrink-0"></span>
              <span><strong>Mortgage:</strong> overpay if your rate is above savings rates.</span>
            </li>
            <li class="flex items-start gap-2">
              <span class="w-1.5 h-1.5 rounded-full bg-violet-500 mt-1.5 flex-shrink-0"></span>
              <span>Keep a minimum of <strong>3 months&rsquo; expenses</strong> as an emergency fund.</span>
            </li>
          </ul>
        </div>

        <!-- Tracking Progress -->
        <div>
          <h2 class="text-base font-bold text-horizon-500 mb-2">Tracking Progress</h2>
          <p class="text-sm text-neutral-600 leading-relaxed mb-2">
            Your <strong>net worth</strong> is the single best measure of your financial progress. Calculate it: assets minus liabilities.
          </p>
          <p class="text-sm text-neutral-600 leading-relaxed">
            Track it monthly or quarterly. It should trend upwards &mdash; even slowly. That upward trend is proof that what you are doing is working.
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
  name: 'BuildingFoundationsGuidePage',
  components: { PublicLayout, GuideNav, GuideArticleFooter },

  mounted() {
    document.title = 'First Home & Financial Foundations Guide UK | Fynla';
    const meta = document.querySelector('meta[name="description"]');
    if (meta) {
      meta.setAttribute('content', 'A practical guide to buying your first home, building savings, managing debt, and laying strong financial foundations in your late 20s and 30s.');
    }
  },
};
</script>
