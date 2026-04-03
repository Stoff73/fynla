<template>
  <PublicLayout>
    <!-- Hero -->
    <div class="relative flex items-center bg-gradient-to-r from-horizon-500 to-raspberry-500 overflow-hidden">
      <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10 text-left w-full">
        <h1 class="text-4xl md:text-6xl font-black text-white mb-4">What is an ISA?</h1>
        <p class="text-lg text-white/70">
          Tax-free savings and investments, explained without the jargon.
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
          <h2 class="text-lg font-bold text-horizon-500 mb-2">The Short Answer</h2>
          <p class="text-sm text-neutral-600 leading-relaxed">
            An Individual Savings Account (ISA) is a tax-free wrapper for your savings or investments. You can save up to
            <strong>&pound;20,000 per tax year</strong> and pay no tax on the interest or investment returns.
          </p>
        </div>

        <!-- Types of ISA -->
        <div>
          <h2 class="text-lg font-bold text-horizon-500 mb-3">Types of ISA</h2>
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div v-for="isa in isaTypes" :key="isa.name" class="bg-eggshell-500 rounded-lg p-4">
              <p class="text-sm font-semibold text-horizon-500 mb-1">{{ isa.name }}</p>
              <p class="text-xs text-neutral-500 leading-relaxed">{{ isa.desc }}</p>
            </div>
          </div>
        </div>

        <!-- ISA Allowance -->
        <div>
          <h2 class="text-lg font-bold text-horizon-500 mb-2">ISA Allowance {{ currentTaxYear }}</h2>
          <ul class="space-y-1.5 text-sm text-neutral-600">
            <li class="flex items-start gap-2">
              <span class="w-1.5 h-1.5 rounded-full bg-spring-500 mt-1.5 flex-shrink-0"></span>
              <span><strong>&pound;20,000</strong> total across all ISAs per tax year.</span>
            </li>
            <li class="flex items-start gap-2">
              <span class="w-1.5 h-1.5 rounded-full bg-spring-500 mt-1.5 flex-shrink-0"></span>
              <span>You can split the allowance between different ISA types.</span>
            </li>
            <li class="flex items-start gap-2">
              <span class="w-1.5 h-1.5 rounded-full bg-spring-500 mt-1.5 flex-shrink-0"></span>
              <span>Lifetime ISA has a <strong>&pound;4,000</strong> sub-limit within the &pound;20,000.</span>
            </li>
          </ul>
        </div>

        <!-- How ISAs Save You Tax -->
        <div>
          <h2 class="text-lg font-bold text-horizon-500 mb-2">How ISAs Save You Tax</h2>
          <p class="text-sm text-neutral-600 leading-relaxed">
            Without an ISA, you would pay income tax on savings interest above your Personal Savings Allowance
            (&pound;1,000 for basic rate taxpayers, &pound;500 for higher rate). Inside an ISA, all interest and
            investment growth is completely tax-free &mdash; no income tax, no Capital Gains Tax, and no tax on dividends.
          </p>
        </div>

        <!-- ISA Tips -->
        <div>
          <h2 class="text-lg font-bold text-horizon-500 mb-2">ISA Tips</h2>
          <ul class="space-y-1.5 text-sm text-neutral-600">
            <li class="flex items-start gap-2">
              <span class="w-1.5 h-1.5 rounded-full bg-violet-500 mt-1.5 flex-shrink-0"></span>
              <span><strong>Use it or lose it.</strong> Your annual allowance does not roll over to the next tax year.</span>
            </li>
            <li class="flex items-start gap-2">
              <span class="w-1.5 h-1.5 rounded-full bg-violet-500 mt-1.5 flex-shrink-0"></span>
              <span><strong>Start early in the tax year.</strong> The sooner your money is sheltered, the more tax-free growth you capture.</span>
            </li>
            <li class="flex items-start gap-2">
              <span class="w-1.5 h-1.5 rounded-full bg-violet-500 mt-1.5 flex-shrink-0"></span>
              <span><strong>Consider Stocks and Shares</strong> for longer-term goals (5+ years) where you can ride out short-term volatility.</span>
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
import { getCurrentTaxYear } from '@/utils/dateFormatter';

export default {
  name: 'WhatIsAnIsaPage',
  components: { PublicLayout, GuideNav, GuideArticleFooter },

  computed: {
    currentTaxYear() {
      return getCurrentTaxYear();
    },
  },

  data() {
    return {
      isaTypes: [
        { name: 'Cash ISA', desc: 'A savings account where the interest is tax-free. Best for short-term goals or your emergency fund.' },
        { name: 'Stocks and Shares ISA', desc: 'Invest in funds, shares, or bonds with no tax on gains or dividends. Better for longer-term goals (5+ years).' },
        { name: 'Lifetime ISA', desc: 'For under-40s saving for a first home or retirement. The government adds a 25% bonus on up to \u00A34,000 per year.' },
        { name: 'Innovative Finance ISA', desc: 'Holds peer-to-peer loans. Higher potential returns but higher risk than cash.' },
      ],
    };
  },

  mounted() {
    document.title = 'What is an ISA? — Individual Savings Account Explained | Fynla';
    let meta = document.querySelector('meta[name="description"]');
    if (!meta) {
      meta = document.createElement('meta');
      meta.setAttribute('name', 'description');
      document.head.appendChild(meta);
    }
    meta.setAttribute('content', 'An ISA is a tax-free savings and investment wrapper. Learn about Cash ISAs, Stocks and Shares ISAs, Lifetime ISAs, the £20,000 annual allowance, and how to maximise your tax-free savings in the UK.');
  },
};
</script>
