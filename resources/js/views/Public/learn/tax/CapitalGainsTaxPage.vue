<template>
  <PublicLayout>
    <!-- Hero -->
    <div class="relative flex items-center bg-gradient-to-r from-horizon-500 to-raspberry-500 overflow-hidden">
      <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10 text-left w-full">
        <h1 class="text-4xl md:text-6xl font-black text-white mb-4">Capital Gains Tax Rates {{ currentTaxYear }}</h1>
        <p class="text-lg text-white/70">
          Current rates, annual exempt amount, and how to reduce your Capital Gains Tax bill.
        </p>
      </div>
    </div>

    <GuideNav />

    <!-- Content -->
    <section class="py-8 bg-eggshell-500">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="bg-white rounded-xl border border-light-gray p-6 sm:p-8 space-y-8">

        <!-- Overview -->
        <div class="bg-eggshell-500 rounded-lg p-5">
          <h2 class="text-base font-bold text-horizon-500 mb-2">What is Capital Gains Tax?</h2>
          <p class="text-sm text-neutral-600 leading-relaxed">
            Capital Gains Tax is charged on the profit you make when you sell or dispose of an asset that has
            increased in value. You only pay tax on the gain &mdash; not the full sale price. Everyone gets an
            annual exempt amount before tax is due.
          </p>
        </div>

        <!-- Annual Exempt Amount -->
        <div>
          <h2 class="text-base font-bold text-horizon-500 mb-2">Annual Exempt Amount</h2>
          <p class="text-sm text-neutral-600 leading-relaxed mb-3">
            The annual exempt amount for {{ currentTaxYear }} is <strong>&pound;3,000</strong>. This was reduced from
            &pound;6,000 in 2023/24 and &pound;12,300 in 2022/23. Gains below this threshold are tax-free.
          </p>
          <p class="text-xs text-neutral-400">
            The annual exempt amount cannot be carried forward &mdash; use it or lose it each tax year.
          </p>
        </div>

        <!-- Tax Rates -->
        <div>
          <h2 class="text-base font-bold text-horizon-500 mb-2">Tax Rates</h2>
          <div class="bg-white rounded-lg border border-light-gray overflow-hidden">
            <table class="w-full text-sm">
              <thead>
                <tr class="bg-savannah-50">
                  <th class="text-left px-4 py-2.5 text-xs font-semibold text-horizon-500">Asset Type</th>
                  <th class="text-right px-4 py-2.5 text-xs font-semibold text-horizon-500">Basic Rate</th>
                  <th class="text-right px-4 py-2.5 text-xs font-semibold text-horizon-500">Higher Rate</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-light-gray">
                <tr>
                  <td class="px-4 py-2.5 text-neutral-500">Most assets (shares, funds, etc.)</td>
                  <td class="px-4 py-2.5 text-neutral-500 text-right">10%</td>
                  <td class="px-4 py-2.5 text-neutral-500 text-right">20%</td>
                </tr>
                <tr>
                  <td class="px-4 py-2.5 text-neutral-500">Residential property</td>
                  <td class="px-4 py-2.5 text-neutral-500 text-right">18%</td>
                  <td class="px-4 py-2.5 text-neutral-500 text-right">24%</td>
                </tr>
              </tbody>
            </table>
          </div>
          <p class="text-xs text-neutral-400 mt-2">
            Which rate you pay depends on your total taxable income plus the gain. If the gain pushes you into the higher rate band, the portion above the threshold is taxed at the higher rate.
          </p>
        </div>

        <!-- Tax-Free Wrappers -->
        <div>
          <h2 class="text-base font-bold text-horizon-500 mb-2">Tax-Free Wrappers</h2>
          <ul class="space-y-1.5 text-sm text-neutral-600">
            <li class="flex items-start gap-2">
              <span class="w-1.5 h-1.5 rounded-full bg-spring-500 mt-1.5 flex-shrink-0"></span>
              <span><strong>ISAs:</strong> gains within an ISA are completely exempt from Capital Gains Tax.</span>
            </li>
            <li class="flex items-start gap-2">
              <span class="w-1.5 h-1.5 rounded-full bg-spring-500 mt-1.5 flex-shrink-0"></span>
              <span><strong>Pensions:</strong> gains within pension wrappers are tax-free.</span>
            </li>
            <li class="flex items-start gap-2">
              <span class="w-1.5 h-1.5 rounded-full bg-spring-500 mt-1.5 flex-shrink-0"></span>
              <span><strong>Main residence:</strong> your primary home is usually exempt under Private Residence Relief.</span>
            </li>
          </ul>
        </div>

        <!-- Ways to Reduce Your Bill -->
        <div>
          <h2 class="text-base font-bold text-horizon-500 mb-2">Ways to Reduce Your Bill</h2>
          <ul class="space-y-1.5 text-sm text-neutral-600">
            <li class="flex items-start gap-2">
              <span class="w-1.5 h-1.5 rounded-full bg-violet-500 mt-1.5 flex-shrink-0"></span>
              <span><strong>Use your annual exempt amount</strong> &mdash; sell assets each year to crystallise gains within the &pound;3,000 allowance.</span>
            </li>
            <li class="flex items-start gap-2">
              <span class="w-1.5 h-1.5 rounded-full bg-violet-500 mt-1.5 flex-shrink-0"></span>
              <span><strong>Offset losses</strong> &mdash; capital losses can be set against gains in the same or future tax years.</span>
            </li>
            <li class="flex items-start gap-2">
              <span class="w-1.5 h-1.5 rounded-full bg-violet-500 mt-1.5 flex-shrink-0"></span>
              <span><strong>Transfer to your spouse</strong> &mdash; transfers between spouses are at no gain/no loss, giving you two annual exempt amounts.</span>
            </li>
            <li class="flex items-start gap-2">
              <span class="w-1.5 h-1.5 rounded-full bg-violet-500 mt-1.5 flex-shrink-0"></span>
              <span><strong>Hold assets in ISAs</strong> &mdash; moving investments into ISA wrappers shelters future gains.</span>
            </li>
          </ul>
        </div>

        <!-- Related Links -->
        <div class="border-t border-light-gray pt-6">
          <h2 class="text-sm font-bold text-horizon-500 mb-3">Related</h2>
          <div class="flex flex-wrap gap-2">
            <router-link to="/learn/glossary" class="text-xs text-raspberry-500 hover:text-raspberry-600 underline">Glossary</router-link>
            <router-link to="/calculators" class="text-xs text-raspberry-500 hover:text-raspberry-600 underline">Calculators</router-link>
            <router-link to="/learn/tax/isa-allowance" class="text-xs text-raspberry-500 hover:text-raspberry-600 underline">ISA Allowance Guide</router-link>
            <router-link to="/learn/tax/tax-year-checklist" class="text-xs text-raspberry-500 hover:text-raspberry-600 underline">Tax Year Checklist</router-link>
          </div>
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
  name: 'CapitalGainsTaxPage',
  components: { PublicLayout, GuideNav, GuideArticleFooter },

  computed: {
    currentTaxYear() {
      return getCurrentTaxYear();
    },
  },

  mounted() {
    document.title = `Capital Gains Tax Rates ${this.currentTaxYear} \u2014 UK Guide | Fynla`;
    const meta = document.querySelector('meta[name="description"]');
    if (meta) {
      meta.setAttribute('content', `Capital Gains Tax rates for ${this.currentTaxYear}: 18% basic rate, 24% higher rate (residential property: 18%/24%). Annual exempt amount: \u00A33,000. Learn how to reduce your CGT bill.`);
    }
  },
};
</script>
