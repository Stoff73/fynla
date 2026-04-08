<template>
  <div class="hero-container bg-light-pink-100 rounded-xl p-6 relative">
    <!-- Minimise/Expand toggle -->
    <button
      @click="toggleCollapsed"
      class="absolute top-3 right-3 w-6 h-6 flex items-center justify-center rounded-md text-neutral-400 hover:text-horizon-500 hover:bg-white/50 transition-colors z-10"
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

    <!-- Expanded: full status bar -->
    <template v-else>
      <!-- Greeting above all panels -->
      <h2 class="text-xl sm:text-2xl md:text-3xl lg:text-4xl font-black text-horizon-500 mb-4 pr-6">{{ greeting }}, {{ firstName }}</h2>

      <!-- Desktop: three-panel row (container-query responsive) -->
      <div class="hero-desktop-layout gap-6 pr-6">

        <!-- LEFT: Scenario Completeness -->
        <div class="flex-1 min-w-0">
          <h4 class="text-lg font-semibold text-horizon-500 mb-2 flex items-center gap-2">
            Scenario Completeness
            <span class="relative group">
              <span class="inline-flex items-center justify-center w-5 h-5 rounded-full bg-horizon-500 text-white text-xs font-bold cursor-help">?</span>
              <span class="absolute left-1/2 -translate-x-1/2 top-7 w-64 bg-horizon-500 text-white text-xs rounded-lg px-3 py-2 shadow-lg opacity-0 group-hover:opacity-100 transition-opacity duration-200 pointer-events-none z-20">
                This shows where you are in your journey. Completing your profile will mean that Fyn can give you more accurate recommendations.
              </span>
            </span>
          </h4>
          <div class="flex items-start gap-4">
            <!-- Progress ring -->
            <div class="flex-shrink-0 relative w-[140px] h-[140px]">
              <svg viewBox="0 0 96 96" class="w-[140px] h-[140px] -rotate-90">
                <circle cx="48" cy="48" r="40" fill="none" stroke-width="6" class="stroke-white/50" />
                <circle cx="48" cy="48" r="40" fill="none" stroke-width="6"
                  :class="progressRingClass"
                  :stroke-dasharray="251.3"
                  :stroke-dashoffset="251.3 - (251.3 * progressPercentage / 100)"
                  stroke-linecap="round" />
              </svg>
              <div class="absolute inset-0 flex items-center justify-center text-2xl md:text-3xl font-extrabold" :class="stageTextClass">
                {{ progressPercentage }}%
              </div>
            </div>

            <!-- Stage info + next step + button (pt-3 aligns with visual top of progress ring) -->
            <div class="flex-1 min-w-0 pt-3">
              <template v-if="!isJourneyComplete">
                <p class="text-sm font-semibold text-horizon-500 mb-0.5">{{ stageLabel }}</p>
                <p class="text-sm text-neutral-500 mb-1">{{ completedCount }} of {{ totalSteps }} steps complete</p>
              </template>

              <!-- Next step -->
              <div v-if="nextStep" class="flex items-center gap-2 mt-2">
                <div
                  class="w-6 h-6 rounded-full flex items-center justify-center text-xs font-bold text-white flex-shrink-0"
                  :class="stageBgClass"
                >
                  {{ nextStepNumber }}
                </div>
                <span class="text-sm text-horizon-500">{{ nextStepTitle }}</span>
              </div>

              <!-- Journey complete -->
              <div v-if="isJourneyComplete" class="flex items-center gap-2 mt-2">
                <div class="w-7 h-7 rounded-full flex items-center justify-center flex-shrink-0 bg-spring-500">
                  <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                  </svg>
                </div>
                <div class="min-w-0">
                  <p class="text-sm font-semibold text-spring-600">Journey complete</p>
                  <p class="text-xs text-neutral-500 mt-0.5">You have completed all onboarding steps.</p>
                </div>
              </div>

              <div class="mt-3 flex items-center gap-4 relative z-10">
                <button
                  v-if="nextStep"
                  class="bg-raspberry-500 text-white px-5 py-2.5 rounded-button text-sm font-bold hover:bg-raspberry-600 transition-colors whitespace-nowrap"
                  @click="continueJourney"
                >
                  Continue Journey
                </button>
                <button
                  class="text-sm font-medium text-raspberry-500 hover:text-raspberry-600 transition-colors"
                  @click="$router.push('/onboarding/welcome')"
                >
                  Start a new journey
                </button>
              </div>
            </div>
          </div>
        </div>

        <!-- MIDDLE: Profile Completeness (progress ring + category links) -->
        <div class="flex flex-shrink-0 w-1/3 flex-col pl-5 status-divider">
          <h4 class="text-lg font-semibold text-horizon-500 mb-2 flex items-center gap-2">
            Profile Completeness
            <span class="relative group">
              <span class="inline-flex items-center justify-center w-5 h-5 rounded-full bg-horizon-500 text-white text-xs font-bold cursor-help">?</span>
              <span class="absolute left-1/2 -translate-x-1/2 top-7 w-64 bg-horizon-500 text-white text-xs rounded-lg px-3 py-2 shadow-lg opacity-0 group-hover:opacity-100 transition-opacity duration-200 pointer-events-none z-20">
                Fyn takes your information and processes the data to provide recommendations on what you should consider to action to help change your financial future.
              </span>
            </span>
          </h4>
          <div class="flex items-start gap-5 flex-1">
            <!-- Progress ring (same size as scenario completeness) -->
            <div class="flex-shrink-0 relative w-[140px] h-[140px]">
              <svg viewBox="0 0 96 96" class="w-[140px] h-[140px] -rotate-90">
                <circle cx="48" cy="48" r="40" fill="none" stroke-width="6" class="stroke-white/50" />
                <circle cx="48" cy="48" r="40" fill="none" stroke-width="6"
                  class="stroke-raspberry-500"
                  :stroke-dasharray="251.3"
                  :stroke-dashoffset="251.3 - (251.3 * overallProfilePercent / 100)"
                  stroke-linecap="round" />
              </svg>
              <div class="absolute inset-0 flex items-center justify-center text-2xl md:text-3xl font-extrabold text-raspberry-500">
                {{ overallProfilePercent }}%
              </div>
            </div>
            <!-- Category links with percentages -->
            <div class="flex flex-col gap-1.5 flex-1 min-w-0 pt-1">
              <router-link
                v-for="cat in categoryCompleteness"
                :key="cat.key"
                :to="cat.route"
                class="group flex items-center justify-between py-1.5 px-2 rounded-md hover:bg-white/50 transition-colors cursor-pointer"
              >
                <span class="text-xs font-medium text-horizon-500 group-hover:text-raspberry-500 transition-colors">{{ cat.label }}</span>
                <span class="text-xs font-bold" :class="cat.percent >= 75 ? 'text-spring-600' : cat.percent >= 25 ? 'text-horizon-400' : 'text-raspberry-500'">{{ cat.percent }}%</span>
              </router-link>
            </div>
          </div>
        </div>

        <!-- RIGHT: Recommended Actions (desktop only) -->
        <div v-if="topActions.length" class="flex flex-shrink-0 w-1/3 flex-col pl-5 status-divider">
          <h4 class="text-lg font-semibold text-horizon-500 mb-2 flex items-center gap-2">
            Fyn's Recommended Actions
            <span class="relative group">
              <span class="inline-flex items-center justify-center w-5 h-5 rounded-full bg-horizon-500 text-white text-xs font-bold cursor-help">?</span>
              <span class="absolute left-1/2 -translate-x-1/2 top-7 w-64 bg-horizon-500 text-white text-xs rounded-lg px-3 py-2 shadow-lg opacity-0 group-hover:opacity-100 transition-opacity duration-200 pointer-events-none z-20">
                Fyn takes your information and processes the data to provide recommendations on what you should consider to action to help change your financial future.
              </span>
            </span>
          </h4>
          <div class="space-y-1.5 pt-1">
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
          <button
            class="mt-3 flex items-center gap-1.5 text-xs font-medium text-raspberry-500 hover:text-raspberry-600 transition-colors"
            @click="$emit('toggle-chat')"
          >
            <img :src="fynIconUrl" alt="Fyn" class="w-4 h-4 rounded-full" />
            Got a question? Ask Fyn
          </button>
        </div>
      </div>

      <!-- Mobile/narrow: swipeable carousel -->
      <div class="hero-mobile-layout">
        <div
          ref="carouselRef"
          class="flex overflow-x-auto snap-x snap-mandatory scrollbar-hide -mx-6 px-6 gap-4"
          @scroll="onCarouselScroll"
        >
          <!-- Slide 1: Scenario Completeness -->
          <div class="snap-center flex-shrink-0 w-full">
            <h4 class="text-lg font-semibold text-horizon-500 mb-2 flex items-center gap-2">
              Scenario Completeness
              <span class="relative group">
                <span class="inline-flex items-center justify-center w-5 h-5 rounded-full bg-horizon-500 text-white text-xs font-bold cursor-help">?</span>
                <span class="absolute left-1/2 -translate-x-1/2 top-7 w-64 bg-horizon-500 text-white text-xs rounded-lg px-3 py-2 shadow-lg opacity-0 group-hover:opacity-100 transition-opacity duration-200 pointer-events-none z-20">
                  This shows where you are in your journey. Completing your profile will mean that Fyn can give you more accurate recommendations.
                </span>
              </span>
            </h4>
            <div class="flex items-start gap-4">
              <!-- Progress ring -->
              <div class="flex-shrink-0 relative w-[140px] h-[140px]">
                <svg viewBox="0 0 96 96" class="w-[140px] h-[140px] -rotate-90">
                  <circle cx="48" cy="48" r="40" fill="none" stroke-width="6" class="stroke-white/50" />
                  <circle cx="48" cy="48" r="40" fill="none" stroke-width="6"
                    :class="progressRingClass"
                    :stroke-dasharray="251.3"
                    :stroke-dashoffset="251.3 - (251.3 * progressPercentage / 100)"
                    stroke-linecap="round" />
                </svg>
                <div class="absolute inset-0 flex items-center justify-center text-2xl font-extrabold" :class="stageTextClass">
                  {{ progressPercentage }}%
                </div>
              </div>

              <!-- Stage info + next step -->
              <div class="flex-1 min-w-0 pt-3">
                <template v-if="!isJourneyComplete">
                  <p class="text-sm font-semibold text-horizon-500 mb-0.5">{{ stageLabel }}</p>
                  <p class="text-sm text-neutral-500 mb-1">{{ completedCount }} of {{ totalSteps }} steps complete</p>
                </template>

                <!-- Next step -->
                <div v-if="nextStep" class="flex items-center gap-2 mt-2">
                  <div
                    class="w-6 h-6 rounded-full flex items-center justify-center text-xs font-bold text-white flex-shrink-0"
                    :class="stageBgClass"
                  >
                    {{ nextStepNumber }}
                  </div>
                  <span class="text-sm text-horizon-500">{{ nextStepTitle }}</span>
                </div>

                <!-- Journey complete -->
                <div v-if="isJourneyComplete" class="flex items-center gap-2 mt-2">
                  <div class="w-7 h-7 rounded-full flex items-center justify-center flex-shrink-0 bg-spring-500">
                    <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                    </svg>
                  </div>
                  <div class="min-w-0">
                    <p class="text-sm font-semibold text-spring-600">Journey complete</p>
                    <p class="text-xs text-neutral-500 mt-0.5">You have completed all onboarding steps.</p>
                  </div>
                </div>
              </div>
            </div>

            <!-- Continue Journey button below the ring row -->
            <div class="mt-3 flex items-center gap-4">
              <button
                v-if="nextStep"
                class="bg-raspberry-500 text-white px-5 py-2.5 rounded-button text-sm font-bold hover:bg-raspberry-600 transition-colors whitespace-nowrap"
                @click="continueJourney"
              >
                Continue Journey
              </button>
              <button
                class="text-sm font-medium text-raspberry-500 hover:text-raspberry-600 transition-colors"
                @click="$router.push('/onboarding/welcome')"
              >
                Start a new journey
              </button>
            </div>
          </div>

          <!-- Slide 2: Profile Completeness -->
          <div class="snap-center flex-shrink-0 w-full">
            <h4 class="text-lg font-semibold text-horizon-500 mb-2 flex items-center gap-2">
              Profile Completeness
              <span class="relative group">
                <span class="inline-flex items-center justify-center w-5 h-5 rounded-full bg-horizon-500 text-white text-xs font-bold cursor-help">?</span>
                <span class="absolute left-1/2 -translate-x-1/2 top-7 w-64 bg-horizon-500 text-white text-xs rounded-lg px-3 py-2 shadow-lg opacity-0 group-hover:opacity-100 transition-opacity duration-200 pointer-events-none z-20">
                  Fyn takes your information and processes the data to provide recommendations on what you should consider to action to help change your financial future.
                </span>
              </span>
            </h4>
            <div class="flex items-start gap-5 flex-1">
              <!-- Progress ring -->
              <div class="flex-shrink-0 relative w-[140px] h-[140px]">
                <svg viewBox="0 0 96 96" class="w-[140px] h-[140px] -rotate-90">
                  <circle cx="48" cy="48" r="40" fill="none" stroke-width="6" class="stroke-white/50" />
                  <circle cx="48" cy="48" r="40" fill="none" stroke-width="6"
                    class="stroke-raspberry-500"
                    :stroke-dasharray="251.3"
                    :stroke-dashoffset="251.3 - (251.3 * overallProfilePercent / 100)"
                    stroke-linecap="round" />
                </svg>
                <div class="absolute inset-0 flex items-center justify-center text-2xl font-extrabold text-raspberry-500">
                  {{ overallProfilePercent }}%
                </div>
              </div>
              <!-- Category links with percentages -->
              <div class="flex flex-col gap-1.5 flex-1 min-w-0 pt-1">
                <router-link
                  v-for="cat in categoryCompleteness"
                  :key="cat.key"
                  :to="cat.route"
                  class="group flex items-center justify-between py-1.5 px-2 rounded-md hover:bg-white/50 transition-colors cursor-pointer"
                >
                  <span class="text-xs font-medium text-horizon-500 group-hover:text-raspberry-500 transition-colors">{{ cat.label }}</span>
                  <span class="text-xs font-bold" :class="cat.percent >= 75 ? 'text-spring-600' : cat.percent >= 25 ? 'text-horizon-400' : 'text-raspberry-500'">{{ cat.percent }}%</span>
                </router-link>
              </div>
            </div>
          </div>

          <!-- Slide 3: Recommended Actions (only if actions exist) -->
          <div v-if="topActions.length" class="snap-center flex-shrink-0 w-full">
            <h4 class="text-lg font-semibold text-horizon-500 mb-2 flex items-center gap-2">
              Fyn's Recommended Actions
              <span class="relative group">
                <span class="inline-flex items-center justify-center w-5 h-5 rounded-full bg-horizon-500 text-white text-xs font-bold cursor-help">?</span>
                <span class="absolute left-1/2 -translate-x-1/2 top-7 w-64 bg-horizon-500 text-white text-xs rounded-lg px-3 py-2 shadow-lg opacity-0 group-hover:opacity-100 transition-opacity duration-200 pointer-events-none z-20">
                  Fyn takes your information and processes the data to provide recommendations on what you should consider to action to help change your financial future.
                </span>
              </span>
            </h4>
            <div class="space-y-1.5 pt-1">
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
            <button
              class="mt-3 flex items-center gap-1.5 text-xs font-medium text-raspberry-500 hover:text-raspberry-600 transition-colors"
              @click="$emit('toggle-chat')"
            >
              <img :src="fynIconUrl" alt="Fyn" class="w-4 h-4 rounded-full" />
              Got a question? Ask Fyn
            </button>
          </div>
        </div>

        <!-- Arrow + dot indicators -->
        <div class="flex justify-center items-center gap-2.5 mt-3">
          <button
            @click="scrollToSlide(Math.max(0, activeSlide - 1))"
            class="text-raspberry-500 p-1"
            aria-label="Previous slide"
          >
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 19l-7-7 7-7"/></svg>
          </button>
          <div class="flex gap-1.5">
            <button
              v-for="(_, index) in carouselSlideCount"
              :key="index"
              @click="scrollToSlide(index)"
              class="w-2 h-2 rounded-full transition-colors"
              :class="activeSlide === index ? 'bg-raspberry-500' : 'bg-neutral-300'"
              :aria-label="'Go to slide ' + (index + 1)"
            />
          </div>
          <button
            @click="scrollToSlide(Math.min(carouselSlideCount - 1, activeSlide + 1))"
            class="text-raspberry-500 p-1"
            aria-label="Next slide"
          >
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"/></svg>
          </button>
        </div>
      </div>
    </template>

  </div>
</template>

<script>
import { mapGetters } from 'vuex';
import storage from '@/utils/storage';
import { fynIconUrl } from '@/constants/fynIcon';

export default {
  name: 'JourneyProgressHero',

  emits: ['toggle-chat'],

  props: {
    suggestedGoals: {
      type: Array,
      default: () => [],
    },
  },

  data() {
    return {
      fynIconUrl,
      heroCollapsed: storage.get('heroCollapsed') === 'true',
      activeSlide: 0,
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

    overallProfilePercent() {
      return this.$store.getters['completeness/overallCompleteness'] || 0;
    },

    categoryCompleteness() {
      const mc = (mod) => this.$store.getters['completeness/moduleCompleteness'](mod);
      const cashMgmt = Math.round((mc('savings') + mc('income')) / 2) || 0;
      const finances = Math.round((mc('investment') + mc('retirement') + mc('property')) / 3) || 0;
      const family = Math.round((mc('protection') + mc('estate')) / 2) || 0;
      const planning = Math.round((mc('goals') + mc('coordination')) / 2) || 0;
      return [
        { label: 'Cash Management', key: 'cash', percent: cashMgmt, route: '/net-worth/cash' },
        { label: 'Finances', key: 'finances', percent: finances, route: '/net-worth/investments' },
        { label: 'Family', key: 'family', percent: family, route: '/protection' },
        { label: 'Planning', key: 'planning', percent: planning, route: '/goals' },
      ];
    },

    carouselSlideCount() {
      return this.topActions.length ? 3 : 2;
    },
  },

  mounted() {
    this.$store.dispatch('plans/fetchPlan', 'protection');
    this.$store.dispatch('plans/fetchPlan', 'investment');
    this.$store.dispatch('completeness/fetchCompleteness');
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

    onCarouselScroll() {
      const el = this.$refs.carouselRef;
      if (!el) return;
      const slideWidth = el.offsetWidth;
      this.activeSlide = Math.round(el.scrollLeft / slideWidth);
    },

    scrollToSlide(index) {
      const el = this.$refs.carouselRef;
      if (!el) return;
      el.scrollTo({ left: index * el.offsetWidth, behavior: 'smooth' });
    },
  },
};
</script>

<style scoped>
.hero-container {
  container-type: inline-size;
}

/* Desktop 3-panel: show when container is wide enough */
.hero-desktop-layout {
  display: none;
}

.hero-mobile-layout {
  display: block;
}

@container (min-width: 850px) {
  .hero-desktop-layout {
    display: flex;
    flex-direction: row;
    align-items: stretch;
  }

  .hero-mobile-layout {
    display: none;
  }
}

@media (max-width: 1500px) {
  .status-divider {
    border-left: none;
  }
}
</style>
