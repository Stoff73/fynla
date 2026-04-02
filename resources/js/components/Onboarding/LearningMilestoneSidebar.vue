<template>
  <aside class="h-full">
    <div v-if="milestone">

      <!-- How this fits your journey -->
      <div ref="journeySection" class="pb-4 border-b border-light-gray/50" :class="{ 'mb-5': !journeyExpanded }">
        <h3 class="text-lg font-bold text-horizon-500 mb-2">How this fits your journey</h3>
        <p v-if="journeyExpanded" class="text-sm text-neutral-500 leading-relaxed">
          {{ milestone.howItFits }}
          <button type="button" class="text-raspberry-500 hover:text-raspberry-700 font-medium ml-1" @click="collapseJourney">Read less</button>
        </p>
        <p v-else class="text-sm text-neutral-500 leading-relaxed journey-truncated">
          {{ milestone.howItFits }}
        </p>
        <button
          v-if="!journeyExpanded && milestone.howItFits && milestone.howItFits.length > 60"
          type="button"
          class="text-sm text-raspberry-500 hover:text-raspberry-700 font-medium mt-1"
          @click="expandJourney"
        >Read more</button>
      </div>

      <!-- Why we ask this — hidden when journey is expanded -->
      <div
        v-if="!journeyExpanded"
        class="why-box-wrapper flex items-start transition-all duration-300 ease-out"
        :style="whyBoxStyle"
      >
        <!-- Arrow element (hidden on mobile) -->
        <div class="hidden lg:block flex-shrink-0 -ml-6 mt-5">
          <svg width="12" height="22" viewBox="0 0 12 22" class="text-horizon-500">
            <polygon points="12,0 0,11 12,22" fill="currentColor" />
          </svg>
        </div>
        <div class="flex-1 bg-white rounded-lg p-4 border-2 border-horizon-500">
          <h4 class="text-lg font-bold text-horizon-500 mb-2">Why we ask this</h4>
          <p class="text-sm text-horizon-500/80 leading-relaxed">
            {{ activeWhyText }}
          </p>
        </div>
      </div>

    </div>

    <!-- Fallback -->
    <div v-else>
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
    override: {
      type: Object,
      default: null,
    },
  },

  data() {
    return {
      collapsed: true,
      journeyExpanded: false,
      expandedSections: {
        whyWeAsk: true,
        howItFits: false,
      },
    };
  },

  watch: {
    override(newVal) {
      // When a field ? icon or focus triggers an update, collapse journey and show why box
      if (newVal?.whyWeAsk) {
        this.journeyExpanded = false;
      }
    },
    step() {
      this.journeyExpanded = false;
      this.expandedSections = { whyWeAsk: true, howItFits: false };
    },
  },

  computed: {
    milestone() {
      return this.$store.getters['lifeStage/learningMilestone']?.(this.step);
    },

    activeWhyText() {
      if (this.override?.whyWeAsk) return this.override.whyWeAsk;
      return this.milestone?.whyWeAsk || '';
    },

    whyBoxStyle() {
      const offsetY = this.override?.fieldOffsetY;
      if (!offsetY || offsetY <= 0) return {};

      // fieldOffsetY = input centre relative to form column top (includes form padding)
      // The sidebar has the same padding, then the "How this fits" section, then the why-box.
      // We want the why-box arrow (at mt-5 = 20px from box top) to align with fieldOffsetY.
      const journeySectionHeight = this.$refs.journeySection?.offsetHeight || 100;
      // Natural Y of why-box top = journeySectionHeight + gap(20px from mb-5)
      const naturalTop = journeySectionHeight + 20;
      // Arrow sits at 20px from box top (mt-5 on the arrow div)
      const arrowY = naturalTop + 20;
      // How much to push down so arrow aligns with field centre
      const topPx = Math.max(0, offsetY - arrowY);
      return { marginTop: topPx + 'px' };
    },

    truncatedHowItFits() {
      const text = this.milestone?.howItFits || '';
      // Show first 2 sentences
      const sentences = text.match(/[^.!?]+[.!?]+/g);
      if (!sentences || sentences.length <= 2) return text;
      return sentences.slice(0, 2).join('').trim() + '..';
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


  methods: {
    expandJourney() {
      this.journeyExpanded = true;
    },

    collapseJourney() {
      this.journeyExpanded = false;
    },

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

/* 2-line clamp for collapsed journey text */
.journey-truncated {
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}

</style>
