<template>
  <PublicLayout>
    <!-- Hero -->
    <section class="bg-gradient-to-r from-horizon-500 to-raspberry-500 py-16 sm:py-20">
      <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
        <h1 class="text-3xl sm:text-4xl font-black text-white mb-3">What is Pension Drawdown?</h1>
        <p class="text-base text-white/80 max-w-xl mx-auto">
          Flexible retirement income, explained simply.
        </p>
      </div>
    </section>

    <!-- Content -->
    <section class="py-12 bg-eggshell-500">
      <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 space-y-10">

        <!-- The Short Answer -->
        <div class="bg-white rounded-lg border border-light-gray p-5">
          <h2 class="text-lg font-bold text-horizon-500 mb-2">The Short Answer</h2>
          <p class="text-sm text-neutral-600 leading-relaxed">
            Drawdown (or flexi-access drawdown) lets you keep your pension invested while taking income from it.
            You choose how much to withdraw and when &mdash; giving you full control over your retirement income.
          </p>
        </div>

        <!-- How It Works -->
        <div>
          <h2 class="text-lg font-bold text-horizon-500 mb-2">How It Works</h2>
          <p class="text-sm text-neutral-600 leading-relaxed mb-3">
            At 55 (rising to 57 in 2028), you can access your Defined Contribution pension. The typical approach is:
          </p>
          <ol class="space-y-1.5 text-sm text-neutral-600 list-decimal list-inside">
            <li>Take up to <strong>25% tax-free</strong> as a lump sum.</li>
            <li>Move the remaining pot into drawdown.</li>
            <li>Draw income as you need it &mdash; monthly, annually, or ad hoc.</li>
            <li>The remaining pot stays invested and can continue to grow (or shrink).</li>
          </ol>
        </div>

        <!-- Drawdown vs Annuity -->
        <div>
          <h2 class="text-lg font-bold text-horizon-500 mb-3">Drawdown vs Annuity</h2>
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div class="bg-white rounded-lg border border-light-gray p-4">
              <p class="text-sm font-semibold text-horizon-500 mb-1">Drawdown</p>
              <ul class="space-y-1 text-xs text-neutral-500">
                <li>Flexible &mdash; withdraw what you need</li>
                <li>Pot can keep growing</li>
                <li>You bear the investment risk</li>
                <li>Remaining pot passes to beneficiaries</li>
              </ul>
            </div>
            <div class="bg-white rounded-lg border border-light-gray p-4">
              <p class="text-sm font-semibold text-horizon-500 mb-1">Annuity</p>
              <ul class="space-y-1 text-xs text-neutral-500">
                <li>Guaranteed income for life</li>
                <li>No investment decisions to make</li>
                <li>Inflexible once purchased</li>
                <li>Rates may be poor at the time you buy</li>
              </ul>
            </div>
          </div>
        </div>

        <!-- The Risks -->
        <div>
          <h2 class="text-lg font-bold text-horizon-500 mb-2">The Risks</h2>
          <ul class="space-y-1.5 text-sm text-neutral-600">
            <li class="flex items-start gap-2">
              <span class="w-1.5 h-1.5 rounded-full bg-raspberry-500 mt-1.5 flex-shrink-0"></span>
              <span><strong>Sequence of returns risk.</strong> Poor investment returns in your early retirement years can devastate your pot &mdash; even if markets recover later.</span>
            </li>
            <li class="flex items-start gap-2">
              <span class="w-1.5 h-1.5 rounded-full bg-raspberry-500 mt-1.5 flex-shrink-0"></span>
              <span><strong>Longevity risk.</strong> Living longer than expected means your money needs to last longer.</span>
            </li>
            <li class="flex items-start gap-2">
              <span class="w-1.5 h-1.5 rounded-full bg-raspberry-500 mt-1.5 flex-shrink-0"></span>
              <span><strong>Taking too much too soon.</strong> Large early withdrawals reduce the pot that is left to grow.</span>
            </li>
          </ul>
        </div>

        <!-- Tax on Drawdown -->
        <div>
          <h2 class="text-lg font-bold text-horizon-500 mb-2">Tax on Drawdown</h2>
          <p class="text-sm text-neutral-600 leading-relaxed">
            Withdrawals above the 25% tax-free amount are taxed as income. Taking large lump sums can push you into
            higher tax bands, so many people spread their withdrawals across multiple tax years to stay within lower bands.
          </p>
        </div>

        <!-- Related Links -->
        <div class="border-t border-light-gray pt-6">
          <h2 class="text-sm font-bold text-horizon-500 mb-3">Related</h2>
          <div class="flex flex-wrap gap-2">
            <router-link to="/features/when-can-i-retire" class="text-xs text-raspberry-500 hover:text-raspberry-600 underline">When can I retire?</router-link>
            <router-link to="/features/pension-tracker" class="text-xs text-raspberry-500 hover:text-raspberry-600 underline">Pension tracker</router-link>
            <router-link to="/learn/glossary" class="text-xs text-raspberry-500 hover:text-raspberry-600 underline">Glossary</router-link>
            <router-link to="/calculators" class="text-xs text-raspberry-500 hover:text-raspberry-600 underline">Pension withdrawal tax calculator</router-link>
            <router-link to="/learn" class="text-xs text-raspberry-500 hover:text-raspberry-600 underline">All guides</router-link>
          </div>
        </div>
      </div>
    </section>

    <!-- CTA -->
    <section class="py-12 bg-white">
      <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
        <h2 class="text-xl font-bold text-horizon-500 mb-2">Model your drawdown strategy in Fynla</h2>
        <p class="text-sm text-neutral-500 mb-6">See how different withdrawal rates affect your retirement income over time.</p>
        <a href="/?demo=true" class="inline-block px-6 py-2.5 bg-spring-500 text-white text-sm font-semibold rounded-lg hover:bg-spring-600 transition-colors">
          Try the demo
        </a>
      </div>
    </section>
  </PublicLayout>
</template>

<script>
import PublicLayout from '@/layouts/PublicLayout.vue';

export default {
  name: 'WhatIsDrawdownPage',
  components: { PublicLayout },

  mounted() {
    document.title = 'What is Pension Drawdown? — Explained Simply | Fynla';
  },
};
</script>
