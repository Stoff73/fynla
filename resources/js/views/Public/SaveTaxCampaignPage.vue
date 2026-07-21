<template>
  <PublicLayout>
    <div class="campaign-body">
      <!-- Hero -->
      <div class="relative flex items-center bg-gradient-to-r from-horizon-500 to-raspberry-500 overflow-hidden">
        <div class="campaign-inner relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12 text-left w-full">
          <h1 class="text-4xl md:text-6xl font-black text-white mb-4 leading-tight">
            Save more on <span class="text-raspberry-300">tax</span>
          </h1>
          <p class="text-lg text-white/70 max-w-2xl">
            Understand your tax position, maximise your allowances, and keep more of what you earn with Fynla's complete financial planning platform.
          </p>
        </div>
      </div>

      <!-- Allowances (live values from active TaxConfiguration) -->
      <div class="bg-eggshell-500 py-14 lg:py-16">
        <div class="campaign-inner max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
          <div class="mb-10 lg:mb-12">
            <span class="inline-block text-xs uppercase tracking-widest text-raspberry-500 mb-2">Tax year {{ taxYear }}</span>
            <h2 class="text-3xl sm:text-4xl lg:text-5xl font-bold text-horizon-500 leading-tight">Your allowances</h2>
          </div>

          <div class="grid grid-cols-1 md:grid-cols-2 gap-8 lg:gap-12">
            <!-- Income column -->
            <div>
              <div class="mb-5 pb-3 border-b-2 border-horizon-500">
                <h3 class="text-xl font-bold text-horizon-500">Income</h3>
              </div>
              <ul class="space-y-4">
                <li
                  v-for="item in incomeAllowances"
                  :key="item.label"
                  class="flex items-baseline justify-between gap-3 pb-3 border-b border-light-gray"
                >
                  <div>
                    <p class="text-sm font-semibold text-horizon-500">{{ item.label }}</p>
                    <p class="text-xs text-neutral-500 mt-0.5">{{ item.note }}</p>
                  </div>
                  <span class="text-lg font-bold text-raspberry-500 whitespace-nowrap">{{ formatAmount(item) }}</span>
                </li>
              </ul>
            </div>

            <!-- Investment / Cash column -->
            <div>
              <div class="mb-5 pb-3 border-b-2 border-raspberry-500">
                <h3 class="text-xl font-bold text-horizon-500">Investment &amp; Cash</h3>
              </div>
              <ul class="space-y-4">
                <li
                  v-for="item in investmentAllowances"
                  :key="item.label"
                  class="flex items-baseline justify-between gap-3 pb-3 border-b border-light-gray"
                >
                  <div>
                    <p class="text-sm font-semibold text-horizon-500">{{ item.label }}</p>
                    <p class="text-xs text-neutral-500 mt-0.5">{{ item.note }}</p>
                  </div>
                  <span class="text-lg font-bold text-raspberry-500 whitespace-nowrap">{{ formatAmount(item) }}</span>
                </li>
              </ul>
            </div>
          </div>

          <p class="text-base sm:text-lg text-neutral-500 leading-relaxed mt-12">
            Knowing how to get the most out of, and use your allowances can be tricky.
            <span class="font-semibold text-horizon-500">Fyn can help.</span>
            Here are a few common situations where the right allowance — or the right account — can save you thousands.
          </p>
        </div>
      </div>

      <!-- What does this mean? -->
      <div class="bg-horizon-500 py-14 lg:py-16">
        <div class="campaign-inner max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
          <h2 class="text-3xl sm:text-4xl lg:text-5xl font-bold text-white mb-10 lg:mb-12 leading-tight">What does this mean?</h2>
          <div class="grid grid-cols-1 lg:grid-cols-2 gap-10 lg:gap-16 items-center">
            <div>
              <p class="text-xs uppercase tracking-widest text-raspberry-300 mb-3">Total available allowances {{ taxYear }}</p>
              <p class="text-7xl md:text-8xl font-black text-white leading-none tracking-tight">{{ formattedTotal }}</p>
            </div>
            <div>
              <p class="text-white/80 text-base sm:text-lg leading-relaxed mb-6">
                That's the combined value of the tax-free and tax-relievable allowances available to every UK taxpayer each year. From income you can earn free of tax, to ISA savings sheltered from capital gains and dividends, to pension contributions that attract full tax relief — the system offers significant scope to keep more of what you earn. The challenge is knowing which allowances apply to your situation and how to use them together effectively.
              </p>
              <router-link
                :to="campaignRegistrationLink"
                class="inline-block px-6 py-3 bg-spring-500 hover:bg-spring-600 text-white text-sm font-semibold rounded-lg transition-colors"
              >
                Register to save tax
              </router-link>
            </div>
          </div>
        </div>
      </div>

      <!-- Examples — "Could this be you?" -->
      <div class="bg-light-pink-100 py-14 lg:py-16">
        <div class="campaign-inner max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
          <div class="mb-10 lg:mb-12">
            <span class="inline-block text-xs uppercase tracking-widest text-raspberry-500 mb-2">Real-life examples</span>
            <h2 class="text-3xl sm:text-4xl lg:text-5xl font-bold text-horizon-500 leading-tight">Could this be you?</h2>
          </div>

          <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
            <article
              v-for="example in examples"
              :key="example.title"
              class="bg-white rounded-2xl border border-light-gray p-6 lg:p-7 flex flex-col"
            >
              <h3 class="text-xl font-bold text-horizon-500 mb-3">{{ example.title }}</h3>
              <p class="text-sm text-neutral-500 leading-relaxed" v-html="example.body"></p>
            </article>
          </div>

          <div class="text-center mt-12">
            <p class="text-sm text-neutral-500 mb-4">Get started now</p>
            <router-link
              :to="campaignRegistrationLink"
              class="inline-block px-8 py-3 bg-spring-500 hover:bg-spring-600 text-white text-base font-semibold rounded-lg transition-colors"
            >
              Register now to ask Fyn
            </router-link>
          </div>
        </div>
      </div>
    </div>
    <StaticFynChat
      source="savetax"
      response-message="I can give you a personalised tax strategy once you register."
    />
  </PublicLayout>
</template>

<script>
import PublicLayout from '@/layouts/PublicLayout.vue';
import StaticFynChat from '@/components/Public/StaticFynChat.vue';
import api from '@/services/api';
import { currencyMixin } from '@/mixins/currencyMixin';

const META_TITLE = 'Save Tax — Maximise Your Allowances | Fynla';
const META_DESC = 'Maximise ISA allowances, pension tax relief, and Marriage Allowance. See your full tax position with Fynla.';

// Fallback values shown if /api/public/tax-allowances is unreachable.
// Kept in sync manually with the active TaxConfiguration so the page never
// renders a blank table — graceful degradation only.
const FALLBACK_TAX_YEAR = '2026/27';
const FALLBACK_INCOME = [
  { key: 'personal_allowance', label: 'Personal Allowance', note: 'Tax-free income each year', amount: 12570 },
  { key: 'savings_allowance', label: 'Savings Allowance', note: 'Basic-rate taxpayers', amount: 1000 },
  { key: 'starting_rate_for_savings', label: 'Starting Rate for Savings', note: 'If non-savings income < £17,570', amount: 5000 },
  { key: 'marriage_allowance', label: 'Marriage Allowance', note: 'Transferable to spouse', amount: 1260 },
];
const FALLBACK_INVESTMENT = [
  { key: 'isa_allowance', label: 'ISA Allowance', note: 'Tax-free savings & investing', amount: 20000 },
  { key: 'cgt_allowance', label: 'Capital Gains Tax Allowance', note: 'Capital gains exempt amount', amount: 3000 },
  { key: 'dividend_allowance', label: 'Dividend Allowance', note: 'Tax-free dividend income', amount: 500 },
  { key: 'pension_annual_allowance', label: 'Pension Annual Allowance', note: 'Tax-relievable contributions', amount: 60000 },
];

export default {
  name: 'SaveTaxCampaignPage',
  components: { PublicLayout, StaticFynChat },
  mixins: [currencyMixin],

  computed: {
    formattedTotal() {
      const total = [...this.incomeAllowances, ...this.investmentAllowances]
        .reduce((sum, item) => sum + (item.amount || 0), 0);
      return this.formatCurrency(total);
    },
  },

  data() {
    return {
      campaignRegistrationLink: { path: '/register', query: { from: 'savetax' } },
      taxYear: FALLBACK_TAX_YEAR,
      incomeAllowances: FALLBACK_INCOME,
      investmentAllowances: FALLBACK_INVESTMENT,
      examples: [
        {
          title: 'Non-working spouse',
          body: 'If no income is earned, a non-earning spouse can still receive up to <span class="font-bold text-horizon-500">£18,750</span> per year of income tax-free by combining the Personal Allowance, Starting Rate for Savings and Personal Savings Allowance.',
        },
        {
          title: 'High income tax trap',
          body: 'If you earn above <span class="font-bold text-horizon-500">£100,000</span> per year, you may have some of your income taxed at an effective rate of <span class="font-bold text-horizon-500">60%</span> due to the tapered withdrawal of your Personal Allowance.',
        },
        {
          title: 'Investment Accounts',
          body: 'Just using these, you can take up to <span class="font-bold text-horizon-500">£3,000</span> per year of tax-free gains and <span class="font-bold text-horizon-500">£500</span> per year of tax-free dividend income — on top of your ISA.',
        },
        {
          title: 'National Insurance contributions',
          body: 'When you pay into your pension, both you and your employer pay <span class="font-bold text-horizon-500">National Insurance contributions</span> on those contributions. But if your employer pays directly into your pension, neither side pays them at all. A <span class="font-bold text-horizon-500">salary sacrifice</span> scheme makes tax-efficient use of this difference.',
        },
      ],
    };
  },

  mounted() {
    document.title = META_TITLE;
    const meta = document.querySelector('meta[name="description"]');
    if (meta) meta.setAttribute('content', META_DESC);
    this.loadLiveAllowances();
  },

  methods: {
    formatAmount(item) {
      if (item == null || typeof item.amount !== 'number') return '';
      // Use currencyMixin's formatCurrency for the £ formatting (CLAUDE.md
      // Rule #6 — never define a local formatter); preserve the null-guard
      // since the live API may return items without an amount field.
      return this.formatCurrency(item.amount);
    },

    async loadLiveAllowances() {
      try {
        const { data } = await api.get('/public/tax-allowances');
        if (data?.tax_year) this.taxYear = data.tax_year;
        if (Array.isArray(data?.income_allowances) && data.income_allowances.length) {
          this.incomeAllowances = data.income_allowances;
        }
        if (Array.isArray(data?.investment_allowances) && data.investment_allowances.length) {
          this.investmentAllowances = data.investment_allowances;
        }
      } catch {
        // Silently fall back to the hardcoded constants — this is a marketing
        // page and a momentary network blip should not show a broken UI.
      }
    },
  },
};
</script>

<style scoped>
.campaign-body {
  margin-right: 0;
}
@media (min-width: 1024px) {
  .campaign-body {
    margin-right: 356px;
  }
  .campaign-body :deep(.campaign-inner) {
    max-width: none;
    margin-left: max(1rem, calc((100vw - 80rem) / 2));
    margin-right: 0;
    padding-left: 1rem;
    padding-right: 2rem;
  }
}
</style>
