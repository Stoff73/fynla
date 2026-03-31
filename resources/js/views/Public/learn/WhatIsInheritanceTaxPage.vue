<template>
  <PublicLayout>
    <!-- Hero -->
    <div class="relative flex items-center bg-gradient-to-r from-horizon-500 to-raspberry-500 overflow-hidden">
      <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10 text-left w-full">
        <h1 class="text-4xl md:text-6xl font-black text-white mb-4">What is Inheritance Tax?</h1>
        <p class="text-lg text-white/70">
          Inheritance Tax explained simply &mdash; thresholds, exemptions, and how to reduce your bill.
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
            Inheritance Tax is a tax on your estate (everything you own) when you die. It is charged at
            <strong>40%</strong> on anything above the nil rate band of <strong>&pound;325,000</strong>. With the
            residence nil rate band, couples can pass on up to &pound;1 million tax-free &mdash; but many estates
            still get caught.
          </p>
        </div>

        <!-- The Nil Rate Band -->
        <div>
          <h2 class="text-base font-bold text-horizon-500 mb-2">The Nil Rate Band</h2>
          <ul class="space-y-1.5 text-sm text-neutral-600">
            <li class="flex items-start gap-2">
              <span class="w-1.5 h-1.5 rounded-full bg-spring-500 mt-1.5 flex-shrink-0"></span>
              <span>Everyone gets a <strong>&pound;325,000</strong> nil rate band (frozen since 2009).</span>
            </li>
            <li class="flex items-start gap-2">
              <span class="w-1.5 h-1.5 rounded-full bg-spring-500 mt-1.5 flex-shrink-0"></span>
              <span>If you leave your main home to direct descendants, you also get a <strong>&pound;175,000</strong> residence nil rate band.</span>
            </li>
            <li class="flex items-start gap-2">
              <span class="w-1.5 h-1.5 rounded-full bg-spring-500 mt-1.5 flex-shrink-0"></span>
              <span>Married couples and civil partners can transfer unused allowances &mdash; meaning up to <strong>&pound;1 million</strong> can pass tax-free.</span>
            </li>
          </ul>
        </div>

        <!-- What's Included in Your Estate -->
        <div>
          <h2 class="text-base font-bold text-horizon-500 mb-2">What Is Included in Your Estate</h2>
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div class="bg-eggshell-500 rounded-lg p-5">
              <p class="text-xs font-semibold text-horizon-500 mb-2">Included</p>
              <ul class="space-y-1 text-xs text-neutral-500">
                <li>Property</li>
                <li>Savings and investments</li>
                <li>Pensions (from April 2027)</li>
                <li>Life insurance (unless held in trust)</li>
                <li>Business assets</li>
                <li>Personal possessions of value</li>
              </ul>
            </div>
            <div class="bg-eggshell-500 rounded-lg p-5">
              <p class="text-xs font-semibold text-horizon-500 mb-2">Deducted</p>
              <ul class="space-y-1 text-xs text-neutral-500">
                <li>Outstanding debts</li>
                <li>Mortgage balances</li>
                <li>Funeral costs</li>
              </ul>
            </div>
          </div>
        </div>

        <!-- Common Exemptions -->
        <div>
          <h2 class="text-base font-bold text-horizon-500 mb-2">Common Exemptions</h2>
          <ul class="space-y-1.5 text-sm text-neutral-600">
            <li class="flex items-start gap-2">
              <span class="w-1.5 h-1.5 rounded-full bg-spring-500 mt-1.5 flex-shrink-0"></span>
              <span><strong>Spouse or civil partner:</strong> gifts between spouses are unlimited and tax-free.</span>
            </li>
            <li class="flex items-start gap-2">
              <span class="w-1.5 h-1.5 rounded-full bg-spring-500 mt-1.5 flex-shrink-0"></span>
              <span><strong>Charities:</strong> gifts to registered charities are exempt.</span>
            </li>
            <li class="flex items-start gap-2">
              <span class="w-1.5 h-1.5 rounded-full bg-spring-500 mt-1.5 flex-shrink-0"></span>
              <span><strong>Annual gift exemption:</strong> &pound;3,000 per year.</span>
            </li>
            <li class="flex items-start gap-2">
              <span class="w-1.5 h-1.5 rounded-full bg-spring-500 mt-1.5 flex-shrink-0"></span>
              <span><strong>Small gifts:</strong> &pound;250 per person per year.</span>
            </li>
            <li class="flex items-start gap-2">
              <span class="w-1.5 h-1.5 rounded-full bg-spring-500 mt-1.5 flex-shrink-0"></span>
              <span><strong>Gifts from surplus income:</strong> regular gifts you can afford from income (no limit).</span>
            </li>
            <li class="flex items-start gap-2">
              <span class="w-1.5 h-1.5 rounded-full bg-spring-500 mt-1.5 flex-shrink-0"></span>
              <span><strong>The seven-year rule:</strong> gifts to individuals become tax-free if you survive seven years.</span>
            </li>
          </ul>
        </div>

        <!-- The April 2027 Change -->
        <div>
          <h2 class="text-base font-bold text-horizon-500 mb-2">The April 2027 Change</h2>
          <div class="bg-eggshell-500 rounded-lg p-5">
            <p class="text-sm text-neutral-600 leading-relaxed">
              Currently, unused pension pots sit outside your estate. From <strong>April 2027</strong>, they will be
              included. This is a major change that could push many estates above the tax-free threshold for the first
              time.
            </p>
          </div>
        </div>

        <!-- How to Reduce Your Inheritance Tax Bill -->
        <div>
          <h2 class="text-base font-bold text-horizon-500 mb-2">How to Reduce Your Inheritance Tax Bill</h2>
          <ul class="space-y-1.5 text-sm text-neutral-600">
            <li class="flex items-start gap-2">
              <span class="w-1.5 h-1.5 rounded-full bg-violet-500 mt-1.5 flex-shrink-0"></span>
              <span>Use your annual gift exemptions every year.</span>
            </li>
            <li class="flex items-start gap-2">
              <span class="w-1.5 h-1.5 rounded-full bg-violet-500 mt-1.5 flex-shrink-0"></span>
              <span>Gift from surplus income &mdash; there is no limit if you can afford it from regular income.</span>
            </li>
            <li class="flex items-start gap-2">
              <span class="w-1.5 h-1.5 rounded-full bg-violet-500 mt-1.5 flex-shrink-0"></span>
              <span>Put life insurance in trust so the payout falls outside your estate.</span>
            </li>
            <li class="flex items-start gap-2">
              <span class="w-1.5 h-1.5 rounded-full bg-violet-500 mt-1.5 flex-shrink-0"></span>
              <span>Consider pension drawdown strategies to reduce your estate before April 2027.</span>
            </li>
            <li class="flex items-start gap-2">
              <span class="w-1.5 h-1.5 rounded-full bg-violet-500 mt-1.5 flex-shrink-0"></span>
              <span>Get professional advice for complex estates.</span>
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
  name: 'WhatIsInheritanceTaxPage',
  components: { PublicLayout, GuideNav, GuideArticleFooter },

  mounted() {
    document.title = 'What is Inheritance Tax? — IHT Explained Simply | Fynla';
    const meta = document.querySelector('meta[name="description"]');
    if (meta) {
      meta.setAttribute('content', 'Inheritance Tax is charged at 40% on estates above £325,000. Learn how the nil rate band works, what exemptions exist, and how to reduce your IHT bill.');
    }
  },
};
</script>
