<template>
  <PublicLayout>
    <!-- Hero -->
    <div class="relative flex items-center bg-gradient-to-r from-horizon-500 to-raspberry-500 overflow-hidden">
      <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10 text-left w-full">
        <h1 class="text-4xl md:text-6xl font-black text-white mb-4">Pension Annual Allowance {{ currentTaxYear }}</h1>
        <p class="text-lg text-white/70">
          How much you can save into pensions each year &mdash; and what happens if you go over.
        </p>
      </div>
    </div>

    <GuideNav />

    <!-- Content -->
    <section class="py-8 bg-eggshell-500">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="bg-white rounded-xl border border-light-gray p-6 sm:p-8 space-y-8">

        <!-- The Standard Allowance -->
        <div class="bg-eggshell-500 rounded-lg p-5">
          <h2 class="text-base font-bold text-horizon-500 mb-2">The Standard Allowance</h2>
          <p class="text-sm text-neutral-600 leading-relaxed">
            The pension annual allowance for {{ currentTaxYear }} is <strong>&pound;60,000</strong>. This is the maximum you can
            contribute to all your pensions in a single tax year while still receiving tax relief. It includes your
            personal contributions, employer contributions, and any tax relief added by HMRC.
          </p>
        </div>

        <!-- Carry Forward -->
        <div>
          <h2 class="text-base font-bold text-horizon-500 mb-2">Carry Forward</h2>
          <p class="text-sm text-neutral-600 leading-relaxed mb-3">
            If you did not use your full allowance in the previous three tax years, you can carry the unused amount
            forward. This means you could potentially contribute well above &pound;60,000 in a single year.
          </p>
          <ul class="space-y-1.5 text-sm text-neutral-600">
            <li class="flex items-start gap-2">
              <span class="w-1.5 h-1.5 rounded-full bg-spring-500 mt-1.5 flex-shrink-0"></span>
              <span>You must have been a member of a registered pension scheme in each carry forward year.</span>
            </li>
            <li class="flex items-start gap-2">
              <span class="w-1.5 h-1.5 rounded-full bg-spring-500 mt-1.5 flex-shrink-0"></span>
              <span>Use the oldest year's unused allowance first.</span>
            </li>
            <li class="flex items-start gap-2">
              <span class="w-1.5 h-1.5 rounded-full bg-spring-500 mt-1.5 flex-shrink-0"></span>
              <span>You must use the current year's &pound;60,000 allowance before dipping into carry forward.</span>
            </li>
          </ul>
        </div>

        <!-- Tapered Annual Allowance -->
        <div>
          <h2 class="text-base font-bold text-horizon-500 mb-2">Tapered Annual Allowance</h2>
          <p class="text-sm text-neutral-600 leading-relaxed mb-3">
            If your adjusted income exceeds <strong>&pound;260,000</strong>, your annual allowance is reduced by
            &pound;1 for every &pound;2 over that threshold. The minimum tapered allowance is
            <strong>&pound;10,000</strong>.
          </p>
          <div class="bg-white rounded-lg border border-light-gray overflow-hidden">
            <table class="w-full text-sm">
              <thead>
                <tr class="bg-savannah-50">
                  <th class="text-left px-4 py-2.5 text-xs font-semibold text-horizon-500">Adjusted Income</th>
                  <th class="text-right px-4 py-2.5 text-xs font-semibold text-horizon-500">Annual Allowance</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-light-gray">
                <tr>
                  <td class="px-4 py-2.5 text-neutral-500">Up to &pound;260,000</td>
                  <td class="px-4 py-2.5 text-neutral-500 text-right">&pound;60,000</td>
                </tr>
                <tr>
                  <td class="px-4 py-2.5 text-neutral-500">&pound;300,000</td>
                  <td class="px-4 py-2.5 text-neutral-500 text-right">&pound;40,000</td>
                </tr>
                <tr>
                  <td class="px-4 py-2.5 text-neutral-500">&pound;360,000 or above</td>
                  <td class="px-4 py-2.5 text-neutral-500 text-right">&pound;10,000</td>
                </tr>
              </tbody>
            </table>
          </div>
          <p class="text-xs text-neutral-400 mt-2">
            The taper only applies if your threshold income also exceeds &pound;200,000.
          </p>
        </div>

        <!-- Money Purchase Annual Allowance -->
        <div>
          <h2 class="text-base font-bold text-horizon-500 mb-2">Money Purchase Annual Allowance</h2>
          <p class="text-sm text-neutral-600 leading-relaxed mb-3">
            If you have flexibly accessed your defined contribution pension (for example, through flexi-access
            drawdown or taking an uncrystallised funds pension lump sum), your allowance for further money purchase
            contributions drops to <strong>&pound;10,000</strong>. This is the Money Purchase Annual Allowance (MPAA).
          </p>
          <ul class="space-y-1.5 text-sm text-neutral-600">
            <li class="flex items-start gap-2">
              <span class="w-1.5 h-1.5 rounded-full bg-violet-500 mt-1.5 flex-shrink-0"></span>
              <span>Taking your 25% tax-free lump sum alone does not trigger the MPAA.</span>
            </li>
            <li class="flex items-start gap-2">
              <span class="w-1.5 h-1.5 rounded-full bg-violet-500 mt-1.5 flex-shrink-0"></span>
              <span>Carry forward cannot be used for the MPAA &mdash; it is a hard &pound;10,000 limit.</span>
            </li>
            <li class="flex items-start gap-2">
              <span class="w-1.5 h-1.5 rounded-full bg-violet-500 mt-1.5 flex-shrink-0"></span>
              <span>Once triggered, the MPAA applies for the rest of your life.</span>
            </li>
          </ul>
        </div>

        <!-- What Happens If You Exceed the Allowance -->
        <div>
          <h2 class="text-base font-bold text-horizon-500 mb-2">What Happens If You Exceed the Allowance</h2>
          <p class="text-sm text-neutral-600 leading-relaxed">
            Contributions above your annual allowance are subject to an annual allowance charge. The excess is added
            to your income for the year and taxed at your marginal rate. If the charge exceeds &pound;2,000, you
            can ask your pension scheme to pay it from your pension pot (known as scheme pays).
          </p>
        </div>

        <!-- Related Links -->
        <div class="border-t border-light-gray pt-6">
          <h2 class="text-sm font-bold text-horizon-500 mb-3">Related</h2>
          <div class="flex flex-wrap gap-2">
            <router-link to="/learn/glossary" class="text-xs text-raspberry-500 hover:text-raspberry-600 underline">Glossary</router-link>
            <router-link to="/features/pension-tracker" class="text-xs text-raspberry-500 hover:text-raspberry-600 underline">Pension Tracker</router-link>
            <router-link to="/calculators" class="text-xs text-raspberry-500 hover:text-raspberry-600 underline">Calculators</router-link>
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
  name: 'PensionAnnualAllowancePage',
  components: { PublicLayout, GuideNav, GuideArticleFooter },

  computed: {
    currentTaxYear() {
      return getCurrentTaxYear();
    },
  },

  mounted() {
    document.title = `Pension Annual Allowance ${this.currentTaxYear} \u2014 UK Guide | Fynla`;
    const meta = document.querySelector('meta[name="description"]');
    if (meta) {
      meta.setAttribute('content', `The pension annual allowance for ${this.currentTaxYear} is \u00A360,000. Learn how it works, carry forward rules, tapered allowance, and the Money Purchase Annual Allowance.`);
    }
  },
};
</script>
