<template>
  <PublicLayout>
    <!-- Hero Section -->
    <div class="relative flex items-center bg-gradient-to-r from-horizon-500 to-raspberry-500 overflow-hidden">
      <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10 text-left w-full">
        <h1 class="text-4xl md:text-6xl font-black text-white mb-4">
          Fynla
          <span class="text-raspberry-300">features</span>
        </h1>
        <p class="text-lg text-white/70 mb-6">
          See how Fynla compares to other financial tools across the features that matter.
        </p>
        <router-link
          to="/register"
          class="inline-block px-8 py-3 bg-spring-500 text-white font-semibold rounded-lg hover:bg-spring-600 transition-colors"
        >
          Get started free
        </router-link>
      </div>
    </div>

    <!-- Comparison Table -->
    <div class="bg-eggshell-500 py-14">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <h2 class="text-xl sm:text-2xl md:text-3xl lg:text-4xl font-bold text-horizon-500 text-center mb-4">Everything you need, one platform</h2>

        <!-- Legend -->
        <div class="flex items-center justify-center gap-6 mb-8">
          <div class="flex items-center gap-2">
            <span class="inline-block w-4 h-4 rounded-full bg-spring-500"></span>
            <span class="text-xs text-neutral-500">Full feature</span>
          </div>
          <div class="flex items-center gap-2">
            <span class="inline-block w-4 h-4 rounded-full harvey-half"></span>
            <span class="text-xs text-neutral-500">Partial / limited</span>
          </div>
          <div class="flex items-center gap-2">
            <span class="inline-block w-4 h-4 rounded-full bg-horizon-500/10 border-2 border-horizon-500/20"></span>
            <span class="text-xs text-neutral-500">Not available</span>
          </div>
        </div>

        <!-- Table -->
        <div class="overflow-x-auto">
          <table class="w-full" style="border-collapse: separate; border-spacing: 8px 0; margin-bottom: 16px;">
            <colgroup>
              <col style="width: 34%;" />
              <col style="width: 16.5%;" />
              <col style="width: 16.5%;" />
              <col style="width: 16.5%;" />
              <col style="width: 16.5%;" />
            </colgroup>
            <thead>
              <tr>
                <th class="text-left py-5 px-5">
                  <span class="text-base font-bold text-horizon-500 uppercase tracking-wider">Feature</span>
                </th>
                <th class="text-center py-5 px-4">
                  <span class="text-base font-bold text-raspberry-500 uppercase tracking-wider">Fynla</span>
                </th>
                <th class="text-center py-5 px-4">
                  <router-link to="/compare/fynla-vs-financial-centralisation-platform" class="text-base font-bold text-horizon-500 uppercase tracking-wider leading-tight hover:text-light-pink-400 transition-colors">Financial centralisation<br/>platform</router-link>
                </th>
                <th class="text-center py-5 px-4">
                  <router-link to="/compare/fynla-vs-financial-planning-platform" class="text-base font-bold text-horizon-500 uppercase tracking-wider leading-tight hover:text-light-pink-400 transition-colors">Financial planning<br/>platform</router-link>
                </th>
                <th class="text-center py-5 px-4">
                  <router-link to="/compare/fynla-vs-financial-investment-platform" class="text-base font-bold text-horizon-500 uppercase tracking-wider leading-tight hover:text-light-pink-400 transition-colors">Financial investment<br/>platform</router-link>
                </th>
              </tr>
            </thead>
            <tbody>
              <template v-for="(section, sIdx) in featureSections" :key="'s-'+sIdx">
                <!-- Section heading -->
                <tr>
                  <td class="pt-6 pb-2 px-5">
                    <p class="text-[0.85rem] font-bold text-neutral-500 uppercase tracking-widest">{{ section.heading }}</p>
                  </td>
                  <td :class="sIdx === 0 ? 'fynla-border-top' : 'fynla-border-mid'" style="padding: 0;"></td>
                  <td :class="sIdx === 0 ? 'other-border-top' : 'other-border-mid'" style="padding: 0;"></td>
                  <td :class="sIdx === 0 ? 'other-border-top' : 'other-border-mid'" style="padding: 0;"></td>
                  <td :class="sIdx === 0 ? 'other-border-top' : 'other-border-mid'" style="padding: 0;"></td>
                </tr>
                <!-- Feature rows -->
                <tr
                  v-for="(feat, fIdx) in section.features"
                  :key="sIdx+'-'+fIdx"
                  class="feature-row transition-colors"
                >
                  <td class="py-4 px-5 border-b border-horizon-500/[0.08]">
                    <p class="text-base font-semibold text-horizon-500">{{ feat.name }}</p>
                    <p v-if="feat.desc" class="text-[0.8rem] text-neutral-500 mt-0.5">{{ feat.desc }}</p>
                  </td>
                  <td
                    class="py-4 px-4 text-center"
                    :class="isLastFeature(sIdx, fIdx) ? 'fynla-border-bot' : 'fynla-border-mid'"
                  >
                    <span class="inline-block w-6 h-6 rounded-full" :class="harveyClass(feat.fynla)"></span>
                  </td>
                  <td
                    class="py-4 px-4 text-center"
                    :class="isLastFeature(sIdx, fIdx) ? 'other-border-bot' : 'other-border-mid'"
                  >
                    <span class="inline-block w-6 h-6 rounded-full" :class="harveyClass(feat.centralisation)"></span>
                  </td>
                  <td
                    class="py-4 px-4 text-center"
                    :class="isLastFeature(sIdx, fIdx) ? 'other-border-bot' : 'other-border-mid'"
                  >
                    <span class="inline-block w-6 h-6 rounded-full" :class="harveyClass(feat.planning)"></span>
                  </td>
                  <td
                    class="py-4 px-4 text-center"
                    :class="isLastFeature(sIdx, fIdx) ? 'other-border-bot' : 'other-border-mid'"
                  >
                    <span class="inline-block w-6 h-6 rounded-full" :class="harveyClass(feat.investment)"></span>
                  </td>
                </tr>
              </template>
            </tbody>
          </table>
        </div>

        <!-- Footnote -->
        <p class="text-sm text-neutral-500 text-center mt-6">
          Feature comparisons have been taken from current platforms that are on the market. You can also see a full overview of each:
        </p>
        <div class="flex flex-wrap items-center justify-center gap-x-4 gap-y-1 mt-2">
          <router-link to="/compare/fynla-vs-financial-centralisation-platform" class="text-sm text-raspberry-500 hover:text-light-pink-400 font-medium transition-colors">Financial centralisation platform</router-link>
          <span class="text-neutral-500">|</span>
          <router-link to="/compare/fynla-vs-financial-planning-platform" class="text-sm text-raspberry-500 hover:text-light-pink-400 font-medium transition-colors">Financial planning platform</router-link>
          <span class="text-neutral-500">|</span>
          <router-link to="/compare/fynla-vs-financial-investment-platform" class="text-sm text-raspberry-500 hover:text-light-pink-400 font-medium transition-colors">Financial investment platform</router-link>
          <span class="text-neutral-500">|</span>
          <router-link to="/compare/fynla-vs-spreadsheets" class="text-sm text-raspberry-500 hover:text-light-pink-400 font-medium transition-colors">Spreadsheets</router-link>
        </div>
      </div>
    </div>

    <!-- CTA Section -->
    <div class="bg-light-pink-100 py-16">
      <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
        <h2 class="text-3xl font-bold text-horizon-500 mb-4">Ready to take control of your finances?</h2>
        <p class="text-neutral-500 mb-8">Start your free trial today. No credit card required.</p>
        <router-link
          to="/register"
          class="inline-flex items-center px-8 py-4 bg-raspberry-500 text-white rounded-xl font-semibold text-lg hover:bg-raspberry-600 transition-all shadow-lg hover:shadow-xl"
        >
          Get started free
          <svg class="w-5 h-5 ml-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6" />
          </svg>
        </router-link>
      </div>
    </div>
  </PublicLayout>
</template>

<script>
import PublicLayout from '@/layouts/PublicLayout.vue';

export default {
  name: 'FeaturesPage',
  components: { PublicLayout },

  data() {
    return {
      // Values: 'full', 'half', 'empty'
      featureSections: [
        {
          heading: 'Budgeting & Savings',
          features: [
            { name: 'Savings goal tracker', desc: 'Set targets and track progress toward your savings goals.', fynla: 'full', centralisation: 'full', planning: 'half', investment: 'empty' },
            { name: 'ISA allowance tracking', desc: 'Monitor your annual ISA allowance usage across all accounts.', fynla: 'full', centralisation: 'empty', planning: 'empty', investment: 'half' },
            { name: 'Student loan planning', desc: 'Understand your plan type, repayment threshold, and strategy.', fynla: 'full', centralisation: 'empty', planning: 'empty', investment: 'empty' },
            { name: 'Budgeting tools', desc: 'Track income, expenditure, and build a realistic spending plan.', fynla: 'full', centralisation: 'full', planning: 'half', investment: 'empty' },
          ],
        },
        {
          heading: 'Net Worth & Investments',
          features: [
            { name: 'Net worth dashboard', desc: 'Everything you own minus everything you owe — one clear number.', fynla: 'full', centralisation: 'full', planning: 'full', investment: 'empty' },
            { name: 'Investment analysis', desc: 'Portfolio tracking, asset allocation, and risk profile matching.', fynla: 'full', centralisation: 'half', planning: 'half', investment: 'full' },
          ],
        },
        {
          heading: 'Pensions & Retirement',
          features: [
            { name: 'Pension tracker', desc: 'All your UK pensions in one place with combined projections.', fynla: 'full', centralisation: 'empty', planning: 'half', investment: 'half' },
            { name: 'When Can I Retire?', desc: 'Data-driven retirement age projection based on your actual finances.', fynla: 'full', centralisation: 'empty', planning: 'full', investment: 'empty' },
            { name: 'Monte Carlo simulations', desc: 'Thousands of market scenarios to test the probability of your plan succeeding.', fynla: 'full', centralisation: 'empty', planning: 'full', investment: 'empty' },
            { name: 'Drawdown management', desc: 'Track pension drawdown rate and model how long your pot will last.', fynla: 'full', centralisation: 'empty', planning: 'half', investment: 'empty' },
          ],
        },
        {
          heading: 'Protection & Estate Planning',
          features: [
            { name: 'Protection gap analyser', desc: 'See where your life insurance and income protection fall short.', fynla: 'full', centralisation: 'empty', planning: 'empty', investment: 'empty' },
            { name: 'Inheritance Tax planning', desc: 'Estate valuation, nil-rate bands, gifting strategies, and 2027 pension changes.', fynla: 'full', centralisation: 'empty', planning: 'empty', investment: 'empty' },
            { name: 'In Case of Emergency letters', desc: 'A complete guide to your finances for your family if the worst happens.', fynla: 'full', centralisation: 'empty', planning: 'empty', investment: 'empty' },
          ],
        },
      ],
    };
  },

  methods: {
    harveyClass(value) {
      if (value === 'full') return 'bg-spring-500';
      if (value === 'half') return 'harvey-half';
      return 'bg-horizon-500/10 border-2 border-horizon-500/20';
    },

    isLastFeature(sIdx, fIdx) {
      const isLastSection = sIdx === this.featureSections.length - 1;
      const isLastInSection = fIdx === this.featureSections[sIdx].features.length - 1;
      return isLastSection && isLastInSection;
    },
  },

  mounted() {
    document.title = 'Fynla Features — Compare Financial Planning Tools | Fynla';
    const meta = document.querySelector('meta[name="description"]');
    if (meta) meta.setAttribute('content', 'See how Fynla compares to financial centralisation, planning, and investment platforms. 14 features across budgeting, pensions, protection, and estate planning.');
  },
};
</script>

<style scoped>
.harvey-half {
  background: linear-gradient(to right, #37B679 50%, rgba(31, 42, 68, 0.1) 50%);
}

/* Row hover highlight */
.feature-row:hover td {
  @apply bg-white;
}

/* Fynla column — light pink background with dark pink border */
.fynla-border-top {
  @apply bg-light-pink-100 border-light-pink-300;
  border-top-width: 3px;
  border-left-width: 3px;
  border-right-width: 3px;
  border-bottom-width: 0;
  border-radius: 16px 16px 0 0;
}
.fynla-border-mid {
  @apply bg-light-pink-100 border-light-pink-300;
  border-left-width: 3px;
  border-right-width: 3px;
  border-top-width: 0;
  border-bottom-width: 0;
}
.fynla-border-bot {
  @apply bg-light-pink-100 border-light-pink-300;
  border-top-width: 0;
  border-left-width: 3px;
  border-right-width: 3px;
  border-bottom-width: 3px;
  border-radius: 0 0 16px 16px;
}

/* Other columns — light blue background */
.other-border-top {
  @apply bg-light-blue-100;
  border-radius: 16px 16px 0 0;
}
.other-border-mid {
  @apply bg-light-blue-100;
}
.other-border-bot {
  @apply bg-light-blue-100;
  border-radius: 0 0 16px 16px;
}
</style>
