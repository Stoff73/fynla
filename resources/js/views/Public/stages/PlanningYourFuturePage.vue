<template>
  <PublicLayout>
    <!-- Hero -->
    <div class="relative flex items-center bg-gradient-to-r from-horizon-500 to-raspberry-500 overflow-hidden">
      <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10 text-left w-full">
        <h1 class="text-4xl md:text-6xl font-black text-white mb-3">
          Planning your future
        </h1>
        <p class="text-lg md:text-xl font-bold text-white/85 mb-6">Can I actually afford to stop working?</p>
        <p class="text-lg text-white/70 max-w-2xl mb-8">
          Retirement planning UK starts with one question: is it enough? You've been saving for years, but
          Fynla shows you exactly where you stand — including pension consolidation strategy and early retirement planning — and what you can do about&nbsp;it.
        </p>
        <div class="flex flex-col sm:flex-row items-start sm:items-center gap-3 sm:gap-5">
          <router-link to="/register?stage=peak" class="inline-block px-8 py-3 bg-spring-500 text-white font-semibold rounded-lg hover:bg-spring-600 transition-colors">Start your journey</router-link>
          <a href="#" class="text-white/85 underline underline-offset-3 hover:text-white transition-colors" @click.prevent="launchDemo">View demo</a>
        </div>
      </div>
    </div>

    <!-- Is this the right stage of your journey? — with journey map -->
    <section class="py-12 bg-eggshell-500">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        
            <h2 class="text-xl sm:text-2xl md:text-3xl lg:text-4xl font-bold text-horizon-500 mb-8">Is this the right stage of your journey?</h2>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
              <div class="flex items-start gap-3">
                <span class="mt-1 flex-shrink-0 w-6 h-6 rounded-full bg-spring-100 flex items-center justify-center">
                  <svg class="w-4 h-4 text-spring-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                </span>
                <p class="text-neutral-500">Retirement is on the horizon — five to twenty years away</p>
              </div>
              <div class="flex items-start gap-3">
                <span class="mt-1 flex-shrink-0 w-6 h-6 rounded-full bg-spring-100 flex items-center justify-center">
                  <svg class="w-4 h-4 text-spring-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                </span>
                <p class="text-neutral-500">You have multiple pension pots and aren't sure of the total picture</p>
              </div>
              <div class="flex items-start gap-3">
                <span class="mt-1 flex-shrink-0 w-6 h-6 rounded-full bg-spring-100 flex items-center justify-center">
                  <svg class="w-4 h-4 text-spring-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                </span>
                <p class="text-neutral-500">You're wondering if you can retire early</p>
              </div>
              <div class="flex items-start gap-3">
                <span class="mt-1 flex-shrink-0 w-6 h-6 rounded-full bg-spring-100 flex items-center justify-center">
                  <svg class="w-4 h-4 text-spring-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                </span>
                <p class="text-neutral-500">You want to maximise your remaining earning years</p>
              </div>
            </div>
          </div>
    </section>

    <!-- What Fynla shows you -->
    <section class="py-12 bg-gradient-to-r from-horizon-600 to-horizon-700">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <h2 class="text-xl sm:text-2xl md:text-3xl lg:text-4xl font-bold text-white mb-8 text-center">What Fynla shows you at this stage</h2>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
          <div class="bg-horizon-500 rounded-card p-6">
            <div class="w-10 h-10 rounded-full bg-spring-500 flex items-center justify-center mb-4">
              <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
            </div>
            <h3 class="font-semibold text-white mb-2">"When Can I Retire?" calculator</h3>
            <p class="text-sm text-horizon-300 leading-relaxed">Enter your target income in retirement and see the earliest age you could stop working based on your current savings trajectory.</p>
          </div>
          <div class="bg-horizon-500 rounded-card p-6">
            <div class="w-10 h-10 rounded-full bg-raspberry-500 flex items-center justify-center mb-4">
              <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" /></svg>
            </div>
            <h3 class="font-semibold text-white mb-2">Monte Carlo simulations</h3>
            <p class="text-sm text-horizon-300 leading-relaxed">Thousands of market scenarios tested against your plan — see the probability of your retirement income lasting your lifetime.</p>
          </div>
          <div class="bg-horizon-500 rounded-card p-6">
            <div class="w-10 h-10 rounded-full bg-violet-500 flex items-center justify-center mb-4">
              <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" /></svg>
            </div>
            <h3 class="font-semibold text-white mb-2">Full pension dashboard with projections</h3>
            <p class="text-sm text-horizon-300 leading-relaxed">Defined Contribution, Defined Benefit, and State Pension — all in one view with projected values at your chosen retirement age.</p>
          </div>
          <div class="bg-horizon-500 rounded-card p-6">
            <div class="w-10 h-10 rounded-full bg-light-blue-500 flex items-center justify-center mb-4">
              <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" /></svg>
            </div>
            <h3 class="font-semibold text-white mb-2">Inheritance Tax exposure</h3>
            <p class="text-sm text-horizon-300 leading-relaxed">From 2027, pensions fall within the Inheritance Tax net. See how this affects your estate and what you can do now.</p>
          </div>
          <div class="bg-horizon-500 rounded-card p-6">
            <div class="w-10 h-10 rounded-full bg-savannah-500 flex items-center justify-center mb-4">
              <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4" /></svg>
            </div>
            <h3 class="font-semibold text-white mb-2">Drawdown vs annuity modelling</h3>
            <p class="text-sm text-horizon-300 leading-relaxed">Compare income drawdown flexibility against the security of an annuity — essential for bridging retirement income and planning your withdrawals.</p>
          </div>
        </div>
      </div>
    </section>

    <!-- Features as answers -->
    <section class="py-12 bg-light-pink-100">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <h2 class="text-xl sm:text-2xl md:text-3xl lg:text-4xl font-bold text-horizon-500 mb-8 text-center">Features that may be relevant to you</h2>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
          <router-link to="/features/when-can-i-retire" class="block bg-white rounded-lg p-5 border-[3px] border-transparent hover:border-raspberry-300 transition-all">
            <p class="text-lg font-bold text-horizon-500 mb-2">When Can I Retire?</p>
            <p class="text-sm text-neutral-500">The question everyone asks. Fynla gives you a data-driven answer based on your actual finances.</p>
          </router-link>
          <router-link to="/features/monte-carlo" class="block bg-white rounded-lg p-5 border-[3px] border-transparent hover:border-raspberry-300 transition-all">
            <p class="text-lg font-bold text-horizon-500 mb-2">Monte Carlo simulations</p>
            <p class="text-sm text-neutral-500">Don't rely on a single forecast. See the range of outcomes and the probability of success.</p>
          </router-link>
          <router-link to="/features/pension-tracker" class="block bg-white rounded-lg p-5 border-[3px] border-transparent hover:border-raspberry-300 transition-all">
            <p class="text-lg font-bold text-horizon-500 mb-2">Pension tracker</p>
            <p class="text-sm text-neutral-500">Every pension in one place — Defined Contribution, Defined Benefit, State Pension, and Self-Invested Personal Pensions.</p>
          </router-link>
          <router-link to="/features/iht-planning" class="block bg-white rounded-lg p-5 border-[3px] border-transparent hover:border-raspberry-300 transition-all">
            <p class="text-lg font-bold text-horizon-500 mb-2">Inheritance Tax planning</p>
            <p class="text-sm text-neutral-500">Understand your Inheritance Tax exposure and the strategies available to reduce it — including the 2027 pension changes.</p>
          </router-link>
        </div>
      </div>
    </section>

    <!-- What do I need to start my journey? -->
    <section class="py-12 bg-white">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <h2 class="text-xl sm:text-2xl md:text-3xl lg:text-4xl font-bold text-horizon-500 mb-3 text-center">What do I need to start my journey?</h2>
        <p class="text-sm text-neutral-500 text-center mb-8 max-w-2xl mx-auto">To get the most from Fynla, have these details ready. You don't need everything at once — you can always come back and add more later.</p>
        <!-- Journey map -->
        <div class="bg-light-blue-100 rounded-2xl px-4 mb-10">
          <div><JourneyMap stage="peak" mode="progress" /></div>
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
          <div v-for="card in needCards" :key="card.title" class="bg-light-blue-100 rounded-xl p-5">
            <div class="w-9 h-9 rounded-full flex items-center justify-center mb-3" :style="{ background: card.iconBg }">
              <svg class="w-[18px] h-[18px]" :style="{ color: card.iconColor }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" :d="card.icon" />
              </svg>
            </div>
            <h3 class="text-base font-bold text-horizon-500 mb-1">{{ card.title }}</h3>
            <p class="text-xs text-neutral-500 mb-2">{{ card.desc }}</p>
            <ul class="space-y-1">
              <li v-for="item in card.items" :key="item" class="flex items-center gap-1.5 text-xs text-neutral-500">
                <span class="w-1 h-1 rounded-full bg-raspberry-500 flex-shrink-0"></span>
                {{ item }}
              </li>
            </ul>
          </div>
        </div>
        <div class="text-center mt-8">
          <router-link to="/register" class="inline-block px-8 py-3 bg-spring-500 text-white font-semibold rounded-lg hover:bg-spring-600 transition-colors">Start your journey</router-link>
          <p class="text-xs text-neutral-400 mt-2">You can always skip steps and come back later</p>
        </div>
      </div>
    </section>

    <!-- Moments -->
    <section class="py-12 bg-light-pink-100">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <h2 class="text-xl sm:text-2xl md:text-3xl lg:text-4xl font-bold text-horizon-500 mb-8 text-center">Moments where Fynla makes a difference</h2>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div class="bg-horizon-500 rounded-lg border border-light-gray p-5">
            <p class="text-base font-semibold text-white mb-1">Three Years Earlier Than Expected</p>
            <p class="text-xs text-white/70 leading-relaxed">"I assumed I'd work until 67 like everyone else. Fynla modelled my actual pensions, savings, and spending — and showed me I could stop at 64. That's three extra years of freedom I didn't know I had."</p>
          </div>
          <div class="bg-horizon-500 rounded-lg border border-light-gray p-5">
            <p class="text-base font-semibold text-white mb-1">Six Jobs, Six Forgotten Pension Pots</p>
            <p class="text-xs text-white/70 leading-relaxed">The average UK worker has 11 jobs in their lifetime. That's potentially 11 pension pots with 11 different providers. Fynla brings them all into one dashboard so you can finally see what you've actually got.</p>
          </div>
        </div>
      </div>
    </section>

    <!-- See it in action -->
    <section class="py-16 bg-gradient-to-r from-horizon-500 to-raspberry-500">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex flex-col lg:flex-row lg:justify-between lg:items-start gap-8">
          <div class="flex-1">
            <h2 class="text-xl sm:text-2xl md:text-3xl lg:text-4xl font-bold text-white leading-none mb-2">David &amp; Sarah Mitchell</h2>
            <p class="text-sm text-white/60 max-w-xl leading-relaxed">
              Multiple properties, Self-Invested Personal Pension and NHS Defined Benefit pension, approaching retirement with serious planning decisions ahead. Fynla shows them exactly when they can afford to stop working.
            </p>
          </div>
          <div class="flex flex-col items-start lg:items-end gap-0 lg:pt-3">
            <h3 class="text-xl sm:text-2xl md:text-3xl font-bold text-white leading-none">See it in action</h3>
            <p class="text-sm sm:text-base font-bold text-white/85 mb-5">Explore Fynla through a real scenario — no sign-up required.</p>
            <a href="#" class="inline-block px-8 py-3 bg-spring-500 text-white font-semibold rounded-lg hover:bg-spring-600 transition-colors" @click.prevent="launchDemo">Try Planning your future demo</a>
          </div>
        </div>
      </div>
    </section>

    <!-- Related + nav merged -->
    <section class="py-8 bg-eggshell-500">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 flex flex-wrap justify-between items-center gap-4">
        <div class="flex items-center gap-4">
          <span class="text-sm font-semibold text-horizon-500">Related</span>
          <router-link to="/features/when-can-i-retire" class="text-sm text-spring-600 hover:text-spring-700 underline underline-offset-2">When Can I Retire?</router-link>
          <router-link to="/features/monte-carlo" class="text-sm text-spring-600 hover:text-spring-700 underline underline-offset-2">Monte Carlo simulations</router-link>
          <router-link to="/features/pension-tracker" class="text-sm text-spring-600 hover:text-spring-700 underline underline-offset-2">Pension tracker</router-link>
          <router-link to="/features/iht-planning" class="text-sm text-spring-600 hover:text-spring-700 underline underline-offset-2">Inheritance Tax planning</router-link>
        </div>
        <div class="flex items-center gap-4">
          <router-link to="/stage/protecting-and-growing" class="text-sm font-medium text-spring-600 hover:text-spring-700 flex items-center gap-1">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" /></svg>
            Protecting and growing
          </router-link>
          <router-link to="/stage/enjoying-your-wealth" class="text-sm font-medium text-spring-600 hover:text-spring-700 flex items-center gap-1">
            Enjoying your wealth
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" /></svg>
          </router-link>
        </div>
      </div>
    </section>
  </PublicLayout>
</template>

<script>
import { nextTick } from 'vue';
import PublicLayout from '@/layouts/PublicLayout.vue';
import JourneyMap from '@/components/Journey/JourneyMap.vue';

import logger from '@/utils/logger';
export default {
  name: 'PlanningYourFuturePage',
  components: { PublicLayout, JourneyMap },
  data() {
    return {
      needCards: [
        { title: 'About You', desc: 'Personal and retirement details', icon: 'M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z', iconBg: 'rgba(214,51,108,0.1)', iconColor: '#D6336C', items: ['Date of birth', 'Target retirement age', 'Health status', 'State Pension forecast'] },
        { title: 'All Pension Details', desc: 'Every pension you have', icon: 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z', iconBg: 'rgba(55,182,121,0.1)', iconColor: '#37B679', items: ['Current workplace pension', 'Previous employer pensions', 'Any Self-Invested Personal Pensions', 'Defined Benefit pension details'] },
        { title: 'Income & Tax', desc: 'Earnings at peak level', icon: 'M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z', iconBg: 'rgba(124,58,237,0.1)', iconColor: '#7C3AED', items: ['Annual salary', 'Bonus and benefits', 'Rental income', 'Dividend income'] },
        { title: 'Investments & Assets', desc: 'Your full portfolio', icon: 'M13 7h8m0 0v8m0-8l-8 8-4-4-6 6', iconBg: 'rgba(59,130,246,0.1)', iconColor: '#3B82F6', items: ['ISA and investment values', 'Property portfolio', 'Other assets', 'Business interests'] },
        { title: 'Estate & Inheritance Tax', desc: 'Legacy planning basics', icon: 'M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4', iconBg: 'rgba(217,119,6,0.1)', iconColor: '#D97706', items: ['Will status', 'Lasting Power of Attorney', 'Gifting history', 'Trust arrangements'] },
        { title: 'Retirement Vision', desc: 'What retirement looks like', icon: 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4', iconBg: 'rgba(214,51,108,0.1)', iconColor: '#D6336C', items: ['Desired retirement income', 'Planned retirement age', 'Drawdown vs annuity preference', 'Early retirement considerations'] },
      ],
    };
  },
  mounted() {
    document.title = 'Retirement Planning UK — When Can I Retire? | Fynla';
    const meta = document.querySelector('meta[name="description"]');
    if (meta) meta.setAttribute('content', 'Find out when you can afford to retire. Fynla consolidates your pension pots, runs Monte Carlo simulations, models drawdown strategies, and calculates your retirement income.');
  },
  methods: {
    async launchDemo() {
      try {
        await this.$store.dispatch('preview/loadPersona', 'peak_earners');
        await nextTick();
        this.$router.push('/dashboard');
      } catch (error) {
        logger.error('Failed to launch demo:', error);
      }
    },
  },
};
</script>
