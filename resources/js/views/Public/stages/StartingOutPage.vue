<template>
  <PublicLayout>
    <!-- Hero -->
    <section class="py-16 sm:py-20 bg-gradient-to-br from-spring-600 to-spring-400 text-white">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <p class="text-sm font-semibold uppercase tracking-wider text-white/70 mb-3">Stage 1 of 5 — Starting Out</p>
        <h1 class="text-4xl sm:text-5xl lg:text-6xl font-bold leading-tight mb-4">
          Where do I even begin?
        </h1>
        <p class="text-lg sm:text-xl text-white/80 max-w-2xl mb-8 leading-relaxed">
          You're earning your own money for the first time. It's exciting — but also overwhelming.
          Fynla gives you a clear starting point, without the jargon.
        </p>
        <router-link
          to="/register"
          class="inline-block px-8 py-3 bg-white text-spring-600 font-semibold rounded-lg hover:bg-white/90 transition-colors"
        >
          Start with your situation
        </router-link>
      </div>
    </section>

    <!-- Is this your stage? -->
    <section class="py-14 bg-white">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <h2 class="text-2xl sm:text-3xl font-bold text-horizon-500 mb-8">Is this your stage?</h2>
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
            <p class="text-neutral-500">You have student loan debt</p>
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
            <p class="text-neutral-500">You want to start saving but don't know where</p>
          </div>
        </div>
      </div>
    </section>

    <!-- What Fynla shows you -->
    <section class="py-14 bg-eggshell-500">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <h2 class="text-2xl sm:text-3xl font-bold text-horizon-500 mb-8">What Fynla shows you at this stage</h2>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
          <div class="bg-white rounded-lg p-6 shadow-sm border border-light-gray">
            <div class="w-10 h-10 rounded-full bg-spring-100 flex items-center justify-center mb-4">
              <svg class="w-5 h-5 text-spring-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
            </div>
            <h3 class="font-semibold text-horizon-500 mb-2">Emergency fund tracker</h3>
            <p class="text-sm text-neutral-500">See how close you are to a three-month safety net — and what it will take to get there.</p>
          </div>
          <div class="bg-white rounded-lg p-6 shadow-sm border border-light-gray">
            <div class="w-10 h-10 rounded-full bg-spring-100 flex items-center justify-center mb-4">
              <svg class="w-5 h-5 text-spring-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>
            </div>
            <h3 class="font-semibold text-horizon-500 mb-2">Student loan overview</h3>
            <p class="text-sm text-neutral-500">Understand your plan type, repayment threshold, and how long it will take to clear.</p>
          </div>
          <div class="bg-white rounded-lg p-6 shadow-sm border border-light-gray">
            <div class="w-10 h-10 rounded-full bg-spring-100 flex items-center justify-center mb-4">
              <svg class="w-5 h-5 text-spring-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
            </div>
            <h3 class="font-semibold text-horizon-500 mb-2">Workplace pension basics</h3>
            <p class="text-sm text-neutral-500">See what your employer is putting in, what you're contributing, and what it could grow to.</p>
          </div>
          <div class="bg-white rounded-lg p-6 shadow-sm border border-light-gray">
            <div class="w-10 h-10 rounded-full bg-spring-100 flex items-center justify-center mb-4">
              <svg class="w-5 h-5 text-spring-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" /></svg>
            </div>
            <h3 class="font-semibold text-horizon-500 mb-2">Simple net worth dashboard</h3>
            <p class="text-sm text-neutral-500">Everything you own minus everything you owe — one clear number to track from day one.</p>
          </div>
        </div>
      </div>
    </section>

    <!-- Features as answers -->
    <section class="py-14 bg-white">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <h2 class="text-2xl sm:text-3xl font-bold text-horizon-500 mb-3">Features that answer your questions</h2>
        <p class="text-neutral-500 mb-8 max-w-2xl">You don't need complex tools yet — you need clarity. These features give you exactly that.</p>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
          <router-link to="/features/net-worth-dashboard" class="block bg-eggshell-500 rounded-lg p-6 border border-light-gray hover:border-spring-300 hover:-translate-y-0.5 transition-all">
            <h3 class="font-semibold text-horizon-500 mb-2">Savings goal tracker</h3>
            <p class="text-sm text-neutral-500">Set a target, track your progress, and build the saving habit that compounds over a lifetime.</p>
          </router-link>
          <router-link to="/features/net-worth-dashboard" class="block bg-eggshell-500 rounded-lg p-6 border border-light-gray hover:border-spring-300 hover:-translate-y-0.5 transition-all">
            <h3 class="font-semibold text-horizon-500 mb-2">ISA allowance tracking</h3>
            <p class="text-sm text-neutral-500">Understand your annual ISA allowance and make sure you're using it before the tax year ends.</p>
          </router-link>
          <router-link to="/features/net-worth-dashboard" class="block bg-eggshell-500 rounded-lg p-6 border border-light-gray hover:border-spring-300 hover:-translate-y-0.5 transition-all">
            <h3 class="font-semibold text-horizon-500 mb-2">Student loan planning</h3>
            <p class="text-sm text-neutral-500">See your balance, plan type, and repayment schedule clearly — no more guessing.</p>
          </router-link>
          <router-link to="/features/net-worth-dashboard" class="block bg-eggshell-500 rounded-lg p-6 border border-light-gray hover:border-spring-300 hover:-translate-y-0.5 transition-all">
            <h3 class="font-semibold text-horizon-500 mb-2">Budget foundations</h3>
            <p class="text-sm text-neutral-500">Know exactly what comes in, what goes out, and what's left to save or invest.</p>
          </router-link>
        </div>
      </div>
    </section>

    <!-- Try a demo persona -->
    <section class="py-14 bg-eggshell-500">
      <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
        <h2 class="text-2xl sm:text-3xl font-bold text-horizon-500 mb-4">See it in action</h2>
        <p class="text-neutral-500 mb-8">Explore Fynla through a real scenario — no sign-up required.</p>
        <div class="bg-white rounded-lg p-8 shadow-sm border border-light-gray inline-block">
          <p class="text-lg font-semibold text-horizon-500 mb-2">Janice — just starting out</p>
          <p class="text-sm text-neutral-500 mb-6">Fresh out of university, student loan, first workplace pension, building an emergency fund.</p>
          <a
            href="/?demo=true"
            class="inline-block px-8 py-3 bg-spring-500 text-white font-semibold rounded-lg hover:bg-spring-600 transition-colors"
          >
            Try Janice's situation
          </a>
        </div>
      </div>
    </section>

    <!-- Related content -->
    <section class="py-10 bg-white">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <h3 class="text-lg font-semibold text-horizon-500 mb-4">Related</h3>
        <div class="flex flex-wrap gap-4">
          <router-link to="/features/net-worth-dashboard" class="text-sm text-spring-600 hover:text-spring-700 underline underline-offset-2">Net worth dashboard</router-link>
          <router-link to="/how-it-works" class="text-sm text-spring-600 hover:text-spring-700 underline underline-offset-2">How it works</router-link>
          <router-link to="/faq" class="text-sm text-spring-600 hover:text-spring-700 underline underline-offset-2">Frequently asked questions</router-link>
        </div>
      </div>
    </section>

    <!-- Stage navigation -->
    <section class="py-8 bg-eggshell-500 border-t border-light-gray">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 flex justify-between items-center">
        <span class="text-sm text-neutral-400">This is the first stage</span>
        <router-link to="/stage/building-foundations" class="text-sm font-medium text-spring-600 hover:text-spring-700 flex items-center gap-1">
          Next: Building Foundations
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" /></svg>
        </router-link>
      </div>
    </section>
  </PublicLayout>
</template>

<script>
import PublicLayout from '@/layouts/PublicLayout.vue';

export default {
  name: 'StartingOutPage',
  components: { PublicLayout },
};
</script>
