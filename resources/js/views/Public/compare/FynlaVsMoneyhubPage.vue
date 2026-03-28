<template>
  <PublicLayout>
    <!-- Hero -->
    <section class="bg-gradient-to-r from-horizon-500 to-raspberry-500 py-10">
      <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
        <h1 class="text-xl font-black text-white mb-2">Fynla vs Moneyhub</h1>
        <p class="text-sm text-white/80 max-w-xl mx-auto">
          Different tools for different needs &mdash; spending tracking vs financial planning.
        </p>
      </div>
    </section>

    <!-- Content -->
    <section class="py-10 bg-eggshell-500">
      <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 space-y-10">

        <!-- Overview -->
        <div class="bg-white rounded-lg border border-light-gray p-5">
          <h2 class="text-base font-bold text-horizon-500 mb-2">Different Questions, Different Tools</h2>
          <p class="text-sm text-neutral-600 leading-relaxed">
            Moneyhub answers &ldquo;where did my money go?&rdquo; Fynla answers &ldquo;where is my money going?&rdquo;
            Moneyhub connects to your bank accounts and tracks spending. Fynla goes deeper into planning &mdash; pensions,
            retirement, Inheritance Tax, and protection. They can work well together.
          </p>
        </div>

        <!-- Moneyhub Strengths -->
        <div>
          <h2 class="text-base font-bold text-horizon-500 mb-2">Where Moneyhub Excels</h2>
          <ul class="space-y-1.5 text-sm text-neutral-600">
            <li class="flex items-start gap-2">
              <span class="w-1.5 h-1.5 rounded-full bg-spring-500 mt-1.5 flex-shrink-0"></span>
              <span><strong>Open banking:</strong> connects directly to your bank accounts, credit cards, and pensions.</span>
            </li>
            <li class="flex items-start gap-2">
              <span class="w-1.5 h-1.5 rounded-full bg-spring-500 mt-1.5 flex-shrink-0"></span>
              <span><strong>Spending tracking:</strong> automatic categorisation of transactions and spending insights.</span>
            </li>
            <li class="flex items-start gap-2">
              <span class="w-1.5 h-1.5 rounded-full bg-spring-500 mt-1.5 flex-shrink-0"></span>
              <span><strong>Account aggregation:</strong> see all your accounts in one place with live balances.</span>
            </li>
            <li class="flex items-start gap-2">
              <span class="w-1.5 h-1.5 rounded-full bg-spring-500 mt-1.5 flex-shrink-0"></span>
              <span><strong>Budgeting tools:</strong> set budgets and track progress against them.</span>
            </li>
          </ul>
        </div>

        <!-- Fynla Strengths -->
        <div>
          <h2 class="text-base font-bold text-horizon-500 mb-2">Where Fynla Excels</h2>
          <ul class="space-y-1.5 text-sm text-neutral-600">
            <li class="flex items-start gap-2">
              <span class="w-1.5 h-1.5 rounded-full bg-spring-500 mt-1.5 flex-shrink-0"></span>
              <span><strong>Retirement projections:</strong> when can you retire, how much will you need, and are you on track?</span>
            </li>
            <li class="flex items-start gap-2">
              <span class="w-1.5 h-1.5 rounded-full bg-spring-500 mt-1.5 flex-shrink-0"></span>
              <span><strong>Inheritance Tax planning:</strong> estimate your Inheritance Tax bill and find ways to reduce it.</span>
            </li>
            <li class="flex items-start gap-2">
              <span class="w-1.5 h-1.5 rounded-full bg-spring-500 mt-1.5 flex-shrink-0"></span>
              <span><strong>Protection gap analysis:</strong> life insurance, critical illness, and income protection needs.</span>
            </li>
            <li class="flex items-start gap-2">
              <span class="w-1.5 h-1.5 rounded-full bg-spring-500 mt-1.5 flex-shrink-0"></span>
              <span><strong>Scenario modelling:</strong> Monte Carlo simulations and what-if analysis for investment outcomes.</span>
            </li>
            <li class="flex items-start gap-2">
              <span class="w-1.5 h-1.5 rounded-full bg-spring-500 mt-1.5 flex-shrink-0"></span>
              <span><strong>Tax allowance tracking:</strong> ISA, pension, and Capital Gains Tax allowances in one dashboard.</span>
            </li>
          </ul>
        </div>

        <!-- Use Cases -->
        <div>
          <h2 class="text-base font-bold text-horizon-500 mb-2">When to Use Each</h2>
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div class="bg-white rounded-lg border border-light-gray p-5">
              <p class="text-xs font-semibold text-horizon-500 mb-2">Moneyhub is better for</p>
              <ul class="space-y-1 text-xs text-neutral-500">
                <li>Day-to-day spending tracking</li>
                <li>Seeing all account balances in one place</li>
                <li>Budgeting and cashflow management</li>
                <li>Automatic bank feed categorisation</li>
              </ul>
            </div>
            <div class="bg-white rounded-lg border border-light-gray p-5">
              <p class="text-xs font-semibold text-horizon-500 mb-2">Fynla is better for</p>
              <ul class="space-y-1 text-xs text-neutral-500">
                <li>Long-term financial planning</li>
                <li>Retirement readiness projections</li>
                <li>Inheritance Tax and estate planning</li>
                <li>Protection and insurance gap analysis</li>
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
        <p class="text-sm text-white/80 mb-6">Go beyond spending tracking &mdash; plan your retirement, estate, and protection in one place.</p>
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
  name: 'FynlaVsMoneyhubPage',
  components: { PublicLayout },

  mounted() {
    document.title = 'Fynla vs Moneyhub \u2014 Which is Right For You? | Fynla';
    const meta = document.querySelector('meta[name="description"]');
    if (meta) {
      meta.setAttribute('content', 'Moneyhub connects to your accounts for spending tracking. Fynla goes deeper into planning \u2014 pensions, retirement, IHT, and protection. Different tools for different needs.');
    }
  },
};
</script>
