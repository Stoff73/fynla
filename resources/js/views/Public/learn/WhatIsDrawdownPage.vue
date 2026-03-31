<template>
  <PublicLayout>
    <!-- Hero -->
    <div class="relative flex items-center bg-gradient-to-r from-horizon-500 to-raspberry-500 overflow-hidden">
      <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10 text-left w-full">
        <h1 class="text-4xl md:text-6xl font-black text-white mb-4">What is Pension Drawdown?</h1>
        <p class="text-lg text-white/70">
          Flexible retirement income, explained simply.
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
            <div class="bg-eggshell-500 rounded-lg p-4">
              <p class="text-sm font-semibold text-horizon-500 mb-1">Drawdown</p>
              <ul class="space-y-1 text-xs text-neutral-500">
                <li>Flexible &mdash; withdraw what you need</li>
                <li>Pot can keep growing</li>
                <li>You bear the investment risk</li>
                <li>Remaining pot passes to beneficiaries</li>
              </ul>
            </div>
            <div class="bg-eggshell-500 rounded-lg p-4">
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
  name: 'WhatIsDrawdownPage',
  components: { PublicLayout, GuideNav, GuideArticleFooter },

  mounted() {
    document.title = 'What is Pension Drawdown? — Flexi-Access Drawdown Explained | Fynla';
    let meta = document.querySelector('meta[name="description"]');
    if (!meta) {
      meta = document.createElement('meta');
      meta.setAttribute('name', 'description');
      document.head.appendChild(meta);
    }
    meta.setAttribute('content', 'Pension drawdown lets you take flexible income from your pension while keeping the rest invested. Learn how drawdown works, tax implications, and whether it is right for your retirement.');
  },
};
</script>
