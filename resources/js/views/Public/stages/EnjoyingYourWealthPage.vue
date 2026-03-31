<template>
  <PublicLayout>
    <!-- Hero -->
    <div class="relative flex items-center bg-gradient-to-r from-horizon-500 to-raspberry-500 overflow-hidden">
      <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10 text-left w-full">
        <h1 class="text-4xl md:text-6xl font-black text-white mb-3">
          Enjoying your wealth
        </h1>
        <p class="text-lg md:text-xl font-bold text-white/85 mb-6">What happens to everything when I'm gone?</p>
        <p class="text-lg text-white/70 max-w-2xl mb-8">
          You've worked hard, saved well, and built a comfortable life. Now the question shifts
          to legacy — estate planning UK focuses on making sure your family is provided for and that Inheritance Tax mitigation
          keeps more of your wealth where it belongs. Fynla helps you plan with&nbsp;clarity.
        </p>
        <div class="flex flex-col sm:flex-row items-start sm:items-center gap-3 sm:gap-5">
          <router-link to="/register?stage=retirement" class="inline-block px-8 py-3 bg-spring-500 text-white font-semibold rounded-lg hover:bg-spring-600 transition-colors">Start your journey</router-link>
          <a href="#" class="text-white/85 underline underline-offset-3 hover:text-white transition-colors" @click.prevent="launchDemo">View demo</a>
        </div>
      </div>
    </div>

    <!-- Is this your stage? -->
    <section class="py-12 bg-eggshell-500">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <h2 class="text-xl sm:text-2xl md:text-3xl lg:text-4xl font-bold text-horizon-500 mb-8">Is this the right stage of your journey?</h2>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-6 max-w-3xl">
          <div class="flex items-start gap-3">
            <span class="mt-1 flex-shrink-0 w-6 h-6 rounded-full bg-spring-100 flex items-center justify-center">
              <svg class="w-4 h-4 text-spring-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
            </span>
            <p class="text-neutral-500">You're already retired or about to be</p>
          </div>
          <div class="flex items-start gap-3">
            <span class="mt-1 flex-shrink-0 w-6 h-6 rounded-full bg-spring-100 flex items-center justify-center">
              <svg class="w-4 h-4 text-spring-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
            </span>
            <p class="text-neutral-500">You're concerned about Inheritance Tax on your estate</p>
          </div>
          <div class="flex items-start gap-3">
            <span class="mt-1 flex-shrink-0 w-6 h-6 rounded-full bg-spring-100 flex items-center justify-center">
              <svg class="w-4 h-4 text-spring-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
            </span>
            <p class="text-neutral-500">Family wealth protection is a priority — you want to make sure your loved ones are provided for</p>
          </div>
          <div class="flex items-start gap-3">
            <span class="mt-1 flex-shrink-0 w-6 h-6 rounded-full bg-spring-100 flex items-center justify-center">
              <svg class="w-4 h-4 text-spring-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
            </span>
            <p class="text-neutral-500">You're thinking about gifting or trust strategies</p>
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
              <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" /></svg>
            </div>
            <h3 class="font-semibold text-white mb-2">Estate valuation and Inheritance Tax calculator</h3>
            <p class="text-sm text-horizon-300 leading-relaxed">See your total estate value, available nil-rate bands, and exactly how much Inheritance Tax your family would face today.</p>
          </div>
          <div class="bg-horizon-500 rounded-card p-6">
            <div class="w-10 h-10 rounded-full bg-raspberry-500 flex items-center justify-center mb-4">
              <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v13m0-13V6a2 2 0 112 2h-2zm0 0V5.5A2.5 2.5 0 109.5 8H12zm-7 4h14M5 12a2 2 0 110-4h14a2 2 0 110 4M5 12v7a2 2 0 002 2h10a2 2 0 002-2v-7" /></svg>
            </div>
            <h3 class="font-semibold text-white mb-2">Gifting strategy modeller</h3>
            <p class="text-sm text-horizon-300 leading-relaxed">Model how regular gifts, annual exemptions, and potentially exempt transfers reduce your Inheritance Tax liability over time.</p>
          </div>
          <div class="bg-horizon-500 rounded-card p-6">
            <div class="w-10 h-10 rounded-full bg-violet-500 flex items-center justify-center mb-4">
              <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
            </div>
            <h3 class="font-semibold text-white mb-2">Pension drawdown tracking</h3>
            <p class="text-sm text-horizon-300 leading-relaxed">Monitor your drawdown rate against your pot size for effective retirement income management, and see how long your pension will last under different scenarios.</p>
          </div>
          <div class="bg-horizon-500 rounded-card p-6">
            <div class="w-10 h-10 rounded-full bg-light-blue-500 flex items-center justify-center mb-4">
              <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>
            </div>
            <h3 class="font-semibold text-white mb-2">In Case of Emergency letters for your family</h3>
            <p class="text-sm text-horizon-300 leading-relaxed">A clear, comprehensive guide to your finances for your loved ones — policies, accounts, contacts, and wishes, all in one document.</p>
          </div>
          <div class="bg-horizon-500 rounded-card p-6">
            <div class="w-10 h-10 rounded-full bg-savannah-500 flex items-center justify-center mb-4">
              <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4" /></svg>
            </div>
            <h3 class="font-semibold text-white mb-2">Will and Power of Attorney guidance</h3>
            <p class="text-sm text-horizon-300 leading-relaxed">Understand what you need in place and track whether your Will, Lasting Power of Attorney, and other essential documents are up to date.</p>
          </div>
        </div>
      </div>
    </section>

    <!-- Features as answers -->
    <section class="py-12 bg-light-pink-100">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <h2 class="text-xl sm:text-2xl md:text-3xl lg:text-4xl font-bold text-horizon-500 mb-8 text-center">Features that may be relevant to you</h2>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
          <router-link to="/features/iht-planning" class="block bg-white rounded-lg p-5 border-[3px] border-transparent hover:border-raspberry-300 transition-all">
            <p class="text-lg font-bold text-horizon-500 mb-2">Inheritance Tax planning</p>
            <p class="text-sm text-neutral-500">See your full Inheritance Tax exposure, available reliefs, and strategies to reduce the liability — including the 2027 pension changes.</p>
          </router-link>
          <router-link to="/features/ice-letters" class="block bg-white rounded-lg p-5 border-[3px] border-transparent hover:border-raspberry-300 transition-all">
            <p class="text-lg font-bold text-horizon-500 mb-2">In Case of Emergency letters</p>
            <p class="text-sm text-neutral-500">The most important document you'll ever create — a complete guide to your finances for your family.</p>
          </router-link>
          <router-link to="/features/net-worth-dashboard" class="block bg-white rounded-lg p-5 border-[3px] border-transparent hover:border-raspberry-300 transition-all">
            <p class="text-lg font-bold text-horizon-500 mb-2">Estate dashboard</p>
            <p class="text-sm text-neutral-500">Your complete estate at a glance — property, investments, pensions, and policies with total valuation.</p>
          </router-link>
          <router-link to="/features/iht-planning" class="block bg-white rounded-lg p-5 border-[3px] border-transparent hover:border-raspberry-300 transition-all">
            <p class="text-lg font-bold text-horizon-500 mb-2">Gifting strategies</p>
            <p class="text-sm text-neutral-500">Model how gifting reduces your Inheritance Tax bill and track the seven-year taper relief on potentially exempt transfers.</p>
          </router-link>
          <router-link to="/features/pension-tracker" class="block bg-white rounded-lg p-5 border-[3px] border-transparent hover:border-raspberry-300 transition-all">
            <p class="text-lg font-bold text-horizon-500 mb-2">Drawdown management</p>
            <p class="text-sm text-neutral-500">Track your pension drawdown rate and see how long your pot will last under different withdrawal scenarios.</p>
          </router-link>
        </div>
      </div>
    </section>

    <!-- Moments -->
    <section class="py-12 bg-eggshell-500">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <h2 class="text-xl sm:text-2xl md:text-3xl lg:text-4xl font-bold text-horizon-500 mb-8 text-center">Moments where Fynla makes a difference</h2>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div class="bg-horizon-500 rounded-lg border border-light-gray p-5">
            <p class="text-base font-semibold text-white mb-1">An Inheritance Tax Bill Nobody Expected</p>
            <p class="text-xs text-white/70 leading-relaxed">"I had no idea our Inheritance Tax bill was going to be £180,000. Fynla showed us in 5 minutes — and then helped us model gifting strategies that could reduce it significantly over the next few years."</p>
          </div>
          <div class="bg-horizon-500 rounded-lg border border-light-gray p-5">
            <p class="text-base font-semibold text-white mb-1">54% of UK Adults Don't Have a Will</p>
            <p class="text-xs text-white/70 leading-relaxed">Without a will, the law decides who gets what — and unmarried partners get nothing. Fynla guides you through getting your estate in order: wills, Lasting Powers of Attorney, Inheritance Tax planning, and In Case of Emergency letters for your family.</p>
          </div>
        </div>
      </div>
    </section>

    <!-- See it in action -->
    <section class="py-16 bg-gradient-to-r from-horizon-500 to-raspberry-500">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex flex-col lg:flex-row lg:justify-between lg:items-start gap-8">
          <div class="flex-1">
            <h2 class="text-6xl lg:text-8xl font-bold text-white leading-none mb-2">Robert &amp; Patricia Williams</h2>
            <p class="text-sm text-white/60 max-w-xl leading-relaxed">
              Retired couple with property, pensions in drawdown, and an estate exposed to Inheritance Tax.
              They need clarity on gifting strategies, estate valuation, and how to protect their family's inheritance.
            </p>
          </div>
          <div class="flex flex-col items-start lg:items-end gap-0 lg:pt-3">
            <h3 class="text-xl sm:text-2xl md:text-3xl font-bold text-white leading-none">See it in action</h3>
            <p class="text-sm sm:text-base font-bold text-white/85 mb-5">Explore Fynla through a real scenario — no sign-up required.</p>
            <a href="#" class="inline-block px-8 py-3 bg-spring-500 text-white font-semibold rounded-lg hover:bg-spring-600 transition-colors" @click.prevent="launchDemo">Try Enjoying your wealth demo</a>
          </div>
        </div>
      </div>
    </section>

    <!-- Related + navigation -->
    <section class="py-8 bg-eggshell-500">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 flex flex-wrap justify-between items-center gap-4">
        <div class="flex items-center gap-4">
          <span class="text-sm font-semibold text-horizon-500">Related</span>
          <router-link to="/features/iht-planning" class="text-sm text-spring-600 hover:text-spring-700 underline underline-offset-2">Inheritance Tax planning</router-link>
          <router-link to="/features/ice-letters" class="text-sm text-spring-600 hover:text-spring-700 underline underline-offset-2">In Case of Emergency letters</router-link>
          <router-link to="/features/pension-tracker" class="text-sm text-spring-600 hover:text-spring-700 underline underline-offset-2">Pension tracker</router-link>
        </div>
        <router-link to="/stage/planning-your-future" class="text-sm font-medium text-spring-600 hover:text-spring-700 flex items-center gap-1">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" /></svg>
          Planning your future
        </router-link>
      </div>
    </section>
  </PublicLayout>
</template>

<script>
import { nextTick } from 'vue';
import PublicLayout from '@/layouts/PublicLayout.vue';

import logger from '@/utils/logger';
export default {
  name: 'EnjoyingYourWealthPage',
  components: { PublicLayout },
  mounted() {
    document.title = 'Estate Planning & Inheritance Tax Planning UK | Fynla';
    const meta = document.querySelector('meta[name="description"]');
    if (meta) meta.setAttribute('content', 'Know your Inheritance Tax exposure and plan to reduce it. Fynla models gifting strategies, tracks pension drawdown, and generates In Case of Emergency letters for your family.');
  },
  methods: {
    async launchDemo() {
      try {
        await this.$store.dispatch('preview/loadPersona', 'retired_couple');
        await nextTick();
        this.$router.push('/dashboard');
      } catch (error) {
        logger.error('Failed to launch demo:', error);
      }
    },
  },
};
</script>
