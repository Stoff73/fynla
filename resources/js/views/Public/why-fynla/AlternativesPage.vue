<template>
  <PublicLayout>
    <!-- Hero -->
    <div class="relative flex items-center bg-gradient-to-r from-horizon-500 to-raspberry-500 overflow-hidden">
      <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10 text-left w-full">
        <h1 class="text-4xl md:text-6xl font-black text-white mb-4">
          How Fynla Compares
        </h1>
        <p class="text-base sm:text-lg text-white/80 max-w-2xl mx-auto">
          An honest look at how Fynla stacks up against the alternatives. We'll tell you where we're strong and where others might be a better fit.
        </p>
      </div>
    </div>

    <!-- Comparison Cards -->
    <section class="py-14 bg-eggshell-500">
      <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
          <!-- vs Spreadsheets -->
          <div class="bg-white rounded-lg p-6 shadow-sm border border-light-gray">
            <div class="flex items-center gap-3 mb-4">
              <div class="w-9 h-9 rounded-full bg-horizon-100 flex items-center justify-center flex-shrink-0">
                <svg class="w-4.5 h-4.5 text-horizon-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M3 14h18m-9-4v8m-7 0h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z" /></svg>
              </div>
              <h3 class="font-bold text-horizon-500">Fynla vs Spreadsheets</h3>
            </div>
            <p class="text-sm text-neutral-500 leading-relaxed">
              Spreadsheets are free and flexible, but they don't calculate Inheritance Tax, run Monte Carlo simulations, or track pension allowances. Fynla automates the hard parts so you can focus on decisions, not formulas.
            </p>
          </div>

          <!-- vs Budgeting Apps -->
          <div class="bg-white rounded-lg p-6 shadow-sm border border-light-gray">
            <div class="flex items-center gap-3 mb-4">
              <div class="w-9 h-9 rounded-full bg-spring-100 flex items-center justify-center flex-shrink-0">
                <svg class="w-4.5 h-4.5 text-spring-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
              </div>
              <h3 class="font-bold text-horizon-500">Fynla vs Budgeting Apps</h3>
            </div>
            <p class="text-sm text-neutral-500 leading-relaxed">
              Budgeting apps like Emma and Plum track where your money went. Fynla plans where it's going. They're different tools for different jobs — you might want both.
            </p>
          </div>

          <!-- vs an IFA -->
          <div class="bg-white rounded-lg p-6 shadow-sm border border-light-gray">
            <div class="flex items-center gap-3 mb-4">
              <div class="w-9 h-9 rounded-full bg-violet-100 flex items-center justify-center flex-shrink-0">
                <svg class="w-4.5 h-4.5 text-violet-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" /></svg>
              </div>
              <h3 class="font-bold text-horizon-500">Fynla vs an Independent Financial Adviser</h3>
            </div>
            <p class="text-sm text-neutral-500 leading-relaxed">
              An Independent Financial Adviser provides regulated personalised advice and can buy products on your behalf. Fynla provides the planning and analysis at a fraction of the cost. For complex situations — trusts, business assets, tax planning — an adviser plus Fynla is the best combination.
            </p>
          </div>

          <!-- vs ProjectionLab / Voyant -->
          <div class="bg-white rounded-lg p-6 shadow-sm border border-light-gray">
            <div class="flex items-center gap-3 mb-4">
              <div class="w-9 h-9 rounded-full bg-raspberry-100 flex items-center justify-center flex-shrink-0">
                <svg class="w-4.5 h-4.5 text-raspberry-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" /></svg>
              </div>
              <h3 class="font-bold text-horizon-500">Fynla vs ProjectionLab / Voyant</h3>
            </div>
            <p class="text-sm text-neutral-500 leading-relaxed">
              ProjectionLab is excellent for advanced modelling if you're comfortable with complexity. Voyant is industry-grade software used by advisers. Fynla is built for people who want the same depth without the learning curve — and with UK-specific tax rules baked in.
            </p>
          </div>
        </div>
      </div>
    </section>

    <!-- CTA -->
    <section class="py-14 bg-white">
      <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10 text-left w-full">
        <h2 class="text-2xl font-bold text-horizon-500 mb-3">Try the free demo</h2>
        <p class="text-sm text-neutral-500 mb-6 max-w-lg mx-auto">
          See how Fynla handles your finances with sample data. No sign-up, no credit card.
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
  name: 'AlternativesPage',

  components: {
    PublicLayout,
  },

  mounted() {
    document.title = 'Fynla vs Alternatives — How We Compare | Fynla';
    const meta = document.querySelector('meta[name="description"]');
    if (meta) {
      meta.setAttribute('content', 'How does Fynla compare to spreadsheets, budgeting apps, Independent Financial Advisers, and other financial planning tools? An honest comparison.');
    }
  },
};
</script>
