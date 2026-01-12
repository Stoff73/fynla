<template>
  <AppLayout>
    <div class="py-4 sm:py-6">
      <!-- Draggable masonry layout for dashboard cards -->
      <draggable
        v-model="widgetOrder"
        item-key="id"
        class="dashboard-masonry columns-1 sm:columns-2 lg:columns-3 gap-6"
        ghost-class="widget-ghost"
        drag-class="widget-drag"
        handle=".drag-handle"
        :animation="200"
        @end="saveWidgetOrder"
      >
        <template #item="{ element }">
          <div
            v-if="isWidgetVisible(element.id)"
            class="break-inside-avoid mb-6 widget-wrapper group"
          >
            <!-- Drag Handle -->
            <div class="drag-handle opacity-0 group-hover:opacity-100 transition-opacity cursor-grab active:cursor-grabbing flex items-center justify-center py-1 mb-1">
              <svg class="w-5 h-5 text-gray-400" fill="currentColor" viewBox="0 0 24 24">
                <path d="M8 6a2 2 0 1 1-4 0 2 2 0 0 1 4 0zM8 12a2 2 0 1 1-4 0 2 2 0 0 1 4 0zM8 18a2 2 0 1 1-4 0 2 2 0 0 1 4 0zM14 6a2 2 0 1 1-4 0 2 2 0 0 1 4 0zM14 12a2 2 0 1 1-4 0 2 2 0 0 1 4 0zM14 18a2 2 0 1 1-4 0 2 2 0 0 1 4 0z" />
              </svg>
            </div>

            <!-- Net Worth -->
            <template v-if="element.id === 'net_worth'">
              <NetWorthOverviewCard />
            </template>

            <!-- Affordability -->
            <template v-else-if="element.id === 'affordability'">
              <div v-if="loading.affordability || loading.taxAllowances" class="card animate-pulse">
                <div class="h-4 bg-gray-200 rounded w-3/4 mb-4"></div>
                <div class="h-8 bg-gray-200 rounded w-1/2 mb-2"></div>
                <div class="h-3 bg-gray-200 rounded w-full"></div>
              </div>
              <AffordabilityOverviewCard v-else />
            </template>

            <!-- Retirement -->
            <template v-else-if="element.id === 'retirement'">
              <div v-if="loading.retirement" class="card animate-pulse">
                <div class="h-4 bg-gray-200 rounded w-3/4 mb-4"></div>
                <div class="h-8 bg-gray-200 rounded w-1/2 mb-2"></div>
                <div class="h-3 bg-gray-200 rounded w-full"></div>
              </div>
              <RetirementOverviewCard v-else />
            </template>

            <!-- Investment -->
            <template v-else-if="element.id === 'investment'">
              <div v-if="loading.investment" class="card animate-pulse">
                <div class="h-4 bg-gray-200 rounded w-3/4 mb-4"></div>
                <div class="h-8 bg-gray-200 rounded w-1/2 mb-2"></div>
                <div class="h-3 bg-gray-200 rounded w-full"></div>
              </div>
              <InvestmentsOverviewCard v-else />
            </template>

            <!-- Tax -->
            <template v-else-if="element.id === 'tax'">
              <div v-if="loading.taxAllowances" class="card animate-pulse">
                <div class="h-4 bg-gray-200 rounded w-3/4 mb-4"></div>
                <div class="h-8 bg-gray-200 rounded w-1/2 mb-2"></div>
                <div class="h-3 bg-gray-200 rounded w-full"></div>
              </div>
              <TaxOptimisationCard v-else />
            </template>

            <!-- Estate -->
            <template v-else-if="element.id === 'estate'">
              <div v-if="loading.estate" class="card animate-pulse">
                <div class="h-4 bg-gray-200 rounded w-3/4 mb-4"></div>
                <div class="h-8 bg-gray-200 rounded w-1/2 mb-2"></div>
                <div class="h-3 bg-gray-200 rounded w-full"></div>
              </div>
              <div v-else-if="errors.estate" class="card border-2 border-red-600 bg-white">
                <h3 class="text-h4 text-red-700 mb-2">Estate Module</h3>
                <p class="text-body text-red-600 mb-4">
                  Failed to load estate data. {{ errors.estate }}
                </p>
                <button
                  @click="retryLoadModule('estate')"
                  class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition-colors"
                >
                  Retry
                </button>
              </div>
              <EstateOverviewCard
                v-else
                :taxable-estate="estateData.taxableEstate"
                :iht-liability="estateData.ihtLiability"
                :probate-readiness="estateData.probateReadiness"
                :future-death-age="estateData.futureDeathAge"
                :future-taxable-estate="estateData.futureTaxableEstate"
                :future-iht-liability="estateData.futureIHTLiability"
                :is-married="estateData.isMarried"
              />
            </template>

            <!-- Protection -->
            <template v-else-if="element.id === 'protection'">
              <div v-if="loading.protection" class="card animate-pulse">
                <div class="h-4 bg-gray-200 rounded w-3/4 mb-4"></div>
                <div class="h-8 bg-gray-200 rounded w-1/2 mb-2"></div>
                <div class="h-3 bg-gray-200 rounded w-full"></div>
              </div>
              <div v-else-if="errors.protection" class="card border-2 border-red-600 bg-white">
                <h3 class="text-h4 text-red-700 mb-2">Protection Module</h3>
                <p class="text-body text-red-600 mb-4">
                  Failed to load protection data. {{ errors.protection }}
                </p>
                <button
                  @click="retryLoadModule('protection')"
                  class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition-colors"
                >
                  Retry
                </button>
              </div>
              <ProtectionOverviewCard
                v-else
                :adequacy-score="protectionData.adequacyScore"
                :total-coverage="protectionData.totalCoverage"
                :premium-total="protectionData.premiumTotal"
                :critical-gaps="protectionData.criticalGaps"
                :life-policies="protectionData.lifePolicies"
                :critical-illness-policies="protectionData.criticalIllnessPolicies"
                :income-protection-policies="protectionData.incomeProtectionPolicies"
                :disability-policies="protectionData.disabilityPolicies"
                :sickness-illness-policies="protectionData.sicknessIllnessPolicies"
              />
            </template>

            <!-- Trusts -->
            <template v-else-if="element.id === 'trusts'">
              <div v-if="loading.trusts" class="card animate-pulse">
                <div class="h-4 bg-gray-200 rounded w-3/4 mb-4"></div>
                <div class="h-8 bg-gray-200 rounded w-1/2 mb-2"></div>
                <div class="h-3 bg-gray-200 rounded w-full"></div>
              </div>
              <TrustsOverviewCard v-else />
            </template>

            <!-- Admin Taxes -->
            <template v-else-if="element.id === 'admin_taxes'">
              <UKTaxesOverviewCard />
            </template>
          </div>
        </template>
      </draggable>
    </div>
  </AppLayout>
</template>

<script>
import { mapGetters, mapState } from 'vuex';
import draggable from 'vuedraggable';
import AppLayout from '@/layouts/AppLayout.vue';
import NetWorthOverviewCard from '@/components/Dashboard/NetWorthOverviewCard.vue';
import AffordabilityOverviewCard from '@/components/Dashboard/AffordabilityOverviewCard.vue';
import RetirementOverviewCard from '@/components/Dashboard/RetirementOverviewCard.vue';
import InvestmentsOverviewCard from '@/components/Dashboard/InvestmentsOverviewCard.vue';
import TaxOptimisationCard from '@/components/Dashboard/TaxOptimisationCard.vue';
import ProtectionOverviewCard from '@/components/Protection/ProtectionOverviewCard.vue';
import EstateOverviewCard from '@/components/Estate/EstateOverviewCard.vue';
import TrustsOverviewCard from '@/components/Trusts/TrustsOverviewCard.vue';
import UKTaxesOverviewCard from '@/components/Dashboard/UKTaxesOverviewCard.vue';
import api from '@/services/api';

const DEFAULT_WIDGET_ORDER = [
  'net_worth',
  'affordability',
  'retirement',
  'investment',
  'tax',
  'estate',
  'protection',
  'trusts',
  'admin_taxes',
];

export default {
  name: 'Dashboard',

  components: {
    draggable,
    AppLayout,
    NetWorthOverviewCard,
    AffordabilityOverviewCard,
    RetirementOverviewCard,
    InvestmentsOverviewCard,
    TaxOptimisationCard,
    ProtectionOverviewCard,
    EstateOverviewCard,
    TrustsOverviewCard,
    UKTaxesOverviewCard,
  },

  data() {
    return {
      widgetOrder: DEFAULT_WIDGET_ORDER.map(id => ({ id })),
      loading: {
        protection: true,
        estate: true,
        trusts: true,
        affordability: true,
        retirement: true,
        investment: true,
        taxAllowances: true,
      },
      errors: {
        protection: null,
        estate: null,
        trusts: null,
      },
      refreshing: false,
      dataLoaded: false,
      savingOrder: false,
    };
  },

  computed: {
    ...mapGetters('auth', ['isAdmin']),
    ...mapState('trusts', { trustsList: 'trusts' }),
    ...mapGetters('netWorth', {
      netWorthValue: 'netWorth',
      netWorthAssets: 'totalAssets',
      netWorthLiabilities: 'totalLiabilities',
    }),
    ...mapGetters('protection', {
      protectionAdequacyScore: 'adequacyScore',
      protectionTotalCoverage: 'totalCoverage',
      protectionTotalPremium: 'totalPremium',
      protectionCoverageGaps: 'coverageGaps',
      protectionLifePolicies: 'lifePolicies',
      protectionCriticalIllnessPolicies: 'criticalIllnessPolicies',
      protectionIncomeProtectionPolicies: 'incomeProtectionPolicies',
      protectionDisabilityPolicies: 'disabilityPolicies',
      protectionSicknessIllnessPolicies: 'sicknessIllnessPolicies',
    }),
    ...mapGetters('estate', {
      estateIHTLiability: 'ihtLiability',
      estateProbateReadiness: 'probateReadiness',
      estateTaxableEstate: 'taxableEstate',
      estateFutureDeathAge: 'futureDeathAge',
      estateFutureTaxableEstate: 'futureTaxableEstate',
      estateFutureIHTLiability: 'futureIHTLiability',
    }),

    // Conditional display computed properties
    shouldShowEstateCard() {
      return this.estateData.ihtLiability > 0;
    },

    shouldShowProtectionCard() {
      return (
        this.protectionData.lifePolicies.length > 0 ||
        this.protectionData.criticalIllnessPolicies.length > 0 ||
        this.protectionData.incomeProtectionPolicies.length > 0 ||
        this.protectionData.disabilityPolicies.length > 0
      );
    },

    shouldShowTrustsCard() {
      const trusts = this.trustsList || [];
      return trusts.length > 0;
    },

    netWorthData() {
      return {
        netWorth: this.netWorthValue || 0,
        totalAssets: this.netWorthAssets || 0,
        totalLiabilities: this.netWorthLiabilities || 0,
      };
    },

    protectionData() {
      const gaps = this.protectionCoverageGaps?.gaps_by_category || {};
      const criticalGaps = Object.values(gaps).filter(gap => gap > 10000).length || 0;
      const adequacyScore = typeof this.protectionAdequacyScore === 'object'
        ? (this.protectionAdequacyScore?.score ?? 0)
        : (this.protectionAdequacyScore || 0);

      return {
        adequacyScore: adequacyScore,
        totalCoverage: this.protectionTotalCoverage || 0,
        premiumTotal: this.protectionTotalPremium || 0,
        criticalGaps: criticalGaps,
        coverageGaps: gaps,
        lifePolicies: this.protectionLifePolicies || [],
        criticalIllnessPolicies: this.protectionCriticalIllnessPolicies || [],
        incomeProtectionPolicies: this.protectionIncomeProtectionPolicies || [],
        disabilityPolicies: this.protectionDisabilityPolicies || [],
        sicknessIllnessPolicies: this.protectionSicknessIllnessPolicies || [],
      };
    },

    estateData() {
      const user = this.$store.state.auth.user;
      const isMarried = user && user.marital_status === 'married';

      return {
        taxableEstate: this.estateTaxableEstate || 0,
        ihtLiability: this.estateIHTLiability || 0,
        probateReadiness: this.estateProbateReadiness || 0,
        futureDeathAge: this.estateFutureDeathAge,
        futureTaxableEstate: this.estateFutureTaxableEstate,
        futureIHTLiability: this.estateFutureIHTLiability,
        isMarried: isMarried,
      };
    },
  },

  methods: {
    isWidgetVisible(widgetId) {
      switch (widgetId) {
        case 'estate':
          return this.shouldShowEstateCard;
        case 'protection':
          return this.shouldShowProtectionCard;
        case 'trusts':
          return this.shouldShowTrustsCard;
        case 'admin_taxes':
          return this.isAdmin;
        default:
          return true;
      }
    },

    initializeWidgetOrder() {
      const user = this.$store.state.auth.user;
      if (user?.dashboard_widget_order && Array.isArray(user.dashboard_widget_order)) {
        // Use saved order, but ensure all widgets are included
        const savedOrder = user.dashboard_widget_order;
        const missingWidgets = DEFAULT_WIDGET_ORDER.filter(id => !savedOrder.includes(id));
        this.widgetOrder = [...savedOrder, ...missingWidgets].map(id => ({ id }));
      } else {
        this.widgetOrder = DEFAULT_WIDGET_ORDER.map(id => ({ id }));
      }
    },

    async saveWidgetOrder() {
      if (this.savingOrder) return;

      this.savingOrder = true;
      const orderIds = this.widgetOrder.map(w => w.id);

      try {
        await api.put('/user/dashboard-widget-order', {
          widget_order: orderIds,
        });
        // Update user in store
        const user = this.$store.state.auth.user;
        if (user) {
          this.$store.commit('auth/setUser', {
            ...user,
            dashboard_widget_order: orderIds,
          });
        }
      } catch (error) {
        console.error('Failed to save widget order:', error);
      } finally {
        this.savingOrder = false;
      }
    },

    async loadAllData() {
      const user = this.$store.state.auth.user;
      const isMarried = user && user.marital_status === 'married';
      const estateCalculationAction = isMarried
        ? 'estate/calculateSecondDeathIHTPlanning'
        : 'estate/calculateIHT';

      const moduleLoaders = [
        { name: 'netWorth', action: 'netWorth/fetchOverview' },
        { name: 'protection', action: 'protection/fetchProtectionData' },
        { name: 'estate', action: 'estate/fetchEstateData' },
        { name: 'estate', action: estateCalculationAction, payload: {} },
        { name: 'trusts', action: 'trusts/fetchTrusts' },
        { name: 'affordability', action: 'userProfile/fetchProfile' },
        { name: 'retirement', action: 'retirement/fetchRetirementData' },
        { name: 'investment', action: 'investment/fetchInvestmentData' },
        { name: 'investment', action: 'investment/analyseInvestment' },
        { name: 'taxAllowances', action: 'savings/fetchSavingsData' },
      ];

      Object.keys(this.loading).forEach(key => {
        this.loading[key] = true;
      });
      Object.keys(this.errors).forEach(key => {
        this.errors[key] = null;
      });

      const moduleActionCounts = {};
      moduleLoaders.forEach(loader => {
        moduleActionCounts[loader.name] = (moduleActionCounts[loader.name] || 0) + 1;
      });

      const moduleCompletedCounts = {};

      const promises = moduleLoaders.map(loader =>
        this.$store.dispatch(loader.action, loader.payload)
          .then(() => ({ module: loader.name, success: true }))
          .catch(error => ({
            module: loader.name,
            success: false,
            error: error.response?.data?.message || error.message || 'Unknown error'
          }))
      );

      const results = await Promise.allSettled(promises);

      results.forEach(result => {
        if (result.status === 'fulfilled') {
          const { module, success, error } = result.value;
          moduleCompletedCounts[module] = (moduleCompletedCounts[module] || 0) + 1;

          if (!success && this.loading.hasOwnProperty(module)) {
            this.errors[module] = error;
          }

          if (this.loading.hasOwnProperty(module) &&
              moduleCompletedCounts[module] >= moduleActionCounts[module]) {
            this.loading[module] = false;
          }
        } else {
          console.error('Failed to load module:', result.reason);
        }
      });
    },

    async retryLoadModule(moduleName) {
      this.loading[moduleName] = true;
      this.errors[moduleName] = null;

      const user = this.$store.state.auth.user;
      const isMarried = user && user.marital_status === 'married';
      const estateCalculationAction = isMarried
        ? 'estate/calculateSecondDeathIHTPlanning'
        : 'estate/calculateIHT';

      const actions = {
        protection: ['protection/fetchProtectionData'],
        estate: ['estate/fetchEstateData', estateCalculationAction],
      };

      try {
        const moduleActions = actions[moduleName] || [];
        await Promise.all(
          moduleActions.map(action => this.$store.dispatch(action))
        );
        this.loading[moduleName] = false;
      } catch (error) {
        this.errors[moduleName] = error.response?.data?.message || error.message || 'Unknown error';
        this.loading[moduleName] = false;
      }
    },

    async refreshDashboard() {
      this.refreshing = true;
      try {
        const user = this.$store.state.auth.user;
        const isMarried = user && user.marital_status === 'married';
        const estateCalculationAction = isMarried
          ? 'estate/calculateSecondDeathIHTPlanning'
          : 'estate/calculateIHT';

        await this.$store.dispatch('netWorth/refreshNetWorth');
        await Promise.allSettled([
          this.$store.dispatch('protection/fetchProtectionData'),
          this.$store.dispatch('savings/fetchSavingsData'),
          this.$store.dispatch('investment/fetchInvestmentData'),
          this.$store.dispatch('estate/fetchEstateData'),
          this.$store.dispatch(estateCalculationAction),
        ]);
      } catch (error) {
        console.error('Error refreshing dashboard:', error);
      } finally {
        this.refreshing = false;
      }
    },
  },

  watch: {
    '$store.state.auth.user': {
      immediate: true,
      handler(user) {
        if (user) {
          this.initializeWidgetOrder();
          if (!this.dataLoaded) {
            this.dataLoaded = true;
            this.loadAllData();
          }
        }
      }
    }
  },
};
</script>

<style scoped>
/* Remove card backgrounds/borders for seamless dashboard look */
.dashboard-masonry :deep(.card),
.dashboard-masonry :deep([class*="overview-card"]) {
  background: transparent !important;
  border: none !important;
  box-shadow: none !important;
  border-radius: 0 !important;
}

/* Subtle hover effect for clickable cards */
.dashboard-masonry :deep(.card):hover,
.dashboard-masonry :deep([class*="overview-card"]):hover {
  background: rgba(59, 130, 246, 0.04) !important;
}

/* Keep loading skeletons visible */
.dashboard-masonry :deep(.animate-pulse) {
  background: #f3f4f6 !important;
  border-radius: 8px !important;
}

/* Add subtle dividers between cards */
.widget-wrapper {
  border-bottom: 1px solid #e5e7eb;
  padding-bottom: 1.5rem;
}

.widget-wrapper:last-child {
  border-bottom: none;
}

/* Drag handle styling */
.drag-handle {
  height: 20px;
  margin-bottom: 4px;
}

/* Ghost element (placeholder) during drag */
.widget-ghost {
  opacity: 0.4;
  background: #f3f4f6 !important;
  border: 2px dashed #3b82f6 !important;
  border-radius: 8px !important;
}

/* Element being dragged */
.widget-drag {
  opacity: 0.95;
  background: white !important;
  box-shadow: 0 10px 25px rgba(0, 0, 0, 0.15) !important;
  border-radius: 8px !important;
}

/* Smooth transitions for reordering */
.sortable-ghost {
  opacity: 0;
}
</style>
