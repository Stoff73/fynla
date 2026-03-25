<template>
  <PublicLayout>
    <!-- Hero -->
    <section class="py-10 sm:py-12 bg-gradient-to-br from-savannah-600 to-savannah-400 text-white">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <p class="text-sm font-semibold uppercase tracking-wider text-white/70 mb-3">Stage 5 of 5 — Enjoying Your Wealth</p>
        <h1 class="text-xl sm:text-2xl lg:text-3xl font-bold leading-tight mb-4">
          What happens to everything when I'm gone?
        </h1>
        <p class="text-sm sm:text-sm text-white/80 max-w-2xl mb-8 leading-relaxed">
          You've worked hard, saved well, and built a comfortable life. Now the question shifts
          to legacy — estate planning UK focuses on making sure your family is provided for and that Inheritance Tax mitigation
          keeps more of your wealth where it belongs. Fynla helps you plan with clarity.
        </p>
        <router-link
          to="/register"
          class="inline-block px-8 py-3 bg-white text-savannah-600 font-semibold rounded-lg hover:bg-white/90 transition-colors"
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
            <span class="mt-1 flex-shrink-0 w-6 h-6 rounded-full bg-savannah-100 flex items-center justify-center">
              <svg class="w-4 h-4 text-savannah-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
            </span>
            <p class="text-neutral-500">You're already retired or about to be</p>
          </div>
          <div class="flex items-start gap-3">
            <span class="mt-1 flex-shrink-0 w-6 h-6 rounded-full bg-savannah-100 flex items-center justify-center">
              <svg class="w-4 h-4 text-savannah-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
            </span>
            <p class="text-neutral-500">You're concerned about Inheritance Tax on your estate</p>
          </div>
          <div class="flex items-start gap-3">
            <span class="mt-1 flex-shrink-0 w-6 h-6 rounded-full bg-savannah-100 flex items-center justify-center">
              <svg class="w-4 h-4 text-savannah-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
            </span>
            <p class="text-neutral-500">Family wealth protection is a priority — you want to make sure your loved ones are provided for</p>
          </div>
          <div class="flex items-start gap-3">
            <span class="mt-1 flex-shrink-0 w-6 h-6 rounded-full bg-savannah-100 flex items-center justify-center">
              <svg class="w-4 h-4 text-savannah-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
            </span>
            <p class="text-neutral-500">You're thinking about gifting or trust strategies</p>
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
            <div class="w-10 h-10 rounded-full bg-savannah-100 flex items-center justify-center mb-4">
              <svg class="w-5 h-5 text-savannah-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" /></svg>
            </div>
            <h3 class="font-semibold text-horizon-500 mb-2">Estate valuation and Inheritance Tax calculator</h3>
            <p class="text-sm text-neutral-500">See your total estate value, available nil-rate bands, and exactly how much Inheritance Tax your family would face today.</p>
          </div>
          <div class="bg-white rounded-lg p-6 shadow-sm border border-light-gray">
            <div class="w-10 h-10 rounded-full bg-savannah-100 flex items-center justify-center mb-4">
              <svg class="w-5 h-5 text-savannah-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v13m0-13V6a2 2 0 112 2h-2zm0 0V5.5A2.5 2.5 0 109.5 8H12zm-7 4h14M5 12a2 2 0 110-4h14a2 2 0 110 4M5 12v7a2 2 0 002 2h10a2 2 0 002-2v-7" /></svg>
            </div>
            <h3 class="font-semibold text-horizon-500 mb-2">Gifting strategy modeller</h3>
            <p class="text-sm text-neutral-500">Model how regular gifts, annual exemptions, and potentially exempt transfers reduce your Inheritance Tax liability over time.</p>
          </div>
          <div class="bg-white rounded-lg p-6 shadow-sm border border-light-gray">
            <div class="w-10 h-10 rounded-full bg-savannah-100 flex items-center justify-center mb-4">
              <svg class="w-5 h-5 text-savannah-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
            </div>
            <h3 class="font-semibold text-horizon-500 mb-2">Pension drawdown tracking</h3>
            <p class="text-sm text-neutral-500">Monitor your drawdown rate against your pot size for effective retirement income management, and see how long your pension will last under different scenarios.</p>
          </div>
          <div class="bg-white rounded-lg p-6 shadow-sm border border-light-gray">
            <div class="w-10 h-10 rounded-full bg-savannah-100 flex items-center justify-center mb-4">
              <svg class="w-5 h-5 text-savannah-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>
            </div>
            <h3 class="font-semibold text-horizon-500 mb-2">In Case of Emergency letters for your family</h3>
            <p class="text-sm text-neutral-500">A clear, comprehensive guide to your finances for your loved ones — policies, accounts, contacts, and wishes, all in one document.</p>
          </div>
          <div class="bg-white rounded-lg p-6 shadow-sm border border-light-gray">
            <div class="w-10 h-10 rounded-full bg-savannah-100 flex items-center justify-center mb-4">
              <svg class="w-5 h-5 text-savannah-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4" /></svg>
            </div>
            <h3 class="font-semibold text-horizon-500 mb-2">Will and Power of Attorney guidance</h3>
            <p class="text-sm text-neutral-500">Understand what you need in place and track whether your Will, Lasting Power of Attorney, and other essential documents are up to date.</p>
          </div>
        </div>
      </div>
    </section>

    <!-- Features as answers -->
    <section class="py-10 bg-white">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <h2 class="text-base sm:text-lg font-bold text-horizon-500 mb-3">Features that answer your questions</h2>
        <p class="text-neutral-500 mb-8 max-w-2xl">Legacy planning can feel daunting. These features make it concrete and actionable.</p>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
          <router-link to="/features/iht-planning" class="block bg-eggshell-500 rounded-lg p-6 border border-light-gray hover:border-savannah-300 hover:-translate-y-0.5 transition-all">
            <h3 class="font-semibold text-horizon-500 mb-2">Inheritance Tax planning</h3>
            <p class="text-sm text-neutral-500">See your full Inheritance Tax exposure, available reliefs, and strategies to reduce the liability — including the 2027 pension changes.</p>
          </router-link>
          <router-link to="/features/ice-letters" class="block bg-eggshell-500 rounded-lg p-6 border border-light-gray hover:border-savannah-300 hover:-translate-y-0.5 transition-all">
            <h3 class="font-semibold text-horizon-500 mb-2">In Case of Emergency letters</h3>
            <p class="text-sm text-neutral-500">The most important document you'll ever create — a complete guide to your finances for your family.</p>
          </router-link>
          <router-link to="/features/net-worth-dashboard" class="block bg-eggshell-500 rounded-lg p-6 border border-light-gray hover:border-savannah-300 hover:-translate-y-0.5 transition-all">
            <h3 class="font-semibold text-horizon-500 mb-2">Estate dashboard</h3>
            <p class="text-sm text-neutral-500">Your complete estate at a glance — property, investments, pensions, and policies with total valuation.</p>
          </router-link>
          <router-link to="/features/iht-planning" class="block bg-eggshell-500 rounded-lg p-6 border border-light-gray hover:border-savannah-300 hover:-translate-y-0.5 transition-all">
            <h3 class="font-semibold text-horizon-500 mb-2">Gifting strategies</h3>
            <p class="text-sm text-neutral-500">Model how gifting reduces your Inheritance Tax bill and track the seven-year taper relief on potentially exempt transfers.</p>
          </router-link>
          <router-link to="/features/pension-tracker" class="block bg-eggshell-500 rounded-lg p-6 border border-light-gray hover:border-savannah-300 hover:-translate-y-0.5 transition-all">
            <h3 class="font-semibold text-horizon-500 mb-2">Drawdown management</h3>
            <p class="text-sm text-neutral-500">Track your pension drawdown rate and see how long your pot will last under different withdrawal scenarios.</p>
          </router-link>
        </div>
      </div>
    </section>

    <!-- Try a demo persona -->
    <section class="py-10 bg-eggshell-500">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
        <h2 class="text-base sm:text-lg font-bold text-horizon-500 mb-4">See it in action</h2>
        <p class="text-neutral-500 mb-8">Explore Fynla through real scenarios — no sign-up required.</p>
        <div class="flex flex-col sm:flex-row gap-6 justify-center">
          <div class="bg-white rounded-lg p-5 shadow-sm border border-light-gray max-w-sm">
            <p class="text-sm font-semibold text-horizon-500 mb-2">Patricia &amp; Harold Bennett</p>
            <p class="text-sm text-neutral-500 mb-6">Retired couple with property, pensions in drawdown, and an estate exposed to Inheritance Tax.</p>
            <a
              href="/?demo=true"
              class="inline-block px-8 py-3 bg-spring-500 text-white font-semibold rounded-lg hover:bg-spring-600 transition-colors"
            >
              Try Patricia &amp; Harold's situation
            </a>
          </div>
          <div class="bg-white rounded-lg p-5 shadow-sm border border-light-gray max-w-sm">
            <p class="text-sm font-semibold text-horizon-500 mb-2">Margaret Thompson</p>
            <p class="text-sm text-neutral-500 mb-6">Widowed, managing her late husband's estate, significant Inheritance Tax exposure, focused on protecting her family's inheritance.</p>
            <a
              href="/?demo=true"
              class="inline-block px-8 py-3 bg-spring-500 text-white font-semibold rounded-lg hover:bg-spring-600 transition-colors"
            >
              Try Margaret Thompson's situation
            </a>
          </div>
        </div>
      </div>
    </section>

    <!-- Related content -->
    <section class="py-10 bg-white">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <h3 class="text-sm font-semibold text-horizon-500 mb-4">Related</h3>
        <div class="flex flex-wrap gap-4">
          <router-link to="/features/iht-planning" class="text-sm text-savannah-600 hover:text-savannah-700 underline underline-offset-2">Inheritance Tax planning</router-link>
          <router-link to="/features/ice-letters" class="text-sm text-savannah-600 hover:text-savannah-700 underline underline-offset-2">In Case of Emergency letters</router-link>
          <router-link to="/features/pension-tracker" class="text-sm text-savannah-600 hover:text-savannah-700 underline underline-offset-2">Pension tracker</router-link>
        </div>
      </div>
    </section>

    <!-- Stage navigation -->
    <section class="py-8 bg-eggshell-500 border-t border-light-gray">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 flex justify-between items-center">
        <router-link to="/stage/planning-your-future" class="text-sm font-medium text-savannah-600 hover:text-savannah-700 flex items-center gap-1">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" /></svg>
          Previous: Planning Your Future
        </router-link>
        <span class="text-sm text-neutral-400">This is the final stage</span>
      </div>
    </section>
  </PublicLayout>
</template>

<script>
import PublicLayout from '@/layouts/PublicLayout.vue';

export default {
  name: 'EnjoyingYourWealthPage',
  components: { PublicLayout },
  mounted() {
    document.title = 'Estate Planning & Inheritance Tax Planning UK | Fynla';
    const meta = document.querySelector('meta[name="description"]');
    if (meta) meta.setAttribute('content', 'Know your Inheritance Tax exposure and plan to reduce it. Fynla models gifting strategies, tracks pension drawdown, and generates ICE letters for your family.');
  },
};
</script>
