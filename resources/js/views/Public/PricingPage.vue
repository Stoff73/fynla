<template>
  <PublicLayout>
    <!-- Hero Section -->
    <div class="relative min-h-[35vh] flex items-center bg-gradient-to-br from-slate-900 via-blue-900 to-slate-900 overflow-hidden">
      <div class="absolute inset-0 overflow-hidden">
        <div class="absolute top-1/4 left-1/4 w-72 h-72 bg-blue-500 rounded-full mix-blend-multiply filter blur-3xl opacity-20 animate-pulse"></div>
        <div class="absolute bottom-1/4 right-1/4 w-72 h-72 bg-purple-500 rounded-full mix-blend-multiply filter blur-3xl opacity-20 animate-pulse" style="animation-delay: 1s;"></div>
      </div>

      <div class="absolute inset-0 bg-[url('data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iNjAiIGhlaWdodD0iNjAiIHZpZXdCb3g9IjAgMCA2MCA2MCIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48ZyBmaWxsPSJub25lIiBmaWxsLXJ1bGU9ImV2ZW5vZGQiPjxwYXRoIGQ9Ik0wIDBoNjB2NjBIMHoiLz48cGF0aCBkPSJNMzAgMzBtLTEgMGExIDEgMCAxIDAgMiAwYTEgMSAwIDEgMCAtMiAwIiBmaWxsPSJyZ2JhKDI1NSwyNTUsMjU1LDAuMSkiLz48L2c+PC9zdmc+')] opacity-40"></div>

      <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-16 text-center w-full">
        <h1 class="text-4xl md:text-6xl font-bold text-white mb-6">
          Simple,
          <span class="bg-gradient-to-r from-emerald-400 to-blue-400 bg-clip-text text-transparent">
            Transparent
          </span>
          Pricing
        </h1>
        <p class="text-xl text-slate-300 max-w-2xl mx-auto mb-8">
          Start with a 7-day free trial on any plan. No credit card required.
        </p>

        <!-- Billing Toggle -->
        <div class="inline-flex items-center gap-3 bg-white/10 backdrop-blur-md rounded-full p-1.5 border border-white/20">
          <button
            @click="isYearly = false"
            :class="[
              'px-5 py-2 rounded-full text-sm font-medium transition-all',
              !isYearly ? 'bg-white text-slate-900 shadow-md' : 'text-slate-300 hover:text-white'
            ]"
          >
            Monthly
          </button>
          <button
            @click="isYearly = true"
            :class="[
              'px-5 py-2 rounded-full text-sm font-medium transition-all',
              isYearly ? 'bg-white text-slate-900 shadow-md' : 'text-slate-300 hover:text-white'
            ]"
          >
            Yearly
            <span class="ml-1 text-xs text-emerald-600 font-semibold" v-if="isYearly">Save up to 32%</span>
          </button>
        </div>
      </div>
    </div>

    <!-- Pricing Cards -->
    <div class="relative bg-gradient-to-b from-slate-900 to-slate-800 py-20 overflow-hidden">
      <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-8 max-w-6xl mx-auto">

          <!-- Student Plan -->
          <div class="bg-white/5 backdrop-blur-md rounded-2xl border border-white/10 p-8 flex flex-col">
            <div class="mb-6">
              <h3 class="text-lg font-semibold text-slate-300 mb-1">Student</h3>
              <p class="text-sm text-slate-400">Get started with the essentials</p>
            </div>

            <div class="mb-6">
              <div class="flex items-baseline gap-1">
                <span class="text-4xl font-bold text-white">{{ isYearly ? '£30' : '£3.99' }}</span>
                <span class="text-slate-400 text-sm">/ {{ isYearly ? 'year' : 'month' }}</span>
              </div>
              <p v-if="isYearly" class="text-sm text-emerald-400 mt-1">£2.50/mo — save 37%</p>
            </div>

            <div class="inline-flex items-center gap-1.5 px-3 py-1 bg-blue-500/10 border border-blue-500/20 rounded-full text-blue-300 text-xs font-medium mb-6 w-fit">
              <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
              </svg>
              7-day free trial
            </div>

            <ul class="space-y-3 mb-8 flex-1">
              <li class="flex items-start gap-3">
                <svg class="w-5 h-5 text-emerald-400 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                </svg>
                <span class="text-slate-300 text-sm">Budgeting tools</span>
              </li>
              <li class="flex items-start gap-3">
                <svg class="w-5 h-5 text-emerald-400 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                </svg>
                <span class="text-slate-300 text-sm">Debt tracking</span>
              </li>
              <li class="flex items-start gap-3">
                <svg class="w-5 h-5 text-emerald-400 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                </svg>
                <span class="text-slate-300 text-sm">Basic investment tracking</span>
              </li>
              <li class="flex items-start gap-3">
                <svg class="w-5 h-5 text-emerald-400 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                </svg>
                <span class="text-slate-300 text-sm">Goal setting</span>
              </li>
            </ul>

            <button
              @click="startTrial('student')"
              class="w-full py-3 px-6 rounded-xl font-semibold text-sm bg-white/10 text-white border border-white/20 hover:bg-white/20 transition-all"
            >
              Start Free Trial
            </button>
          </div>

          <!-- Standard Plan (Featured) -->
          <div class="bg-white/5 backdrop-blur-md rounded-2xl border-2 border-blue-500/50 p-8 flex flex-col relative">
            <div class="absolute -top-4 left-1/2 -translate-x-1/2">
              <span class="px-4 py-1.5 bg-blue-500 text-white text-xs font-semibold rounded-full shadow-lg">Most Popular</span>
            </div>

            <div class="mb-6">
              <h3 class="text-lg font-semibold text-white mb-1">Standard</h3>
              <p class="text-sm text-slate-400">Full platform access</p>
            </div>

            <div class="mb-6">
              <div class="flex items-baseline gap-1">
                <span class="text-4xl font-bold text-white">{{ isYearly ? '£100' : '£10.99' }}</span>
                <span class="text-slate-400 text-sm">/ {{ isYearly ? 'year' : 'month' }}</span>
              </div>
              <p v-if="isYearly" class="text-sm text-emerald-400 mt-1">£8.33/mo — save 24%</p>
            </div>

            <div class="inline-flex items-center gap-1.5 px-3 py-1 bg-blue-500/10 border border-blue-500/20 rounded-full text-blue-300 text-xs font-medium mb-6 w-fit">
              <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
              </svg>
              7-day free trial
            </div>

            <ul class="space-y-3 mb-8 flex-1">
              <li class="flex items-start gap-3">
                <svg class="w-5 h-5 text-emerald-400 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                </svg>
                <span class="text-slate-300 text-sm">All platform capabilities</span>
              </li>
              <li class="flex items-start gap-3">
                <svg class="w-5 h-5 text-emerald-400 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                </svg>
                <span class="text-slate-300 text-sm">Protection, Savings, Investments</span>
              </li>
              <li class="flex items-start gap-3">
                <svg class="w-5 h-5 text-emerald-400 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                </svg>
                <span class="text-slate-300 text-sm">Retirement & Estate planning</span>
              </li>
              <li class="flex items-start gap-3">
                <svg class="w-5 h-5 text-emerald-400 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                </svg>
                <span class="text-slate-300 text-sm">Spouse linking & joint assets</span>
              </li>
              <li class="flex items-start gap-3">
                <svg class="w-5 h-5 text-emerald-400 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                </svg>
                <span class="text-slate-300 text-sm">1 document upload per day</span>
              </li>
              <li class="flex items-start gap-3">
                <svg class="w-5 h-5 text-emerald-400 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                </svg>
                <span class="text-slate-300 text-sm">5 uploads per month maximum</span>
              </li>
            </ul>

            <button
              @click="startTrial('standard')"
              class="w-full py-3 px-6 rounded-xl font-semibold text-sm bg-blue-500 text-white hover:bg-blue-400 transition-all shadow-lg"
            >
              Start Free Trial
            </button>
          </div>

          <!-- Pro Plan -->
          <div class="bg-white/5 backdrop-blur-md rounded-2xl border border-white/10 p-8 flex flex-col">
            <div class="mb-6">
              <h3 class="text-lg font-semibold text-slate-300 mb-1">Pro</h3>
              <p class="text-sm text-slate-400">For power users who need it all</p>
            </div>

            <div class="mb-6">
              <div class="flex items-baseline gap-1">
                <span class="text-4xl font-bold text-white">{{ isYearly ? '£200' : '£19.99' }}</span>
                <span class="text-slate-400 text-sm">/ {{ isYearly ? 'year' : 'month' }}</span>
              </div>
              <p v-if="isYearly" class="text-sm text-emerald-400 mt-1">£16.67/mo — save 17%</p>
            </div>

            <div class="inline-flex items-center gap-1.5 px-3 py-1 bg-blue-500/10 border border-blue-500/20 rounded-full text-blue-300 text-xs font-medium mb-6 w-fit">
              <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
              </svg>
              7-day free trial
            </div>

            <ul class="space-y-3 mb-8 flex-1">
              <li class="flex items-start gap-3">
                <svg class="w-5 h-5 text-emerald-400 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                </svg>
                <span class="text-slate-300 text-sm">Everything in Standard</span>
              </li>
              <li class="flex items-start gap-3">
                <svg class="w-5 h-5 text-emerald-400 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                </svg>
                <span class="text-white text-sm font-medium">Unlimited document uploads</span>
              </li>
              <li class="flex items-start gap-3">
                <svg class="w-5 h-5 text-emerald-400 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                </svg>
                <span class="text-slate-300 text-sm">Priority support</span>
              </li>
            </ul>

            <button
              @click="startTrial('pro')"
              class="w-full py-3 px-6 rounded-xl font-semibold text-sm bg-white/10 text-white border border-white/20 hover:bg-white/20 transition-all"
            >
              Start Free Trial
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Trust Indicators -->
    <div class="bg-slate-50 py-12 border-t border-slate-200">
      <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-8 text-center">
          <div class="flex flex-col items-center">
            <div class="w-12 h-12 bg-emerald-100 rounded-xl flex items-center justify-center mb-3">
              <svg class="w-6 h-6 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
              </svg>
            </div>
            <h3 class="text-base font-semibold text-slate-900 mb-1">7-Day Free Trial</h3>
            <p class="text-sm text-slate-600">Try any plan risk-free. No credit card required to start.</p>
          </div>
          <div class="flex flex-col items-center">
            <div class="w-12 h-12 bg-blue-100 rounded-xl flex items-center justify-center mb-3">
              <svg class="w-6 h-6 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
              </svg>
            </div>
            <h3 class="text-base font-semibold text-slate-900 mb-1">UK Data Security</h3>
            <p class="text-sm text-slate-600">AES-256 encryption. Data stored in UK data centres. GDPR compliant.</p>
          </div>
          <div class="flex flex-col items-center">
            <div class="w-12 h-12 bg-slate-100 rounded-xl flex items-center justify-center mb-3">
              <svg class="w-6 h-6 text-slate-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4" />
              </svg>
            </div>
            <h3 class="text-base font-semibold text-slate-900 mb-1">Cancel Anytime</h3>
            <p class="text-sm text-slate-600">No lock-in contracts. Downgrade or cancel whenever you like.</p>
          </div>
        </div>
      </div>
    </div>

    <!-- FAQ Section -->
    <div class="bg-white py-16">
      <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center mb-10">
          <h2 class="text-3xl font-bold text-slate-900 mb-3">Frequently Asked Questions</h2>
          <p class="text-slate-600">Everything you need to know about Fynla plans</p>
        </div>

        <div class="space-y-4">
          <div
            v-for="(faq, index) in faqs"
            :key="index"
            class="bg-slate-50 rounded-xl border border-slate-200 overflow-hidden"
          >
            <button
              @click="toggleFaq(index)"
              class="w-full flex items-center justify-between px-6 py-4 text-left hover:bg-slate-100 transition-colors"
            >
              <span class="text-base font-semibold text-slate-900">{{ faq.question }}</span>
              <svg
                :class="['w-5 h-5 text-slate-400 transition-transform duration-200', openFaq === index ? 'rotate-180' : '']"
                fill="none" viewBox="0 0 24 24" stroke="currentColor"
              >
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
              </svg>
            </button>
            <div v-if="openFaq === index" class="px-6 pb-4">
              <p class="text-sm text-slate-600 leading-relaxed">{{ faq.answer }}</p>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- CTA Section -->
    <div class="relative bg-gradient-to-br from-slate-900 via-blue-900 to-slate-900 py-16 overflow-hidden">
      <div class="absolute inset-0">
        <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[600px] h-[600px] bg-blue-500/10 rounded-full blur-3xl"></div>
      </div>
      <div class="relative max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
        <h2 class="text-3xl font-bold text-white mb-4">Ready to Take Control of Your Finances?</h2>
        <p class="text-slate-300 mb-8">Start your 7-day free trial today. No credit card required.</p>
        <router-link
          to="/register"
          class="inline-flex items-center px-8 py-4 bg-white text-slate-900 rounded-xl font-semibold text-lg hover:bg-blue-50 transition-all shadow-lg hover:shadow-xl"
        >
          Get Started Free
          <svg class="w-5 h-5 ml-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6" />
          </svg>
        </router-link>
      </div>
    </div>
  </PublicLayout>
</template>

<script>
import PublicLayout from '@/layouts/PublicLayout.vue';

export default {
  name: 'PricingPage',

  components: {
    PublicLayout,
  },

  data() {
    return {
      isYearly: true,
      openFaq: null,
      faqs: [
        {
          question: 'What happens when my free trial ends?',
          answer: 'After 7 days, you will be asked to choose a plan to continue. If you do not subscribe, your account will become read-only — you can still view your data, but cannot make changes until you activate a plan.',
        },
        {
          question: 'Can I switch plans later?',
          answer: 'Yes, you can upgrade or downgrade your plan at any time from your account settings. When upgrading, you will be charged the prorated difference. When downgrading, the change takes effect at the end of your current billing period.',
        },
        {
          question: 'What payment methods do you accept?',
          answer: 'We accept all major credit and debit cards (Visa, Mastercard, American Express) through our secure payment processor Stripe. All payments are processed securely and we never store your card details.',
        },
        {
          question: 'Is my financial data safe?',
          answer: 'Absolutely. We use AES-256 encryption for sensitive data, store everything in UK-based data centres, and are fully GDPR compliant. Your data is never shared with third parties. You can export or delete your data at any time.',
        },
        {
          question: 'What is the difference between Standard and Pro?',
          answer: 'Both plans include all platform features — protection, savings, investments, retirement, and estate planning. The key difference is document uploads: Standard allows 1 upload per day (5 per month maximum), while Pro offers unlimited document uploads and priority support.',
        },
        {
          question: 'Can I cancel my subscription?',
          answer: 'Yes, you can cancel at any time from your account settings. There are no lock-in contracts or cancellation fees. Your access continues until the end of your current billing period.',
        },
      ],
    };
  },

  methods: {
    startTrial(plan) {
      this.$router.push({
        path: '/register',
        query: {
          plan,
          billing: this.isYearly ? 'yearly' : 'monthly',
        },
      });
    },

    toggleFaq(index) {
      this.openFaq = this.openFaq === index ? null : index;
    },
  },
};
</script>
