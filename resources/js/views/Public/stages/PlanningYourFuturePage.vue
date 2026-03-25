<template>
  <PublicLayout>
    <!-- Hero -->
    <section class="py-10 sm:py-12 bg-gradient-to-br from-violet-600 to-violet-400 text-white">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <p class="text-sm font-semibold uppercase tracking-wider text-white/70 mb-3">Stage 4 of 5 — Planning Your Future</p>
        <h1 class="text-xl sm:text-2xl lg:text-3xl font-bold leading-tight mb-4">
          Can I actually afford to stop working?
        </h1>
        <p class="text-sm sm:text-sm text-white/80 max-w-2xl mb-8 leading-relaxed">
          Retirement planning UK starts with one question: is it enough? You've been saving for years, but
          Fynla shows you exactly where you stand — including pension consolidation strategy and early retirement planning — and what you can do about it.
        </p>
        <router-link
          to="/register"
          class="inline-block px-8 py-3 bg-white text-violet-600 font-semibold rounded-lg hover:bg-white/90 transition-colors"
        >
          Start with your situation
        </router-link>
      </div>
    </section>

    <!-- Is this your stage? -->
    <section class="py-10 bg-white">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <h2 class="text-base sm:text-lg font-bold text-horizon-500 mb-8">Is this your stage?</h2>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-6 max-w-3xl">
          <div class="flex items-start gap-3">
            <span class="mt-1 flex-shrink-0 w-6 h-6 rounded-full bg-violet-100 flex items-center justify-center">
              <svg class="w-4 h-4 text-violet-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
            </span>
            <p class="text-neutral-500">Retirement is on the horizon — five to twenty years away</p>
          </div>
          <div class="flex items-start gap-3">
            <span class="mt-1 flex-shrink-0 w-6 h-6 rounded-full bg-violet-100 flex items-center justify-center">
              <svg class="w-4 h-4 text-violet-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
            </span>
            <p class="text-neutral-500">You have multiple pension pots and aren't sure of the total picture</p>
          </div>
          <div class="flex items-start gap-3">
            <span class="mt-1 flex-shrink-0 w-6 h-6 rounded-full bg-violet-100 flex items-center justify-center">
              <svg class="w-4 h-4 text-violet-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
            </span>
            <p class="text-neutral-500">You're wondering if you can retire early</p>
          </div>
          <div class="flex items-start gap-3">
            <span class="mt-1 flex-shrink-0 w-6 h-6 rounded-full bg-violet-100 flex items-center justify-center">
              <svg class="w-4 h-4 text-violet-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
            </span>
            <p class="text-neutral-500">You want to maximise your remaining earning years</p>
          </div>
        </div>
      </div>
    </section>

    <!-- What Fynla shows you -->
    <section class="py-10 bg-eggshell-500">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <h2 class="text-base sm:text-lg font-bold text-horizon-500 mb-8">What Fynla shows you at this stage</h2>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
          <div class="bg-white rounded-lg p-6 shadow-sm border border-light-gray">
            <div class="w-10 h-10 rounded-full bg-violet-100 flex items-center justify-center mb-4">
              <svg class="w-5 h-5 text-violet-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
            </div>
            <h3 class="font-semibold text-horizon-500 mb-2">"When Can I Retire?" calculator</h3>
            <p class="text-sm text-neutral-500">Enter your target income in retirement and see the earliest age you could stop working based on your current savings trajectory.</p>
          </div>
          <div class="bg-white rounded-lg p-6 shadow-sm border border-light-gray">
            <div class="w-10 h-10 rounded-full bg-violet-100 flex items-center justify-center mb-4">
              <svg class="w-5 h-5 text-violet-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" /></svg>
            </div>
            <h3 class="font-semibold text-horizon-500 mb-2">Monte Carlo simulations</h3>
            <p class="text-sm text-neutral-500">Thousands of market scenarios tested against your plan — see the probability of your retirement income lasting your lifetime.</p>
          </div>
          <div class="bg-white rounded-lg p-6 shadow-sm border border-light-gray">
            <div class="w-10 h-10 rounded-full bg-violet-100 flex items-center justify-center mb-4">
              <svg class="w-5 h-5 text-violet-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" /></svg>
            </div>
            <h3 class="font-semibold text-horizon-500 mb-2">Full pension dashboard with projections</h3>
            <p class="text-sm text-neutral-500">Defined Contribution, Defined Benefit, and State Pension — all in one view with projected values at your chosen retirement age.</p>
          </div>
          <div class="bg-white rounded-lg p-6 shadow-sm border border-light-gray">
            <div class="w-10 h-10 rounded-full bg-violet-100 flex items-center justify-center mb-4">
              <svg class="w-5 h-5 text-violet-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" /></svg>
            </div>
            <h3 class="font-semibold text-horizon-500 mb-2">Inheritance Tax exposure</h3>
            <p class="text-sm text-neutral-500">From 2027, pensions fall within the Inheritance Tax net. See how this affects your estate and what you can do now.</p>
          </div>
          <div class="bg-white rounded-lg p-6 shadow-sm border border-light-gray">
            <div class="w-10 h-10 rounded-full bg-violet-100 flex items-center justify-center mb-4">
              <svg class="w-5 h-5 text-violet-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4" /></svg>
            </div>
            <h3 class="font-semibold text-horizon-500 mb-2">Drawdown vs annuity modelling</h3>
            <p class="text-sm text-neutral-500">Compare income drawdown flexibility against the security of an annuity — essential for bridging retirement income and planning your withdrawals.</p>
          </div>
        </div>
      </div>
    </section>

    <!-- Features as answers -->
    <section class="py-10 bg-white">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <h2 class="text-base sm:text-lg font-bold text-horizon-500 mb-3">Features that answer your questions</h2>
        <p class="text-neutral-500 mb-8 max-w-2xl">The stakes are higher now. These features give you confidence that your plan will work.</p>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
          <router-link to="/features/when-can-i-retire" class="block bg-eggshell-500 rounded-lg p-6 border border-light-gray hover:border-violet-300 hover:-translate-y-0.5 transition-all">
            <h3 class="font-semibold text-horizon-500 mb-2">When Can I Retire?</h3>
            <p class="text-sm text-neutral-500">The question everyone asks. Fynla gives you a data-driven answer based on your actual finances.</p>
          </router-link>
          <router-link to="/features/monte-carlo" class="block bg-eggshell-500 rounded-lg p-6 border border-light-gray hover:border-violet-300 hover:-translate-y-0.5 transition-all">
            <h3 class="font-semibold text-horizon-500 mb-2">Monte Carlo simulations</h3>
            <p class="text-sm text-neutral-500">Don't rely on a single forecast. See the range of outcomes and the probability of success.</p>
          </router-link>
          <router-link to="/features/pension-tracker" class="block bg-eggshell-500 rounded-lg p-6 border border-light-gray hover:border-violet-300 hover:-translate-y-0.5 transition-all">
            <h3 class="font-semibold text-horizon-500 mb-2">Pension tracker</h3>
            <p class="text-sm text-neutral-500">Every pension in one place — Defined Contribution, Defined Benefit, State Pension, and Self-Invested Personal Pensions.</p>
          </router-link>
          <router-link to="/features/iht-planning" class="block bg-eggshell-500 rounded-lg p-6 border border-light-gray hover:border-violet-300 hover:-translate-y-0.5 transition-all">
            <h3 class="font-semibold text-horizon-500 mb-2">Inheritance Tax planning</h3>
            <p class="text-sm text-neutral-500">Understand your Inheritance Tax exposure and the strategies available to reduce it — including the 2027 pension changes.</p>
          </router-link>
        </div>
      </div>
    </section>

    <!-- Try a demo persona -->
    <section class="py-10 bg-eggshell-500">
      <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
        <h2 class="text-base sm:text-lg font-bold text-horizon-500 mb-4">See it in action</h2>
        <p class="text-neutral-500 mb-8">Explore Fynla through a real scenario — no sign-up required.</p>
        <div class="bg-white rounded-lg p-5 shadow-sm border border-light-gray inline-block">
          <p class="text-sm font-semibold text-horizon-500 mb-2">David &amp; Sarah Mitchell — peak earners</p>
          <p class="text-sm text-neutral-500 mb-6">Multiple properties, Self-Invested Personal Pension and NHS Defined Benefit pension, approaching retirement with serious planning decisions ahead.</p>
          <a
            href="/?demo=true"
            class="inline-block px-8 py-3 bg-spring-500 text-white font-semibold rounded-lg hover:bg-spring-600 transition-colors"
          >
            Try David &amp; Sarah Mitchell's situation
          </a>
        </div>
      </div>
    </section>

    <!-- Related content -->
    <section class="py-10 bg-white">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <h3 class="text-sm font-semibold text-horizon-500 mb-4">Related</h3>
        <div class="flex flex-wrap gap-4">
          <router-link to="/features/when-can-i-retire" class="text-sm text-violet-600 hover:text-violet-700 underline underline-offset-2">When Can I Retire?</router-link>
          <router-link to="/features/monte-carlo" class="text-sm text-violet-600 hover:text-violet-700 underline underline-offset-2">Monte Carlo simulations</router-link>
          <router-link to="/features/pension-tracker" class="text-sm text-violet-600 hover:text-violet-700 underline underline-offset-2">Pension tracker</router-link>
          <router-link to="/features/iht-planning" class="text-sm text-violet-600 hover:text-violet-700 underline underline-offset-2">Inheritance Tax planning</router-link>
        </div>
      </div>
    </section>

    <!-- Stage navigation -->
    <section class="py-8 bg-eggshell-500 border-t border-light-gray">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 flex justify-between items-center">
        <router-link to="/stage/protecting-and-growing" class="text-sm font-medium text-violet-600 hover:text-violet-700 flex items-center gap-1">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" /></svg>
          Previous: Protecting and Growing
        </router-link>
        <router-link to="/stage/enjoying-your-wealth" class="text-sm font-medium text-violet-600 hover:text-violet-700 flex items-center gap-1">
          Next: Enjoying Your Wealth
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" /></svg>
        </router-link>
      </div>
    </section>
  </PublicLayout>
</template>

<script>
import PublicLayout from '@/layouts/PublicLayout.vue';

export default {
  name: 'PlanningYourFuturePage',
  components: { PublicLayout },
  mounted() {
    document.title = 'Retirement Planning UK — When Can I Retire? | Fynla';
    const meta = document.querySelector('meta[name="description"]');
    if (meta) meta.setAttribute('content', 'Find out when you can afford to retire. Fynla consolidates your pension pots, runs Monte Carlo simulations, models drawdown strategies, and calculates your retirement income.');
  },
};
</script>
