<template>
  <PublicLayout>
    <!-- Hero -->
    <div class="relative flex items-center bg-gradient-to-r from-horizon-500 to-raspberry-500 overflow-hidden">
      <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10 text-left w-full">
        <h1 class="text-4xl md:text-6xl font-black text-white mb-4">
          One<br/>
          <span class="text-raspberry-300">platform</span>
        </h1>
        <p class="text-xl text-white/90 font-semibold mb-2">You don't outgrow Fynla</p>
        <p class="text-lg text-white/70">
          Most financial tools solve one problem. Fynla connects everything — pensions, property, savings, investments, protection, tax, and estate planning — in one platform.
        </p>
      </div>
    </div>

    <!-- The problem with fragmented tools -->
    <section class="py-14 bg-white">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <h2 class="text-xl sm:text-2xl md:text-3xl lg:text-4xl font-bold text-horizon-500 mb-3">The problem with fragmented tools</h2>
        <p class="text-sm text-neutral-500 leading-relaxed">
          A pension tracker here, a budgeting app there, a spreadsheet for your investments. None of them talk to each other. You never see the full picture — and that means you can't plan properly.
        </p>
      </div>
    </section>

    <!-- Everything connected -->
    <section class="py-14 bg-gradient-to-r from-horizon-600 to-horizon-700">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <h2 class="text-xl sm:text-2xl md:text-3xl lg:text-4xl font-bold text-white mb-8 text-center">Everything connected</h2>
        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4">
          <div v-for="mod in modules" :key="mod.name" class="bg-horizon-500 rounded-card p-5">
            <div class="w-10 h-10 rounded-full bg-raspberry-500 flex items-center justify-center mb-4">
              <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" :d="mod.icon" />
              </svg>
            </div>
            <h3 class="text-white mb-2">{{ mod.name }}</h3>
            <p class="text-sm text-horizon-300 leading-relaxed">{{ mod.desc }}</p>
          </div>
        </div>
        <div class="mt-8 text-center">
          <router-link to="/features" class="text-sm text-white font-medium hover:text-light-pink-200 transition-colors">
            View all features &rarr;
          </router-link>
        </div>
      </div>
    </section>

    <!-- Your personal finance journey on one platform -->
    <section class="py-14 bg-light-pink-100">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <h2 class="text-xl sm:text-2xl md:text-3xl lg:text-4xl font-bold text-horizon-500 mb-3 text-center">Your personal finance journey on one platform</h2>
        <p class="text-sm text-neutral-500 mb-8 text-center">
          From your first pay cheque to planning your legacy, Fynla covers every stage of your financial life.
        </p>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-6">
          <router-link
            v-for="stage in stages"
            :key="stage.slug"
            :to="`/stage/${stage.slug}`"
            class="lg:aspect-square bg-gradient-to-br from-horizon-600 to-horizon-700 rounded-card border border-white/10 shadow-sm p-5 flex flex-col items-start justify-center cursor-pointer hover:border-white/30 hover:-translate-y-0.5 transition-all duration-200 no-underline"
          >
            <p class="text-2xl font-bold text-white mb-1 leading-tight"><span class="text-raspberry-400">{{ stage.firstName }}</span><br/>{{ stage.secondName }}</p>
            <p class="text-xs text-white/70 leading-snug">{{ stage.desc }}</p>
          </router-link>
        </div>
      </div>
    </section>

    <!-- Explore the platform -->
    <section class="py-14 bg-eggshell-500">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
        <h2 class="text-xl sm:text-2xl md:text-3xl lg:text-4xl font-bold text-horizon-500 mb-3">Explore the platform</h2>
        <p class="text-sm text-neutral-500 mb-6">
          See how all seven modules work together with sample data. No sign-up required.
        </p>
        <a
          href="#" @click.prevent="$router.push({ query: { demo: 'true' } })"
          class="inline-block px-8 py-3 bg-raspberry-500 text-white text-sm font-semibold rounded-lg hover:bg-raspberry-600 transition-colors"
        >
          Try the free demo
        </a>
      </div>
    </section>
  </PublicLayout>
</template>

<script>
import PublicLayout from '@/layouts/PublicLayout.vue';

export default {
  name: 'OnePlatformPage',

  components: {
    PublicLayout,
  },

  data() {
    return {
      modules: [
        {
          name: 'Protection',
          desc: 'Analyse your life cover, critical illness, and income protection gaps.',
          icon: 'M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z',
        },
        {
          name: 'Savings',
          desc: 'Track your emergency fund, ISAs, and savings accounts in one place.',
          icon: 'M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z',
        },
        {
          name: 'Investment',
          desc: 'Portfolio analysis, Monte Carlo projections, and risk profiling.',
          icon: 'M13 7h8m0 0v8m0-8l-8 8-4-4-6 6',
        },
        {
          name: 'Retirement',
          desc: 'Pension projections, state pension tracking, and retirement readiness.',
          icon: 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z',
        },
        {
          name: 'Estate',
          desc: 'Inheritance Tax calculations, nil-rate band tracking, and legacy planning.',
          icon: 'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6',
        },
        {
          name: 'Goals & life events',
          desc: 'Set financial goals and plan around the milestones that matter to you.',
          icon: 'M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z',
        },
        {
          name: 'Net worth',
          desc: 'Your complete financial position — assets, liabilities, and property — at a glance.',
          icon: 'M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z',
        },
      ],
      stages: [
        { slug: 'starting-out', firstName: 'Starting', secondName: 'out', desc: 'Build strong financial habits for your future.', colour: '#1D9E75' },
        { slug: 'building-foundations', firstName: 'Building', secondName: 'foundations', desc: 'Save, invest, and grow your wealth with confidence.', colour: '#5DCAA5' },
        { slug: 'protecting-and-growing', firstName: 'Protecting', secondName: 'and growing', desc: 'Ensure your family and assets are fully covered.', colour: '#378ADD' },
        { slug: 'planning-your-future', firstName: 'Planning', secondName: 'your future', desc: 'Retirement projections, pension tracking, and estate planning.', colour: '#7F77DD' },
        { slug: 'enjoying-your-wealth', firstName: 'Enjoying', secondName: 'your wealth', desc: 'Make the most of your financial success.', colour: '#B8956A' },
      ],
    };
  },

  mounted() {
    document.title = 'One platform for your entire financial life | Fynla';
    const meta = document.querySelector('meta[name="description"]');
    if (meta) {
      meta.setAttribute('content', 'Pensions, property, savings, investments, protection, tax, estate — all in one place. Fynla is the financial planning platform you don\'t outgrow.');
    }
  },
};
</script>
