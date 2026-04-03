<template>
  <PublicLayout>
    <!-- Hero -->
    <div class="relative flex items-center bg-gradient-to-r from-horizon-500 to-raspberry-500 overflow-hidden">
      <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10 text-left w-full">
        <h1 class="text-4xl md:text-6xl font-black text-white mb-4">ISA Allowance and Rules</h1>
        <p class="text-lg text-white/70">
          Everything you need to know about ISAs &mdash; types, limits, and strategies for maximising your tax-free savings.
        </p>
      </div>
    </div>

    <GuideNav />

    <!-- Content -->
    <section class="py-8 bg-eggshell-500">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="bg-white rounded-xl border border-light-gray p-6 sm:p-8 space-y-8">

        <!-- The Annual Allowance -->
        <div class="bg-eggshell-500 rounded-lg p-5">
          <h2 class="text-base font-bold text-horizon-500 mb-2">The Annual Allowance</h2>
          <p class="text-sm text-neutral-600 leading-relaxed">
            The ISA allowance is <strong>&pound;20,000</strong> per tax year (6 April to 5 April). You can split
            this across different ISA types, but the total must not exceed &pound;20,000. The allowance does not
            carry forward &mdash; if you don't use it, you lose it.
          </p>
        </div>

        <!-- Types of ISA -->
        <div>
          <h2 class="text-base font-bold text-horizon-500 mb-2">Types of ISA</h2>
          <div class="space-y-3">
            <div class="bg-eggshell-500 rounded-lg p-5">
              <p class="text-sm font-semibold text-horizon-500 mb-1">Cash ISA</p>
              <p class="text-xs text-neutral-500">
                Tax-free savings account. Interest earned is free from income tax. You can hold one Cash ISA per tax year (multiple from different years).
              </p>
            </div>
            <div class="bg-eggshell-500 rounded-lg p-5">
              <p class="text-sm font-semibold text-horizon-500 mb-1">Stocks and Shares ISA</p>
              <p class="text-xs text-neutral-500">
                Invest in funds, shares, and bonds with no Capital Gains Tax or income tax on returns. Best suited for goals five or more years away.
              </p>
            </div>
            <div class="bg-eggshell-500 rounded-lg p-5">
              <p class="text-sm font-semibold text-horizon-500 mb-1">Lifetime ISA</p>
              <p class="text-xs text-neutral-500">
                For 18&ndash;39 year olds saving for a first home or retirement. &pound;4,000 annual sub-limit (counts towards &pound;20,000 total). 25% government bonus on contributions &mdash; up to &pound;1,000 per year. 25% penalty on withdrawals for other purposes.
              </p>
            </div>
            <div class="bg-eggshell-500 rounded-lg p-5">
              <p class="text-sm font-semibold text-horizon-500 mb-1">Innovative Finance ISA</p>
              <p class="text-xs text-neutral-500">
                Holds peer-to-peer lending investments. Interest and gains are tax-free, but the underlying investments carry higher risk.
              </p>
            </div>
          </div>
        </div>

        <!-- Limits Table -->
        <div>
          <h2 class="text-base font-bold text-horizon-500 mb-2">Limits at a Glance</h2>
          <div class="bg-white rounded-lg border border-light-gray overflow-hidden">
            <table class="w-full text-sm">
              <thead>
                <tr class="bg-savannah-50">
                  <th class="text-left px-4 py-2.5 text-xs font-semibold text-horizon-500">ISA Type</th>
                  <th class="text-right px-4 py-2.5 text-xs font-semibold text-horizon-500">Annual Limit</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-light-gray">
                <tr>
                  <td class="px-4 py-2.5 text-neutral-500">Adult ISA (all types combined)</td>
                  <td class="px-4 py-2.5 text-neutral-500 text-right">&pound;20,000</td>
                </tr>
                <tr>
                  <td class="px-4 py-2.5 text-neutral-500">Lifetime ISA sub-limit</td>
                  <td class="px-4 py-2.5 text-neutral-500 text-right">&pound;4,000</td>
                </tr>
                <tr>
                  <td class="px-4 py-2.5 text-neutral-500">Junior ISA</td>
                  <td class="px-4 py-2.5 text-neutral-500 text-right">&pound;9,000</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <!-- Key Rules -->
        <div>
          <h2 class="text-base font-bold text-horizon-500 mb-2">Key Rules</h2>
          <ul class="space-y-1.5 text-sm text-neutral-600">
            <li class="flex items-start gap-2">
              <span class="w-1.5 h-1.5 rounded-full bg-spring-500 mt-1.5 flex-shrink-0"></span>
              <span>You can hold one of each ISA type per tax year.</span>
            </li>
            <li class="flex items-start gap-2">
              <span class="w-1.5 h-1.5 rounded-full bg-spring-500 mt-1.5 flex-shrink-0"></span>
              <span>Unused allowance does not carry forward to the next tax year.</span>
            </li>
            <li class="flex items-start gap-2">
              <span class="w-1.5 h-1.5 rounded-full bg-spring-500 mt-1.5 flex-shrink-0"></span>
              <span>ISAs are individual &mdash; there is no joint ISA. Couples each get &pound;20,000.</span>
            </li>
            <li class="flex items-start gap-2">
              <span class="w-1.5 h-1.5 rounded-full bg-spring-500 mt-1.5 flex-shrink-0"></span>
              <span>You must be a UK resident and aged 18 or over (16 for Cash ISAs).</span>
            </li>
          </ul>
        </div>

        <!-- Transferring ISAs -->
        <div>
          <h2 class="text-base font-bold text-horizon-500 mb-2">Transferring ISAs</h2>
          <p class="text-sm text-neutral-600 leading-relaxed mb-3">
            You can transfer ISAs between providers without losing your tax-free status. Current year contributions
            must be transferred in full; previous years' contributions can be transferred partially.
          </p>
          <p class="text-xs text-neutral-400">
            Always use the formal ISA transfer process &mdash; withdrawing and redepositing uses up your annual allowance.
          </p>
        </div>

        <!-- Flexible ISAs -->
        <div>
          <h2 class="text-base font-bold text-horizon-500 mb-2">Flexible ISAs</h2>
          <p class="text-sm text-neutral-600 leading-relaxed">
            Some providers offer flexible ISAs. If you withdraw money, you can replace it within the same tax year
            without it counting towards your annual allowance. Not all providers offer this, so check before withdrawing.
          </p>
        </div>

        <!-- Related Links -->
        <div class="border-t border-light-gray pt-6">
          <h2 class="text-sm font-bold text-horizon-500 mb-3">Related</h2>
          <div class="flex flex-wrap gap-2">
            <router-link to="/learn/what-is-an-isa" class="text-xs text-raspberry-500 hover:text-raspberry-600 underline">What is an ISA?</router-link>
            <router-link to="/learn/should-i-use-a-lisa-or-isa" class="text-xs text-raspberry-500 hover:text-raspberry-600 underline">Should I use a Lifetime ISA or ISA?</router-link>
            <router-link to="/calculators" class="text-xs text-raspberry-500 hover:text-raspberry-600 underline">Calculators</router-link>
            <router-link to="/insights/isa-allowance-2025-26" class="text-xs text-raspberry-500 hover:text-raspberry-600 underline">ISA Allowance {{ currentTaxYear }} (Insights)</router-link>
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
  name: 'IsaAllowanceTaxPage',
  components: { PublicLayout, GuideNav, GuideArticleFooter },

  computed: {
    currentTaxYear() {
      return getCurrentTaxYear();
    },
  },

  mounted() {
    document.title = 'ISA Allowance & Rules \u2014 Complete UK Guide | Fynla';
    const meta = document.querySelector('meta[name="description"]');
    if (meta) {
      meta.setAttribute('content', 'Everything you need to know about ISA allowances: \u00A320,000 annual limit, types of ISA, rules, and strategies for maximising your tax-free savings.');
    }
  },
};
</script>
