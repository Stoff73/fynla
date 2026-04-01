<template>
  <PublicLayout>
    <!-- Hero -->
    <div class="relative flex items-center bg-gradient-to-r from-horizon-500 to-raspberry-500 overflow-hidden">
      <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10 text-left w-full">
        <h1 class="text-4xl md:text-6xl font-black text-white mb-4">
          Best Financial<br/>
          <span class="text-raspberry-300">Planning Tools</span>
          UK 2025
        </h1>
        <p class="text-lg text-white/70">
          An honest comparison of the leading options &mdash; features, pricing, and who each is best for.
        </p>
      </div>
    </div>

    <!-- Content -->
    <section class="py-10 bg-eggshell-500">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-10">

        <!-- Overview -->
        <div class="bg-light-blue-100 rounded-xl p-8">
          <h2 class="text-xl sm:text-2xl md:text-3xl font-bold text-horizon-500 mb-4">How we compared</h2>
          <p class="text-sm text-neutral-600 leading-relaxed">
            We looked at the most popular financial planning tools available to UK individuals in 2025. Each has
            genuine strengths. We have tried to be honest about where Fynla fits &mdash; and where others may be
            a better choice.
          </p>
        </div>

        <!-- Comparison Table -->
        <div>
          <h2 class="text-xl sm:text-2xl md:text-3xl font-bold text-horizon-500 mb-4">Feature comparison</h2>
          <div class="bg-white rounded-lg border border-light-gray overflow-x-auto">
            <table class="w-full text-sm min-w-[600px]">
              <thead>
                <tr class="bg-savannah-50">
                  <th class="text-left px-3 py-2.5 text-xs font-semibold text-horizon-500">Feature</th>
                  <th class="text-center px-3 py-2.5 text-xs font-semibold text-horizon-500">Fynla</th>
                  <th class="text-center px-3 py-2.5 text-xs font-semibold text-horizon-500">Planning Platform</th>
                  <th class="text-center px-3 py-2.5 text-xs font-semibold text-horizon-500">Investment Platform</th>
                  <th class="text-center px-3 py-2.5 text-xs font-semibold text-horizon-500">Centralisation Platform</th>
                  <th class="text-center px-3 py-2.5 text-xs font-semibold text-horizon-500">Spreadsheets</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-light-gray text-xs">
                <tr>
                  <td class="px-3 py-2 text-neutral-500">UK tax rules</td>
                  <td class="px-3 py-2 text-center text-spring-500 font-semibold">Full</td>
                  <td class="px-3 py-2 text-center text-neutral-400">Partial</td>
                  <td class="px-3 py-2 text-center text-spring-500 font-semibold">Full</td>
                  <td class="px-3 py-2 text-center text-neutral-400">No</td>
                  <td class="px-3 py-2 text-center text-neutral-400">Manual</td>
                </tr>
                <tr>
                  <td class="px-3 py-2 text-neutral-500">Inheritance Tax</td>
                  <td class="px-3 py-2 text-center text-spring-500 font-semibold">Yes</td>
                  <td class="px-3 py-2 text-center text-neutral-400">No</td>
                  <td class="px-3 py-2 text-center text-spring-500 font-semibold">Yes</td>
                  <td class="px-3 py-2 text-center text-neutral-400">No</td>
                  <td class="px-3 py-2 text-center text-neutral-400">Manual</td>
                </tr>
                <tr>
                  <td class="px-3 py-2 text-neutral-500">Protection analysis</td>
                  <td class="px-3 py-2 text-center text-spring-500 font-semibold">Yes</td>
                  <td class="px-3 py-2 text-center text-neutral-400">No</td>
                  <td class="px-3 py-2 text-center text-neutral-400">Limited</td>
                  <td class="px-3 py-2 text-center text-neutral-400">No</td>
                  <td class="px-3 py-2 text-center text-neutral-400">No</td>
                </tr>
                <tr>
                  <td class="px-3 py-2 text-neutral-500">Monte Carlo</td>
                  <td class="px-3 py-2 text-center text-spring-500 font-semibold">Yes</td>
                  <td class="px-3 py-2 text-center text-spring-500 font-semibold">Yes</td>
                  <td class="px-3 py-2 text-center text-spring-500 font-semibold">Yes</td>
                  <td class="px-3 py-2 text-center text-neutral-400">No</td>
                  <td class="px-3 py-2 text-center text-neutral-400">No</td>
                </tr>
                <tr>
                  <td class="px-3 py-2 text-neutral-500">Account aggregation</td>
                  <td class="px-3 py-2 text-center text-neutral-400">No</td>
                  <td class="px-3 py-2 text-center text-neutral-400">No</td>
                  <td class="px-3 py-2 text-center text-neutral-400">Limited</td>
                  <td class="px-3 py-2 text-center text-spring-500 font-semibold">Yes</td>
                  <td class="px-3 py-2 text-center text-neutral-400">No</td>
                </tr>
                <tr>
                  <td class="px-3 py-2 text-neutral-500">Spending tracking</td>
                  <td class="px-3 py-2 text-center text-neutral-400">No</td>
                  <td class="px-3 py-2 text-center text-neutral-400">No</td>
                  <td class="px-3 py-2 text-center text-neutral-400">No</td>
                  <td class="px-3 py-2 text-center text-spring-500 font-semibold">Yes</td>
                  <td class="px-3 py-2 text-center text-neutral-400">Manual</td>
                </tr>
                <tr>
                  <td class="px-3 py-2 text-neutral-500">Ease of use</td>
                  <td class="px-3 py-2 text-center text-spring-500 font-semibold">High</td>
                  <td class="px-3 py-2 text-center text-spring-500 font-semibold">High</td>
                  <td class="px-3 py-2 text-center text-neutral-400">Low</td>
                  <td class="px-3 py-2 text-center text-spring-500 font-semibold">High</td>
                  <td class="px-3 py-2 text-center text-neutral-400">Varies</td>
                </tr>
                <tr>
                  <td class="px-3 py-2 text-neutral-500">Self-service</td>
                  <td class="px-3 py-2 text-center text-spring-500 font-semibold">Yes</td>
                  <td class="px-3 py-2 text-center text-spring-500 font-semibold">Yes</td>
                  <td class="px-3 py-2 text-center text-neutral-400">No</td>
                  <td class="px-3 py-2 text-center text-spring-500 font-semibold">Yes</td>
                  <td class="px-3 py-2 text-center text-spring-500 font-semibold">Yes</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <!-- Pricing -->
        <div>
          <h2 class="text-xl sm:text-2xl md:text-3xl font-bold text-horizon-500 mb-4">Pricing</h2>
          <div class="bg-white rounded-lg border border-light-gray overflow-hidden">
            <table class="w-full text-sm">
              <thead>
                <tr class="bg-savannah-50">
                  <th class="text-left px-4 py-2.5 text-xs font-semibold text-horizon-500">Tool</th>
                  <th class="text-right px-4 py-2.5 text-xs font-semibold text-horizon-500">Price</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-light-gray">
                <tr>
                  <td class="px-4 py-2.5 text-neutral-500">Fynla</td>
                  <td class="px-4 py-2.5 text-neutral-500 text-right">&pound;8.50&ndash;&pound;20/month</td>
                </tr>
                <tr>
                  <td class="px-4 py-2.5 text-neutral-500">Planning Platform</td>
                  <td class="px-4 py-2.5 text-neutral-500 text-right">Free tier + paid plans</td>
                </tr>
                <tr>
                  <td class="px-4 py-2.5 text-neutral-500">Investment Platform</td>
                  <td class="px-4 py-2.5 text-neutral-500 text-right">Adviser licence only</td>
                </tr>
                <tr>
                  <td class="px-4 py-2.5 text-neutral-500">Centralisation Platform</td>
                  <td class="px-4 py-2.5 text-neutral-500 text-right">Free + premium plans</td>
                </tr>
                <tr>
                  <td class="px-4 py-2.5 text-neutral-500">Spreadsheets</td>
                  <td class="px-4 py-2.5 text-neutral-500 text-right">Free</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <!-- Individual Summaries -->
        <div>
          <h2 class="text-xl sm:text-2xl md:text-3xl font-bold text-horizon-500 mb-4">Tool-by-tool summary</h2>
          <div class="space-y-3">
            <div class="bg-white rounded-lg border border-light-gray p-5">
              <p class="text-sm font-semibold text-horizon-500 mb-1">Fynla</p>
              <p class="text-xs text-neutral-500">
                The most complete self-service UK financial planning platform. Covers retirement, protection, estate
                planning, investments, and savings with integrated UK tax rules. Best for individuals and couples who
                want professional-grade planning without an adviser.
              </p>
              <router-link to="/pricing" class="text-xs text-raspberry-500 hover:text-raspberry-600 underline mt-2 inline-block">View pricing</router-link>
            </div>
            <div class="bg-white rounded-lg border border-light-gray p-5">
              <p class="text-sm font-semibold text-horizon-500 mb-1">Financial Planning Platform</p>
              <p class="text-xs text-neutral-500">
                Excellent for flexible financial modelling and the FIRE community. Strong free tier. Less depth on UK-specific
                tax, Inheritance Tax, and protection analysis.
              </p>
              <router-link to="/compare/fynla-vs-financial-planning-platform" class="text-xs text-raspberry-500 hover:text-raspberry-600 underline mt-2 inline-block">Full comparison</router-link>
            </div>
            <div class="bg-white rounded-lg border border-light-gray p-5">
              <p class="text-sm font-semibold text-horizon-500 mb-1">Financial Investment Platform</p>
              <p class="text-xs text-neutral-500">
                The industry standard for UK financial advisers. Incredibly deep but designed for professionals, not
                individuals. Requires an adviser licence.
              </p>
              <router-link to="/compare/fynla-vs-financial-investment-platform" class="text-xs text-raspberry-500 hover:text-raspberry-600 underline mt-2 inline-block">Full comparison</router-link>
            </div>
            <div class="bg-white rounded-lg border border-light-gray p-5">
              <p class="text-sm font-semibold text-horizon-500 mb-1">Financial Centralisation Platform</p>
              <p class="text-xs text-neutral-500">
                Best for spending tracking and account aggregation via open banking. Different focus to Fynla &mdash;
                complements rather than competes.
              </p>
              <router-link to="/compare/fynla-vs-financial-centralisation-platform" class="text-xs text-raspberry-500 hover:text-raspberry-600 underline mt-2 inline-block">Full comparison</router-link>
            </div>
            <div class="bg-white rounded-lg border border-light-gray p-5">
              <p class="text-sm font-semibold text-horizon-500 mb-1">Spreadsheets</p>
              <p class="text-xs text-neutral-500">
                Free and endlessly customisable. Great starting point, but limited by formula errors, manual tax updates,
                and the inability to run simulations or protection analysis.
              </p>
              <router-link to="/compare/fynla-vs-spreadsheets" class="text-xs text-raspberry-500 hover:text-raspberry-600 underline mt-2 inline-block">Full comparison</router-link>
            </div>
          </div>
        </div>

        <!-- Related Links -->
        <div class="border-t border-light-gray pt-6 flex flex-wrap items-center gap-x-4 gap-y-1">
          <span class="text-sm font-bold text-horizon-500">Related</span>
          <router-link to="/how-it-works" class="text-xs text-raspberry-500 hover:text-light-pink-400 transition-colors">How Fynla Works</router-link>
          <span class="text-neutral-300">|</span>
          <router-link to="/why-fynla/our-approach" class="text-xs text-raspberry-500 hover:text-light-pink-400 transition-colors">Our Approach</router-link>
          <span class="text-neutral-300">|</span>
          <router-link to="/pricing" class="text-xs text-raspberry-500 hover:text-light-pink-400 transition-colors">Fynla Pricing</router-link>
        </div>
      </div>
    </section>

    <!-- CTA -->
    <section class="py-10 bg-light-pink-100">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="bg-gradient-to-r from-horizon-500 to-raspberry-500 rounded-xl px-6 py-6">
          <h2 class="text-xl sm:text-2xl md:text-3xl lg:text-4xl font-bold text-white mb-2">Ready to see how Fynla compares?</h2>
          <p class="text-lg text-white/70 mb-4">Explore the full platform with our interactive demo &mdash; no sign-up required.</p>
          <a href="#" @click.prevent="$router.push({ query: { demo: 'true' } })" class="inline-block px-6 py-2.5 bg-spring-500 text-white text-sm font-semibold rounded-lg hover:bg-spring-600 transition-colors">
            Try the demo
          </a>
        </div>
      </div>
    </section>
  </PublicLayout>
</template>

<script>
import PublicLayout from '@/layouts/PublicLayout.vue';

export default {
  name: 'BestFinancialPlanningToolsPage',
  components: { PublicLayout },

  mounted() {
    document.title = 'Best Financial Planning Tools UK 2025 \u2014 Comparison Guide | Fynla';
    const meta = document.querySelector('meta[name="description"]');
    if (meta) {
      meta.setAttribute('content', 'Comparing the best UK financial planning tools: Fynla, financial planning platforms, investment platforms, centralisation platforms, and spreadsheets. Features, pricing, and who each is best for.');
    }
  },
};
</script>
