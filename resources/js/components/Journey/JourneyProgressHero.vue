<template>
  <div class="bg-light-pink-100 rounded-xl p-6 relative">
    <!-- Minimise/Expand toggle -->
    <button
      @click="toggleCollapsed"
      class="absolute top-3 right-3 w-6 h-6 flex items-center justify-center rounded-md text-neutral-400 hover:text-horizon-500 hover:bg-white/50 transition-colors"
      :title="heroCollapsed ? 'Expand' : 'Minimise'"
    >
      <svg class="w-4 h-4 transition-transform duration-200" :class="{ 'rotate-180': heroCollapsed }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7" />
      </svg>
    </button>

    <!-- Collapsed: slim bar with greeting + percentage -->
    <div v-if="heroCollapsed" class="flex items-center gap-3 pr-8">
      <h2 class="text-lg font-bold text-horizon-500">{{ greeting }}, {{ firstName }}</h2>
      <span class="text-sm font-extrabold" :class="stageTextClass">{{ progressPercentage }}%</span>
      <span class="text-sm text-neutral-500">{{ stageLabel }}</span>
    </div>

    <!-- Expanded: full hero -->
    <template v-else>
    <!-- Main hero row: progress ring + greeting + step info + CTA -->
    <div class="flex flex-col sm:flex-row sm:items-start gap-4 sm:gap-6 pr-6">
      <!-- Left: Circular progress ring -->
      <div class="flex-shrink-0 relative w-[160px] h-[160px]">
        <svg viewBox="0 0 96 96" class="w-[160px] h-[160px] -rotate-90">
          <circle cx="48" cy="48" r="40" fill="none" stroke-width="6" class="stroke-white/50" />
          <circle cx="48" cy="48" r="40" fill="none" stroke-width="6"
            :class="progressRingClass"
            :stroke-dasharray="251.3"
            :stroke-dashoffset="251.3 - (251.3 * progressPercentage / 100)"
            stroke-linecap="round" />
        </svg>
        <div class="absolute inset-0 flex items-center justify-center text-xl sm:text-2xl md:text-3xl lg:text-4xl font-extrabold" :class="stageTextClass">
          {{ progressPercentage }}%
        </div>
      </div>

      <!-- Centre: Greeting + stage label + next step -->
      <div class="flex-1 min-w-0">
        <h2 class="text-xl sm:text-2xl md:text-3xl lg:text-4xl font-black text-horizon-500 -mt-[4px]">{{ greeting }}, {{ firstName }}</h2>
        <p class="text-sm text-neutral-500 mt-1">
          <span class="font-semibold text-horizon-500">{{ stageLabel }}</span>
          <span class="mx-1.5">&middot;</span>
          <span>{{ completedCount }} of {{ totalSteps }} steps complete</span>
        </p>

        <!-- Next step info (merged into same card) -->
        <div v-if="nextStep" class="flex items-center gap-2 mt-3">
          <div
            class="w-6 h-6 rounded-full flex items-center justify-center text-xs font-bold text-white flex-shrink-0"
            :class="stageBgClass"
          >
            {{ nextStepNumber }}
          </div>
          <span class="text-sm text-horizon-500">{{ nextStepTitle }}</span>
        </div>

        <!-- Journey complete message (inline, all steps done) -->
        <div v-if="isJourneyComplete" class="flex items-center gap-2 mt-2">
          <div class="w-7 h-7 rounded-full flex items-center justify-center flex-shrink-0 bg-spring-500">
            <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
            </svg>
          </div>
          <div class="min-w-0">
            <p class="text-sm font-semibold text-spring-600">Journey complete</p>
            <p class="text-xs text-neutral-500 mt-0.5">You have completed all onboarding steps. Explore your dashboard to review your financial plan.</p>
          </div>
        </div>
      </div>

      <!-- Right: Continue Journey button -->
      <button
        v-if="nextStep"
        class="flex-shrink-0 bg-raspberry-500 text-white px-5 py-2.5 rounded-button text-sm font-bold hover:bg-raspberry-600 transition-colors whitespace-nowrap"
        @click="continueJourney"
      >
        Continue Journey
      </button>

      <!-- Right: Suggested for You (desktop only) -->
      <div v-if="suggestedGoals.length && !heroCollapsed" class="hidden lg:block flex-shrink-0 w-64 pl-5 ml-2">
        <h4 class="text-sm font-semibold text-horizon-500 mb-2">Suggested for You</h4>
        <div class="space-y-1.5">
          <div
            v-for="suggestion in suggestedGoals.slice(0, 3)"
            :key="suggestion.id"
            class="group flex items-center gap-2 p-2 rounded-lg cursor-pointer bg-eggshell-500 hover:bg-light-pink-200 transition-colors"
            @click="$emit('suggested-goal', suggestion)"
          >
            <div class="w-5 h-5 rounded-full bg-violet-100 flex items-center justify-center flex-shrink-0">
              <svg class="w-3 h-3 text-violet-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z" />
              </svg>
            </div>
            <span class="text-xs font-medium text-horizon-500 group-hover:text-raspberry-500 truncate transition-colors">{{ suggestion.label }}</span>
          </div>
        </div>
      </div>
    </div>
    </template>

  </div>
</template>

<script>
import { mapGetters } from 'vuex';
import storage from '@/utils/storage';

export default {
  name: 'JourneyProgressHero',

  emits: ['suggested-goal'],

  props: {
    suggestedGoals: {
      type: Array,
      default: () => [],
    },
  },

  data() {
    return {
      heroCollapsed: storage.get('heroCollapsed') === 'true',
    };
  },

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
      const completeness = this.$store.getters['lifeStage/stepCompleteness'] || {};
      const stageSteps = this.onboardingSteps || [];
      return stageSteps.filter(s => completeness[s]?.status === 'complete').length;
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

    progressRingClass() {
      return 'stroke-raspberry-500';
    },

    stageTextClass() {
      return 'text-raspberry-500';
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

    toggleCollapsed() {
      this.heroCollapsed = !this.heroCollapsed;
      storage.set('heroCollapsed', this.heroCollapsed);
    },
  },
};
</script>
