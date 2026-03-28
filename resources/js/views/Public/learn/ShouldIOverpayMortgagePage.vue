<template>
  <PublicLayout>
    <!-- Hero -->
    <section class="bg-gradient-to-r from-horizon-500 to-raspberry-500 py-16 sm:py-20">
      <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
        <h1 class="text-3xl sm:text-4xl font-black text-white mb-3">Should I Overpay My Mortgage?</h1>
        <p class="text-base text-white/80 max-w-xl mx-auto">
          A practical guide to one of the most common financial decisions.
        </p>
      </div>
    </section>

    <!-- Content -->
    <section class="py-12 bg-eggshell-500">
      <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 space-y-10">

        <!-- The Quick Answer -->
        <div class="bg-white rounded-lg border border-light-gray p-5">
          <h2 class="text-lg font-bold text-horizon-500 mb-2">The Quick Answer</h2>
          <p class="text-sm text-neutral-600 leading-relaxed">
            It depends on your mortgage rate versus what you would earn elsewhere. If your mortgage rate is higher
            than your savings rate, overpaying usually makes sense. But there are exceptions worth considering.
          </p>
        </div>

        <!-- The Maths -->
        <div>
          <h2 class="text-lg font-bold text-horizon-500 mb-2">The Maths</h2>
          <p class="text-sm text-neutral-600 leading-relaxed">
            If your mortgage rate is 4.5% and your savings account pays 4%, overpaying saves you more &mdash; because
            you are effectively earning a guaranteed 4.5% return by reducing your debt. But if you could invest at
            6&ndash;8% over the long term in a Stocks and Shares ISA, investing might win &mdash; with more risk attached.
          </p>
        </div>

        <!-- When Overpaying Makes Sense -->
        <div>
          <h2 class="text-lg font-bold text-horizon-500 mb-2">When Overpaying Makes Sense</h2>
          <ul class="space-y-1.5 text-sm text-neutral-600">
            <li class="flex items-start gap-2">
              <span class="w-1.5 h-1.5 rounded-full bg-spring-500 mt-1.5 flex-shrink-0"></span>
              <span>Your mortgage rate is relatively high (above 4&ndash;5%).</span>
            </li>
            <li class="flex items-start gap-2">
              <span class="w-1.5 h-1.5 rounded-full bg-spring-500 mt-1.5 flex-shrink-0"></span>
              <span>You have already maximised your ISA allowance for the year.</span>
            </li>
            <li class="flex items-start gap-2">
              <span class="w-1.5 h-1.5 rounded-full bg-spring-500 mt-1.5 flex-shrink-0"></span>
              <span>You have a solid emergency fund (3&ndash;6 months of essential expenses).</span>
            </li>
            <li class="flex items-start gap-2">
              <span class="w-1.5 h-1.5 rounded-full bg-spring-500 mt-1.5 flex-shrink-0"></span>
              <span>You value the certainty of reducing debt over the uncertainty of investment returns.</span>
            </li>
          </ul>
        </div>

        <!-- When It Might Not -->
        <div>
          <h2 class="text-lg font-bold text-horizon-500 mb-2">When It Might Not</h2>
          <ul class="space-y-1.5 text-sm text-neutral-600">
            <li class="flex items-start gap-2">
              <span class="w-1.5 h-1.5 rounded-full bg-raspberry-500 mt-1.5 flex-shrink-0"></span>
              <span>You have not built an emergency fund yet &mdash; that should come first.</span>
            </li>
            <li class="flex items-start gap-2">
              <span class="w-1.5 h-1.5 rounded-full bg-raspberry-500 mt-1.5 flex-shrink-0"></span>
              <span>Your mortgage rate is low (under 3%) and you can earn more elsewhere.</span>
            </li>
            <li class="flex items-start gap-2">
              <span class="w-1.5 h-1.5 rounded-full bg-raspberry-500 mt-1.5 flex-shrink-0"></span>
              <span>You are not maximising your pension tax relief &mdash; employer match is free money.</span>
            </li>
            <li class="flex items-start gap-2">
              <span class="w-1.5 h-1.5 rounded-full bg-raspberry-500 mt-1.5 flex-shrink-0"></span>
              <span>You have higher-interest debts (credit cards, personal loans) to clear first.</span>
            </li>
          </ul>
        </div>

        <!-- Overpayment Limits -->
        <div>
          <h2 class="text-lg font-bold text-horizon-500 mb-2">Overpayment Limits</h2>
          <p class="text-sm text-neutral-600 leading-relaxed">
            Most lenders allow you to overpay up to <strong>10% of your outstanding balance per year</strong> without
            early repayment charges. Check your mortgage terms before overpaying &mdash; going above the limit can
            trigger penalties that wipe out the benefit.
          </p>
        </div>

        <!-- The Psychological Factor -->
        <div>
          <h2 class="text-lg font-bold text-horizon-500 mb-2">The Psychological Factor</h2>
          <p class="text-sm text-neutral-600 leading-relaxed">
            Numbers aside, being mortgage-free is powerful. Many people overpay for peace of mind even when the
            pure maths says invest. There is nothing wrong with that &mdash; a financial plan should work for
            <em>you</em>, not just on a spreadsheet.
          </p>
        </div>

        <!-- Related Links -->
        <div class="border-t border-light-gray pt-6">
          <h2 class="text-sm font-bold text-horizon-500 mb-3">Related</h2>
          <div class="flex flex-wrap gap-2">
            <router-link to="/calculators" class="text-xs text-raspberry-500 hover:text-raspberry-600 underline">Mortgage repayment calculator</router-link>
            <router-link to="/learn/glossary" class="text-xs text-raspberry-500 hover:text-raspberry-600 underline">Glossary</router-link>
            <router-link to="/stage/building-foundations" class="text-xs text-raspberry-500 hover:text-raspberry-600 underline">Building Foundations stage</router-link>
            <router-link to="/learn" class="text-xs text-raspberry-500 hover:text-raspberry-600 underline">All guides</router-link>
          </div>
        </div>
      </div>
    </section>

    <!-- CTA -->
    <section class="py-12 bg-white">
      <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
        <h2 class="text-xl font-bold text-horizon-500 mb-2">Model mortgage overpayment in your full financial plan</h2>
        <p class="text-sm text-neutral-500 mb-6">See how overpaying affects your net worth, mortgage term, and total interest paid.</p>
        <a href="/?demo=true" class="inline-block px-6 py-2.5 bg-spring-500 text-white text-sm font-semibold rounded-lg hover:bg-spring-600 transition-colors">
          Try the demo
        </a>
      </div>
    </section>
  </PublicLayout>
</template>

<script>
import PublicLayout from '@/layouts/PublicLayout.vue';

export default {
  name: 'ShouldIOverpayMortgagePage',
  components: { PublicLayout },

  mounted() {
    document.title = 'Should I Overpay My Mortgage? — UK Guide | Fynla';
  },
};
</script>
