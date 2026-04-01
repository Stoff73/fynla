<template>
  <PublicLayout>
    <!-- Hero -->
    <div class="relative flex items-center bg-gradient-to-r from-horizon-500 to-raspberry-500 overflow-hidden">
      <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10 text-left w-full">
        <h1 class="text-4xl md:text-6xl font-black text-white mb-3">
          Protecting and growing
        </h1>
        <p class="text-lg md:text-xl font-bold text-white/85 mb-6">Are we protected — and are we moving forward?</p>
        <p class="text-lg text-white/70 max-w-2xl mb-8">
          You've got a mortgage, dependants, and growing responsibilities. A family financial protection assessment
          is essential at this stage. Fynla brings it all together so you can protect what you've built while making sure your money is still working for&nbsp;you.
        </p>
        <div class="flex flex-col sm:flex-row items-start sm:items-center gap-3 sm:gap-5">
          <router-link to="/register?stage=mid_career" class="inline-block px-8 py-3 bg-spring-500 text-white font-semibold rounded-lg hover:bg-spring-600 transition-colors">Start your journey</router-link>
          <a href="#" class="text-white/85 underline underline-offset-3 hover:text-white transition-colors" @click.prevent="launchDemo">View demo</a>
        </div>
      </div>
    </div>

    <!-- Is this your stage? — with journey map -->
    <section class="py-12 bg-eggshell-500">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        
            <h2 class="text-xl sm:text-2xl md:text-3xl lg:text-4xl font-bold text-horizon-500 mb-8">Is this the right stage of your journey?</h2>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
              <div class="flex items-start gap-3">
                <span class="mt-1 flex-shrink-0 w-6 h-6 rounded-full bg-spring-100 flex items-center justify-center">
                  <svg class="w-4 h-4 text-spring-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                </span>
                <p class="text-neutral-500">You have a mortgage and dependants</p>
              </div>
              <div class="flex items-start gap-3">
                <span class="mt-1 flex-shrink-0 w-6 h-6 rounded-full bg-spring-100 flex items-center justify-center">
                  <svg class="w-4 h-4 text-spring-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                </span>
                <p class="text-neutral-500">You're not sure if your life insurance is enough — you need a life insurance gap analysis</p>
              </div>
              <div class="flex items-start gap-3">
                <span class="mt-1 flex-shrink-0 w-6 h-6 rounded-full bg-spring-100 flex items-center justify-center">
                  <svg class="w-4 h-4 text-spring-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                </span>
                <p class="text-neutral-500">You've got pensions from multiple employers</p>
              </div>
              <div class="flex items-start gap-3">
                <span class="mt-1 flex-shrink-0 w-6 h-6 rounded-full bg-spring-100 flex items-center justify-center">
                  <svg class="w-4 h-4 text-spring-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                </span>
                <p class="text-neutral-500">You want to grow your investments but aren't sure how much risk to take</p>
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
              <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" /></svg>
            </div>
            <h3 class="font-semibold text-white mb-2">Protection gap analysis</h3>
            <p class="text-sm text-horizon-300 leading-relaxed">See exactly where your life insurance, critical illness, and income protection fall short — and by how much.</p>
          </div>
          <div class="bg-horizon-500 rounded-card p-6">
            <div class="w-10 h-10 rounded-full bg-raspberry-500 flex items-center justify-center mb-4">
              <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" /></svg>
            </div>
            <h3 class="font-semibold text-white mb-2">Net worth with property equity</h3>
            <p class="text-sm text-horizon-300 leading-relaxed">Your home is likely your biggest asset. See your true equity and how it fits your total net worth.</p>
          </div>
          <div class="bg-horizon-500 rounded-card p-6">
            <div class="w-10 h-10 rounded-full bg-violet-500 flex items-center justify-center mb-4">
              <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
            </div>
            <h3 class="font-semibold text-white mb-2">Multiple pension consolidation view</h3>
            <p class="text-sm text-horizon-300 leading-relaxed">See all your Defined Contribution pensions from different employers in one place, with combined projections.</p>
          </div>
          <div class="bg-horizon-500 rounded-card p-6">
            <div class="w-10 h-10 rounded-full bg-light-blue-500 flex items-center justify-center mb-4">
              <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" /></svg>
            </div>
            <h3 class="font-semibold text-white mb-2">Investment portfolio tracking</h3>
            <p class="text-sm text-horizon-300 leading-relaxed">Monitor your investments, understand your asset allocation, and see how your risk profile matches your goals.</p>
          </div>
          <div class="bg-horizon-500 rounded-card p-6">
            <div class="w-10 h-10 rounded-full bg-savannah-500 flex items-center justify-center mb-4">
              <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z" /></svg>
            </div>
            <h3 class="font-semibold text-white mb-2">Joint financial planning for couples</h3>
            <p class="text-sm text-horizon-300 leading-relaxed">See both partners' finances side by side — joint assets, individual pensions, shared liabilities, complete picture.</p>
          </div>
        </div>
      </div>
    </section>

    <!-- Features as answers -->
    <section class="py-12 bg-light-pink-100">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <h2 class="text-xl sm:text-2xl md:text-3xl lg:text-4xl font-bold text-horizon-500 mb-8 text-center">Features that may be relevant to you</h2>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
          <router-link to="/features/protection-gap" class="block bg-white rounded-lg p-5 border-[3px] border-transparent hover:border-raspberry-300 transition-all">
            <p class="text-lg font-bold text-horizon-500 mb-2">Protection gap analyser</p>
            <p class="text-sm text-neutral-500">Fynla calculates exactly how much cover you need based on your mortgage, income, and family situation.</p>
          </router-link>
          <router-link to="/features/net-worth-dashboard" class="block bg-white rounded-lg p-5 border-[3px] border-transparent hover:border-raspberry-300 transition-all">
            <p class="text-lg font-bold text-horizon-500 mb-2">Net worth dashboard</p>
            <p class="text-sm text-neutral-500">Track your complete financial position including property equity, pensions, investments, and debts.</p>
          </router-link>
          <router-link to="/features/pension-tracker" class="block bg-white rounded-lg p-5 border-[3px] border-transparent hover:border-raspberry-300 transition-all">
            <p class="text-lg font-bold text-horizon-500 mb-2">Pension tracker</p>
            <p class="text-sm text-neutral-500">Consolidate the view of multiple workplace pensions and see what they add up to at retirement.</p>
          </router-link>
          <router-link to="/features/net-worth-dashboard" class="block bg-white rounded-lg p-5 border-[3px] border-transparent hover:border-raspberry-300 transition-all">
            <p class="text-lg font-bold text-horizon-500 mb-2">Investment analysis</p>
            <p class="text-sm text-neutral-500">Understand your portfolio allocation, risk profile, and whether your investments match your goals.</p>
          </router-link>
          <router-link to="/features/ice-letters" class="block bg-white rounded-lg p-5 border-[3px] border-transparent hover:border-raspberry-300 transition-all">
            <p class="text-lg font-bold text-horizon-500 mb-2">In Case of Emergency letters</p>
            <p class="text-sm text-neutral-500">Give your family a clear guide to your finances if the worst happens — policies, accounts, contacts, all in one place.</p>
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
          <div><JourneyMap stage="mid_career" mode="progress" /></div>
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
            <p class="text-base font-semibold text-white mb-1">The Document Your Family Needs</p>
            <p class="text-xs text-white/70 leading-relaxed">If something happened to you tonight, would your partner know where the pensions are? The mortgage account number? The life insurance provider? Fynla's In Case of Emergency letter puts everything in one document — auto-populated from your plan.</p>
          </div>
          <div class="bg-horizon-500 rounded-lg border border-light-gray p-5">
            <p class="text-base font-semibold text-white mb-1">The Cover You Didn't Know You Were Missing</p>
            <p class="text-xs text-white/70 leading-relaxed">Your workplace life insurance pays 2x salary. Your mortgage is 6x salary. Fynla calculates what your family would actually need and shows you the gap — across life insurance, income protection, and critical illness.</p>
          </div>
        </div>
      </div>
    </section>

    <!-- See it in action -->
    <section class="py-16 bg-gradient-to-r from-horizon-500 to-raspberry-500">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex flex-col lg:flex-row lg:justify-between lg:items-start gap-8">
          <div class="flex-1">
            <h2 class="text-xl sm:text-2xl md:text-3xl lg:text-4xl font-bold text-white leading-none mb-2">James &amp; Emily Carter</h2>
            <p class="text-sm text-white/60 max-w-xl leading-relaxed">
              Young family with a joint mortgage, workplace pensions, and two children to protect.
              They need to know if their life insurance is enough and whether their money is working hard enough for the future.
            </p>
          </div>
          <div class="flex flex-col items-start lg:items-end gap-0 lg:pt-3">
            <h3 class="text-xl sm:text-2xl md:text-3xl font-bold text-white leading-none">See it in action</h3>
            <p class="text-sm sm:text-base font-bold text-white/85 mb-5">Explore Fynla through a real scenario — no sign-up required.</p>
            <a href="#" class="inline-block px-8 py-3 bg-spring-500 text-white font-semibold rounded-lg hover:bg-spring-600 transition-colors" @click.prevent="launchDemo">Try Protecting and growing demo</a>
          </div>
        </div>
      </div>
    </section>

    <!-- Related + stage nav -->
    <section class="py-8 bg-eggshell-500">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 flex flex-wrap justify-between items-center gap-4">
        <div class="flex items-center gap-4">
          <span class="text-sm font-semibold text-horizon-500">Related</span>
          <router-link to="/features/protection-gap" class="text-sm text-spring-600 hover:text-spring-700 underline underline-offset-2">Protection gap analyser</router-link>
          <router-link to="/features/ice-letters" class="text-sm text-spring-600 hover:text-spring-700 underline underline-offset-2">In Case of Emergency letters</router-link>
          <router-link to="/features/net-worth-dashboard" class="text-sm text-spring-600 hover:text-spring-700 underline underline-offset-2">Net worth dashboard</router-link>
          <router-link to="/features/pension-tracker" class="text-sm text-spring-600 hover:text-spring-700 underline underline-offset-2">Pension tracker</router-link>
        </div>
        <div class="flex items-center gap-4">
          <router-link to="/stage/building-foundations" class="text-sm font-medium text-spring-600 hover:text-spring-700 flex items-center gap-1">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" /></svg>
            Building foundations
          </router-link>
          <router-link to="/stage/planning-your-future" class="text-sm font-medium text-spring-600 hover:text-spring-700 flex items-center gap-1">
            Planning your future
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
  name: 'ProtectingAndGrowingPage',
  components: { PublicLayout, JourneyMap },
  data() {
    return {
      needCards: [
        { title: 'About You & Family', desc: 'Personal and family details', icon: 'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z', iconBg: 'rgba(214,51,108,0.1)', iconColor: '#D6336C', items: ['Date of birth (you and partner)', 'Number of dependants', 'Occupation details', 'Health and smoking status'] },
        { title: 'Income & Tax', desc: 'Household earnings', icon: 'M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z', iconBg: 'rgba(55,182,121,0.1)', iconColor: '#37B679', items: ['Both partners\' salaries', 'Tax codes', 'Bonus and overtime', 'Rental or investment income'] },
        { title: 'Protection Policies', desc: 'Your existing cover', icon: 'M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z', iconBg: 'rgba(124,58,237,0.1)', iconColor: '#7C3AED', items: ['Life insurance policy details', 'Critical illness cover', 'Income protection details', 'Employer death-in-service benefit'] },
        { title: 'Property & Mortgage', desc: 'Your home details', icon: 'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6', iconBg: 'rgba(59,130,246,0.1)', iconColor: '#3B82F6', items: ['Property value estimate', 'Mortgage balance and rate', 'Fixed rate end date', 'Monthly mortgage payment'] },
        { title: 'Pensions & Investments', desc: 'Your growth assets', icon: 'M13 7h8m0 0v8m0-8l-8 8-4-4-6 6', iconBg: 'rgba(217,119,6,0.1)', iconColor: '#D97706', items: ['Workplace pension statements', 'Previous employer pensions', 'ISA and investment accounts', 'Risk attitude'] },
        { title: 'Your Goals', desc: 'Family financial priorities', icon: 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4', iconBg: 'rgba(214,51,108,0.1)', iconColor: '#D6336C', items: ['Protection gap concerns', 'Investment growth targets', 'Children\'s education plans', 'Mortgage strategy'] },
      ],
    };
  },
  mounted() {
    document.title = 'Family Financial Protection & Investment Planning UK | Fynla';
    const meta = document.querySelector('meta[name="description"]');
    if (meta) meta.setAttribute('content', 'Are your family and finances properly protected? Fynla analyses your life insurance gap, tracks investment portfolios, consolidates pension views, and supports joint financial planning for couples.');
  },
  methods: {
    async launchDemo() {
      try {
        await this.$store.dispatch('preview/loadPersona', 'young_family');
        await nextTick();
        this.$router.push('/dashboard');
      } catch (error) {
        logger.error('Failed to launch demo:', error);
      }
    },
  },
};
</script>
