<template>
  <aside class="bg-white border-l border-light-gray h-full overflow-y-auto">
    <div v-if="milestone" class="p-6 space-y-6">

      <!-- Did you know? — stage-coloured gradient card -->
      <div
        :class="gradientCardClasses"
        class="rounded-xl p-5 shadow-sm"
      >
        <div class="flex items-start gap-3 mb-3">
          <svg class="w-5 h-5 flex-shrink-0 mt-0.5" :class="iconColourClass" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z" />
          </svg>
          <h3 class="text-sm font-bold" :class="headingColourClass">Did you know?</h3>
        </div>
        <p class="text-sm leading-relaxed" :class="textColourClass">
          {{ milestone.didYouKnow }}
        </p>
      </div>

      <!-- Why we ask this -->
      <div class="space-y-2">
        <button
          type="button"
          class="w-full flex items-center justify-between text-left"
          @click="toggleSection('whyWeAsk')"
        >
          <h4 class="text-sm font-semibold text-horizon-500">Why we ask this</h4>
          <svg
            class="w-4 h-4 text-neutral-500 transition-transform duration-200"
            :class="{ 'rotate-180': expandedSections.whyWeAsk }"
            fill="none" viewBox="0 0 24 24" stroke="currentColor"
          >
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
          </svg>
        </button>
        <transition name="expand">
          <p v-if="expandedSections.whyWeAsk" class="text-sm text-neutral-500 leading-relaxed pl-0">
            {{ milestone.whyWeAsk }}
          </p>
        </transition>
      </div>

      <!-- How this fits your journey -->
      <div class="space-y-2 border-t border-light-gray pt-4">
        <button
          type="button"
          class="w-full flex items-center justify-between text-left"
          @click="toggleSection('howItFits')"
        >
          <h4 class="text-sm font-semibold text-horizon-500">How this fits your journey</h4>
          <svg
            class="w-4 h-4 text-neutral-500 transition-transform duration-200"
            :class="{ 'rotate-180': expandedSections.howItFits }"
            fill="none" viewBox="0 0 24 24" stroke="currentColor"
          >
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
          </svg>
        </button>
        <transition name="expand">
          <p v-if="expandedSections.howItFits" class="text-sm text-neutral-500 leading-relaxed pl-0">
            {{ milestone.howItFits }}
          </p>
        </transition>
      </div>

      <!-- Quick stat (optional) -->
      <div v-if="milestone.quickStat" class="border-t border-light-gray pt-4">
        <div class="rounded-lg p-4" :class="statBgClass">
          <p class="text-2xl font-bold" :class="statValueClass">
            {{ milestone.quickStat.value }}
          </p>
          <p class="text-xs mt-1" :class="statLabelClass">
            {{ milestone.quickStat.label }}
          </p>
        </div>
      </div>

    </div>

    <!-- Fallback when no milestone data -->
    <div v-else class="p-6">
      <p class="text-sm text-neutral-500">
        Complete each step to learn more about your financial journey.
      </p>
    </div>
  </aside>
</template>

<script>
export default {
  name: 'LearningMilestoneSidebar',

  props: {
    step: {
      type: String,
      required: true,
    },
    stage: {
      type: String,
      required: true,
    },
  },

  data() {
    return {
      collapsed: true,
      expandedSections: {
        whyWeAsk: true,
        howItFits: false,
      },
    };
  },

  computed: {
    milestone() {
      return this.$store.getters['lifeStage/learningMilestone'](this.step);
    },

    // Stage colour mapping:
    // violet for university, spring for early_career, raspberry for mid_career,
    // light-blue for peak, horizon for retirement
    stageColour() {
      const colourMap = {
        university: 'violet',
        early_career: 'spring',
        mid_career: 'raspberry',
        peak: 'light-blue',
        retirement: 'horizon',
      };
      return colourMap[this.stage] || 'violet';
    },

    gradientCardClasses() {
      const map = {
        violet: 'bg-gradient-to-br from-violet-50 to-violet-100 border border-violet-200',
        spring: 'bg-gradient-to-br from-spring-50 to-spring-100 border border-spring-200',
        raspberry: 'bg-gradient-to-br from-raspberry-50 to-raspberry-100 border border-raspberry-200',
        'light-blue': 'bg-gradient-to-br from-light-blue-100 to-horizon-100 border border-horizon-200',
        horizon: 'bg-gradient-to-br from-horizon-50 to-horizon-100 border border-horizon-200',
      };
      return map[this.stageColour] || map.violet;
    },

    iconColourClass() {
      const map = {
        violet: 'text-violet-500',
        spring: 'text-spring-500',
        raspberry: 'text-raspberry-500',
        'light-blue': 'text-light-blue-500',
        horizon: 'text-horizon-500',
      };
      return map[this.stageColour] || 'text-violet-500';
    },

    headingColourClass() {
      const map = {
        violet: 'text-violet-700',
        spring: 'text-spring-700',
        raspberry: 'text-raspberry-700',
        'light-blue': 'text-horizon-500',
        horizon: 'text-horizon-500',
      };
      return map[this.stageColour] || 'text-violet-700';
    },

    textColourClass() {
      const map = {
        violet: 'text-violet-800',
        spring: 'text-spring-800',
        raspberry: 'text-raspberry-800',
        'light-blue': 'text-horizon-500',
        horizon: 'text-horizon-600',
      };
      return map[this.stageColour] || 'text-violet-800';
    },

    statBgClass() {
      const map = {
        violet: 'bg-violet-50',
        spring: 'bg-spring-50',
        raspberry: 'bg-raspberry-50',
        'light-blue': 'bg-light-blue-100',
        horizon: 'bg-horizon-50',
      };
      return map[this.stageColour] || 'bg-violet-50';
    },

    statValueClass() {
      const map = {
        violet: 'text-violet-600',
        spring: 'text-spring-600',
        raspberry: 'text-raspberry-600',
        'light-blue': 'text-light-blue-500',
        horizon: 'text-horizon-500',
      };
      return map[this.stageColour] || 'text-violet-600';
    },

    statLabelClass() {
      const map = {
        violet: 'text-violet-700',
        spring: 'text-spring-700',
        raspberry: 'text-raspberry-700',
        'light-blue': 'text-horizon-400',
        horizon: 'text-horizon-400',
      };
      return map[this.stageColour] || 'text-violet-700';
    },
  },

  watch: {
    step() {
      // Reset accordion sections when step changes
      this.expandedSections = {
        whyWeAsk: true,
        howItFits: false,
      };
    },
  },

  methods: {
    toggleSection(section) {
      this.expandedSections[section] = !this.expandedSections[section];
    },

    toggleCollapsed() {
      this.collapsed = !this.collapsed;
    },
  },
};
</script>

<style scoped>
.expand-enter-active,
.expand-leave-active {
  transition: all 0.25s ease;
  overflow: hidden;
}

.expand-enter-from,
.expand-leave-to {
  opacity: 0;
  max-height: 0;
}

.expand-enter-to,
.expand-leave-from {
  opacity: 1;
  max-height: 500px;
}
</style>
