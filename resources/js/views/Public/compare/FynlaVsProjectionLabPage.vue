<template>
  <PublicLayout>
    <!-- Hero -->
    <div class="relative flex items-center bg-gradient-to-r from-horizon-500 to-raspberry-500 overflow-hidden">
      <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10 text-left w-full">
        <h1 class="text-4xl md:text-6xl font-black text-white mb-4">
          Fynla vs<br/>
          <span class="text-raspberry-300">Financial Planning Platform</span>
        </h1>
        <p class="text-lg text-white/70">
          An honest comparison &mdash; features, pricing, and who each tool is best for.
        </p>
      </div>
    </div>

    <!-- Content -->
    <section class="py-10 bg-eggshell-500">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-10">

        <!-- Overview -->
        <div class="bg-light-blue-100 rounded-xl p-8">
          <h2 class="text-xl sm:text-2xl md:text-3xl font-bold text-horizon-500 mb-4">Two different approaches</h2>
          <p class="text-sm text-neutral-600 leading-relaxed">
            A financial planning platform and Fynla are both financial planning tools, but they serve different audiences. A typical planning platform
            is popular in the Financial Independence, Retire Early community and excels at flexible modelling. Fynla is
            built specifically for UK financial planning with integrated tax rules, protection analysis, and estate planning.
          </p>
        </div>

        <!-- ProjectionLab Strengths -->
        <div>
          <h2 class="text-xl sm:text-2xl md:text-3xl font-bold text-horizon-500 mb-4">Where a planning platform excels</h2>
          <ul class="space-y-1.5 text-sm text-neutral-600">
            <li class="flex items-start gap-2">
              <span class="w-1.5 h-1.5 rounded-full bg-spring-500 mt-1.5 flex-shrink-0"></span>
              <span><strong>Advanced modelling:</strong> highly customisable cashflow projections with flexible scenarios.</span>
            </li>
            <li class="flex items-start gap-2">
              <span class="w-1.5 h-1.5 rounded-full bg-spring-500 mt-1.5 flex-shrink-0"></span>
              <span><strong>FIRE community:</strong> strong focus on financial independence timelines and safe withdrawal rates.</span>
            </li>
            <li class="flex items-start gap-2">
              <span class="w-1.5 h-1.5 rounded-full bg-spring-500 mt-1.5 flex-shrink-0"></span>
              <span><strong>Free tier:</strong> a generous free plan lets you explore core features before committing.</span>
            </li>
            <li class="flex items-start gap-2">
              <span class="w-1.5 h-1.5 rounded-full bg-spring-500 mt-1.5 flex-shrink-0"></span>
              <span><strong>Clean interface:</strong> well-designed, modern user experience.</span>
            </li>
          </ul>
        </div>

        <!-- Fynla Strengths -->
        <div>
          <h2 class="text-xl sm:text-2xl md:text-3xl font-bold text-horizon-500 mb-4">Where Fynla excels</h2>
          <ul class="space-y-1.5 text-sm text-neutral-600">
            <li class="flex items-start gap-2">
              <span class="w-1.5 h-1.5 rounded-full bg-spring-500 mt-1.5 flex-shrink-0"></span>
              <span><strong>UK-specific tax rules:</strong> income tax bands, National Insurance, ISA allowances, pension annual allowance, and Capital Gains Tax all built in.</span>
            </li>
            <li class="flex items-start gap-2">
              <span class="w-1.5 h-1.5 rounded-full bg-spring-500 mt-1.5 flex-shrink-0"></span>
              <span><strong>Inheritance Tax planning:</strong> full estate analysis with nil rate band, residence nil rate band, and the 2027 pension changes.</span>
            </li>
            <li class="flex items-start gap-2">
              <span class="w-1.5 h-1.5 rounded-full bg-spring-500 mt-1.5 flex-shrink-0"></span>
              <span><strong>Protection analysis:</strong> life insurance, critical illness, and income protection gap calculations.</span>
            </li>
            <li class="flex items-start gap-2">
              <span class="w-1.5 h-1.5 rounded-full bg-spring-500 mt-1.5 flex-shrink-0"></span>
              <span><strong>Easier learning curve:</strong> guided onboarding and five integrated modules rather than one flexible canvas.</span>
            </li>
          </ul>
        </div>

        <!-- Comparison Table -->
        <div>
          <h2 class="text-xl sm:text-2xl md:text-3xl font-bold text-horizon-500 mb-4">Side-by-Side</h2>
          <div class="bg-light-blue-100 rounded-xl overflow-hidden">
            <table class="w-full text-sm">
              <thead>
                <tr>
                  <th class="text-left px-5 py-3 text-base font-bold text-horizon-500">Feature</th>
                  <th class="text-center px-5 py-3 text-base font-bold text-raspberry-500">Fynla</th>
                  <th class="text-center px-5 py-3 text-base font-bold text-horizon-500">Planning Platform</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-white/50">
                <tr>
                  <td class="px-5 py-3 text-sm text-neutral-600">UK tax rules built in</td>
                  <td class="px-5 py-3 text-center"><span class="inline-block w-6 h-6 rounded-full bg-spring-500"></span></td>
                  <td class="px-5 py-3 text-center"><span class="inline-block w-6 h-6 rounded-full harvey-half"></span></td>
                </tr>
                <tr>
                  <td class="px-5 py-3 text-sm text-neutral-600">Inheritance Tax planning</td>
                  <td class="px-5 py-3 text-center"><span class="inline-block w-6 h-6 rounded-full bg-spring-500"></span></td>
                  <td class="px-5 py-3 text-center"><span class="inline-block w-6 h-6 rounded-full bg-horizon-500/10 border-2 border-horizon-500/20"></span></td>
                </tr>
                <tr>
                  <td class="px-5 py-3 text-sm text-neutral-600">Protection gap analysis</td>
                  <td class="px-5 py-3 text-center"><span class="inline-block w-6 h-6 rounded-full bg-spring-500"></span></td>
                  <td class="px-5 py-3 text-center"><span class="inline-block w-6 h-6 rounded-full bg-horizon-500/10 border-2 border-horizon-500/20"></span></td>
                </tr>
                <tr>
                  <td class="px-5 py-3 text-sm text-neutral-600">Monte Carlo simulations</td>
                  <td class="px-5 py-3 text-center"><span class="inline-block w-6 h-6 rounded-full bg-spring-500"></span></td>
                  <td class="px-5 py-3 text-center"><span class="inline-block w-6 h-6 rounded-full bg-spring-500"></span></td>
                </tr>
                <tr>
                  <td class="px-5 py-3 text-sm text-neutral-600">FIRE planning focus</td>
                  <td class="px-5 py-3 text-center"><span class="inline-block w-6 h-6 rounded-full harvey-half"></span></td>
                  <td class="px-5 py-3 text-center"><span class="inline-block w-6 h-6 rounded-full bg-spring-500"></span></td>
                </tr>
                <tr>
                  <td class="px-5 py-3 text-sm text-neutral-600">Free tier</td>
                  <td class="px-5 py-3 text-center"><span class="inline-block w-6 h-6 rounded-full harvey-half"></span></td>
                  <td class="px-5 py-3 text-center"><span class="inline-block w-6 h-6 rounded-full bg-spring-500"></span></td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <!-- Who Is Each Best For -->
        <div>
          <h2 class="text-xl sm:text-2xl md:text-3xl font-bold text-horizon-500 mb-4">Who is each best for?</h2>
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div class="bg-light-pink-100 rounded-xl p-6">
              <p class="text-base font-bold text-horizon-500 mb-3">Choose Fynla if you</p>
              <ul class="space-y-1.5 text-sm text-neutral-500">
                <li>Are based in the UK and want accurate tax calculations</li>
                <li>Need Inheritance Tax or protection planning</li>
                <li>Want a guided, all-in-one platform</li>
                <li>Prefer straightforward over highly customisable</li>
              </ul>
            </div>
            <div class="bg-light-pink-100 rounded-xl p-6">
              <p class="text-base font-bold text-horizon-500 mb-3">Choose a planning platform if you</p>
              <ul class="space-y-1.5 text-sm text-neutral-500">
                <li>Want maximum modelling flexibility</li>
                <li>Are focused on FIRE or early retirement</li>
                <li>Prefer a free tier to start</li>
                <li>Don't need UK-specific tax or estate planning</li>
              </ul>
            </div>
          </div>
        </div>

        <!-- Related Links -->
        <div class="border-t border-light-gray pt-6 flex flex-wrap items-center gap-x-4 gap-y-1">
          <span class="text-sm font-bold text-horizon-500">Related</span>
          <router-link to="/compare/best-financial-planning-tools-uk" class="text-xs text-raspberry-500 hover:text-light-pink-400 transition-colors">Best Financial Planning Tools UK</router-link>
          <span class="text-neutral-300">|</span>
          <router-link to="/compare/fynla-vs-spreadsheets" class="text-xs text-raspberry-500 hover:text-light-pink-400 transition-colors">Fynla vs Spreadsheets</router-link>
          <span class="text-neutral-300">|</span>
          <router-link to="/pricing" class="text-xs text-raspberry-500 hover:text-light-pink-400 transition-colors">Fynla Pricing</router-link>
        </div>
      </div>
    </section>

    <!-- CTA -->
    <section class="py-10 bg-light-pink-100">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="bg-gradient-to-r from-horizon-500 to-raspberry-500 rounded-xl px-6 py-6">
          <h2 class="text-xl sm:text-2xl md:text-3xl lg:text-4xl font-bold text-white mb-2">Ready to see the difference?</h2>
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
  name: 'FynlaVsProjectionLabPage',
  components: { PublicLayout },

  mounted() {
    document.title = 'Fynla vs Financial Planning Platform \u2014 Honest Comparison | Fynla';
    const meta = document.querySelector('meta[name="description"]');
    if (meta) {
      meta.setAttribute('content', 'How does Fynla compare to a financial planning platform? An honest look at features, pricing, ease of use, and who each tool is best for.');
    }
  },
};
</script>

<style scoped>
.harvey-half {
  background: linear-gradient(to right, #37B679 50%, rgba(31, 42, 68, 0.1) 50%);
}
</style>
