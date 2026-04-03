<template>
  <PublicLayout>
    <!-- Hero -->
    <div class="relative flex items-center bg-gradient-to-r from-horizon-500 to-raspberry-500 overflow-hidden">
      <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10 text-left w-full">
        <h1 class="text-4xl md:text-6xl font-black text-white mb-4">Inheritance Tax Thresholds {{ currentTaxYear }}</h1>
        <p class="text-lg text-white/70">
          The nil rate band, residence nil rate band, and how they work together.
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
          <h2 class="text-base font-bold text-horizon-500 mb-2">Current Thresholds</h2>
          <p class="text-sm text-neutral-600 leading-relaxed">
            Inheritance Tax is charged at <strong>40%</strong> on the value of your estate above the available
            thresholds. There are two main allowances, and both are frozen until at least 2028.
          </p>
        </div>

        <!-- Nil Rate Band -->
        <div>
          <h2 class="text-base font-bold text-horizon-500 mb-2">Nil Rate Band &mdash; &pound;325,000</h2>
          <ul class="space-y-1.5 text-sm text-neutral-600">
            <li class="flex items-start gap-2">
              <span class="w-1.5 h-1.5 rounded-full bg-spring-500 mt-1.5 flex-shrink-0"></span>
              <span>Every individual has a &pound;325,000 nil rate band.</span>
            </li>
            <li class="flex items-start gap-2">
              <span class="w-1.5 h-1.5 rounded-full bg-spring-500 mt-1.5 flex-shrink-0"></span>
              <span>It has been frozen at this level since 2009 and will remain so until at least April 2028.</span>
            </li>
            <li class="flex items-start gap-2">
              <span class="w-1.5 h-1.5 rounded-full bg-spring-500 mt-1.5 flex-shrink-0"></span>
              <span>Any unused nil rate band can be transferred to a surviving spouse or civil partner.</span>
            </li>
          </ul>
        </div>

        <!-- Residence Nil Rate Band -->
        <div>
          <h2 class="text-base font-bold text-horizon-500 mb-2">Residence Nil Rate Band &mdash; &pound;175,000</h2>
          <ul class="space-y-1.5 text-sm text-neutral-600">
            <li class="flex items-start gap-2">
              <span class="w-1.5 h-1.5 rounded-full bg-spring-500 mt-1.5 flex-shrink-0"></span>
              <span>An additional &pound;175,000 allowance when you leave your main home to direct descendants (children, grandchildren).</span>
            </li>
            <li class="flex items-start gap-2">
              <span class="w-1.5 h-1.5 rounded-full bg-spring-500 mt-1.5 flex-shrink-0"></span>
              <span>Also transferable between spouses, even if the first to die did not own a property.</span>
            </li>
            <li class="flex items-start gap-2">
              <span class="w-1.5 h-1.5 rounded-full bg-spring-500 mt-1.5 flex-shrink-0"></span>
              <span>Does not apply if you leave your home to a sibling, niece/nephew, or a trust (with limited exceptions).</span>
            </li>
          </ul>
        </div>

        <!-- Combined Couple Allowance -->
        <div class="bg-eggshell-500 rounded-lg p-5">
          <h2 class="text-base font-bold text-horizon-500 mb-2">Combined Couple Allowance</h2>
          <div class="bg-white rounded-lg border border-light-gray overflow-hidden mt-3">
            <table class="w-full text-sm">
              <thead>
                <tr class="bg-savannah-50">
                  <th class="text-left px-4 py-2.5 text-xs font-semibold text-horizon-500">Allowance</th>
                  <th class="text-right px-4 py-2.5 text-xs font-semibold text-horizon-500">Individual</th>
                  <th class="text-right px-4 py-2.5 text-xs font-semibold text-horizon-500">Couple</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-light-gray">
                <tr>
                  <td class="px-4 py-2.5 text-neutral-500">Nil rate band</td>
                  <td class="px-4 py-2.5 text-neutral-500 text-right">&pound;325,000</td>
                  <td class="px-4 py-2.5 text-neutral-500 text-right">&pound;650,000</td>
                </tr>
                <tr>
                  <td class="px-4 py-2.5 text-neutral-500">Residence nil rate band</td>
                  <td class="px-4 py-2.5 text-neutral-500 text-right">&pound;175,000</td>
                  <td class="px-4 py-2.5 text-neutral-500 text-right">&pound;350,000</td>
                </tr>
                <tr class="bg-savannah-50 font-semibold">
                  <td class="px-4 py-2.5 text-horizon-500">Total</td>
                  <td class="px-4 py-2.5 text-horizon-500 text-right">&pound;500,000</td>
                  <td class="px-4 py-2.5 text-horizon-500 text-right">&pound;1,000,000</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <!-- RNRB Taper -->
        <div>
          <h2 class="text-base font-bold text-horizon-500 mb-2">Residence Nil Rate Band Taper</h2>
          <p class="text-sm text-neutral-600 leading-relaxed mb-3">
            If the net value of your estate exceeds <strong>&pound;2 million</strong>, the residence nil rate band is
            reduced by &pound;1 for every &pound;2 above that threshold. For estates worth &pound;2.35 million or
            more, the residence nil rate band is lost entirely.
          </p>
          <p class="text-xs text-neutral-400">
            The standard nil rate band (&pound;325,000) is not affected by the taper.
          </p>
        </div>

        <!-- April 2027 Pension Changes -->
        <div>
          <h2 class="text-base font-bold text-horizon-500 mb-2">April 2027: Pensions Brought Into Scope</h2>
          <p class="text-sm text-neutral-600 leading-relaxed">
            From April 2027, unused pension funds will be included in the value of your estate for Inheritance Tax
            purposes. This is a significant change that could push many estates above the nil rate band for the
            first time. If you have large pension pots, it is worth reviewing your estate plan now.
          </p>
        </div>

        <!-- Related Links -->
        <div class="border-t border-light-gray pt-6">
          <h2 class="text-sm font-bold text-horizon-500 mb-3">Related</h2>
          <div class="flex flex-wrap gap-2">
            <router-link to="/learn/what-is-inheritance-tax" class="text-xs text-raspberry-500 hover:text-raspberry-600 underline">What is Inheritance Tax?</router-link>
            <router-link to="/features/iht-planning" class="text-xs text-raspberry-500 hover:text-raspberry-600 underline">Inheritance Tax planning</router-link>
            <router-link to="/insights/pension-iht-changes-2027" class="text-xs text-raspberry-500 hover:text-raspberry-600 underline">Pension Inheritance Tax changes 2027</router-link>
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
  name: 'IhtThresholdsPage',
  components: { PublicLayout, GuideNav, GuideArticleFooter },

  computed: {
    currentTaxYear() {
      return getCurrentTaxYear();
    },
  },

  mounted() {
    document.title = `Inheritance Tax Thresholds & Residence Nil Rate Band ${this.currentTaxYear} | Fynla`;
    const meta = document.querySelector('meta[name="description"]');
    if (meta) {
      meta.setAttribute('content', 'Current UK Inheritance Tax thresholds: nil rate band \u00A3325,000, residence nil rate band \u00A3175,000. Learn how they work, who qualifies, and the taper rules.');
    }
  },
};
</script>
