<template>
  <PublicLayout>
    <!-- Hero -->
    <div class="relative flex items-center bg-gradient-to-r from-horizon-500 to-raspberry-500 overflow-hidden">
      <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10 text-left w-full">
        <h1 class="text-4xl md:text-6xl font-black text-white mb-4">What is a SIPP?</h1>
        <p class="text-lg text-white/70 max-w-xl mx-auto">
          Self-Invested Personal Pensions explained &mdash; more control over your retirement savings.
        </p>
      </div>
    </div>

    <!-- Content -->
    <section class="py-10 bg-eggshell-500">
      <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 space-y-10">

        <!-- The Short Answer -->
        <div class="bg-white rounded-lg border border-light-gray p-5">
          <h2 class="text-base font-bold text-horizon-500 mb-2">The Short Answer</h2>
          <p class="text-sm text-neutral-600 leading-relaxed">
            A Self-Invested Personal Pension (SIPP) is a pension that gives you full control over how your money is
            invested. Unlike a workplace pension where your employer chooses the provider and fund range, a SIPP lets
            you pick from thousands of funds, shares, bonds, and other investments.
          </p>
        </div>

        <!-- SIPP vs Workplace Pension -->
        <div>
          <h2 class="text-base font-bold text-horizon-500 mb-3">SIPP vs Workplace Pension</h2>
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div class="bg-white rounded-lg border border-light-gray p-5">
              <p class="text-sm font-semibold text-horizon-500 mb-1">Workplace Pension</p>
              <ul class="space-y-1 text-xs text-neutral-500">
                <li>Employer contributes alongside you</li>
                <li>Limited fund choice</li>
                <li>Low fees (usually)</li>
                <li>Simple &mdash; set and forget</li>
              </ul>
            </div>
            <div class="bg-white rounded-lg border border-light-gray p-5">
              <p class="text-sm font-semibold text-horizon-500 mb-1">SIPP</p>
              <ul class="space-y-1 text-xs text-neutral-500">
                <li>You choose your investments</li>
                <li>Wider fund range</li>
                <li>Potentially higher fees</li>
                <li>More control and flexibility</li>
              </ul>
            </div>
          </div>
          <p class="text-xs text-neutral-500 mt-3 leading-relaxed">
            You can have both &mdash; many people use a workplace pension for the employer match and a SIPP for
            additional contributions.
          </p>
        </div>

        <!-- Tax Relief -->
        <div>
          <h2 class="text-base font-bold text-horizon-500 mb-2">Tax Relief</h2>
          <p class="text-sm text-neutral-600 leading-relaxed mb-3">
            SIPPs get the same tax relief as any pension:
          </p>
          <ul class="space-y-1.5 text-sm text-neutral-600">
            <li class="flex items-start gap-2">
              <span class="w-1.5 h-1.5 rounded-full bg-spring-500 mt-1.5 flex-shrink-0"></span>
              <span><strong>Basic rate:</strong> the government adds 25% &mdash; you pay &pound;80, your SIPP receives &pound;100.</span>
            </li>
            <li class="flex items-start gap-2">
              <span class="w-1.5 h-1.5 rounded-full bg-spring-500 mt-1.5 flex-shrink-0"></span>
              <span><strong>Higher rate:</strong> claim an extra 20% via self-assessment.</span>
            </li>
            <li class="flex items-start gap-2">
              <span class="w-1.5 h-1.5 rounded-full bg-spring-500 mt-1.5 flex-shrink-0"></span>
              <span><strong>Additional rate:</strong> claim an extra 25% via self-assessment.</span>
            </li>
          </ul>
          <p class="text-xs text-neutral-500 mt-3 leading-relaxed">
            The &pound;60,000 annual allowance applies across all your pensions combined.
          </p>
        </div>

        <!-- Who's It For? -->
        <div>
          <h2 class="text-base font-bold text-horizon-500 mb-2">Who Is It For?</h2>
          <ul class="space-y-1.5 text-sm text-neutral-600">
            <li class="flex items-start gap-2">
              <span class="w-1.5 h-1.5 rounded-full bg-violet-500 mt-1.5 flex-shrink-0"></span>
              <span>People who want control over their investments.</span>
            </li>
            <li class="flex items-start gap-2">
              <span class="w-1.5 h-1.5 rounded-full bg-violet-500 mt-1.5 flex-shrink-0"></span>
              <span>Those consolidating multiple old workplace pensions.</span>
            </li>
            <li class="flex items-start gap-2">
              <span class="w-1.5 h-1.5 rounded-full bg-violet-500 mt-1.5 flex-shrink-0"></span>
              <span>Higher earners who have maxed their workplace pension match.</span>
            </li>
            <li class="flex items-start gap-2">
              <span class="w-1.5 h-1.5 rounded-full bg-violet-500 mt-1.5 flex-shrink-0"></span>
              <span>Self-employed people with no employer pension available.</span>
            </li>
          </ul>
        </div>

        <!-- Costs to Watch -->
        <div>
          <h2 class="text-base font-bold text-horizon-500 mb-2">Costs to Watch</h2>
          <ul class="space-y-1.5 text-sm text-neutral-600">
            <li class="flex items-start gap-2">
              <span class="w-1.5 h-1.5 rounded-full bg-raspberry-500 mt-1.5 flex-shrink-0"></span>
              <span><strong>Platform fee:</strong> 0.15&ndash;0.45% per year.</span>
            </li>
            <li class="flex items-start gap-2">
              <span class="w-1.5 h-1.5 rounded-full bg-raspberry-500 mt-1.5 flex-shrink-0"></span>
              <span><strong>Fund fees:</strong> 0.05&ndash;1.5% depending on fund type.</span>
            </li>
            <li class="flex items-start gap-2">
              <span class="w-1.5 h-1.5 rounded-full bg-raspberry-500 mt-1.5 flex-shrink-0"></span>
              <span><strong>Trading costs:</strong> if buying individual shares.</span>
            </li>
          </ul>
          <p class="text-xs text-neutral-500 mt-3 leading-relaxed">
            These add up &mdash; a 0.5% difference in fees can cost tens of thousands of pounds over 30 years.
          </p>
        </div>

        <!-- Related Links -->
        <div class="border-t border-light-gray pt-6">
          <h2 class="text-sm font-bold text-horizon-500 mb-3">Related</h2>
          <div class="flex flex-wrap gap-2">
            <router-link to="/features/pension-tracker" class="text-xs text-raspberry-500 hover:text-raspberry-600 underline">Pension tracker</router-link>
            <router-link to="/learn/glossary#self-invested-personal-pension" class="text-xs text-raspberry-500 hover:text-raspberry-600 underline">Glossary: SIPP</router-link>
            <router-link to="/learn/should-i-consolidate-pensions" class="text-xs text-raspberry-500 hover:text-raspberry-600 underline">Should I consolidate my pensions?</router-link>
            <router-link to="/stage/planning-your-future" class="text-xs text-raspberry-500 hover:text-raspberry-600 underline">Planning Your Future</router-link>
            <router-link to="/learn" class="text-xs text-raspberry-500 hover:text-raspberry-600 underline">All guides</router-link>
          </div>
        </div>
      </div>
    </section>

    <!-- CTA -->
    <div class="relative flex items-center bg-gradient-to-r from-horizon-500 to-raspberry-500 overflow-hidden">
      <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10 text-left w-full">
        <h2 class="text-xl font-bold text-white mb-2">Track your SIPP alongside all your pensions in Fynla</h2>
        <p class="text-lg text-white/70 mb-6">See every pension in one place &mdash; workplace, SIPP, and Defined Benefit &mdash; with projections to retirement.</p>
        <a href="/?demo=true" class="inline-block px-6 py-2.5 bg-white text-horizon-600 text-sm font-semibold rounded-lg hover:bg-eggshell-500 transition-colors">
          Try the demo
        </a>
      </div>
    </div>
  </PublicLayout>
</template>

<script>
import PublicLayout from '@/layouts/PublicLayout.vue';

export default {
  name: 'WhatIsASippPage',
  components: { PublicLayout },

  mounted() {
    document.title = 'What is a SIPP? — Self-Invested Personal Pension Explained | Fynla';
    const meta = document.querySelector('meta[name="description"]');
    if (meta) {
      meta.setAttribute('content', 'A SIPP gives you full control over your pension investments. Learn how it differs from a workplace pension, the benefits, costs, and who it\'s suited for.');
    }
  },
};
</script>
