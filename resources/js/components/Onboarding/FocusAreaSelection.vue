<template>
  <div class="max-w-5xl mx-auto">
    <div class="bg-white rounded-lg border border-light-gray shadow-sm p-6 mb-6">
      <!-- Welcome Header -->
      <div class="flex items-center justify-between mb-8">
        <div>
          <h1 class="text-h2 font-display text-horizon-500 mb-2">
            Where are you in your financial journey?
          </h1>
          <p class="text-body text-neutral-500">
            Choose the stage that best describes where you are right now.
          </p>
        </div>
        <img :src="logoImage" alt="Fynla" class="h-24 w-auto hidden sm:block">
      </div>

      <!-- Life Stage Cards -->
      <div class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-5 gap-4 mb-8">
        <button
          v-for="stage in stages"
          :key="stage.id"
          type="button"
          class="card group text-left transition-all duration-200 hover:shadow-md hover:-translate-y-1 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-violet-500 cursor-pointer"
          :class="stageCardBorderClass(stage)"
          @click="handleStageClick(stage.id)"
        >
          <!-- Stage Icon Circle -->
          <div class="flex justify-center mb-4">
            <div
              class="w-14 h-14 rounded-full flex items-center justify-center"
              :class="stageIconBgClass(stage)"
            >
              <component :is="stageIconComponent(stage)" class="w-7 h-7 text-white" />
            </div>
          </div>

          <!-- Stage Label -->
          <h3 class="text-base font-bold text-horizon-500 text-center mb-1">
            {{ stage.label }}
          </h3>

          <!-- Age Range -->
          <p class="text-sm font-semibold text-center mb-2" :class="stageTextColourClass(stage)">
            Ages {{ stage.ageRange }}
          </p>

          <!-- Tagline -->
          <p class="text-xs text-neutral-500 text-center leading-relaxed">
            {{ stage.tagline }}
          </p>
        </button>
      </div>

      <!-- Divider -->
      <div class="relative mb-6">
        <div class="absolute inset-0 flex items-center">
          <div class="w-full border-t border-light-gray"></div>
        </div>
        <div class="relative flex justify-center">
          <span class="bg-white px-4 text-sm text-neutral-500">or focus on a specific area</span>
        </div>
      </div>

      <!-- Focus Area Pills -->
      <div class="flex flex-wrap justify-center gap-3 mb-6">
        <button
          v-for="area in focusAreas"
          :key="area.id"
          type="button"
          class="inline-flex items-center px-5 py-2.5 rounded-full border border-light-gray text-sm font-medium text-horizon-500 bg-white hover:bg-savannah-100 hover:border-savannah-400 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-violet-500 transition-all duration-200"
          @click="handleFocusClick(area.id)"
        >
          <component :is="focusAreaIcon(area)" class="w-4 h-4 mr-2" :class="focusAreaIconColour(area)" />
          {{ area.label }}
        </button>
      </div>

      <!-- Skip to Dashboard -->
      <div class="text-center">
        <button
          type="button"
          class="inline-flex items-center text-sm font-medium text-neutral-500 hover:text-raspberry-500 transition-colors"
          @click="skipOnboarding"
        >
          Skip to Dashboard
          <svg class="w-4 h-4 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3" />
          </svg>
        </button>
      </div>
    </div>
  </div>
</template>

<script>
import { LIFE_STAGES, STAGE_ORDER } from '@/constants/lifeStageConfig';

export default {
  name: 'FocusAreaSelection',

  emits: ['stage-selected', 'focus-selected', 'selected'],

  data() {
    return {
      logoImage: '/images/logos/LogoHiResFynlaDark.png',
      focusAreas: [
        { id: 'budgeting', label: 'Cash & Budget', colour: 'spring' },
        { id: 'protection', label: 'Protection', colour: 'raspberry' },
        { id: 'investment', label: 'Investment', colour: 'violet' },
        { id: 'retirement', label: 'Retirement', colour: 'horizon' },
      ],
    };
  },

  computed: {
    stages() {
      return STAGE_ORDER.map(id => LIFE_STAGES[id]);
    },
  },

  methods: {
    handleStageClick(stageId) {
      this.$emit('stage-selected', stageId);
    },

    handleFocusClick(areaId) {
      this.$emit('focus-selected', areaId);
      // Backward compatibility with existing flow
      this.$emit('selected', areaId);
    },

    skipOnboarding() {
      this.$router.push({ name: 'Dashboard' });
    },

    stageCardBorderClass(stage) {
      const map = {
        violet: 'hover:border-violet-400',
        spring: 'hover:border-spring-400',
        raspberry: 'hover:border-raspberry-400',
        'light-blue': 'hover:border-light-blue-500',
        horizon: 'hover:border-horizon-400',
      };
      return map[stage.colour] || 'hover:border-violet-400';
    },

    stageIconBgClass(stage) {
      const map = {
        violet: 'bg-gradient-to-br from-violet-400 to-violet-600',
        spring: 'bg-gradient-to-br from-spring-400 to-spring-600',
        raspberry: 'bg-gradient-to-br from-raspberry-400 to-raspberry-600',
        'light-blue': 'bg-gradient-to-br from-light-blue-500 to-horizon-400',
        horizon: 'bg-gradient-to-br from-horizon-400 to-horizon-600',
      };
      return map[stage.colour] || 'bg-gradient-to-br from-violet-400 to-violet-600';
    },

    stageTextColourClass(stage) {
      const map = {
        violet: 'text-violet-500',
        spring: 'text-spring-500',
        raspberry: 'text-raspberry-500',
        'light-blue': 'text-light-blue-500',
        horizon: 'text-horizon-500',
      };
      return map[stage.colour] || 'text-violet-500';
    },

    stageIconComponent(stage) {
      const icons = {
        'graduation-cap': 'IconGraduationCap',
        'briefcase': 'IconBriefcase',
        'shield': 'IconShield',
        'chart-line': 'IconChartLine',
        'sun': 'IconSun',
      };
      return icons[stage.icon] || 'IconGraduationCap';
    },

    focusAreaIcon(area) {
      const icons = {
        budgeting: 'IconWallet',
        protection: 'IconShieldSmall',
        investment: 'IconTrending',
        retirement: 'IconClock',
      };
      return icons[area.id] || 'IconWallet';
    },

    focusAreaIconColour(area) {
      const map = {
        spring: 'text-spring-500',
        raspberry: 'text-raspberry-500',
        violet: 'text-violet-500',
        horizon: 'text-horizon-500',
      };
      return map[area.colour] || 'text-neutral-500';
    },
  },

  components: {
    IconGraduationCap: {
      template: `<svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
        <path stroke-linecap="round" stroke-linejoin="round" d="M12 14l9-5-9-5-9 5 9 5z" />
        <path stroke-linecap="round" stroke-linejoin="round" d="M12 14l6.16-3.422a12.083 12.083 0 01.665 6.479A11.952 11.952 0 0012 20.055a11.952 11.952 0 00-6.824-2.998 12.078 12.078 0 01.665-6.479L12 14z" />
        <path stroke-linecap="round" stroke-linejoin="round" d="M12 14l9-5-9-5-9 5 9 5zm0 0l6.16-3.422a12.083 12.083 0 01.665 6.479A11.952 11.952 0 0012 20.055a11.952 11.952 0 00-6.824-2.998 12.078 12.078 0 01.665-6.479L12 14zm-4 6v-7.5l4-2.222" />
      </svg>`,
    },
    IconBriefcase: {
      template: `<svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
        <path stroke-linecap="round" stroke-linejoin="round" d="M20 7H4a2 2 0 00-2 2v10a2 2 0 002 2h16a2 2 0 002-2V9a2 2 0 00-2-2z" />
        <path stroke-linecap="round" stroke-linejoin="round" d="M16 7V5a2 2 0 00-2-2h-4a2 2 0 00-2 2v2" />
      </svg>`,
    },
    IconShield: {
      template: `<svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
      </svg>`,
    },
    IconChartLine: {
      template: `<svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
        <path stroke-linecap="round" stroke-linejoin="round" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" />
      </svg>`,
    },
    IconSun: {
      template: `<svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
        <path stroke-linecap="round" stroke-linejoin="round" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z" />
      </svg>`,
    },
    IconWallet: {
      template: `<svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
        <path stroke-linecap="round" stroke-linejoin="round" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" />
      </svg>`,
    },
    IconShieldSmall: {
      template: `<svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
      </svg>`,
    },
    IconTrending: {
      template: `<svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
        <path stroke-linecap="round" stroke-linejoin="round" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" />
      </svg>`,
    },
    IconClock: {
      template: `<svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
        <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
      </svg>`,
    },
  },
};
</script>
