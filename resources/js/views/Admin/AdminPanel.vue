<template>
  <AppLayout>
    <div class="py-2 sm:py-0">
      <!-- Header -->
      <div class="mb-6">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
          <div>
            <h1 class="font-display text-2xl sm:text-h1 text-horizon-500">Admin Panel</h1>
            <p class="text-body text-neutral-500 mt-2">
              System administration and management
            </p>
          </div>
          <div class="flex items-center space-x-2 flex-shrink-0">
            <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-raspberry-100 text-raspberry-800">
              <svg class="w-4 h-4 mr-1" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M2.166 4.999A11.954 11.954 0 0010 1.944 11.954 11.954 0 0017.834 5c.11.65.166 1.32.166 2.001 0 5.225-3.34 9.67-8 11.317C5.34 16.67 2 12.225 2 7c0-.682.057-1.35.166-2.001zm11.541 3.708a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
              </svg>
              Administrator
            </span>
          </div>
        </div>
      </div>

      <!-- Shared admin tab bar: persists across CMS/Campaigns routes, active tab
           in raspberry (pink), dropdown groups open on hover. -->
      <AdminNav />

      <!-- Tab Content -->
      <div class="space-y-6">
        <AdminDashboard v-if="activeTab === 'dashboard'" @change-tab="activeTab = $event" />
        <UserMetrics v-if="activeTab === 'user-metrics'" />
        <UserManagement v-if="activeTab === 'users'" />
        <DatabaseBackup v-if="activeTab === 'backups'" />
        <DecisionMatrix v-if="activeTab === 'decision-matrix'" />
        <TaxSettings v-if="activeTab === 'tax-settings'" />
        <AiSettings v-if="activeTab === 'ai-settings'" />
        <AiAudit v-if="activeTab === 'ai-audit'" />
        <EvalRecordings v-if="activeTab === 'eval-recordings'" />
        <DiscountCodes v-if="activeTab === 'discount-codes'" />
        <TierConfiguration v-if="activeTab === 'tier-configuration'" />
        <SavingsMarketRates v-if="activeTab === 'savings-market-rates'" />
        <ActuarialLifeTables v-if="activeTab === 'actuarial-life-tables'" />
        <CurrencyRates v-if="activeTab === 'currency-rates'" />
      </div>
    </div>
  </AppLayout>
</template>

<script>
import { mapGetters } from 'vuex';
import AppLayout from '../../layouts/AppLayout.vue';
import AdminNav from '../../components/Admin/AdminNav.vue';
import AdminDashboard from '../../components/Admin/AdminDashboard.vue';
import UserManagement from '../../components/Admin/UserManagement.vue';
import DatabaseBackup from '../../components/Admin/DatabaseBackup.vue';
import TaxSettings from '../../components/Admin/TaxSettings.vue';
import AiSettings from '../../components/Admin/AiSettings.vue';
import { defineAsyncComponent } from 'vue';
const DecisionMatrix = defineAsyncComponent(() => import('../../components/Admin/DecisionMatrix.vue'));
const UserMetrics = defineAsyncComponent(() => import('../../components/Admin/metrics/UserMetrics.vue'));
const AiAudit = defineAsyncComponent(() => import('../../components/Admin/AiAudit.vue'));
const DiscountCodes = defineAsyncComponent(() => import('../../components/Admin/DiscountCodes.vue'));
const EvalRecordings = defineAsyncComponent(() => import('../../components/Admin/EvalRecordings.vue'));
const TierConfiguration = defineAsyncComponent(() => import('../../components/Admin/TierConfiguration.vue'));
const SavingsMarketRates = defineAsyncComponent(() => import('../../components/Admin/SavingsMarketRates.vue'));
const ActuarialLifeTables = defineAsyncComponent(() => import('../../components/Admin/ActuarialLifeTables.vue'));
const CurrencyRates = defineAsyncComponent(() => import('../../components/Admin/CurrencyRates.vue'));

export default {
  name: 'AdminPanel',

  components: {
    AppLayout,
    AdminNav,
    AdminDashboard,
    UserManagement,
    DatabaseBackup,
    TaxSettings,
    AiSettings,
    DecisionMatrix,
    UserMetrics,
    AiAudit,
    DiscountCodes,
    EvalRecordings,
    TierConfiguration,
    SavingsMarketRates,
    ActuarialLifeTables,
    CurrencyRates,
  },

  data() {
    return {
      activeTab: this.$route.query.tab || 'dashboard',
    };
  },

  computed: {
    ...mapGetters('auth', ['currentUser', 'isAdmin']),
  },

  watch: {
    // AdminNav navigates in-page sections via /admin?tab=<id>; keep the
    // rendered content in sync with the query param.
    '$route.query.tab'(tab) {
      this.activeTab = tab || 'dashboard';
    },
  },

};
</script>

<style scoped>
.-webkit-overflow-scrolling-touch {
  -webkit-overflow-scrolling: touch;
}
</style>
