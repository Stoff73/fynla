<template>
  <PullToRefresh @refresh="refreshDashboard">
    <div class="px-4 pt-4 pb-6 space-y-4">
      <!-- Biometric setup banner (shown until user enables Face ID) -->
      <BiometricPrompt
        v-if="showBiometricPrompt"
        @close="dismissBiometricPrompt"
      />

      <!-- Compact Journey Progress (shown when a life stage is set) -->
      <div v-if="currentStage" class="bg-white rounded-xl border border-light-gray shadow-sm p-4">
        <div class="flex items-center justify-between mb-2">
          <div class="min-w-0">
            <h2 class="text-lg font-bold text-horizon-500 truncate">{{ greeting }}, {{ firstName }}</h2>
            <p class="text-xs text-neutral-500 mt-0.5">
              <span :class="stageColourTextClass" class="font-semibold">{{ stageLabel }}</span>
              <span class="mx-1">&middot;</span>
              <span>{{ progressPercentage }}% complete</span>
            </p>
          </div>
          <button
            v-if="nextStep"
            class="flex-shrink-0 bg-raspberry-500 text-white px-3 py-1.5 rounded-button text-xs font-bold hover:bg-raspberry-600 transition-colors whitespace-nowrap ml-3"
            @click="continueJourney"
          >
            Continue
          </button>
        </div>
        <!-- Compact progress bar -->
        <div class="h-1.5 bg-eggshell-500 rounded-full overflow-hidden">
          <div
            class="h-full rounded-full transition-all duration-500"
            :class="progressBarClass"
            :style="{ width: progressPercentage + '%' }"
          ></div>
        </div>
      </div>

      <!-- Greeting (only when no life stage is set) -->
      <div v-else>
        <h2 class="text-xl font-bold text-horizon-500">{{ greeting }}, {{ firstName }}</h2>
        <p class="text-sm text-neutral-500 mt-0.5">Here's your financial snapshot</p>
      </div>

      <!-- Loading skeleton -->
      <template v-if="loading && !hasData">
        <div class="bg-savannah-100 animate-pulse rounded-xl h-32"></div>
        <div class="bg-savannah-100 animate-pulse rounded-xl h-20"></div>
        <div class="grid grid-cols-2 gap-3">
          <div v-for="i in 4" :key="i" class="bg-savannah-100 animate-pulse rounded-xl h-24"></div>
        </div>
      </template>

      <!-- Content -->
      <template v-else-if="hasData">
        <MobileNetWorthCard
          :net-worth="netWorth?.total || 0"
        />

        <FynInsightCard
          v-if="insight"
          :insight="insight"
        />

        <MobileAlertsList
          v-if="alerts && alerts.length"
          :alerts="alerts"
        />

        <div class="grid grid-cols-2 gap-3">
          <ModuleSummaryCard
            v-for="mod in filteredModules"
            :key="mod.name"
            :module-data="mod"
            @click="navigateToModule(mod.name)"
          />
        </div>
      </template>

      <!-- Empty state -->
      <div v-else class="text-center py-12">
        <img :src="'/images/logos/favicon.png'" alt="Fynla" class="w-16 h-16 mx-auto mb-4 opacity-50" />
        <p class="text-neutral-500">Welcome to Fynla! Your financial data will appear here once added.</p>
      </div>
    </div>

  </PullToRefresh>
</template>

<script>
import { mapState, mapActions, mapGetters } from 'vuex';
import { platform } from '@/utils/platform';
import { getItem } from '@/services/tokenStorage';
import MobileNetWorthCard from '@/mobile/MobileNetWorthCard.vue';
import FynInsightCard from '@/mobile/FynInsightCard.vue';
import MobileAlertsList from '@/mobile/MobileAlertsList.vue';
import ModuleSummaryCard from '@/mobile/ModuleSummaryCard.vue';
import PullToRefresh from '@/mobile/PullToRefresh.vue';
import BiometricPrompt from '@/mobile/BiometricPrompt.vue';

/**
 * Maps dashboard card IDs from lifeStageConfig to mobile module names.
 * The dashboard card IDs use descriptive names, but mobile modules use
 * simple module names. This mapping bridges the two naming conventions.
 */
const CARD_TO_MODULE = {
  'budget-tracker': 'savings',
  'student-loan': 'savings',
  'savings': 'savings',
  'net-worth': 'coordination',
  'protection': 'protection',
  'cash-savings': 'savings',
  'investments': 'investment',
  'retirement': 'retirement',
  'estate': 'estate',
  'goals': 'goals',
  'life-timeline': 'goals',
  'tax-allowances': 'coordination',
};

export default {
  name: 'MobileDashboard',

  components: {
    MobileNetWorthCard,
    FynInsightCard,
    MobileAlertsList,
    ModuleSummaryCard,
    PullToRefresh,
    BiometricPrompt,
  },

  data() {
    return {
      showBiometricPrompt: false,
    };
  },

  computed: {
    ...mapState('mobileDashboard', ['netWorth', 'modules', 'alerts', 'insight', 'loading']),
    ...mapState('auth', ['user']),
    ...mapGetters('lifeStage', {
      currentStage: 'currentStage',
      stageLabel: 'stageLabel',
      stageColour: 'stageColour',
      progressPercentage: 'progressPercentage',
      nextStep: 'nextStep',
      dashboardCards: 'dashboardCards',
    }),

    hasData() {
      return !!(this.netWorth || (this.modules && this.modules.length) || this.insight);
    },

    firstName() {
      return this.user?.first_name || 'there';
    },

    greeting() {
      const hour = new Date().getHours();
      if (hour < 12) return 'Good morning';
      if (hour < 18) return 'Good afternoon';
      return 'Good evening';
    },

    stageColourTextClass() {
      const map = {
        violet: 'text-violet-500',
        spring: 'text-spring-500',
        raspberry: 'text-raspberry-500',
        'light-blue': 'text-light-blue-500',
        horizon: 'text-horizon-500',
      };
      return map[this.stageColour] || 'text-raspberry-500';
    },

    progressBarClass() {
      const colour = this.stageColour || 'raspberry';
      const gradients = {
        violet: 'bg-gradient-to-r from-violet-500 to-violet-400',
        spring: 'bg-gradient-to-r from-spring-500 to-spring-400',
        raspberry: 'bg-gradient-to-r from-raspberry-500 to-raspberry-400',
        'light-blue': 'bg-gradient-to-r from-light-blue-500 to-violet-400',
        horizon: 'bg-gradient-to-r from-horizon-500 to-horizon-400',
      };
      return gradients[colour] || 'bg-gradient-to-r from-raspberry-500 to-raspberry-400';
    },

    /**
     * Filter module summary cards based on the active life stage's dashboard card config.
     * When a stage is set, only show modules that are relevant to that stage.
     * When no stage is set, show all modules (existing behaviour).
     */
    filteredModules() {
      if (!this.currentStage || !this.dashboardCards || !this.dashboardCards.length) {
        return this.modules || [];
      }

      // Build set of relevant module names from dashboard cards config
      const relevantModules = new Set();
      this.dashboardCards.forEach(cardId => {
        const moduleName = CARD_TO_MODULE[cardId];
        if (moduleName) {
          relevantModules.add(moduleName);
        }
      });

      // Filter modules, keeping order from API
      const filtered = (this.modules || []).filter(mod => relevantModules.has(mod.name));

      // If filtering results in too few cards, fall back to all modules
      return filtered.length >= 2 ? filtered : (this.modules || []);
    },
  },

  async mounted() {
    if (!this.hasData) {
      this.fetchDashboard();
    }
    await this.checkBiometricSetup();
  },

  methods: {
    ...mapActions('mobileDashboard', ['fetchDashboard', 'refreshDashboard']),

    navigateToModule(moduleName) {
      this.$router.push(`/m/module/${moduleName}`);
    },

    continueJourney() {
      if (this.nextStep) {
        this.$router.push({ path: '/onboarding', query: { step: this.nextStep } });
      }
    },

    async checkBiometricSetup() {
      if (!platform.canUseBiometrics()) return;

      // If already set up, don't show the banner
      const biometricFlag = await getItem('biometric_enabled');
      if (biometricFlag === 'true') return;

      // Show the setup banner — actual biometric API calls happen
      // only when the user taps "Set up"
      this.showBiometricPrompt = true;
    },

    dismissBiometricPrompt() {
      this.showBiometricPrompt = false;
    },
  },
};
</script>
