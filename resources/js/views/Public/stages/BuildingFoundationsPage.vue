<template>
  <PublicLayout>
    <!-- Hero -->
    <section class="py-16 sm:py-20 bg-gradient-to-br from-spring-500 to-spring-300 text-white">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <p class="text-sm font-semibold uppercase tracking-wider text-white/70 mb-3">Stage 2 of 5 — Building Foundations</p>
        <h1 class="text-4xl sm:text-5xl lg:text-6xl font-bold leading-tight mb-4">
          Am I actually making progress?
        </h1>
        <p class="text-lg sm:text-xl text-white/80 max-w-2xl mb-8 leading-relaxed">
          You're saving, paying off debts, and thinking about a first home. But it's hard to tell
          if the numbers are moving in the right direction. Fynla shows you the full picture.
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
              <svg class="w-4 h-4 text-spring-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
            </span>
            <p class="text-neutral-500">You're saving for a home deposit</p>
          </div>
          <div class="flex items-start gap-3">
            <span class="mt-1 flex-shrink-0 w-6 h-6 rounded-full bg-spring-100 flex items-center justify-center">
              <svg class="w-4 h-4 text-spring-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
            </span>
            <p class="text-neutral-500">You have a workplace pension but aren't sure if it's enough</p>
          </div>
          <div class="flex items-start gap-3">
            <span class="mt-1 flex-shrink-0 w-6 h-6 rounded-full bg-spring-100 flex items-center justify-center">
              <svg class="w-4 h-4 text-spring-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
            </span>
            <p class="text-neutral-500">You're juggling saving and paying off debts</p>
          </div>
          <div class="flex items-start gap-3">
            <span class="mt-1 flex-shrink-0 w-6 h-6 rounded-full bg-spring-100 flex items-center justify-center">
              <svg class="w-4 h-4 text-spring-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
            </span>
            <p class="text-neutral-500">You want to know if you're on track</p>
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
              <svg class="w-5 h-5 text-spring-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" /></svg>
            </div>
            <h3 class="font-semibold text-horizon-500 mb-2">Net worth growth tracking</h3>
            <p class="text-sm text-neutral-500">Watch your net worth climb as you save and pay down debt — proof that you're moving forward.</p>
          </div>
          <div class="bg-white rounded-lg p-6 shadow-sm border border-light-gray">
            <div class="w-10 h-10 rounded-full bg-spring-100 flex items-center justify-center mb-4">
              <svg class="w-5 h-5 text-spring-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
            </div>
            <h3 class="font-semibold text-horizon-500 mb-2">ISA and Lifetime ISA allowance management</h3>
            <p class="text-sm text-neutral-500">Track how much of your annual ISA allowance you've used and maximise your tax-free savings.</p>
          </div>
          <div class="bg-white rounded-lg p-6 shadow-sm border border-light-gray">
            <div class="w-10 h-10 rounded-full bg-spring-100 flex items-center justify-center mb-4">
              <svg class="w-5 h-5 text-spring-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" /></svg>
            </div>
            <h3 class="font-semibold text-horizon-500 mb-2">Mortgage affordability</h3>
            <p class="text-sm text-neutral-500">See what you can afford based on your income, savings, and existing commitments.</p>
          </div>
          <div class="bg-white rounded-lg p-6 shadow-sm border border-light-gray">
            <div class="w-10 h-10 rounded-full bg-spring-100 flex items-center justify-center mb-4">
              <svg class="w-5 h-5 text-spring-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
            </div>
            <h3 class="font-semibold text-horizon-500 mb-2">Pension pot projections</h3>
            <p class="text-sm text-neutral-500">Your workplace pension is growing — see what it could be worth by the time you retire.</p>
          </div>
        </div>
      </div>
    </section>

    <!-- Features as answers -->
    <section class="py-14 bg-white">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <h2 class="text-2xl sm:text-3xl font-bold text-horizon-500 mb-3">Features that answer your questions</h2>
        <p class="text-neutral-500 mb-8 max-w-2xl">You're building something real. These features help you see the progress and stay on course.</p>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
          <router-link to="/features/net-worth-dashboard" class="block bg-eggshell-500 rounded-lg p-6 border border-light-gray hover:border-spring-300 hover:-translate-y-0.5 transition-all">
            <h3 class="font-semibold text-horizon-500 mb-2">Net worth dashboard</h3>
            <p class="text-sm text-neutral-500">Everything you own minus everything you owe — one number that tells you where you stand.</p>
          </router-link>
          <router-link to="/features/net-worth-dashboard" class="block bg-eggshell-500 rounded-lg p-6 border border-light-gray hover:border-spring-300 hover:-translate-y-0.5 transition-all">
            <h3 class="font-semibold text-horizon-500 mb-2">ISA tracking</h3>
            <p class="text-sm text-neutral-500">See your ISA contributions, remaining allowance, and tax-free growth at a glance.</p>
          </router-link>
          <router-link to="/features/net-worth-dashboard" class="block bg-eggshell-500 rounded-lg p-6 border border-light-gray hover:border-spring-300 hover:-translate-y-0.5 transition-all">
            <h3 class="font-semibold text-horizon-500 mb-2">Savings goals</h3>
            <p class="text-sm text-neutral-500">Set targets for your deposit, emergency fund, or anything else — and track your progress.</p>
          </router-link>
          <router-link to="/features/pension-tracker" class="block bg-eggshell-500 rounded-lg p-6 border border-light-gray hover:border-spring-300 hover:-translate-y-0.5 transition-all">
            <h3 class="font-semibold text-horizon-500 mb-2">Pension tracker</h3>
            <p class="text-sm text-neutral-500">Understand your workplace pension contributions and see long-term projections.</p>
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
          <p class="text-lg font-semibold text-horizon-500 mb-2">John Morgan — building foundations</p>
          <p class="text-sm text-neutral-500 mb-6">Late twenties, saving for a deposit, workplace pension, balancing debt repayment and savings goals.</p>
          <a
            href="/?demo=true"
            class="inline-block px-8 py-3 bg-spring-500 text-white font-semibold rounded-lg hover:bg-spring-600 transition-colors"
          >
            Try John Morgan's situation
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
          <router-link to="/features/pension-tracker" class="text-sm text-spring-600 hover:text-spring-700 underline underline-offset-2">Pension tracker</router-link>
          <router-link to="/calculators" class="text-sm text-spring-600 hover:text-spring-700 underline underline-offset-2">Calculators</router-link>
        </div>
      </div>
    </section>

    <!-- Stage navigation -->
    <section class="py-8 bg-eggshell-500 border-t border-light-gray">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 flex justify-between items-center">
        <router-link to="/stage/starting-out" class="text-sm font-medium text-spring-600 hover:text-spring-700 flex items-center gap-1">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" /></svg>
          Previous: Starting Out
        </router-link>
        <router-link to="/stage/protecting-and-growing" class="text-sm font-medium text-spring-600 hover:text-spring-700 flex items-center gap-1">
          Next: Protecting and Growing
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" /></svg>
        </router-link>
      </div>
    </section>
  </PublicLayout>
</template>

<script>
import PublicLayout from '@/layouts/PublicLayout.vue';

export default {
  name: 'BuildingFoundationsPage',
  components: { PublicLayout },
};
</script>
