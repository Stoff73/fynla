<template>
  <PublicLayout>
    <!-- Hero Section -->
    <div class="relative flex items-center bg-gradient-to-r from-horizon-500 to-raspberry-500 overflow-hidden">
      <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10 text-left w-full">
        <h1 class="text-4xl md:text-6xl font-black text-white mb-4">
          Simple,
          <span class="text-raspberry-300">Transparent</span>
          Pricing
        </h1>
        <p class="text-lg text-white/70">
          <template v-if="isAuthenticated">Choose the plan that's right for you.</template>
          <template v-else>Start with a 7-day free trial on any plan. No credit card required.</template>
        </p>
      </div>
    </div>

    <!-- Pricing Cards -->
    <div class="relative bg-light-pink-100 pt-10 pb-20 overflow-hidden">
      <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <!-- Billing Toggle -->
        <div class="flex justify-center mb-6">
          <div class="inline-flex items-center gap-3 bg-white rounded-full p-1.5 border border-light-gray shadow-sm">
            <button
              @click="isYearly = false"
              :class="[
                'px-5 py-2 rounded-full text-sm font-medium transition-all',
                !isYearly ? 'bg-horizon-500 text-white shadow-md' : 'text-horizon-400 hover:text-horizon-500'
              ]"
            >
              Monthly
            </button>
            <button
              @click="isYearly = true"
              :class="[
                'px-5 py-2 rounded-full text-sm font-medium transition-all',
                isYearly ? 'bg-horizon-500 text-white shadow-md' : 'text-horizon-400 hover:text-horizon-500'
              ]"
            >
              Yearly
              <span class="ml-1 text-xs text-spring-500 font-semibold" v-if="isYearly">Save up to 33%</span>
            </button>
          </div>
        </div>

        <!-- Launch Offer Banner -->
        <div class="flex justify-center mb-10">
          <div class="bg-gradient-to-r from-raspberry-500 to-violet-500 rounded-xl px-8 py-4 text-center shadow-lg">
            <p class="text-xl sm:text-2xl font-bold text-white mb-1">Limited Time Offer</p>
            <p class="text-sm text-white/80">Lock in discounted pricing today for your first 12 months</p>
          </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 max-w-7xl mx-auto">

          <!-- Tier cards — identity, pricing and features driven entirely by /api/pricing-config -->
          <div
            v-for="(tier, index) in tiers"
            :key="tier.tier"
            class="bg-horizon-500 rounded-2xl p-8 flex flex-col"
            :class="index === featuredIndex
              ? 'border-[3px] border-raspberry-500 relative'
              : 'border border-horizon-500'"
          >
            <div v-if="index === featuredIndex" class="absolute -top-4 left-1/2 -translate-x-1/2">
              <span class="px-4 py-1.5 bg-raspberry-500 text-white text-xs font-semibold rounded-full shadow-lg">Most Popular</span>
            </div>

            <div class="mb-6">
              <h3 class="text-2xl font-bold text-white mb-1">{{ tier.display_name }}</h3>
              <p class="text-sm text-white/60">{{ tierTagline(tier) }}</p>
            </div>

            <div class="mb-6">
              <div class="flex items-baseline gap-1">
                <span class="whitespace-nowrap"><span class="text-3xl font-bold text-raspberry-500">{{ tierPrice(tier) }}</span><span class="text-white/50 text-sm">{{ tierPriceSuffix(tier) }}</span></span>
              </div>
              <p v-if="isYearly && tierMonthlyEquivalent(tier)" class="text-sm text-spring-500 mt-1">{{ tierMonthlyEquivalent(tier) }}</p>
            </div>

            <div v-if="!isAuthenticated" class="inline-flex items-center px-3 py-1 bg-light-pink-100 border border-light-pink-200 rounded-full text-raspberry-500 text-xs font-medium mb-6 w-fit">
              7-day free trial
            </div>

            <ul class="space-y-3 mb-8 flex-1">
              <li v-if="index > 0" class="text-white/80 text-sm font-medium">
                Everything in {{ tiers[index - 1].display_name }}, plus:
              </li>
              <li
                v-for="feature in tierFeatures(tier)"
                :key="feature.key"
                class="text-sm"
                :class="feature.included ? 'text-white/70' : 'text-white/35 line-through'"
              >
                {{ feature.label }}
              </li>
            </ul>

            <button
              @click="startTrial(tier.tier)"
              class="w-full py-3 px-6 rounded-xl font-semibold text-sm bg-spring-500 text-white hover:bg-spring-600 transition-all"
            >
              {{ ctaLabel }}
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Trust Indicators -->
    <div class="bg-eggshell-500 py-12 border-t border-light-gray">
      <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
        <h2 class="text-xl sm:text-2xl md:text-3xl lg:text-4xl font-bold text-horizon-500 text-center mb-10">Why Fynla?</h2>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-8 text-center">
          <div class="flex flex-col items-center">
            <div class="w-12 h-12 bg-spring-100 rounded-xl flex items-center justify-center mb-3">
              <svg class="w-6 h-6 text-spring-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
              </svg>
            </div>
            <h3 class="text-lg font-semibold text-horizon-500 mb-1">7-Day Free Trial</h3>
            <p class="text-sm text-neutral-500">Try any plan risk-free. No credit card required to start.</p>
          </div>
          <div class="flex flex-col items-center">
            <div class="w-12 h-12 bg-violet-100 rounded-xl flex items-center justify-center mb-3">
              <svg class="w-6 h-6 text-violet-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
              </svg>
            </div>
            <h3 class="text-lg font-semibold text-horizon-500 mb-1">UK Data Security</h3>
            <p class="text-sm text-neutral-500">AES-256 encryption. Data stored in UK data centres. GDPR compliant.</p>
          </div>
          <div class="flex flex-col items-center">
            <div class="w-12 h-12 bg-light-blue-100 rounded-xl flex items-center justify-center mb-3 border border-light-gray">
              <svg class="w-6 h-6 text-horizon-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4" />
              </svg>
            </div>
            <h3 class="text-lg font-semibold text-horizon-500 mb-1">Cancel Anytime</h3>
            <p class="text-sm text-neutral-500">No lock-in contracts. Downgrade or cancel whenever you like.</p>
          </div>
        </div>
        <p class="text-center text-sm text-neutral-500 mt-8">
          To find out more about how Fynla can help you, take a look at
          <router-link to="/features" class="text-raspberry-500 hover:text-raspberry-600 font-medium">our features</router-link>.
        </p>
      </div>
    </div>

    <!-- FAQ Section -->
    <div class="bg-horizon-500 py-16">
      <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center mb-10">
          <h2 class="text-xl sm:text-2xl md:text-3xl lg:text-4xl font-bold text-white mb-3">Frequently asked questions</h2>
          <p class="text-white/70">Everything you need to know about Fynla plans</p>
        </div>

        <div class="space-y-4">
          <div
            v-for="(faq, index) in faqs"
            :key="index"
            class="bg-white/10 rounded-xl border border-white/20 overflow-hidden"
          >
            <button
              @click="toggleFaq(index)"
              class="w-full flex items-center justify-between px-6 py-4 text-left hover:bg-white/20 transition-colors"
            >
              <span class="text-base font-semibold text-white">{{ faq.question }}</span>
              <svg
                :class="['w-5 h-5 text-white/50 transition-transform duration-200', openFaq === index ? 'rotate-180' : '']"
                fill="none" viewBox="0 0 24 24" stroke="currentColor"
              >
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
              </svg>
            </button>
            <div v-if="openFaq === index" class="px-6 pb-4 pt-[10px]">
              <p class="text-sm text-white/80 leading-relaxed">{{ faq.answer }}</p>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- CTA Section -->
    <div class="relative bg-light-pink-100 py-16 overflow-hidden">
      <div class="relative max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
        <h2 class="text-3xl font-bold text-horizon-500 mb-4">Ready to take control of your finances?</h2>
        <p class="text-neutral-500 mb-8">
          <template v-if="isAuthenticated">Upgrade your plan to unlock more features.</template>
          <template v-else>Start your 7-day free trial today. No credit card required.</template>
        </p>
        <router-link
          to="/register"
          class="inline-flex items-center px-8 py-4 bg-raspberry-500 text-white rounded-xl font-semibold text-lg hover:bg-raspberry-600 transition-all shadow-lg hover:shadow-xl"
        >
          Get started free
          <svg class="w-5 h-5 ml-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6" />
          </svg>
        </router-link>
      </div>
    </div>
  </PublicLayout>
</template>

<script>
import { mapGetters } from 'vuex';
import PublicLayout from '@/layouts/PublicLayout.vue';
import { getPricingFaqs } from '@/constants/faqData';
import api from '@/services/api';

// Human-readable, British-English labels for each capability_matrix /
// count_caps entity key. Acronyms spelled out (Rule #10; ISA may stay).
const FEATURE_LABELS = {
  dashboard: 'Financial dashboard',
  income: 'Income tracking',
  expenditure: 'Expenditure tracking',
  liabilities: 'Liabilities tracking',
  protection: 'Protection module',
  savings_account: 'Savings accounts',
  investment: 'Investments',
  investments_exotic: 'Alternative investments',
  pension_account: 'Pensions',
  retirement_decumulation: 'Retirement decumulation planning',
  property: 'Property',
  chattels: 'Personal valuables',
  goals: 'Goals and life events',
  family_module: 'Family module',
  benefits_child: 'Child benefit modelling',
  estate: 'Estate planning',
  letter_to_spouse: 'Letter to spouse and expression of wishes',
};

export default {
  name: 'PricingPage',

  components: {
    PublicLayout,
  },

  computed: {
    ...mapGetters('auth', ['isAuthenticated']),

    ctaLabel() {
      return this.isAuthenticated ? 'Upgrade now' : 'Start Free Trial';
    },

    // Feature the most-popular badge: second-from-top tier when present.
    featuredIndex() {
      if (this.tiers.length === 0) return -1;
      return Math.min(this.tiers.length - 2, this.tiers.length - 1);
    },
  },

  data() {
    return {
      isYearly: true,
      openFaq: null,
      faqs: getPricingFaqs().map(item => ({ question: item.q, answer: item.a })),
      tiers: [],
    };
  },

  mounted() {
    document.title = 'Pricing — Simple, Transparent Plans | Fynla';
    const meta = document.querySelector('meta[name="description"]');
    if (meta) meta.setAttribute('content', 'Start with a 7-day free trial. Choose the Fynla tier that fits — from the Free tier to higher tiers with full estate planning and unlimited accounts. No credit card required.');
    this.fetchTiers();
  },

  methods: {
    async fetchTiers() {
      try {
        // Single source of truth: the live tier store (PR 4).
        const response = await api.get('/pricing-config');
        this.tiers = response.data.data || [];
      } catch {
        // Cards render their fallback ('...') price until the store responds.
      }
    },

    tierTagline(tier) {
      if ((tier.price_monthly_pence ?? 0) === 0) {
        return 'Get started with the essentials';
      }
      if (this.featuredIndex >= 0 && this.tiers[this.featuredIndex] === tier) {
        return 'Our most popular tier';
      }
      return 'More capabilities and higher limits';
    },

    tierPrice(tier) {
      const pence = this.isYearly ? tier.price_annual_pence : tier.price_monthly_pence;
      if (pence === null || pence === undefined) return '...';
      if (pence === 0) return 'Free';
      return '£' + (pence / 100).toFixed(2);
    },

    tierPriceSuffix(tier) {
      const pence = this.isYearly ? tier.price_annual_pence : tier.price_monthly_pence;
      if (pence === 0) return '';
      return this.isYearly ? '/year' : '/month';
    },

    tierMonthlyEquivalent(tier) {
      const annual = tier.price_annual_pence;
      const monthly = tier.price_monthly_pence;
      if (!annual || !monthly) return '';
      const monthlyEq = (annual / 12 / 100).toFixed(2);
      const saving = Math.round((1 - annual / (monthly * 12)) * 100);
      if (saving <= 0) return '';
      return `£${monthlyEq}/mo — save ${saving}%`;
    },

    // Build the feature list from the tier's own capability_matrix +
    // count_caps. full → included; teaser → preview only; limited → "Up to N"
    // (or "Unlimited" when cap is null); none → shown as not included.
    tierFeatures(tier) {
      const matrix = tier.capability_matrix || {};
      const caps = tier.count_caps || {};
      return Object.keys(FEATURE_LABELS)
        .filter(key => key in matrix)
        .map(key => {
          const capability = matrix[key];
          const name = FEATURE_LABELS[key];
          if (capability === 'full') {
            return { key, label: name, included: true };
          }
          if (capability === 'teaser') {
            return { key, label: `${name} — preview only`, included: true };
          }
          if (capability === 'limited') {
            const cap = caps[key];
            if (cap === null || cap === undefined) {
              return { key, label: `Unlimited ${name.toLowerCase()}`, included: true };
            }
            return { key, label: `Up to ${cap} ${name.toLowerCase()}`, included: true };
          }
          // 'none' or unknown — show as not included
          return { key, label: name, included: false };
        });
    },

    startTrial(tierKey) {
      // Pass the tier KEY (free/tier1/tier2/tier3), never a legacy slug (§5.2).
      if (this.isAuthenticated) {
        this.$router.push({
          path: '/checkout',
          query: {
            plan: tierKey,
            cycle: this.isYearly ? 'yearly' : 'monthly',
          },
        });
      } else {
        this.$router.push({
          path: '/register',
          query: {
            plan: tierKey,
            billing: this.isYearly ? 'yearly' : 'monthly',
          },
        });
      }
    },

    toggleFaq(index) {
      this.openFaq = this.openFaq === index ? null : index;
    },
  },
};
</script>
