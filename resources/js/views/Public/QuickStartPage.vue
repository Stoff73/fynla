<template>
  <PublicLayout>
    <!-- Hero -->
    <div class="relative flex items-center bg-gradient-to-r from-horizon-500 to-raspberry-500 overflow-hidden">
      <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12 text-left w-full">
        <h1 class="text-4xl md:text-6xl font-black text-white mb-4">
          Get started with <span class="text-raspberry-300">Fynla</span>
        </h1>
        <p class="text-lg text-white/70 max-w-2xl mb-6">
          Your complete UK financial planning platform. See your full financial picture, get clear recommendations, and take control of your future.
        </p>
        <router-link
          to="/register"
          class="inline-block px-10 py-3 text-lg bg-spring-500 text-white rounded-button font-medium hover:bg-spring-600 transition-all"
        >
          Start your free trial
        </router-link>
      </div>
    </div>

    <!-- Meet Fyn -->
    <div class="bg-light-pink-100 pt-6 pb-8 lg:pt-[10px]" :class="fynDetailsOpen ? 'lg:pb-14' : 'lg:pb-0'">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex flex-col lg:flex-row lg:items-center lg:gap-10">
          <div class="flex-1 lg:self-start lg:pt-10">
            <div class="flex items-end justify-between lg:hidden mb-1">
              <h2 class="text-6xl font-bold text-horizon-500 leading-none">Meet Fyn</h2>
              <img src="/images/Fyn/Design Character 001a.webp" alt="Fyn — your AI financial companion" loading="lazy" width="324" height="427" class="h-28 w-auto -mb-2" />
            </div>
            <h2 class="hidden lg:block text-8xl font-bold text-horizon-500 leading-none mb-1 lg:-mt-[24px]">Meet Fyn</h2>
            <p class="text-xl sm:text-2xl md:text-3xl lg:text-4xl font-semibold text-neutral-500 mb-2">
              Your financial companion for life
            </p>
            <p class="text-sm text-neutral-500 mb-4 max-w-2xl leading-relaxed">
              Need help? Fyn is the face of our Fynla brain and will help you with your finance goals by giving you financial clarity. Fyn will help with everything from planning to saving and investments, through to your net worth and real estate. Tell Fyn what you'd like to do and he'll walk you through your dashboard step-by-step.
            </p>

            <div class="max-w-2xl mb-5">
              <button type="button" class="flex items-center gap-2 text-sm font-semibold text-horizon-500 hover:text-raspberry-500 transition-colors" @click="fynDetailsOpen = !fynDetailsOpen">
                <span>What can Fyn help you with?</span>
                <svg class="w-4 h-4 transition-transform duration-200" :class="{ 'rotate-180': fynDetailsOpen }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                </svg>
              </button>
              <transition name="expand">
                <ul v-if="fynDetailsOpen" class="mt-3 space-y-2 text-sm text-neutral-500">
                  <li class="flex items-start gap-2">
                    <svg class="w-4 h-4 text-spring-500 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" /></svg>
                    Your financial companion to answer any questions you have
                  </li>
                  <li class="flex items-start gap-2">
                    <svg class="w-4 h-4 text-spring-500 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" /></svg>
                    Help understand and complete your dashboard
                  </li>
                  <li class="flex items-start gap-2">
                    <svg class="w-4 h-4 text-spring-500 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" /></svg>
                    Support you to achieve your financial goals
                  </li>
                </ul>
              </transition>
            </div>

            <router-link
              to="/register?from=fyn"
              class="inline-block px-12 py-3 text-lg bg-light-blue-500 text-white rounded-button font-medium hover:opacity-90 transition-colors whitespace-nowrap"
            >
              Quick start with Fyn
            </router-link>
          </div>

          <div class="hidden lg:flex lg:w-auto lg:flex-shrink-0 items-center justify-end" :class="fynDetailsOpen ? 'lg:self-end' : ''">
            <img src="/images/Fyn/Design Character 001a.webp" alt="Fyn — your AI financial companion" loading="lazy" width="324" height="427" :class="fynDetailsOpen ? 'h-[427px] w-auto lg:-mb-[6em]' : 'h-[427px] w-auto lg:-mb-[3em]'" />
          </div>
        </div>
      </div>
    </div>

    <!-- Why Fynla? -->
    <div class="bg-eggshell-500 py-12">
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
      </div>
    </div>

    <!-- Reviews -->
    <ReviewCarousel />

    <!-- Features -->
    <div class="bg-gradient-to-r from-horizon-600 to-horizon-700 py-10 lg:py-12">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <h2 class="text-xl sm:text-2xl md:text-3xl lg:text-4xl text-center mb-4 text-white">How Fyn can help you</h2>
        <p class="text-center text-white/70 text-sm max-w-2xl mx-auto mb-10 leading-relaxed">
          We leverage tools designed for individuals and families to plan savings, investments, retirement and estate with confidence and within local regulations.
        </p>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
          <div v-for="feature in features" :key="feature.title" class="bg-horizon-500 rounded-card p-6 transition-all duration-200">
            <div class="w-10 h-10 rounded-full flex items-center justify-center mb-4" :class="feature.iconBg">
              <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" :d="feature.icon" />
              </svg>
            </div>
            <h3 class="text-white mb-2">{{ feature.title }}</h3>
            <p class="text-sm text-horizon-300 leading-relaxed mb-4">{{ feature.desc }}</p>
            <div class="flex flex-wrap gap-2">
              <span v-for="tag in feature.tags" :key="tag" class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium bg-white/10 text-white/70">{{ tag }}</span>
            </div>
          </div>
        </div>

        <div class="text-center mt-10">
          <router-link to="/features" class="text-white font-medium hover:text-white/70 transition-colors">
            View detailed list of features &gt;
          </router-link>
        </div>
      </div>
    </div>

    <!-- Your Personal Journey -->
    <div class="bg-eggshell-500 pt-10 lg:pt-12 pb-14">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <h2 class="text-xl sm:text-2xl md:text-3xl lg:text-4xl text-center mb-12">Your personal journey</h2>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-6 mb-10">
          <router-link v-for="stage in stages" :key="stage.slug" :to="'/stage/' + stage.slug" class="lg:aspect-square bg-gradient-to-br from-horizon-600 to-horizon-700 rounded-card border border-white/10 shadow-sm p-5 flex flex-col items-start justify-center cursor-pointer hover:border-white/30 hover:-translate-y-0.5 transition-all duration-200 no-underline">
            <p class="text-2xl font-bold text-white mb-1 leading-tight"><span class="text-raspberry-400">{{ stage.accent }}</span><br/>{{ stage.rest }}</p>
            <p class="text-xs text-white/70 leading-snug">{{ stage.desc }}</p>
          </router-link>
        </div>
      </div>
    </div>

    <!-- FAQs -->
    <div class="bg-horizon-500 py-16">
      <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center mb-10">
          <h2 class="text-xl sm:text-2xl md:text-3xl lg:text-4xl font-bold text-white mb-3">Frequently asked questions</h2>
          <p class="text-white/70">Everything you need to know about getting started</p>
        </div>

        <div class="space-y-4">
          <div v-for="(faq, index) in faqs" :key="index" class="bg-white/10 rounded-xl border border-white/20 overflow-hidden">
            <button @click="toggleFaq(index)" class="w-full flex items-center justify-between px-6 py-4 text-left hover:bg-white/20 transition-colors">
              <span class="text-base font-semibold text-white">{{ faq.question }}</span>
              <svg :class="['w-5 h-5 text-white/50 transition-transform duration-200', openFaq === index ? 'rotate-180' : '']" fill="none" viewBox="0 0 24 24" stroke="currentColor">
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

    <!-- Final CTA -->
    <div class="bg-light-pink-100 py-14">
      <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
        <h2 class="text-xl sm:text-2xl md:text-3xl lg:text-4xl font-bold text-horizon-500 mb-3">Ready to take control?</h2>
        <p class="text-sm text-neutral-500 mb-6 max-w-md mx-auto">
          Start your free 7-day trial today. No credit card required.
        </p>
        <router-link to="/register" class="inline-block px-8 py-3 bg-raspberry-500 text-white text-lg font-semibold rounded-lg hover:bg-raspberry-600 transition-colors">
          Get started for free
        </router-link>
      </div>
    </div>
  </PublicLayout>
</template>

<script>
import PublicLayout from '@/layouts/PublicLayout.vue';
import ReviewCarousel from '@/components/Public/ReviewCarousel.vue';
import { getPricingFaqs } from '@/constants/faqData';

export default {
  name: 'QuickStartPage',
  components: { PublicLayout, ReviewCarousel },

  data() {
    return {
      fynDetailsOpen: false,
      openFaq: null,
      faqs: getPricingFaqs().map(item => ({ question: item.q, answer: item.a })),
      features: [
        { title: 'Protection', desc: 'Analyse life insurance, critical illness, and income protection coverage gaps to ensure your family is fully protected.', iconBg: 'bg-raspberry-500', icon: 'M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z', tags: ['Life Cover', 'Income Protection'] },
        { title: 'Savings', desc: 'Track emergency funds, ISA allowances, and savings goals across all your accounts with smart benchmarking.', iconBg: 'bg-spring-500', icon: 'M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z', tags: ['Emergency Fund', 'ISA'] },
        { title: 'Investment', desc: 'Portfolio analysis, risk profiling, and Monte Carlo projections to optimise your investment strategy.', iconBg: 'bg-violet-500', icon: 'M13 7h8m0 0v8m0-8l-8 8-4-4-6 6', tags: ['Portfolio', 'Risk Profile'] },
        { title: 'Retirement', desc: 'Defined Contribution, Defined Benefit, and State Pension tracking with retirement income projections.', iconBg: 'bg-light-blue-500', icon: 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z', tags: ['Pension', 'State Pension'] },
        { title: 'Estate', desc: 'Inheritance Tax calculations, gifting strategies, and estate value projections for effective planning.', iconBg: 'bg-savannah-500', icon: 'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6', tags: ['Inheritance Tax', 'Trusts'] },
        { title: 'Net Worth', desc: 'Complete balance sheet with properties, assets, and liabilities tracking for a clear financial picture.', iconBg: 'bg-horizon-400', icon: 'M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z', tags: ['Assets', 'Liabilities'] },
      ],
      stages: [
        { slug: 'starting-out', accent: 'Starting', rest: 'out', desc: 'Build strong financial habits for your future.' },
        { slug: 'building-foundations', accent: 'Building', rest: 'foundations', desc: 'Save, invest, and grow your wealth with confidence.' },
        { slug: 'protecting-and-growing', accent: 'Protecting', rest: 'and growing', desc: 'Ensure your family and assets are fully covered.' },
        { slug: 'planning-your-future', accent: 'Planning', rest: 'your future', desc: 'Retirement projections, pension tracking, and estate planning.' },
        { slug: 'enjoying-your-wealth', accent: 'Enjoying', rest: 'your wealth', desc: 'Make the most of your financial success.' },
      ],
    };
  },

  methods: {
    toggleFaq(index) {
      this.openFaq = this.openFaq === index ? null : index;
    },
  },

  mounted() {
    document.title = 'Quick Start \u2014 Get Started with Fynla | UK Financial Planning';
    const meta = document.querySelector('meta[name="description"]');
    if (meta) meta.setAttribute('content', 'Get started with Fynla in minutes. Meet Fyn, your AI financial companion. Plan savings, investments, pensions, retirement and estate in one place.');
  },
};
</script>
