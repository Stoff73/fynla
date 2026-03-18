<template>
  <div class="bg-white rounded-lg border border-light-gray shadow-sm p-6">
    <!-- Main hero row: greeting + progress bar + CTA -->
    <div class="flex flex-col sm:flex-row sm:items-center gap-4 sm:gap-6">
      <!-- Left: Greeting + stage label + step count -->
      <div class="flex-1 min-w-0">
        <h2 class="text-lg font-black text-horizon-500 truncate">{{ greeting }}, {{ firstName }}</h2>
        <p class="text-sm text-neutral-500 mt-0.5">
          <span :class="stageTextClass" class="font-semibold">{{ stageLabel }}</span>
          <span class="mx-1.5">&middot;</span>
          <span>{{ completedCount }} of {{ totalSteps }} steps complete</span>
        </p>
      </div>

      <!-- Centre: Progress bar -->
      <div class="w-full sm:w-48 flex-shrink-0">
        <div class="flex justify-between text-xs mb-1">
          <span class="text-neutral-500">Journey</span>
          <span :class="stageTextClass" class="font-bold">{{ progressPercentage }}%</span>
        </div>
        <div class="h-1.5 bg-eggshell-500 rounded-full overflow-hidden">
          <div
            class="h-full rounded-full transition-all duration-500"
            :class="progressBarClass"
            :style="{ width: progressPercentage + '%' }"
          ></div>
        </div>
      </div>

      <!-- Right: Continue Journey button (when steps remain) -->
      <button
        v-if="nextStep"
        class="flex-shrink-0 bg-raspberry-500 text-white px-4 py-2 rounded-button text-sm font-bold hover:bg-raspberry-600 transition-colors whitespace-nowrap"
        @click="continueJourney"
      >
        Continue Journey
      </button>
    </div>

    <!-- Next step CTA row (below the main row, when steps remain) -->
    <div
      v-if="nextStep && nextStepMilestone"
      class="mt-4 pt-4 border-t border-light-gray flex flex-col sm:flex-row sm:items-center gap-3"
    >
      <!-- Step number circle -->
      <div
        class="w-7 h-7 rounded-full flex items-center justify-center text-xs font-bold text-white flex-shrink-0"
        :class="stageBgClass"
      >
        {{ nextStepNumber }}
      </div>

      <!-- Step info -->
      <div class="flex-1 min-w-0">
        <p class="text-sm font-semibold text-horizon-500">
          Next: {{ nextStepTitle }}
        </p>
        <p v-if="nextStepMilestone.didYouKnow" class="text-xs text-neutral-500 mt-0.5 line-clamp-1">
          {{ truncateText(nextStepMilestone.didYouKnow, 120) }}
        </p>
      </div>

      <!-- Continue button for the next step -->
      <button
        class="flex-shrink-0 text-sm font-semibold hover:underline whitespace-nowrap"
        :class="stageTextClass"
        @click="continueJourney"
      >
        Continue
      </button>
    </div>

    <!-- Journey complete message (all steps done) -->
    <div
      v-if="isJourneyComplete"
      class="mt-4 pt-4 border-t border-light-gray flex flex-col sm:flex-row sm:items-center gap-3"
    >
      <div
        class="w-7 h-7 rounded-full flex items-center justify-center flex-shrink-0 bg-spring-500"
      >
        <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
        </svg>
      </div>
      <div class="flex-1 min-w-0">
        <p class="text-sm font-semibold text-spring-600">
          Journey complete
        </p>
        <p class="text-xs text-neutral-500 mt-0.5">
          You have completed all onboarding steps. Explore your dashboard to review your financial plan.
        </p>
      </div>
    </div>
  </div>
</template>

<script>
import { mapGetters } from 'vuex';

export default {
  name: 'JourneyProgressHero',

  computed: {
    ...mapGetters('auth', { currentUser: 'currentUser' }),
    ...mapGetters('lifeStage', [
      'stageLabel',
      'stageColour',
      'progressPercentage',
      'onboardingSteps',
      'nextStep',
      'learningMilestone',
    ]),

    firstName() {
      return this.currentUser?.first_name || 'there';
    },

    greeting() {
      const hour = new Date().getHours();
      if (hour >= 5 && hour < 12) return 'Good morning';
      if (hour >= 12 && hour < 17) return 'Good afternoon';
      return 'Good evening';
    },

    completedCount() {
      const allCompleted = this.$store.getters['lifeStage/allCompletedSteps'] || [];
      const stageSteps = this.onboardingSteps || [];
      return allCompleted.filter(s => stageSteps.includes(s)).length;
    },

    totalSteps() {
      return this.onboardingSteps?.length || 0;
    },

    isJourneyComplete() {
      return this.totalSteps > 0 && !this.nextStep;
    },

    nextStepNumber() {
      if (!this.nextStep || !this.onboardingSteps) return 1;
      return this.onboardingSteps.indexOf(this.nextStep) + 1;
    },

    nextStepTitle() {
      if (!this.nextStep) return '';
      // Convert step ID to readable title
      const titles = {
        'personal-info': 'About You',
        'student-loan': 'Student Loan',
        'income': 'Income',
        'income-career': 'Income & Career',
        'expenditure': 'Spending',
        'savings': 'Savings',
        'savings-emergency': 'Savings & Emergency Fund',
        'first-home-lisa': 'First Home & Lifetime ISA',
        'pension-auto-enrolment': 'Pension & Auto-enrolment',
        'investments': 'Investments',
        'goals': 'Goals',
        'family': 'Family',
        'property-mortgage': 'Property & Mortgage',
        'protection-insurance': 'Protection & Insurance',
        'pensions': 'Pensions',
        'pension-review': 'Pension Review',
        'will-estate': 'Will & Estate',
        'estate-iht': 'Estate & Inheritance Tax',
        'income-tax': 'Income & Tax',
        'investments-isa': 'Investments & ISA',
        'property-portfolio': 'Property Portfolio',
        'estate-legacy': 'Estate & Legacy',
        'pension-drawdown': 'Pension & Drawdown',
        'state-pension': 'State Pension',
      };
      return titles[this.nextStep] || this.nextStep.replace(/-/g, ' ').replace(/\b\w/g, c => c.toUpperCase());
    },

    nextStepMilestone() {
      if (!this.nextStep) return null;
      return this.learningMilestone(this.nextStep);
    },

    progressBarClass() {
      const colour = this.stageColour || 'raspberry';
      // Map stage colours to gradient classes
      const gradients = {
        violet: 'bg-gradient-to-r from-violet-500 to-violet-400',
        spring: 'bg-gradient-to-r from-spring-500 to-spring-400',
        raspberry: 'bg-gradient-to-r from-raspberry-500 to-raspberry-400',
        'light-blue': 'bg-gradient-to-r from-light-blue-500 to-violet-400',
        horizon: 'bg-gradient-to-r from-horizon-500 to-horizon-400',
      };
      return gradients[colour] || 'bg-gradient-to-r from-raspberry-500 to-raspberry-400';
    },

    stageTextClass() {
      const map = {
        violet: 'text-violet-500',
        spring: 'text-spring-500',
        raspberry: 'text-raspberry-500',
        'light-blue': 'text-light-blue-500',
        horizon: 'text-horizon-500',
      };
      return map[this.stageColour] || 'text-raspberry-500';
    },

    stageBgClass() {
      const map = {
        violet: 'bg-violet-500',
        spring: 'bg-spring-500',
        raspberry: 'bg-raspberry-500',
        'light-blue': 'bg-light-blue-500',
        horizon: 'bg-horizon-500',
      };
      return map[this.stageColour] || 'bg-raspberry-500';
    },
  },

  methods: {
    continueJourney() {
      if (this.nextStep) {
        this.$router.push({ path: '/onboarding', query: { step: this.nextStep } });
      }
    },

    truncateText(text, maxLength) {
      if (!text || text.length <= maxLength) return text;
      return text.substring(0, maxLength).trim() + '...';
    },
  },
};
</script>
