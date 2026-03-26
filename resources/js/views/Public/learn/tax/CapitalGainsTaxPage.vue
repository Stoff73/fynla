<template>
  <PublicLayout>
    <!-- Hero -->
    <section class="bg-gradient-to-r from-horizon-500 to-raspberry-500 py-10">
      <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
        <h1 class="text-xl font-black text-white mb-2">Capital Gains Tax Rates 2025/26</h1>
        <p class="text-sm text-white/80 max-w-xl mx-auto">
          Current rates, annual exempt amount, and how to reduce your Capital Gains Tax bill.
        </p>
      </div>
    </section>

    <!-- Content -->
    <section class="py-10 bg-eggshell-500">
      <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 space-y-10">

        <!-- Overview -->
        <div class="bg-white rounded-lg border border-light-gray p-5">
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
            The annual exempt amount for 2025/26 is <strong>&pound;3,000</strong>. This was reduced from
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
    </section>

    <!-- CTA -->
    <section class="py-10 bg-gradient-to-r from-horizon-500 to-raspberry-500">
      <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
        <h2 class="text-xl font-bold text-white mb-2">Track your investments and tax wrappers in Fynla</h2>
        <p class="text-sm text-white/80 mb-6">See your ISA usage, investment performance, and tax efficiency in one place.</p>
        <a href="/?demo=true" class="inline-block px-6 py-2.5 bg-white text-horizon-600 text-sm font-semibold rounded-lg hover:bg-eggshell-500 transition-colors">
          Try the demo
        </a>
      </div>
    </section>
  </PublicLayout>
</template>

<script>
import PublicLayout from '@/layouts/PublicLayout.vue';

export default {
  name: 'CapitalGainsTaxPage',
  components: { PublicLayout },

  mounted() {
    document.title = 'Capital Gains Tax Rates 2025/26 \u2014 UK Guide | Fynla';
    const meta = document.querySelector('meta[name="description"]');
    if (meta) {
      meta.setAttribute('content', 'Capital Gains Tax rates for 2025/26: 18% basic rate, 24% higher rate (residential property: 18%/24%). Annual exempt amount: \u00A33,000. Learn how to reduce your CGT bill.');
    }
  },
};
</script>
