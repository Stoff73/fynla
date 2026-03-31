<template>
  <PublicLayout>
    <!-- Hero -->
    <div class="relative flex items-center bg-gradient-to-r from-horizon-500 to-raspberry-500 overflow-hidden">
      <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10 text-left w-full">
        <h1 class="text-4xl md:text-6xl font-black text-white mb-4">What is Salary Sacrifice?</h1>
        <p class="text-lg text-white/70 max-w-xl mx-auto">
          Pension salary sacrifice explained &mdash; how it works, the savings, and whether it suits you.
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
            Salary sacrifice (sometimes called salary exchange) is an arrangement where you give up part of your salary
            in exchange for a larger employer pension contribution. You pay less income tax and National Insurance.
            Your employer pays less National Insurance too &mdash; and good employers pass that saving into your pension.
          </p>
        </div>

        <!-- How It Works -->
        <div>
          <h2 class="text-base font-bold text-horizon-500 mb-2">How It Works</h2>
          <p class="text-sm text-neutral-600 leading-relaxed mb-3">
            Here is an example. You earn &pound;50,000. You sacrifice &pound;5,000 of salary. Your gross pay drops to
            &pound;45,000. But your employer puts the full &pound;5,000 (plus their National Insurance saving) into your
            pension. You save 20&ndash;40% income tax plus 12% National Insurance on the sacrificed amount.
          </p>
          <div class="bg-white rounded-lg border border-light-gray p-5">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <div>
                <p class="text-xs font-semibold text-neutral-500 mb-1">Without salary sacrifice</p>
                <ul class="space-y-1 text-xs text-neutral-500">
                  <li>Gross salary: &pound;50,000</li>
                  <li>You contribute &pound;5,000 from net pay</li>
                  <li>You still pay National Insurance on &pound;50,000</li>
                </ul>
              </div>
              <div>
                <p class="text-xs font-semibold text-spring-600 mb-1">With salary sacrifice</p>
                <ul class="space-y-1 text-xs text-neutral-500">
                  <li>Gross salary: &pound;45,000</li>
                  <li>Employer contributes &pound;5,000+ to pension</li>
                  <li>You pay National Insurance on &pound;45,000 only</li>
                </ul>
              </div>
            </div>
          </div>
        </div>

        <!-- The Benefits -->
        <div>
          <h2 class="text-base font-bold text-horizon-500 mb-2">The Benefits</h2>
          <ul class="space-y-1.5 text-sm text-neutral-600">
            <li class="flex items-start gap-2">
              <span class="w-1.5 h-1.5 rounded-full bg-spring-500 mt-1.5 flex-shrink-0"></span>
              <span><strong>More goes into your pension</strong> for the same cost to you.</span>
            </li>
            <li class="flex items-start gap-2">
              <span class="w-1.5 h-1.5 rounded-full bg-spring-500 mt-1.5 flex-shrink-0"></span>
              <span><strong>Lower National Insurance contributions</strong> for you and your employer.</span>
            </li>
            <li class="flex items-start gap-2">
              <span class="w-1.5 h-1.5 rounded-full bg-spring-500 mt-1.5 flex-shrink-0"></span>
              <span><strong>Reduces your taxable income</strong> &mdash; helpful near tax band boundaries.</span>
            </li>
            <li class="flex items-start gap-2">
              <span class="w-1.5 h-1.5 rounded-full bg-spring-500 mt-1.5 flex-shrink-0"></span>
              <span><strong>Especially powerful for higher-rate taxpayers</strong> who save 40% income tax plus National Insurance.</span>
            </li>
          </ul>
        </div>

        <!-- The Risks -->
        <div>
          <h2 class="text-base font-bold text-horizon-500 mb-2">The Risks</h2>
          <p class="text-sm text-neutral-600 leading-relaxed mb-3">
            A lower gross salary could affect:
          </p>
          <ul class="space-y-1.5 text-sm text-neutral-600">
            <li class="flex items-start gap-2">
              <span class="w-1.5 h-1.5 rounded-full bg-raspberry-500 mt-1.5 flex-shrink-0"></span>
              <span><strong>Mortgage applications</strong> &mdash; lenders use gross salary to assess affordability.</span>
            </li>
            <li class="flex items-start gap-2">
              <span class="w-1.5 h-1.5 rounded-full bg-raspberry-500 mt-1.5 flex-shrink-0"></span>
              <span><strong>Statutory sick pay</strong> and <strong>maternity or paternity pay</strong> &mdash; both based on gross earnings.</span>
            </li>
            <li class="flex items-start gap-2">
              <span class="w-1.5 h-1.5 rounded-full bg-raspberry-500 mt-1.5 flex-shrink-0"></span>
              <span><strong>Life insurance</strong> &mdash; if your employer scheme is based on salary.</span>
            </li>
            <li class="flex items-start gap-2">
              <span class="w-1.5 h-1.5 rounded-full bg-raspberry-500 mt-1.5 flex-shrink-0"></span>
              <span><strong>No guarantee of employer savings</strong> &mdash; your employer is not obligated to pass on their National Insurance saving.</span>
            </li>
          </ul>
        </div>

        <!-- Who Should Consider It? -->
        <div>
          <h2 class="text-base font-bold text-horizon-500 mb-2">Who Should Consider It?</h2>
          <ul class="space-y-1.5 text-sm text-neutral-600">
            <li class="flex items-start gap-2">
              <span class="w-1.5 h-1.5 rounded-full bg-violet-500 mt-1.5 flex-shrink-0"></span>
              <span>Higher and additional rate taxpayers get the biggest benefit.</span>
            </li>
            <li class="flex items-start gap-2">
              <span class="w-1.5 h-1.5 rounded-full bg-violet-500 mt-1.5 flex-shrink-0"></span>
              <span>Anyone whose employer passes on the National Insurance saving.</span>
            </li>
            <li class="flex items-start gap-2">
              <span class="w-1.5 h-1.5 rounded-full bg-violet-500 mt-1.5 flex-shrink-0"></span>
              <span>People not planning to apply for a mortgage soon.</span>
            </li>
          </ul>
        </div>

        <!-- Related Links -->
        <div class="border-t border-light-gray pt-6">
          <h2 class="text-sm font-bold text-horizon-500 mb-3">Related</h2>
          <div class="flex flex-wrap gap-2">
            <router-link to="/calculators" class="text-xs text-raspberry-500 hover:text-raspberry-600 underline">Calculators</router-link>
            <router-link to="/learn/glossary#salary-sacrifice" class="text-xs text-raspberry-500 hover:text-raspberry-600 underline">Glossary: salary sacrifice</router-link>
            <router-link to="/stage/planning-your-future" class="text-xs text-raspberry-500 hover:text-raspberry-600 underline">Planning Your Future</router-link>
            <router-link to="/learn" class="text-xs text-raspberry-500 hover:text-raspberry-600 underline">All guides</router-link>
          </div>
        </div>
      </div>
    </section>

    <!-- CTA -->
    <div class="relative flex items-center bg-gradient-to-r from-horizon-500 to-raspberry-500 overflow-hidden">
      <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10 text-left w-full">
        <h2 class="text-xl font-bold text-white mb-2">Model salary sacrifice in your financial plan</h2>
        <p class="text-lg text-white/70 mb-6">See how salary sacrifice could boost your pension &mdash; and what it means for your take-home pay.</p>
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
  name: 'WhatIsSalarySacrificePage',
  components: { PublicLayout },

  mounted() {
    document.title = 'What is Salary Sacrifice? — Pension Salary Sacrifice Explained | Fynla';
    const meta = document.querySelector('meta[name="description"]');
    if (meta) {
      meta.setAttribute('content', 'Salary sacrifice lets you boost your pension while paying less tax and National Insurance. Learn how it works, the savings, and whether it\'s right for you.');
    }
  },
};
</script>
