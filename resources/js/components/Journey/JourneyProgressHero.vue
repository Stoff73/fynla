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
        <div class="absolute inset-0 flex items-center justify-center text-xl sm:text-2xl md:text-3xl font-extrabold -mt-[2px]" :class="stageTextClass">
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

        <!-- Continue Journey button (below stage text) -->
        <button
          v-if="nextStep"
          class="mt-4 bg-raspberry-500 text-white px-5 py-2.5 rounded-button text-sm font-bold hover:bg-raspberry-600 transition-colors whitespace-nowrap"
          @click="continueJourney"
        >
          Continue Journey
        </button>
      </div>

      <!-- Right: Recommended Actions (desktop only) -->
      <div v-if="topActions.length && !heroCollapsed" class="hidden lg:block flex-shrink-0 w-1/3 pl-5 ml-2 border-l border-white/40">
        <h4 class="text-sm font-semibold text-horizon-500 mb-2">Recommended Actions</h4>
        <div class="space-y-1.5">
          <router-link
            v-for="action in topActions.slice(0, 3)"
            :key="action.id"
            to="/actions"
            class="group flex items-center gap-2 p-2 rounded-lg cursor-pointer bg-eggshell-500 hover:bg-light-pink-200 transition-colors"
          >
            <div class="w-1.5 h-1.5 rounded-full bg-raspberry-500 flex-shrink-0"></div>
            <span class="text-xs font-medium text-horizon-500 group-hover:text-raspberry-500 truncate transition-colors">{{ action.title }}</span>
          </router-link>
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

    topActions() {
      const priorityOrder = { critical: 0, high: 1, medium: 2, low: 3 };
      const protectionPlan = this.$store.getters['plans/getPlan']('protection');
      const investmentPlan = this.$store.getters['plans/getPlan']('investment');
      const allActions = [
        ...(protectionPlan?.actions || []),
        ...(investmentPlan?.actions || []),
      ];
      return allActions
        .slice()
        .sort((a, b) => (priorityOrder[a.priority] ?? 4) - (priorityOrder[b.priority] ?? 4))
        .slice(0, 3);
    },
  },

  mounted() {
    this.$store.dispatch('plans/fetchPlan', 'protection');
    this.$store.dispatch('plans/fetchPlan', 'investment');
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
