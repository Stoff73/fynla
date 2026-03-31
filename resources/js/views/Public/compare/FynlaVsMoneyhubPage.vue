<template>
  <PublicLayout>
    <!-- Hero -->
    <div class="relative flex items-center bg-gradient-to-r from-horizon-500 to-raspberry-500 overflow-hidden">
      <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10 text-left w-full">
        <h1 class="text-4xl md:text-6xl font-black text-white mb-4">
          Fynla vs<br/>
          <span class="text-raspberry-300">Financial Centralisation Platform</span>
        </h1>
        <p class="text-lg text-white/70">
          Different tools for different needs &mdash; spending tracking vs financial planning.
        </p>
      </div>
    </div>

    <!-- Content -->
    <section class="py-10 bg-eggshell-500">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-10">

        <!-- Overview -->
        <div class="bg-light-blue-100 rounded-xl p-8">
          <h2 class="text-xl sm:text-2xl md:text-3xl font-bold text-horizon-500 mb-4">Different questions, different tools</h2>
          <p class="text-sm text-neutral-600 leading-relaxed">
            A financial centralisation platform answers &ldquo;where did my money go?&rdquo; Fynla answers &ldquo;where is my money going?&rdquo;
            A centralisation platform connects to your bank accounts and tracks spending. Fynla goes deeper into planning &mdash; pensions,
            retirement, Inheritance Tax, and protection. They can work well together.
          </p>
        </div>

        <!-- Moneyhub Strengths -->
        <div>
          <h2 class="text-xl sm:text-2xl md:text-3xl font-bold text-horizon-500 mb-4">Where a centralisation platform excels</h2>
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
          <h2 class="text-xl sm:text-2xl md:text-3xl font-bold text-horizon-500 mb-4">Where Fynla excels</h2>
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
          <h2 class="text-xl sm:text-2xl md:text-3xl font-bold text-horizon-500 mb-4">When to use each</h2>
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div class="bg-light-pink-100 rounded-xl p-6">
              <p class="text-base font-bold text-horizon-500 mb-3">A centralisation platform is better for</p>
              <ul class="space-y-1.5 text-sm text-neutral-500">
                <li>Day-to-day spending tracking</li>
                <li>Seeing all account balances in one place</li>
                <li>Budgeting and cashflow management</li>
                <li>Automatic bank feed categorisation</li>
              </ul>
            </div>
            <div class="bg-light-pink-100 rounded-xl p-6">
              <p class="text-base font-bold text-horizon-500 mb-3">Fynla is better for</p>
              <ul class="space-y-1.5 text-sm text-neutral-500">
                <li>Long-term financial planning</li>
                <li>Retirement readiness projections</li>
                <li>Inheritance Tax and estate planning</li>
                <li>Protection and insurance gap analysis</li>
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
          <h2 class="text-xl sm:text-2xl md:text-3xl lg:text-4xl font-bold text-white mb-2">Go beyond spending tracking</h2>
          <p class="text-lg text-white/70 mb-4">Plan your retirement, estate, and protection in one place.</p>
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
  name: 'FynlaVsMoneyhubPage',
  components: { PublicLayout },

  mounted() {
    document.title = 'Fynla vs Financial Centralisation Platform \u2014 Which is Right For You? | Fynla';
    const meta = document.querySelector('meta[name="description"]');
    if (meta) {
      meta.setAttribute('content', 'A financial centralisation platform connects to your accounts for spending tracking. Fynla goes deeper into planning \u2014 pensions, retirement, IHT, and protection. Different tools for different needs.');
    }
  },
};
</script>
