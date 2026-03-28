<template>
  <PublicLayout>
    <!-- Hero -->
    <section class="bg-gradient-to-r from-horizon-500 to-raspberry-500 py-10">
      <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
        <h1 class="text-xl font-black text-white mb-2">Fynla vs ProjectionLab</h1>
        <p class="text-sm text-white/80 max-w-xl mx-auto">
          An honest comparison &mdash; features, pricing, and who each tool is best for.
        </p>
      </div>
    </section>

    <!-- Content -->
    <section class="py-10 bg-eggshell-500">
      <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 space-y-10">

        <!-- Overview -->
        <div class="bg-white rounded-lg border border-light-gray p-5">
          <h2 class="text-base font-bold text-horizon-500 mb-2">Two Different Approaches</h2>
          <p class="text-sm text-neutral-600 leading-relaxed">
            ProjectionLab and Fynla are both financial planning tools, but they serve different audiences. ProjectionLab
            is popular in the Financial Independence, Retire Early community and excels at flexible modelling. Fynla is
            built specifically for UK financial planning with integrated tax rules, protection analysis, and estate planning.
          </p>
        </div>

        <!-- ProjectionLab Strengths -->
        <div>
          <h2 class="text-base font-bold text-horizon-500 mb-2">Where ProjectionLab Excels</h2>
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
          <h2 class="text-base font-bold text-horizon-500 mb-2">Where Fynla Excels</h2>
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
          <h2 class="text-base font-bold text-horizon-500 mb-2">Side-by-Side</h2>
          <div class="bg-white rounded-lg border border-light-gray overflow-hidden">
            <table class="w-full text-sm">
              <thead>
                <tr class="bg-savannah-50">
                  <th class="text-left px-4 py-2.5 text-xs font-semibold text-horizon-500">Feature</th>
                  <th class="text-center px-4 py-2.5 text-xs font-semibold text-horizon-500">Fynla</th>
                  <th class="text-center px-4 py-2.5 text-xs font-semibold text-horizon-500">ProjectionLab</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-light-gray">
                <tr>
                  <td class="px-4 py-2.5 text-neutral-500">UK tax rules built in</td>
                  <td class="px-4 py-2.5 text-center text-spring-500 font-semibold">Yes</td>
                  <td class="px-4 py-2.5 text-center text-neutral-400">Partial</td>
                </tr>
                <tr>
                  <td class="px-4 py-2.5 text-neutral-500">Inheritance Tax planning</td>
                  <td class="px-4 py-2.5 text-center text-spring-500 font-semibold">Yes</td>
                  <td class="px-4 py-2.5 text-center text-neutral-400">No</td>
                </tr>
                <tr>
                  <td class="px-4 py-2.5 text-neutral-500">Protection gap analysis</td>
                  <td class="px-4 py-2.5 text-center text-spring-500 font-semibold">Yes</td>
                  <td class="px-4 py-2.5 text-center text-neutral-400">No</td>
                </tr>
                <tr>
                  <td class="px-4 py-2.5 text-neutral-500">Monte Carlo simulations</td>
                  <td class="px-4 py-2.5 text-center text-spring-500 font-semibold">Yes</td>
                  <td class="px-4 py-2.5 text-center text-spring-500 font-semibold">Yes</td>
                </tr>
                <tr>
                  <td class="px-4 py-2.5 text-neutral-500">FIRE planning focus</td>
                  <td class="px-4 py-2.5 text-center text-neutral-400">Partial</td>
                  <td class="px-4 py-2.5 text-center text-spring-500 font-semibold">Yes</td>
                </tr>
                <tr>
                  <td class="px-4 py-2.5 text-neutral-500">Free tier</td>
                  <td class="px-4 py-2.5 text-center text-neutral-400">Demo</td>
                  <td class="px-4 py-2.5 text-center text-spring-500 font-semibold">Yes</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <!-- Who Is Each Best For -->
        <div>
          <h2 class="text-base font-bold text-horizon-500 mb-2">Who Is Each Best For?</h2>
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div class="bg-white rounded-lg border border-light-gray p-5">
              <p class="text-xs font-semibold text-horizon-500 mb-2">Choose Fynla if you</p>
              <ul class="space-y-1 text-xs text-neutral-500">
                <li>Are based in the UK and want accurate tax calculations</li>
                <li>Need Inheritance Tax or protection planning</li>
                <li>Want a guided, all-in-one platform</li>
                <li>Prefer straightforward over highly customisable</li>
              </ul>
            </div>
            <div class="bg-white rounded-lg border border-light-gray p-5">
              <p class="text-xs font-semibold text-horizon-500 mb-2">Choose ProjectionLab if you</p>
              <ul class="space-y-1 text-xs text-neutral-500">
                <li>Want maximum modelling flexibility</li>
                <li>Are focused on FIRE or early retirement</li>
                <li>Prefer a free tier to start</li>
                <li>Don't need UK-specific tax or estate planning</li>
              </ul>
            </div>
          </div>
        </div>

        <!-- Related Links -->
        <div class="border-t border-light-gray pt-6">
          <h2 class="text-sm font-bold text-horizon-500 mb-3">Related</h2>
          <div class="flex flex-wrap gap-2">
            <router-link to="/compare/best-financial-planning-tools-uk" class="text-xs text-raspberry-500 hover:text-raspberry-600 underline">Best Financial Planning Tools UK</router-link>
            <router-link to="/compare/fynla-vs-spreadsheets" class="text-xs text-raspberry-500 hover:text-raspberry-600 underline">Fynla vs Spreadsheets</router-link>
            <router-link to="/pricing" class="text-xs text-raspberry-500 hover:text-raspberry-600 underline">Fynla Pricing</router-link>
          </div>
        </div>
      </div>
    </section>

    <!-- CTA -->
    <section class="py-10 bg-gradient-to-r from-horizon-500 to-raspberry-500">
      <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
        <h2 class="text-xl font-bold text-white mb-2">Try Fynla free</h2>
        <p class="text-sm text-white/80 mb-6">Explore the full platform with our interactive demo &mdash; no sign-up required.</p>
        <a href="/?demo=true" class="inline-block px-6 py-2.5 bg-white text-horizon-600 text-sm font-semibold rounded-lg hover:bg-eggshell-500 transition-colors">
          Try the demo
        </a>
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
    document.title = 'Fynla vs ProjectionLab \u2014 Honest Comparison | Fynla';
    const meta = document.querySelector('meta[name="description"]');
    if (meta) {
      meta.setAttribute('content', 'How does Fynla compare to ProjectionLab? An honest look at features, pricing, ease of use, and who each tool is best for.');
    }
  },
};
</script>
