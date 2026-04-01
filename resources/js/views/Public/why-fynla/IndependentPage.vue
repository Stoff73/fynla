<template>
  <PublicLayout>
    <!-- Hero -->
    <div class="relative flex items-center bg-gradient-to-r from-horizon-500 to-raspberry-500 overflow-hidden">
      <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10 text-left w-full">
        <h1 class="text-4xl md:text-6xl font-black text-white mb-4">
          Your financial companion<br/>
          <span class="text-raspberry-300">for life</span>
        </h1>
        <p class="text-xl text-white/90 font-semibold mb-2">Not tied to an adviser. Not trying to replace one.</p>
        <p class="text-lg text-white/70">
          Fynla gives you the planning tools that used to be locked behind expensive adviser fees. Use them on your own, or alongside your adviser.
        </p>
      </div>
    </div>

    <!-- What an adviser does vs what Fynla does -->
    <section class="py-14 bg-light-pink-100">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <h2 class="text-xl sm:text-2xl md:text-3xl lg:text-4xl font-bold text-horizon-500 mb-6 text-center">What an adviser does vs what Fynla does</h2>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
          <div class="bg-white rounded-xl p-6">
            <h3 class="text-base font-bold text-horizon-500 mb-3">What an adviser provides</h3>
            <ul class="space-y-2">
              <li class="flex items-start gap-2 text-sm text-neutral-500">
                <svg class="w-4 h-4 text-horizon-400 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                Personalised, regulated recommendations
              </li>
              <li class="flex items-start gap-2 text-sm text-neutral-500">
                <svg class="w-4 h-4 text-horizon-400 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                Product execution and switching
              </li>
              <li class="flex items-start gap-2 text-sm text-neutral-500">
                <svg class="w-4 h-4 text-horizon-400 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                Financial Conduct Authority regulatory protection
              </li>
            </ul>
          </div>
          <div class="bg-white rounded-xl p-6">
            <h3 class="text-base font-bold text-raspberry-500 mb-3">What Fynla provides</h3>
            <ul class="space-y-2">
              <li class="flex items-start gap-2 text-sm text-neutral-500">
                <svg class="w-4 h-4 text-raspberry-400 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                Comprehensive financial planning and projections
              </li>
              <li class="flex items-start gap-2 text-sm text-neutral-500">
                <svg class="w-4 h-4 text-raspberry-400 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                Analysis and scenario modelling
              </li>
              <li class="flex items-start gap-2 text-sm text-neutral-500">
                <svg class="w-4 h-4 text-raspberry-400 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                The part most people actually need
              </li>
            </ul>
          </div>
        </div>
      </div>
    </section>

    <!-- Works with your adviser too -->
    <section class="py-14 bg-eggshell-500">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <h2 class="text-xl sm:text-2xl md:text-3xl lg:text-4xl font-bold text-horizon-500 mb-3 text-center">Works with your adviser too</h2>
        <p class="text-sm text-neutral-500 leading-relaxed text-center">
          If you do use an adviser, Fynla makes those conversations more productive. You arrive with your full financial picture already mapped out — pensions, property, savings, investments, protection, and estate — so your adviser can focus on the advice rather than fact-finding.
        </p>
      </div>
    </section>

    <!-- Independent Financial Adviser vs Fyn -->
    <section class="py-14 bg-horizon-500">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
        <h2 class="text-xl sm:text-2xl md:text-3xl lg:text-4xl font-bold text-white mb-8">Independent adviser vs Fynla</h2>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-6 max-w-3xl mx-auto">
          <div class="bg-white/10 rounded-xl p-8">
            <p class="text-lg sm:text-xl font-bold text-white mb-3">Independent Financial Adviser</p>
            <p class="text-3xl sm:text-4xl font-black text-white mb-2">&pound;1k &ndash; &pound;3k</p>
            <p class="text-sm text-white/60">One-off review, repeated annually</p>
          </div>
          <div class="bg-white/10 rounded-xl p-8 border-[3px] border-raspberry-500">
            <p class="text-lg sm:text-xl font-bold text-raspberry-300 mb-3">Fynla</p>
            <p class="text-3xl sm:text-4xl font-black text-white mb-2">&pound;8.50 &ndash; &pound;20<span class="text-lg">/mo</span></p>
            <p class="text-sm text-white/60">Same depth of analysis, updated continuously</p>
          </div>
        </div>
      </div>
    </section>

    <!-- Try it yourself -->
    <section class="py-14 bg-light-pink-100">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
        <h2 class="text-xl sm:text-2xl md:text-3xl lg:text-4xl font-bold text-horizon-500 mb-3">Try it yourself</h2>
        <p class="text-sm text-neutral-500 mb-6">
          See the full platform with sample data. No sign-up, no credit card.
        </p>
        <a
          href="#" @click.prevent="$router.push({ query: { demo: 'true' } })"
          class="inline-block px-8 py-3 bg-raspberry-500 text-white text-sm font-semibold rounded-lg hover:bg-raspberry-600 transition-colors"
        >
          Try the free demo
        </a>
      </div>
    </section>
  </PublicLayout>
</template>

<script>
import PublicLayout from '@/layouts/PublicLayout.vue';

export default {
  name: 'IndependentPage',

  components: {
    PublicLayout,
  },

  mounted() {
    document.title = 'Your financial companion for life | Fynla';
    const meta = document.querySelector('meta[name="description"]');
    if (meta) {
      meta.setAttribute('content', 'You don\'t need to pay an Independent Financial Adviser thousands to understand your finances. Fynla gives you the same planning tools at a fraction of the cost.');
    }
  },
};
</script>
