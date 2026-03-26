<template>
  <PublicLayout>
    <!-- Hero -->
    <section class="py-10 sm:py-12 text-white" style="background: linear-gradient(135deg, #378ADD 0%, #5AADEE 100%);">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <p class="text-sm font-semibold uppercase tracking-wider text-white/70 mb-3">Stage 3 of 5 — Protecting and Growing</p>
        <h1 class="text-xl sm:text-2xl lg:text-3xl font-bold leading-tight mb-4">
          Are we protected — and are we moving forward?
        </h1>
        <p class="text-sm sm:text-sm text-white/80 max-w-2xl mb-8 leading-relaxed">
          You've got a mortgage, dependants, and growing responsibilities. A family financial protection assessment
          is essential at this stage. Fynla brings it all together so you can protect what you've built while making sure your money is still working for you.
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
            <p class="text-neutral-500">You have a mortgage and dependants</p>
          </div>
          <div class="flex items-start gap-3">
            <span class="mt-1 flex-shrink-0 w-6 h-6 rounded-full bg-violet-100 flex items-center justify-center">
              <svg class="w-4 h-4 text-violet-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
            </span>
            <p class="text-neutral-500">You're not sure if your life insurance is enough — you need a life insurance gap analysis</p>
          </div>
          <div class="flex items-start gap-3">
            <span class="mt-1 flex-shrink-0 w-6 h-6 rounded-full bg-violet-100 flex items-center justify-center">
              <svg class="w-4 h-4 text-violet-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
            </span>
            <p class="text-neutral-500">You've got pensions from multiple employers</p>
          </div>
          <div class="flex items-start gap-3">
            <span class="mt-1 flex-shrink-0 w-6 h-6 rounded-full bg-violet-100 flex items-center justify-center">
              <svg class="w-4 h-4 text-violet-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
            </span>
            <p class="text-neutral-500">You want to grow your investments but aren't sure how much risk to take</p>
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
              <svg class="w-5 h-5 text-violet-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" /></svg>
            </div>
            <h3 class="font-semibold text-horizon-500 mb-2">Protection gap analysis</h3>
            <p class="text-sm text-neutral-500">See exactly where your life insurance, critical illness, and income protection fall short — and by how much.</p>
          </div>
          <div class="bg-white rounded-lg p-6 shadow-sm border border-light-gray">
            <div class="w-10 h-10 rounded-full bg-violet-100 flex items-center justify-center mb-4">
              <svg class="w-5 h-5 text-violet-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" /></svg>
            </div>
            <h3 class="font-semibold text-horizon-500 mb-2">Net worth with property equity</h3>
            <p class="text-sm text-neutral-500">Your home is likely your biggest asset. See your true equity and how it fits your total net worth.</p>
          </div>
          <div class="bg-white rounded-lg p-6 shadow-sm border border-light-gray">
            <div class="w-10 h-10 rounded-full bg-violet-100 flex items-center justify-center mb-4">
              <svg class="w-5 h-5 text-violet-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
            </div>
            <h3 class="font-semibold text-horizon-500 mb-2">Multiple pension consolidation view</h3>
            <p class="text-sm text-neutral-500">See all your Defined Contribution pensions from different employers in one place, with combined projections.</p>
          </div>
          <div class="bg-white rounded-lg p-6 shadow-sm border border-light-gray">
            <div class="w-10 h-10 rounded-full bg-violet-100 flex items-center justify-center mb-4">
              <svg class="w-5 h-5 text-violet-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" /></svg>
            </div>
            <h3 class="font-semibold text-horizon-500 mb-2">Investment portfolio tracking</h3>
            <p class="text-sm text-neutral-500">Monitor your investments, understand your asset allocation, and see how your risk profile matches your goals.</p>
          </div>
          <div class="bg-white rounded-lg p-6 shadow-sm border border-light-gray">
            <div class="w-10 h-10 rounded-full bg-violet-100 flex items-center justify-center mb-4">
              <svg class="w-5 h-5 text-violet-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z" /></svg>
            </div>
            <h3 class="font-semibold text-horizon-500 mb-2">Joint financial planning for couples</h3>
            <p class="text-sm text-neutral-500">See both partners' finances side by side — joint assets, individual pensions, shared liabilities, complete picture.</p>
          </div>
        </div>
      </div>
    </section>

    <!-- Features as answers -->
    <section class="py-10 bg-white">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <h2 class="text-base sm:text-lg font-bold text-horizon-500 mb-3">Features that answer your questions</h2>
        <p class="text-neutral-500 mb-8 max-w-2xl">At this stage, you need both protection and growth. These features address the anxieties that come with that balance.</p>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
          <router-link to="/features/protection-gap" class="block bg-eggshell-500 rounded-lg p-6 border border-light-gray hover:border-violet-300 hover:-translate-y-0.5 transition-all">
            <h3 class="font-semibold text-horizon-500 mb-2">Protection gap analyser</h3>
            <p class="text-sm text-neutral-500">Fynla calculates exactly how much cover you need based on your mortgage, income, and family situation.</p>
          </router-link>
          <router-link to="/features/net-worth-dashboard" class="block bg-eggshell-500 rounded-lg p-6 border border-light-gray hover:border-violet-300 hover:-translate-y-0.5 transition-all">
            <h3 class="font-semibold text-horizon-500 mb-2">Net worth dashboard</h3>
            <p class="text-sm text-neutral-500">Track your complete financial position including property equity, pensions, investments, and debts.</p>
          </router-link>
          <router-link to="/features/pension-tracker" class="block bg-eggshell-500 rounded-lg p-6 border border-light-gray hover:border-violet-300 hover:-translate-y-0.5 transition-all">
            <h3 class="font-semibold text-horizon-500 mb-2">Pension tracker</h3>
            <p class="text-sm text-neutral-500">Consolidate the view of multiple workplace pensions and see what they add up to at retirement.</p>
          </router-link>
          <router-link to="/features/net-worth-dashboard" class="block bg-eggshell-500 rounded-lg p-6 border border-light-gray hover:border-violet-300 hover:-translate-y-0.5 transition-all">
            <h3 class="font-semibold text-horizon-500 mb-2">Investment analysis</h3>
            <p class="text-sm text-neutral-500">Understand your portfolio allocation, risk profile, and whether your investments match your goals.</p>
          </router-link>
          <router-link to="/features/ice-letters" class="block bg-eggshell-500 rounded-lg p-6 border border-light-gray hover:border-violet-300 hover:-translate-y-0.5 transition-all">
            <h3 class="font-semibold text-horizon-500 mb-2">In Case of Emergency letters</h3>
            <p class="text-sm text-neutral-500">Give your family a clear guide to your finances if the worst happens — policies, accounts, contacts, all in one place.</p>
          </router-link>
        </div>
      </div>
    </section>

    <!-- Moments -->
    <section class="py-10 bg-white">
      <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
        <h2 class="text-base font-bold text-horizon-500 mb-4">Moments where Fynla makes a difference</h2>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div class="bg-eggshell-500 rounded-lg border border-light-gray p-5">
            <p class="text-sm font-semibold text-horizon-500 mb-1">The Document Your Family Needs</p>
            <p class="text-xs text-neutral-500 leading-relaxed">If something happened to you tonight, would your partner know where the pensions are? The mortgage account number? The life insurance provider? Fynla's ICE letter puts everything in one document — auto-populated from your plan.</p>
          </div>
          <div class="bg-eggshell-500 rounded-lg border border-light-gray p-5">
            <p class="text-sm font-semibold text-horizon-500 mb-1">The Cover You Didn't Know You Were Missing</p>
            <p class="text-xs text-neutral-500 leading-relaxed">Your workplace life insurance pays 2x salary. Your mortgage is 6x salary. Fynla calculates what your family would actually need and shows you the gap — across life insurance, income protection, and critical illness.</p>
          </div>
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
            <p class="text-sm font-semibold text-horizon-500 mb-2">James &amp; Emily Carter</p>
            <p class="text-sm text-neutral-500 mb-6">Young family with a joint mortgage, workplace pensions, and two children to protect.</p>
            <a
              href="/?demo=true"
              class="inline-block px-8 py-3 bg-spring-500 text-white font-semibold rounded-lg hover:bg-spring-600 transition-colors"
            >
              Try James &amp; Emily's situation
            </a>
          </div>
          <div class="bg-white rounded-lg p-5 shadow-sm border border-light-gray max-w-sm">
            <p class="text-sm font-semibold text-horizon-500 mb-2">Alex Chen</p>
            <p class="text-sm text-neutral-500 mb-6">Entrepreneur with a Self-Invested Personal Pension, business interests, and investment portfolio to manage.</p>
            <a
              href="/?demo=true"
              class="inline-block px-8 py-3 bg-spring-500 text-white font-semibold rounded-lg hover:bg-spring-600 transition-colors"
            >
              Try Alex Chen's situation
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
          <router-link to="/features/protection-gap" class="text-sm text-violet-600 hover:text-violet-700 underline underline-offset-2">Protection gap analyser</router-link>
          <router-link to="/features/ice-letters" class="text-sm text-violet-600 hover:text-violet-700 underline underline-offset-2">In Case of Emergency letters</router-link>
          <router-link to="/features/net-worth-dashboard" class="text-sm text-violet-600 hover:text-violet-700 underline underline-offset-2">Net worth dashboard</router-link>
          <router-link to="/features/pension-tracker" class="text-sm text-violet-600 hover:text-violet-700 underline underline-offset-2">Pension tracker</router-link>
        </div>
      </div>
    </section>

    <!-- Stage navigation -->
    <section class="py-8 bg-eggshell-500 border-t border-light-gray">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 flex justify-between items-center">
        <router-link to="/stage/building-foundations" class="text-sm font-medium text-violet-600 hover:text-violet-700 flex items-center gap-1">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" /></svg>
          Previous: Building Foundations
        </router-link>
        <router-link to="/stage/planning-your-future" class="text-sm font-medium text-violet-600 hover:text-violet-700 flex items-center gap-1">
          Next: Planning Your Future
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" /></svg>
        </router-link>
      </div>
    </section>
  </PublicLayout>
</template>

<script>
import PublicLayout from '@/layouts/PublicLayout.vue';

export default {
  name: 'ProtectingAndGrowingPage',
  components: { PublicLayout },
  mounted() {
    document.title = 'Family Financial Protection & Investment Planning UK | Fynla';
    const meta = document.querySelector('meta[name="description"]');
    if (meta) meta.setAttribute('content', 'Are your family and finances properly protected? Fynla analyses your life insurance gap, tracks investment portfolios, consolidates pension views, and supports joint financial planning for couples.');
  },
};
</script>
