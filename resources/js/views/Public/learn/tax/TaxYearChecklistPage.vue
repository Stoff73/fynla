<template>
  <PublicLayout>
    <!-- Hero -->
    <div class="relative flex items-center bg-gradient-to-r from-horizon-500 to-raspberry-500 overflow-hidden">
      <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10 text-left w-full">
        <h1 class="text-4xl md:text-6xl font-black text-white mb-4">Tax Year Checklist {{ currentTaxYear }}</h1>
        <p class="text-lg text-white/70">
          Everything to do before 5 April 2026 &mdash; and what opens up on 6 April.
        </p>
      </div>
    </div>

    <GuideNav />

    <!-- Content -->
    <section class="py-8 bg-eggshell-500">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="bg-white rounded-xl border border-light-gray p-6 sm:p-8 space-y-8">

        <!-- Before 5 April -->
        <div>
          <h2 class="text-base font-bold text-horizon-500 mb-3">Before 5 April 2026</h2>
          <div class="space-y-3">

            <div class="bg-eggshell-500 rounded-lg p-5">
              <div class="flex items-start gap-3">
                <span class="w-5 h-5 rounded border-2 border-spring-500 flex-shrink-0 mt-0.5"></span>
                <div>
                  <p class="text-sm font-semibold text-horizon-500">Use your ISA allowance</p>
                  <p class="text-xs text-neutral-500 mt-1">
                    You can contribute up to &pound;20,000 across all ISA types. Unused allowance does not carry forward.
                  </p>
                </div>
              </div>
            </div>

            <div class="bg-eggshell-500 rounded-lg p-5">
              <div class="flex items-start gap-3">
                <span class="w-5 h-5 rounded border-2 border-spring-500 flex-shrink-0 mt-0.5"></span>
                <div>
                  <p class="text-sm font-semibold text-horizon-500">Use your pension annual allowance</p>
                  <p class="text-xs text-neutral-500 mt-1">
                    Up to &pound;60,000 in pension contributions (including employer contributions and tax relief). Check if you have carry forward from the previous three years.
                  </p>
                </div>
              </div>
            </div>

            <div class="bg-eggshell-500 rounded-lg p-5">
              <div class="flex items-start gap-3">
                <span class="w-5 h-5 rounded border-2 border-spring-500 flex-shrink-0 mt-0.5"></span>
                <div>
                  <p class="text-sm font-semibold text-horizon-500">Use your annual gift exemptions</p>
                  <p class="text-xs text-neutral-500 mt-1">
                    &pound;3,000 annual exemption (can carry forward one year if unused). Plus &pound;250 small gifts to any number of individuals.
                  </p>
                </div>
              </div>
            </div>

            <div class="bg-eggshell-500 rounded-lg p-5">
              <div class="flex items-start gap-3">
                <span class="w-5 h-5 rounded border-2 border-spring-500 flex-shrink-0 mt-0.5"></span>
                <div>
                  <p class="text-sm font-semibold text-horizon-500">Use your Capital Gains Tax annual exempt amount</p>
                  <p class="text-xs text-neutral-500 mt-1">
                    &pound;3,000 of gains are tax-free. Consider selling and rebuying assets (known as bed and ISA) to crystallise gains within the allowance.
                  </p>
                </div>
              </div>
            </div>

            <div class="bg-eggshell-500 rounded-lg p-5">
              <div class="flex items-start gap-3">
                <span class="w-5 h-5 rounded border-2 border-spring-500 flex-shrink-0 mt-0.5"></span>
                <div>
                  <p class="text-sm font-semibold text-horizon-500">Review salary sacrifice</p>
                  <p class="text-xs text-neutral-500 mt-1">
                    Salary sacrifice pension contributions save both income tax and National Insurance. Check if your employer offers it.
                  </p>
                </div>
              </div>
            </div>

            <div class="bg-eggshell-500 rounded-lg p-5">
              <div class="flex items-start gap-3">
                <span class="w-5 h-5 rounded border-2 border-spring-500 flex-shrink-0 mt-0.5"></span>
                <div>
                  <p class="text-sm font-semibold text-horizon-500">Check marriage allowance eligibility</p>
                  <p class="text-xs text-neutral-500 mt-1">
                    If one partner earns below the personal allowance (&pound;12,570), they can transfer &pound;1,260 to the other &mdash; saving up to &pound;252 per year.
                  </p>
                </div>
              </div>
            </div>

            <div class="bg-eggshell-500 rounded-lg p-5">
              <div class="flex items-start gap-3">
                <span class="w-5 h-5 rounded border-2 border-spring-500 flex-shrink-0 mt-0.5"></span>
                <div>
                  <p class="text-sm font-semibold text-horizon-500">File self-assessment if needed</p>
                  <p class="text-xs text-neutral-500 mt-1">
                    The deadline for online self-assessment for 2024/25 is 31 January 2026. Don't leave it until the last minute.
                  </p>
                </div>
              </div>
            </div>

          </div>
        </div>

        <!-- After 6 April -->
        <div>
          <h2 class="text-base font-bold text-horizon-500 mb-3">After 6 April 2026</h2>
          <div class="space-y-3">

            <div class="bg-eggshell-500 rounded-lg p-5">
              <div class="flex items-start gap-3">
                <span class="w-5 h-5 rounded border-2 border-horizon-400 flex-shrink-0 mt-0.5"></span>
                <div>
                  <p class="text-sm font-semibold text-horizon-500">New tax year opens</p>
                  <p class="text-xs text-neutral-500 mt-1">
                    All annual allowances reset. ISA, pension, Capital Gains Tax, and gift exemptions start fresh.
                  </p>
                </div>
              </div>
            </div>

            <div class="bg-eggshell-500 rounded-lg p-5">
              <div class="flex items-start gap-3">
                <span class="w-5 h-5 rounded border-2 border-horizon-400 flex-shrink-0 mt-0.5"></span>
                <div>
                  <p class="text-sm font-semibold text-horizon-500">Check new rates and thresholds</p>
                  <p class="text-xs text-neutral-500 mt-1">
                    Review any changes to tax bands, National Insurance, or allowances announced in the Budget.
                  </p>
                </div>
              </div>
            </div>

          </div>
        </div>

        <!-- Related Links -->
        <div class="border-t border-light-gray pt-6">
          <h2 class="text-sm font-bold text-horizon-500 mb-3">Related</h2>
          <div class="flex flex-wrap gap-2">
            <router-link to="/learn/glossary" class="text-xs text-raspberry-500 hover:text-raspberry-600 underline">Glossary</router-link>
            <router-link to="/calculators" class="text-xs text-raspberry-500 hover:text-raspberry-600 underline">Calculators</router-link>
            <router-link to="/learn/tax/isa-allowance" class="text-xs text-raspberry-500 hover:text-raspberry-600 underline">ISA Allowance Guide</router-link>
            <router-link to="/learn/tax/pension-annual-allowance" class="text-xs text-raspberry-500 hover:text-raspberry-600 underline">Pension Annual Allowance</router-link>
            <router-link to="/insights/isa-allowance-2025-26" class="text-xs text-raspberry-500 hover:text-raspberry-600 underline">ISA Allowance {{ currentTaxYear }}</router-link>
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
  name: 'TaxYearChecklistPage',
  components: { PublicLayout, GuideNav, GuideArticleFooter },

  computed: {
    currentTaxYear() {
      return getCurrentTaxYear();
    },
  },

  mounted() {
    document.title = `Tax Year Checklist ${this.currentTaxYear} \u2014 What to Do Before 5 April | Fynla`;
    const meta = document.querySelector('meta[name="description"]');
    if (meta) {
      meta.setAttribute('content', 'Don\'t miss these tax year deadlines. Use your ISA allowance, pension contributions, gift exemptions, and more before 5 April 2026.');
    }
  },
};
</script>
