<template>
  <PublicLayout>
    <!-- Hero -->
    <div class="relative flex items-center bg-gradient-to-r from-horizon-500 to-raspberry-500 overflow-hidden">
      <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10 text-left w-full">
        <h1 class="text-4xl md:text-6xl font-black text-white mb-4">
          Financial Planning That Puts You in Control
        </h1>
        <p class="text-base sm:text-lg text-white/80 max-w-2xl mx-auto">
          No jargon. No sales calls. No commission. Just a clear view of your finances and the tools to plan your future.
        </p>
      </div>
    </div>

    <!-- Built for Real People -->
    <section class="py-14 bg-white">
      <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex items-start gap-4 mb-3">
          <div class="w-10 h-10 rounded-full bg-horizon-100 flex items-center justify-center flex-shrink-0">
            <svg class="w-5 h-5 text-horizon-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z" /></svg>
          </div>
          <div>
            <h2 class="text-2xl font-bold text-horizon-500 mb-2">Built for Real People</h2>
            <p class="text-sm text-neutral-500 leading-relaxed">
              Fynla isn't for traders or finance professionals. It's for people who want to understand their money, plan properly, and make informed decisions without paying thousands for an adviser.
            </p>
          </div>
        </div>
      </div>
    </section>

    <!-- Your Data, Your Control -->
    <section class="py-14 bg-eggshell-500">
      <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex items-start gap-4 mb-3">
          <div class="w-10 h-10 rounded-full bg-spring-100 flex items-center justify-center flex-shrink-0">
            <svg class="w-5 h-5 text-spring-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" /></svg>
          </div>
          <div>
            <h2 class="text-2xl font-bold text-horizon-500 mb-2">Your Data, Your Control</h2>
            <p class="text-sm text-neutral-500 leading-relaxed">
              You enter your data manually. No bank connections required. No third-party sharing. You decide what goes in and who sees it.
            </p>
          </div>
        </div>
      </div>
    </section>

    <!-- Planning, Not Products -->
    <section class="py-14 bg-white">
      <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex items-start gap-4 mb-3">
          <div class="w-10 h-10 rounded-full bg-violet-100 flex items-center justify-center flex-shrink-0">
            <svg class="w-5 h-5 text-violet-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>
          </div>
          <div>
            <h2 class="text-2xl font-bold text-horizon-500 mb-2">Planning, Not Products</h2>
            <p class="text-sm text-neutral-500 leading-relaxed">
              We don't sell financial products, earn commission, or recommend providers. We give you the numbers so you can make your own decisions.
            </p>
          </div>
        </div>
      </div>
    </section>

    <!-- Works at Every Stage -->
    <section class="py-14 bg-eggshell-500">
      <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex items-start gap-4 mb-3">
          <div class="w-10 h-10 rounded-full bg-raspberry-100 flex items-center justify-center flex-shrink-0">
            <svg class="w-5 h-5 text-raspberry-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" /></svg>
          </div>
          <div>
            <h2 class="text-2xl font-bold text-horizon-500 mb-2">Works at Every Stage</h2>
            <p class="text-sm text-neutral-500 leading-relaxed">
              Whether you're starting out with your first savings account or planning your estate in retirement, Fynla grows with you. One platform, every stage of life.
            </p>
          </div>
        </div>
      </div>
    </section>

    <!-- CTA -->
    <section class="py-14 bg-white">
      <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10 text-left w-full">
        <h2 class="text-2xl font-bold text-horizon-500 mb-3">See it in action</h2>
        <p class="text-sm text-neutral-500 mb-6 max-w-lg mx-auto">
          Explore the full platform with sample data. No sign-up required.
        </p>
        <router-link
          to="/?demo=true"
          class="inline-block px-8 py-3 bg-raspberry-500 text-white text-sm font-semibold rounded-lg hover:bg-raspberry-600 transition-colors"
        >
          Try the Free Demo
        </router-link>
      </div>
    </section>
  </PublicLayout>
</template>

<script>
import PublicLayout from '@/layouts/PublicLayout.vue';

export default {
  name: 'OurApproachPage',

  components: {
    PublicLayout,
  },

  mounted() {
    document.title = 'Our Approach to Financial Planning | Fynla';
    const meta = document.querySelector('meta[name="description"]');
    if (meta) {
      meta.setAttribute('content', 'Fynla puts you in control of your financial plan. No jargon, no sales, no commission. See your complete picture and plan your future with confidence.');
    }
  },
};
</script>
