<template>
  <PublicLayout>
    <!-- Hero -->
    <section class="bg-gradient-to-r from-horizon-500 to-raspberry-500 py-16 sm:py-20">
      <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
        <h1 class="text-3xl sm:text-4xl font-black text-white mb-3">Learn</h1>
        <p class="text-base text-white/80 max-w-xl mx-auto">
          Understand your finances. Make better decisions.
        </p>
      </div>
    </section>

    <!-- Content -->
    <section class="py-12 bg-eggshell-500">
      <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 space-y-12">

        <!-- Concept Explainers -->
        <div>
          <h2 class="text-lg font-bold text-horizon-500 mb-1">Concept Explainers</h2>
          <p class="text-xs text-neutral-500 mb-4">Clear, jargon-free guides to the building blocks of financial planning.</p>
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <router-link
              v-for="card in explainers"
              :key="card.to"
              :to="card.to"
              class="bg-white rounded-lg border border-light-gray p-4 hover:border-raspberry-300 hover:shadow-sm transition-all group flex items-center justify-between"
            >
              <div>
                <p class="text-sm font-semibold text-horizon-500 group-hover:text-raspberry-500 transition-colors">{{ card.title }}</p>
                <p class="text-xs text-neutral-500 mt-0.5">{{ card.desc }}</p>
              </div>
              <svg class="w-4 h-4 text-neutral-400 group-hover:text-raspberry-500 flex-shrink-0 ml-3 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
              </svg>
            </router-link>
          </div>
        </div>

        <!-- Decision Guides -->
        <div>
          <h2 class="text-lg font-bold text-horizon-500 mb-1">Decision Guides</h2>
          <p class="text-xs text-neutral-500 mb-4">Practical frameworks for the big money questions.</p>
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <router-link
              v-for="card in decisions"
              :key="card.to"
              :to="card.to"
              class="bg-white rounded-lg border border-light-gray p-4 hover:border-raspberry-300 hover:shadow-sm transition-all group flex items-center justify-between"
            >
              <div>
                <p class="text-sm font-semibold text-horizon-500 group-hover:text-raspberry-500 transition-colors">{{ card.title }}</p>
                <p class="text-xs text-neutral-500 mt-0.5">{{ card.desc }}</p>
              </div>
              <svg class="w-4 h-4 text-neutral-400 group-hover:text-raspberry-500 flex-shrink-0 ml-3 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
              </svg>
            </router-link>
          </div>
        </div>

        <!-- Life Stage Guides -->
        <div>
          <h2 class="text-lg font-bold text-horizon-500 mb-1">Life Stage Guides</h2>
          <p class="text-xs text-neutral-500 mb-4">Step-by-step guides tailored to where you are in life.</p>
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <router-link
              to="/learn/guide/starting-out"
              class="bg-white rounded-lg border border-light-gray p-4 hover:border-raspberry-300 hover:shadow-sm transition-all group flex items-center justify-between"
            >
              <div>
                <p class="text-sm font-semibold text-horizon-500 group-hover:text-raspberry-500 transition-colors">Starting Out</p>
                <p class="text-xs text-neutral-500 mt-0.5">Your first steps in financial planning.</p>
              </div>
              <svg class="w-4 h-4 text-neutral-400 group-hover:text-raspberry-500 flex-shrink-0 ml-3 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
              </svg>
            </router-link>
            <div class="bg-white rounded-lg border border-light-gray border-dashed p-4 flex items-center justify-between opacity-60">
              <div>
                <p class="text-sm font-semibold text-neutral-400">More guides coming soon</p>
                <p class="text-xs text-neutral-400 mt-0.5">Building Foundations, Protecting and Growing, and more.</p>
              </div>
            </div>
          </div>
        </div>

        <!-- Glossary -->
        <div>
          <h2 class="text-lg font-bold text-horizon-500 mb-1">Glossary A&ndash;Z</h2>
          <p class="text-xs text-neutral-500 mb-4">Financial terms explained in plain English.</p>
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <router-link
              to="/learn/glossary"
              class="bg-white rounded-lg border border-light-gray p-4 hover:border-raspberry-300 hover:shadow-sm transition-all group flex items-center justify-between"
            >
              <div>
                <p class="text-sm font-semibold text-horizon-500 group-hover:text-raspberry-500 transition-colors">Financial Planning Glossary</p>
                <p class="text-xs text-neutral-500 mt-0.5">Plain English definitions from A to Z.</p>
              </div>
              <svg class="w-4 h-4 text-neutral-400 group-hover:text-raspberry-500 flex-shrink-0 ml-3 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
              </svg>
            </router-link>
          </div>
        </div>

      </div>
    </section>

    <!-- CTA -->
    <section class="py-12 bg-white">
      <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
        <h2 class="text-xl font-bold text-horizon-500 mb-2">Ready to put knowledge into action?</h2>
        <p class="text-sm text-neutral-500 mb-6">Try Fynla free and see your full financial picture in minutes.</p>
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
  name: 'LearnHubPage',
  components: { PublicLayout },

  data() {
    return {
      explainers: [
        { title: 'What is an ISA?', desc: 'Tax-free savings and investments, explained simply.', to: '/learn/what-is-an-isa' },
        { title: 'What is Pension Drawdown?', desc: 'How drawing income from your pension works.', to: '/learn/what-is-drawdown' },
      ],
      decisions: [
        { title: 'Should I Overpay My Mortgage?', desc: 'When it makes sense and when it doesn\'t.', to: '/learn/should-i-overpay-my-mortgage' },
        { title: 'Should I Consolidate My Pensions?', desc: 'Simplify your pots without losing valuable benefits.', to: '/learn/should-i-consolidate-pensions' },
      ],
    };
  },

  mounted() {
    document.title = 'Learn — Financial Planning Guides & Education | Fynla';
  },
};
</script>
