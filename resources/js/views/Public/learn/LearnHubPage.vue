<template>
  <PublicLayout>
    <!-- Hero -->
    <section class="bg-gradient-to-r from-horizon-500 to-raspberry-500 py-10">
      <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
        <h1 class="text-xl font-black text-white mb-2">Learn</h1>
        <p class="text-sm text-white/80 max-w-xl mx-auto">
          Understand your finances. Make better decisions.
        </p>
      </div>
    </section>

    <!-- Intro -->
    <section class="py-8 bg-white">
      <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">
        <p class="text-sm text-neutral-600 leading-relaxed">
          Whether you're trying to understand what a pension drawdown is, deciding if you should overpay your mortgage, or checking this year's tax allowances &mdash; we've written a guide for it. Everything here is free, jargon-free, and written for real people, not finance professionals.
        </p>
      </div>
    </section>

    <!-- 4-Column Grid -->
    <section class="py-8 bg-eggshell-500">
      <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">

          <!-- Concept Explainers -->
          <div class="bg-white rounded-lg border border-light-gray p-5">
            <div class="flex items-center gap-2 mb-3">
              <span class="w-2 h-2 rounded-full bg-raspberry-500"></span>
              <h2 class="text-sm font-bold text-horizon-500">Concept Explainers</h2>
            </div>
            <ul class="space-y-2">
              <li v-for="link in explainers" :key="link.to">
                <router-link :to="link.to" class="flex items-center text-sm text-neutral-600 hover:text-raspberry-500 transition-colors">
                  <span class="w-1 h-1 rounded-full bg-raspberry-400 flex-shrink-0 mr-2"></span>
                  {{ link.title }}
                </router-link>
              </li>
            </ul>
          </div>

          <!-- Decision Guides -->
          <div class="bg-white rounded-lg border border-light-gray p-5">
            <div class="flex items-center gap-2 mb-3">
              <span class="w-2 h-2 rounded-full bg-spring-500"></span>
              <h2 class="text-sm font-bold text-horizon-500">Decision Guides</h2>
            </div>
            <ul class="space-y-2">
              <li v-for="link in decisions" :key="link.to">
                <router-link :to="link.to" class="flex items-center text-sm text-neutral-600 hover:text-spring-600 transition-colors">
                  <span class="w-1 h-1 rounded-full bg-spring-400 flex-shrink-0 mr-2"></span>
                  {{ link.title }}
                </router-link>
              </li>
            </ul>
          </div>

          <!-- Life Stage Guides -->
          <div class="bg-white rounded-lg border border-light-gray p-5">
            <div class="flex items-center gap-2 mb-3">
              <span class="w-2 h-2 rounded-full bg-violet-500"></span>
              <h2 class="text-sm font-bold text-horizon-500">Life Stage Guides</h2>
            </div>
            <ul class="space-y-2">
              <li v-for="link in stages" :key="link.to">
                <router-link :to="link.to" class="flex items-center text-sm text-neutral-600 hover:text-violet-600 transition-colors">
                  <span class="w-1 h-1 rounded-full bg-violet-400 flex-shrink-0 mr-2"></span>
                  {{ link.title }}
                </router-link>
              </li>
            </ul>
          </div>

          <!-- Tax & Allowances -->
          <div class="bg-white rounded-lg border border-light-gray p-5">
            <div class="flex items-center gap-2 mb-3">
              <span class="w-2 h-2 rounded-full bg-savannah-500"></span>
              <h2 class="text-sm font-bold text-horizon-500">Tax &amp; Allowances</h2>
            </div>
            <ul class="space-y-2">
              <li v-for="link in tax" :key="link.to">
                <router-link :to="link.to" class="flex items-center text-sm text-neutral-600 hover:text-savannah-600 transition-colors">
                  <span class="w-1 h-1 rounded-full bg-savannah-400 flex-shrink-0 mr-2"></span>
                  {{ link.title }}
                </router-link>
              </li>
            </ul>
          </div>

        </div>
      </div>
    </section>

    <!-- Glossary + Insights -->
    <section class="py-8 bg-eggshell-500 border-t border-light-gray">
      <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
          <router-link
            to="/learn/glossary"
            class="bg-white rounded-lg border border-light-gray p-5 hover:border-raspberry-400 hover:shadow-sm transition-all group"
          >
            <h3 class="text-sm font-bold text-horizon-500 group-hover:text-raspberry-500 transition-colors mb-1">Glossary A&ndash;Z</h3>
            <p class="text-xs text-neutral-500">37 financial terms explained in plain English</p>
          </router-link>
          <router-link
            to="/insights"
            class="bg-white rounded-lg border border-light-gray p-5 hover:border-raspberry-400 hover:shadow-sm transition-all group"
          >
            <h3 class="text-sm font-bold text-horizon-500 group-hover:text-raspberry-500 transition-colors mb-1">Latest Insights</h3>
            <p class="text-xs text-neutral-500">Timely commentary on tax changes and financial news</p>
          </router-link>
          <router-link
            to="/faq"
            class="bg-white rounded-lg border border-light-gray p-5 hover:border-raspberry-400 hover:shadow-sm transition-all group"
          >
            <h3 class="text-sm font-bold text-horizon-500 group-hover:text-raspberry-500 transition-colors mb-1">FAQ</h3>
            <p class="text-xs text-neutral-500">Common questions about Fynla answered</p>
          </router-link>
        </div>
      </div>
    </section>

    <!-- CTA -->
    <section class="py-10 bg-white">
      <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
        <div class="bg-gradient-to-r from-horizon-500 to-raspberry-500 rounded-xl px-6 py-6">
          <h2 class="text-base font-bold text-white mb-2">Ready to put knowledge into action?</h2>
          <p class="text-sm text-white/80 mb-4">Try Fynla free and see your full financial picture in minutes.</p>
          <router-link to="/?demo=true" class="inline-block px-6 py-2.5 bg-white text-horizon-500 text-sm font-semibold rounded-lg hover:bg-white/90 transition-colors">
            Try the demo
          </router-link>
        </div>
      </div>
    </section>
  </PublicLayout>
</template>

<script>
import PublicLayout from '@/layouts/PublicLayout.vue';

export default {
  name: 'LearnHubPage',
  components: { PublicLayout },

  data() {
    return {
      explainers: [
        { title: 'What is an ISA?', to: '/learn/what-is-an-isa' },
        { title: 'What is Drawdown?', to: '/learn/what-is-drawdown' },
        { title: 'What is Salary Sacrifice?', to: '/learn/what-is-salary-sacrifice' },
        { title: 'What is a Lasting Power of Attorney?', to: '/learn/what-is-an-lpa' },
        { title: 'What is a Self-Invested Personal Pension?', to: '/learn/what-is-a-sipp' },
        { title: 'What is Inheritance Tax?', to: '/learn/what-is-inheritance-tax' },
        { title: 'What is Financial Planning?', to: '/learning-centre' },
      ],
      decisions: [
        { title: 'Should I overpay my mortgage?', to: '/learn/should-i-overpay-my-mortgage' },
        { title: 'Should I consolidate my pensions?', to: '/learn/should-i-consolidate-pensions' },
        { title: 'When should I make a will?', to: '/learn/when-should-i-make-a-will' },
        { title: 'Lifetime ISA or ISA?', to: '/learn/should-i-use-a-lisa-or-isa' },
        { title: 'When can I afford to retire?', to: '/learn/when-can-i-afford-to-retire' },
      ],
      stages: [
        { title: 'Starting Out: money basics', to: '/learn/guide/starting-out' },
        { title: 'Building Foundations: first home', to: '/learn/guide/building-foundations' },
        { title: 'Protecting and Growing: family finances', to: '/learn/guide/protecting-and-growing' },
        { title: 'Planning Your Future: retirement', to: '/learn/guide/planning-your-future' },
        { title: 'Enjoying Your Wealth: estate planning', to: '/learn/guide/enjoying-your-wealth' },
      ],
      tax: [
        { title: 'ISA allowance guide', to: '/learn/tax/isa-allowance' },
        { title: 'Pension annual allowance', to: '/learn/tax/pension-annual-allowance' },
        { title: 'Inheritance Tax thresholds', to: '/learn/tax/iht-thresholds' },
        { title: 'Capital gains tax rates', to: '/learn/tax/capital-gains-tax' },
        { title: 'Tax year checklist', to: '/learn/tax/tax-year-checklist' },
      ],
    };
  },

  mounted() {
    document.title = 'Learn — Financial Planning Guides & Education | Fynla';
  },
};
</script>
