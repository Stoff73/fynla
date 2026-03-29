<template>
  <PublicLayout>
    <!-- Hero — pricing page style -->
    <div class="relative flex items-center bg-gradient-to-r from-horizon-500 to-raspberry-500 overflow-hidden">
      <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10 text-left w-full">
        <h1 class="text-4xl md:text-6xl font-black text-white mb-3">
          Starting out
        </h1>
        <p class="text-lg md:text-xl font-bold text-white/85 mb-6">Financial planning for beginners</p>
        <p class="text-lg text-white/70 max-w-2xl mb-8">
          You're earning your own money for the first time. It's exciting — but also overwhelming.
          Fynla gives you a clear starting point for financial planning, without the jargon.
        </p>
        <div class="flex items-center gap-5">
          <router-link
            to="/register"
            class="inline-block px-8 py-3 bg-spring-500 text-white font-semibold rounded-lg hover:bg-spring-600 transition-colors"
          >
            Start your journey
          </router-link>
          <a
            href="#"
            class="text-white/85 underline underline-offset-3 hover:text-white transition-colors"
            @click.prevent="launchDemo"
          >
            View demo
          </a>
        </div>
      </div>
    </div>

    <!-- Is this the right stage of your journey? — eggshell bg -->
    <section class="py-12 bg-eggshell-500">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <h2 class="text-xl sm:text-2xl md:text-3xl lg:text-4xl font-bold text-horizon-500 mb-8">Is this the right stage of your journey?</h2>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-6 max-w-3xl">
          <div class="flex items-start gap-3">
            <span class="mt-1 flex-shrink-0 w-6 h-6 rounded-full bg-spring-100 flex items-center justify-center">
              <svg class="w-4 h-4 text-spring-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
            </span>
            <p class="text-neutral-500">You've just started earning for the first time</p>
          </div>
          <div class="flex items-start gap-3">
            <span class="mt-1 flex-shrink-0 w-6 h-6 rounded-full bg-spring-100 flex items-center justify-center">
              <svg class="w-4 h-4 text-spring-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
            </span>
            <p class="text-neutral-500">You have student loan debt and need graduate money management</p>
          </div>
          <div class="flex items-start gap-3">
            <span class="mt-1 flex-shrink-0 w-6 h-6 rounded-full bg-spring-100 flex items-center justify-center">
              <svg class="w-4 h-4 text-spring-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
            </span>
            <p class="text-neutral-500">You're not sure what a pension even does</p>
          </div>
          <div class="flex items-start gap-3">
            <span class="mt-1 flex-shrink-0 w-6 h-6 rounded-full bg-spring-100 flex items-center justify-center">
              <svg class="w-4 h-4 text-spring-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
            </span>
            <p class="text-neutral-500">You want to build first-time savings habits but don't know where to start</p>
          </div>
        </div>
      </div>
    </section>

    <!-- What Fynla shows you — homepage "How Fyn can help you" style (horizon blue cards) -->
    <section class="py-12 bg-gradient-to-r from-horizon-600 to-horizon-700">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <h2 class="text-xl sm:text-2xl md:text-3xl lg:text-4xl font-bold text-white mb-8 text-center">What Fynla shows you at this stage</h2>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
          <div class="bg-horizon-500 rounded-card p-6">
            <div class="w-10 h-10 rounded-full bg-spring-500 flex items-center justify-center mb-4">
              <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
            </div>
            <h3 class="text-white font-semibold mb-2">Emergency fund tracker</h3>
            <p class="text-sm text-horizon-300 leading-relaxed">See how close you are to a three-month safety net — and what it will take to get there.</p>
          </div>
          <div class="bg-horizon-500 rounded-card p-6">
            <div class="w-10 h-10 rounded-full bg-raspberry-500 flex items-center justify-center mb-4">
              <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>
            </div>
            <h3 class="text-white font-semibold mb-2">Student loan overview</h3>
            <p class="text-sm text-horizon-300 leading-relaxed">Understand your plan type, repayment threshold, and how long it will take to clear.</p>
          </div>
          <div class="bg-horizon-500 rounded-card p-6">
            <div class="w-10 h-10 rounded-full bg-violet-500 flex items-center justify-center mb-4">
              <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
            </div>
            <h3 class="text-white font-semibold mb-2">Workplace pension basics</h3>
            <p class="text-sm text-horizon-300 leading-relaxed">See what your employer is putting in, what you're contributing, and what it could grow to.</p>
          </div>
          <div class="bg-horizon-500 rounded-card p-6">
            <div class="w-10 h-10 rounded-full bg-light-blue-500 flex items-center justify-center mb-4">
              <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" /></svg>
            </div>
            <h3 class="text-white font-semibold mb-2">Simple net worth dashboard</h3>
            <p class="text-sm text-horizon-300 leading-relaxed">Everything you own minus everything you owe — one clear number to track from day one.</p>
          </div>
        </div>
      </div>
    </section>

    <!-- Features that may be relevant — Next steps style (light pink bg, white cards) -->
    <section class="py-12 bg-light-pink-100">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <h2 class="text-xl sm:text-2xl md:text-3xl lg:text-4xl font-bold text-horizon-500 mb-8 text-center">Features that may be relevant to you</h2>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
          <router-link to="/features/net-worth-dashboard" class="block bg-white rounded-lg p-5 border-[3px] border-transparent hover:border-raspberry-300 transition-all">
            <p class="text-lg font-bold text-horizon-500 mb-2">Savings goal tracker</p>
            <p class="text-sm text-neutral-500">Set a target, track your progress, and build the saving habit that compounds over a lifetime.</p>
          </router-link>
          <router-link to="/features/net-worth-dashboard" class="block bg-white rounded-lg p-5 border-[3px] border-transparent hover:border-raspberry-300 transition-all">
            <p class="text-lg font-bold text-horizon-500 mb-2">ISA allowance tracking</p>
            <p class="text-sm text-neutral-500">Understand your annual ISA allowance and make sure you're using it before the tax year ends.</p>
          </router-link>
          <router-link to="/features/net-worth-dashboard" class="block bg-white rounded-lg p-5 border-[3px] border-transparent hover:border-raspberry-300 transition-all">
            <p class="text-lg font-bold text-horizon-500 mb-2">Student loan planning</p>
            <p class="text-sm text-neutral-500">See your balance, plan type, and repayment schedule clearly — no more guessing.</p>
          </router-link>
          <router-link to="/features/net-worth-dashboard" class="block bg-white rounded-lg p-5 border-[3px] border-transparent hover:border-raspberry-300 transition-all">
            <p class="text-lg font-bold text-horizon-500 mb-2">Budget foundations</p>
            <p class="text-sm text-neutral-500">Know exactly what comes in, what goes out, and what's left to save or invest.</p>
          </router-link>
        </div>
      </div>
    </section>

    <!-- Small steps that make a big difference — eggshell bg, horizon blue cards -->
    <section class="py-12 bg-eggshell-500">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <h2 class="text-xl sm:text-2xl md:text-3xl lg:text-4xl font-bold text-horizon-500 mb-8 text-center">Small steps that make a big difference</h2>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div class="bg-horizon-500 rounded-lg p-6">
            <p class="text-base font-semibold text-white mb-2">Your first £1,000</p>
            <p class="text-sm text-white/70 leading-relaxed">Building an emergency fund feels impossible at first. Fynla breaks it into monthly targets and tracks your progress — so you can see that £1,000 safety net getting closer every month.</p>
          </div>
          <div class="bg-horizon-500 rounded-lg p-6">
            <p class="text-base font-semibold text-white mb-2">Free money you might be missing</p>
            <p class="text-sm text-white/70 leading-relaxed">If your employer matches pension contributions and you're not contributing enough to get the full match, you're leaving free money on the table. Fynla shows you exactly what you're missing.</p>
          </div>
        </div>
      </div>
    </section>

    <!-- See it in action — CTA style -->
    <section class="py-16 bg-gradient-to-r from-horizon-500 to-raspberry-500">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex flex-col lg:flex-row lg:justify-between lg:items-start gap-8">
          <div class="flex-1">
            <h2 class="text-6xl lg:text-8xl font-bold text-white leading-none mb-2">Janice</h2>
            <p class="text-sm text-white/60 max-w-xl leading-relaxed">
              Fresh out of university with a student loan and her first workplace pension.
              She's building an emergency fund and learning what her money is actually doing.
              Fynla shows her exactly where she stands — and what to focus on first.
            </p>
          </div>
          <div class="flex flex-col items-start lg:items-end gap-0 lg:pt-3">
            <h3 class="text-xl sm:text-2xl md:text-3xl font-bold text-white leading-none">See it in action</h3>
            <p class="text-sm sm:text-base font-bold text-white/85 mb-5">Explore Fynla through a real scenario — no sign-up required.</p>
            <a
              href="#"
              class="inline-block px-8 py-3 bg-spring-500 text-white font-semibold rounded-lg hover:bg-spring-600 transition-colors"
              @click.prevent="launchDemo"
            >
              Try Starting out demo
            </a>
          </div>
        </div>
      </div>
    </section>

    <!-- Related + stage navigation (combined) -->
    <section class="py-8 bg-eggshell-500">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 flex flex-wrap justify-between items-center gap-4">
        <div class="flex items-center gap-4">
          <span class="text-sm font-semibold text-horizon-500">Related</span>
          <router-link to="/features/net-worth-dashboard" class="text-sm text-spring-600 hover:text-spring-700 underline underline-offset-2">Net worth dashboard</router-link>
          <router-link to="/how-it-works" class="text-sm text-spring-600 hover:text-spring-700 underline underline-offset-2">How it works</router-link>
          <router-link to="/faq" class="text-sm text-spring-600 hover:text-spring-700 underline underline-offset-2">Frequently asked questions</router-link>
        </div>
        <router-link to="/stage/building-foundations" class="text-sm font-medium text-spring-600 hover:text-spring-700 flex items-center gap-1">
          Building foundations
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" /></svg>
        </router-link>
      </div>
    </section>
  </PublicLayout>
</template>

<script>
import PublicLayout from '@/layouts/PublicLayout.vue';
import { nextTick } from 'vue';

export default {
  name: 'StartingOutPage',
  components: { PublicLayout },
  mounted() {
    document.title = 'Financial Planning for Beginners UK — Starting Out | Fynla';
    const meta = document.querySelector('meta[name="description"]');
    if (meta) meta.setAttribute('content', 'New to managing money? Fynla helps you build an emergency fund, understand your workplace pension, track ISA savings, and plan your student loan — all in one place.');
  },
  methods: {
    async launchDemo() {
      try {
        await this.$store.dispatch('preview/loadPersona', 'student');
        await nextTick();
        this.$router.push('/dashboard');
      } catch (error) {
        console.error('Failed to launch demo:', error);
      }
    },
  },
};
</script>
