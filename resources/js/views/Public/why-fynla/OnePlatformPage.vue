<template>
  <PublicLayout>
    <!-- Hero -->
    <div class="relative flex items-center bg-gradient-to-r from-horizon-500 to-raspberry-500 overflow-hidden">
      <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10 text-left w-full">
        <h1 class="text-4xl md:text-6xl font-black text-white mb-4">
          You Don't Outgrow Fynla
        </h1>
        <p class="text-base sm:text-lg text-white/80 max-w-2xl mx-auto">
          Most financial tools solve one problem. Fynla connects everything — pensions, property, savings, investments, protection, tax, and estate planning — in one platform.
        </p>
      </div>
    </div>

    <!-- The Problem with Fragmented Tools -->
    <section class="py-14 bg-white">
      <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
        <h2 class="text-2xl font-bold text-horizon-500 mb-3">The Problem with Fragmented Tools</h2>
        <p class="text-sm text-neutral-500 leading-relaxed max-w-3xl">
          A pension tracker here, a budgeting app there, a spreadsheet for your investments. None of them talk to each other. You never see the full picture — and that means you can't plan properly.
        </p>
      </div>
    </section>

    <!-- Everything Connected -->
    <section class="py-14 bg-eggshell-500">
      <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
        <h2 class="text-2xl font-bold text-horizon-500 mb-8">Everything Connected</h2>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5">
          <div v-for="mod in modules" :key="mod.name" class="bg-white rounded-lg p-5 shadow-sm border border-light-gray">
            <div class="w-9 h-9 rounded-full flex items-center justify-center mb-3" :class="mod.bgClass">
              <svg class="w-4.5 h-4.5" :class="mod.iconClass" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" :d="mod.icon" />
              </svg>
            </div>
            <h3 class="font-semibold text-horizon-500 mb-1 text-sm">{{ mod.name }}</h3>
            <p class="text-xs text-neutral-500">{{ mod.desc }}</p>
          </div>
        </div>
      </div>
    </section>

    <!-- One Platform, Every Stage -->
    <section class="py-14 bg-white">
      <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
        <h2 class="text-2xl font-bold text-horizon-500 mb-3">One Platform, Every Stage</h2>
        <p class="text-sm text-neutral-500 mb-6 max-w-3xl">
          From your first pay cheque to planning your legacy, Fynla covers every stage of your financial life.
        </p>
        <div class="flex flex-col sm:flex-row flex-wrap gap-3">
          <router-link
            v-for="stage in stages"
            :key="stage.slug"
            :to="`/stage/${stage.slug}`"
            class="inline-flex items-center gap-2 px-4 py-2 bg-eggshell-500 rounded-lg border border-light-gray hover:border-horizon-300 transition-colors text-sm text-horizon-500 font-medium"
          >
            <span class="w-2.5 h-2.5 rounded-full flex-shrink-0" :style="{ backgroundColor: stage.colour }"></span>
            {{ stage.name }}
          </router-link>
        </div>
      </div>
    </section>

    <!-- CTA -->
    <section class="py-14 bg-eggshell-500">
      <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10 text-left w-full">
        <h2 class="text-2xl font-bold text-horizon-500 mb-3">Explore the platform</h2>
        <p class="text-sm text-neutral-500 mb-6 max-w-lg mx-auto">
          See how all seven modules work together with sample data. No sign-up required.
        </p>
        <router-link
          to="/?demo=true"
          class="inline-block px-8 py-3 bg-raspberry-500 text-white text-sm font-semibold rounded-lg hover:bg-raspberry-600 transition-colors"
        >
          Try the Free Demo
        </router-link>
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
          bgClass: 'bg-raspberry-100',
          iconClass: 'text-raspberry-500',
          icon: 'M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z',
        },
        {
          name: 'Savings',
          desc: 'Track your emergency fund, ISAs, and savings accounts in one place.',
          bgClass: 'bg-spring-100',
          iconClass: 'text-spring-600',
          icon: 'M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z',
        },
        {
          name: 'Investment',
          desc: 'Portfolio analysis, Monte Carlo projections, and risk profiling.',
          bgClass: 'bg-violet-100',
          iconClass: 'text-violet-600',
          icon: 'M13 7h8m0 0v8m0-8l-8 8-4-4-6 6',
        },
        {
          name: 'Retirement',
          desc: 'Pension projections, state pension tracking, and retirement readiness.',
          bgClass: 'bg-horizon-100',
          iconClass: 'text-horizon-500',
          icon: 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z',
        },
        {
          name: 'Estate',
          desc: 'Inheritance Tax calculations, nil-rate band tracking, and legacy planning.',
          bgClass: 'bg-savannah-100',
          iconClass: 'text-savannah-600',
          icon: 'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6',
        },
        {
          name: 'Goals & Life Events',
          desc: 'Set financial goals and plan around the milestones that matter to you.',
          bgClass: 'bg-spring-100',
          iconClass: 'text-spring-600',
          icon: 'M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z',
        },
        {
          name: 'Net Worth',
          desc: 'Your complete financial position — assets, liabilities, and property — at a glance.',
          bgClass: 'bg-horizon-100',
          iconClass: 'text-horizon-500',
          icon: 'M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z',
        },
      ],
      stages: [
        { slug: 'starting-out', name: 'Starting Out', colour: '#1D9E75' },
        { slug: 'building-foundations', name: 'Building Foundations', colour: '#5DCAA5' },
        { slug: 'protecting-and-growing', name: 'Protecting and Growing', colour: '#378ADD' },
        { slug: 'planning-your-future', name: 'Planning Your Future', colour: '#7F77DD' },
        { slug: 'enjoying-your-wealth', name: 'Enjoying Your Wealth', colour: '#B8956A' },
      ],
    };
  },

  mounted() {
    document.title = 'One Platform for Your Entire Financial Life | Fynla';
    const meta = document.querySelector('meta[name="description"]');
    if (meta) {
      meta.setAttribute('content', 'Pensions, property, savings, investments, protection, tax, estate — all in one place. Fynla is the financial planning platform you don\'t outgrow.');
    }
  },
};
</script>
