<template>
  <PublicLayout>
    <!-- Hero -->
    <div class="relative flex items-center bg-gradient-to-r from-horizon-500 to-raspberry-500 overflow-hidden">
      <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10 text-left w-full">
        <h1 class="text-4xl md:text-6xl font-black text-white mb-3">
          Building foundations
        </h1>
        <p class="text-lg md:text-xl font-bold text-white/85 mb-6">Am I actually making progress?</p>
        <p class="text-lg text-white/70 max-w-2xl mb-8">
          You're saving, paying off debts, and thinking about a first home. But it's hard to tell
          if the numbers are moving in the right direction. Fynla shows you the full picture — including first-time buyer mortgage planning and your ISA savings&nbsp;strategy.
        </p>
        <div class="flex flex-col sm:flex-row items-start sm:items-center gap-3 sm:gap-5">
          <router-link to="/register" class="inline-block px-8 py-3 bg-spring-500 text-white font-semibold rounded-lg hover:bg-spring-600 transition-colors">Start with your situation</router-link>
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
            <p class="text-neutral-500">You're saving for a home deposit</p>
          </div>
          <div class="flex items-start gap-3">
            <span class="mt-1 flex-shrink-0 w-6 h-6 rounded-full bg-spring-100 flex items-center justify-center">
              <svg class="w-4 h-4 text-spring-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
            </span>
            <p class="text-neutral-500">You have a workplace pension but aren't sure if it's enough</p>
          </div>
          <div class="flex items-start gap-3">
            <span class="mt-1 flex-shrink-0 w-6 h-6 rounded-full bg-spring-100 flex items-center justify-center">
              <svg class="w-4 h-4 text-spring-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
            </span>
            <p class="text-neutral-500">You're juggling saving and paying off debts</p>
          </div>
          <div class="flex items-start gap-3">
            <span class="mt-1 flex-shrink-0 w-6 h-6 rounded-full bg-spring-100 flex items-center justify-center">
              <svg class="w-4 h-4 text-spring-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
            </span>
            <p class="text-neutral-500">You're building wealth in your 30s and want to know if you're on track</p>
          </div>
        </div>
      </div>
    </section>

    <!-- What Fynla shows you -->
    <section class="py-12 bg-gradient-to-r from-horizon-600 to-horizon-700">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <h2 class="text-xl sm:text-2xl md:text-3xl lg:text-4xl font-bold text-white mb-8 text-center">What Fynla shows you at this stage</h2>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
          <div class="bg-horizon-500 rounded-card p-6">
            <div class="w-10 h-10 rounded-full bg-spring-500 flex items-center justify-center mb-4">
              <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" /></svg>
            </div>
            <h3 class="font-semibold text-white mb-2">Net worth growth tracking</h3>
            <p class="text-sm text-horizon-300 leading-relaxed">Watch your net worth climb as you save and pay down debt — proof that you're moving forward.</p>
          </div>
          <div class="bg-horizon-500 rounded-card p-6">
            <div class="w-10 h-10 rounded-full bg-raspberry-500 flex items-center justify-center mb-4">
              <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
            </div>
            <h3 class="font-semibold text-white mb-2">ISA and Lifetime ISA allowance management</h3>
            <p class="text-sm text-horizon-300 leading-relaxed">Track how much of your annual ISA allowance you've used and maximise your tax-free savings.</p>
          </div>
          <div class="bg-horizon-500 rounded-card p-6">
            <div class="w-10 h-10 rounded-full bg-violet-500 flex items-center justify-center mb-4">
              <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" /></svg>
            </div>
            <h3 class="font-semibold text-white mb-2">Mortgage affordability</h3>
            <p class="text-sm text-horizon-300 leading-relaxed">See what you can afford based on your income, savings, and existing commitments.</p>
          </div>
          <div class="bg-horizon-500 rounded-card p-6">
            <div class="w-10 h-10 rounded-full bg-light-blue-500 flex items-center justify-center mb-4">
              <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
            </div>
            <h3 class="font-semibold text-white mb-2">Pension pot projections</h3>
            <p class="text-sm text-horizon-300 leading-relaxed">Your workplace pension is growing — see what it could be worth by the time you retire.</p>
          </div>
        </div>
      </div>
    </section>

    <!-- Features as answers -->
    <section class="py-12 bg-light-pink-100">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <h2 class="text-xl sm:text-2xl md:text-3xl lg:text-4xl font-bold text-horizon-500 mb-8 text-center">Features that may be relevant to you</h2>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
          <router-link to="/features/net-worth-dashboard" class="block bg-white rounded-lg p-5 border-[3px] border-transparent hover:border-raspberry-300 transition-all">
            <p class="text-lg font-bold text-horizon-500 mb-2">Net worth dashboard</p>
            <p class="text-sm text-neutral-500">Everything you own minus everything you owe — one number that tells you where you stand.</p>
          </router-link>
          <router-link to="/features/net-worth-dashboard" class="block bg-white rounded-lg p-5 border-[3px] border-transparent hover:border-raspberry-300 transition-all">
            <p class="text-lg font-bold text-horizon-500 mb-2">ISA tracking</p>
            <p class="text-sm text-neutral-500">See your ISA contributions, remaining allowance, and tax-free growth at a glance.</p>
          </router-link>
          <router-link to="/features/net-worth-dashboard" class="block bg-white rounded-lg p-5 border-[3px] border-transparent hover:border-raspberry-300 transition-all">
            <p class="text-lg font-bold text-horizon-500 mb-2">Savings goals</p>
            <p class="text-sm text-neutral-500">Set targets for your deposit, emergency fund, or anything else — and track your progress.</p>
          </router-link>
          <router-link to="/features/pension-tracker" class="block bg-white rounded-lg p-5 border-[3px] border-transparent hover:border-raspberry-300 transition-all">
            <p class="text-lg font-bold text-horizon-500 mb-2">Pension tracker</p>
            <p class="text-sm text-neutral-500">Understand your workplace pension contributions and see long-term projections.</p>
          </router-link>
        </div>
      </div>
    </section>

    <!-- Moments -->
    <section class="py-12 bg-eggshell-500">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <h2 class="text-xl sm:text-2xl md:text-3xl lg:text-4xl font-bold text-horizon-500 mb-8 text-center">Moments where Fynla makes a difference</h2>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div class="bg-horizon-500 rounded-lg p-5">
            <p class="text-base font-semibold text-white mb-1">Saved Thousands on a Mortgage</p>
            <p class="text-xs text-white/70 leading-relaxed">"Fynla alerted me that my fixed-rate mortgage was ending in 6 months. I locked in a new rate early — before rates went up. That single alert saved me thousands over the next 5 years."</p>
          </div>
          <div class="bg-horizon-500 rounded-lg p-5">
            <p class="text-base font-semibold text-white mb-1">ISA Allowance — Use It or Lose It</p>
            <p class="text-xs text-white/70 leading-relaxed">Fynla tracks your ISA contributions across all your accounts and shows you how much allowance you have left this tax year. No more scrambling in March or accidentally exceeding the limit.</p>
          </div>
        </div>
      </div>
    </section>

    <!-- See it in action -->
    <section class="py-16 bg-gradient-to-r from-horizon-500 to-raspberry-500">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex flex-col lg:flex-row lg:justify-between lg:items-start gap-8">
          <div class="flex-1">
            <h2 class="text-6xl lg:text-8xl font-bold text-white leading-none mb-2">John Morgan</h2>
            <p class="text-sm text-white/60 max-w-xl leading-relaxed">
              Late twenties, saving for a deposit, workplace pension, balancing debt repayment and savings goals. Fynla shows him exactly where he stands and what to prioritise next.
            </p>
          </div>
          <div class="flex flex-col items-start lg:items-end gap-0 lg:pt-3">
            <h3 class="text-xl sm:text-2xl md:text-3xl font-bold text-white leading-none">See it in action</h3>
            <p class="text-sm sm:text-base font-bold text-white/85 mb-5">Explore Fynla through a real scenario — no sign-up required.</p>
            <a href="#" class="inline-block px-8 py-3 bg-spring-500 text-white font-semibold rounded-lg hover:bg-spring-600 transition-colors" @click.prevent="launchDemo">Try Building foundations demo</a>
          </div>
        </div>
      </div>
    </section>

    <!-- Related + Stage navigation -->
    <section class="py-8 bg-eggshell-500">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 flex flex-wrap justify-between items-center gap-4">
        <div class="flex items-center gap-4">
          <span class="text-sm font-semibold text-horizon-500">Related</span>
          <router-link to="/features/net-worth-dashboard" class="text-sm text-spring-600 hover:text-spring-700 underline underline-offset-2">Net worth dashboard</router-link>
          <router-link to="/features/pension-tracker" class="text-sm text-spring-600 hover:text-spring-700 underline underline-offset-2">Pension tracker</router-link>
          <router-link to="/calculators" class="text-sm text-spring-600 hover:text-spring-700 underline underline-offset-2">Calculators</router-link>
        </div>
        <div class="flex items-center gap-4">
          <router-link to="/stage/starting-out" class="text-sm font-medium text-spring-600 hover:text-spring-700 flex items-center gap-1">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" /></svg>
            Starting out
          </router-link>
          <router-link to="/stage/protecting-and-growing" class="text-sm font-medium text-spring-600 hover:text-spring-700 flex items-center gap-1">
            Protecting and growing
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

export default {
  name: 'BuildingFoundationsPage',
  components: { PublicLayout },
  mounted() {
    document.title = 'First-Time Buyer Financial Planning — Building Foundations | Fynla';
    const meta = document.querySelector('meta[name="description"]');
    if (meta) meta.setAttribute('content', 'Saving for your first home? Fynla tracks your ISA savings, models mortgage affordability, monitors pension growth, and helps you build wealth with confidence.');
  },
  methods: {
    async launchDemo() {
      try {
        await this.$store.dispatch('preview/loadPersona', 'young_saver');
        await nextTick();
        this.$router.push('/dashboard');
      } catch (error) {
        console.error('Failed to launch demo:', error);
      }
    },
  },
};
</script>
