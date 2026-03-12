<template>
  <PullToRefresh @refresh="refreshDashboard">
    <div class="px-4 pt-4 pb-6 space-y-4">
      <!-- Greeting -->
      <div>
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
            v-for="mod in modules"
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

    <!-- Biometric setup modal (shown once after first login) -->
    <BiometricPrompt
      v-if="showBiometricPrompt"
      @close="dismissBiometricPrompt"
    />
  </PullToRefresh>
</template>

<script>
import { mapState, mapActions } from 'vuex';
import { platform } from '@/utils/platform';
import MobileNetWorthCard from '@/mobile/MobileNetWorthCard.vue';
import FynInsightCard from '@/mobile/FynInsightCard.vue';
import MobileAlertsList from '@/mobile/MobileAlertsList.vue';
import ModuleSummaryCard from '@/mobile/ModuleSummaryCard.vue';
import PullToRefresh from '@/mobile/PullToRefresh.vue';
import BiometricPrompt from '@/mobile/BiometricPrompt.vue';

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

    async checkBiometricSetup() {
      if (!platform.canUseBiometrics()) return;

      // Check if user came from verification (first login) or biometric setup route
      const fromSetup = this.$route.query.biometricSetup === '1';
      if (!fromSetup) return;

      try {
        const { NativeBiometric } = await import('@capgo/capacitor-native-biometric');
        const { isAvailable } = await NativeBiometric.isAvailable();
        if (!isAvailable) return;

        // Check if credentials already stored
        try {
          const credentials = await NativeBiometric.getCredentials({ server: 'fynla.org' });
          if (credentials?.password) return; // Already set up
        } catch {
          // No credentials stored — show the prompt
        }

        this.showBiometricPrompt = true;
      } catch {
        // Biometric not available
      }
    },

    dismissBiometricPrompt() {
      this.showBiometricPrompt = false;
      // Remove the query param so it doesn't show again on refresh
      if (this.$route.query.biometricSetup) {
        this.$router.replace({ path: this.$route.path });
      }
    },
  },
};
</script>
