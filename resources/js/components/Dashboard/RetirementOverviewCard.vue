<template>
  <div class="card">
    <!-- Retirement Section (clickable) -->
    <div
      class="cursor-pointer hover:bg-gray-50 -m-6 p-6 transition-colors"
      :class="hasTrusts ? 'pb-4 rounded-t-lg' : 'rounded-lg'"
      @click="navigateToRetirement"
    >
      <!-- Primary Value Section -->
      <div class="border-b border-gray-200 pb-4 mb-4">
        <span class="text-sm text-gray-500">Projected Annual Income</span>
        <div class="flex items-baseline gap-2 mt-1">
          <span class="text-3xl font-bold text-primary-600">
            {{ formatCurrency(projectedIncome) }}
          </span>
          <span class="text-sm text-gray-500">/year</span>
        </div>
      </div>

      <!-- Breakdown -->
      <div class="space-y-3">
        <div class="flex justify-between items-center">
          <span class="text-sm text-gray-600">Potential Income</span>
          <span class="text-sm font-semibold text-gray-900">{{ formatCurrency(potentialRetirementIncome) }}/yr</span>
        </div>
        <div v-if="dbPensionIncome > 0" class="flex justify-between items-center">
          <span class="text-sm text-gray-600">Guaranteed Income</span>
          <span class="text-sm font-semibold text-gray-900">{{ formatCurrency(dbPensionIncome) }}/yr</span>
        </div>
        <div v-if="hasPensions" class="flex justify-between items-center">
          <span class="text-sm text-gray-600">State Pension</span>
          <span class="text-sm font-semibold text-gray-900">{{ formatCurrency(statePensionIncome) }}/yr</span>
        </div>
      </div>

      <!-- Target Income -->
      <div class="mt-3 pt-3 border-t border-gray-200">
        <div class="flex justify-between items-center">
          <div class="flex items-center gap-1">
            <span class="text-sm text-gray-600">Target Income</span>
            <span class="text-xs text-gray-400">({{ isUserEnteredTarget ? 'user set' : '75% default' }})</span>
          </div>
          <span class="text-sm font-semibold text-gray-900">{{ formatCurrency(retirementTargetIncome) }}/yr</span>
        </div>
      </div>

      <!-- Income Gap Status -->
      <div
        v-if="hasIncomeGap"
        class="mt-4 p-3 bg-white border-2 border-amber-500 rounded-lg"
      >
        <div class="flex items-center gap-2">
          <svg class="w-5 h-5 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
          </svg>
          <span class="text-sm font-medium text-amber-700">
            {{ formatCurrency(incomeGap) }} income gap to target
          </span>
        </div>
      </div>

      <div
        v-else-if="projectedIncome > 0"
        class="mt-4 p-3 bg-white border-2 border-green-600 rounded-lg"
      >
        <div class="flex items-center gap-2">
          <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
          </svg>
          <span class="text-sm font-medium text-green-700">On track for retirement</span>
        </div>
      </div>
    </div>

    <!-- Trusts Section (only shown if trusts exist) -->
    <template v-if="hasTrusts">
      <!-- Divider -->
      <div class="border-t border-gray-200 mt-6 mb-4"></div>

      <!-- Trusts Content (clickable) -->
      <div
        class="cursor-pointer hover:bg-gray-50 -mx-6 -mb-6 p-6 pt-0 rounded-b-lg transition-colors"
        @click="navigateToTrusts"
      >
        <div class="trust-sections">
          <div
            v-for="trust in displayedTrusts"
            :key="trust.id"
            class="trust-item"
          >
            <div class="trust-info">
              <div class="trust-name-row">
                <span class="trust-name">{{ trust.trust_name }}</span>
              </div>
              <p class="trust-details">{{ formatTrustType(trust.trust_type) }}</p>
            </div>
            <span class="trust-value">{{ formatCurrency(trust.current_value) }}</span>
          </div>
          <p v-if="trusts.length > 3" class="text-sm text-gray-500 mt-2">
            +{{ trusts.length - 3 }} more {{ trusts.length - 3 === 1 ? 'trust' : 'trusts' }}
          </p>
        </div>

        <!-- Tax Info Banner -->
        <div v-if="hasRelevantPropertyTrusts" class="info-banner">
          <svg class="info-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
          </svg>
          <span class="info-text">
            6% charge on asset value on {{ nextChargeDate }}
          </span>
        </div>
      </div>
    </template>
  </div>
</template>

<script>
import { mapState, mapGetters } from 'vuex';
import { currencyMixin } from '@/mixins/currencyMixin';

// UK full new State Pension 2025/26 (approximate)
const DEFAULT_STATE_PENSION = 11500;

export default {
  name: 'RetirementOverviewCard',
  mixins: [currencyMixin],

  computed: {
    ...mapState('retirement', ['dcPensions', 'dbPensions', 'statePension', 'profile', 'analysis']),
    ...mapGetters('retirement', ['totalPensionWealth']),
    ...mapGetters('userProfile', ['totalAnnualIncome']),
    ...mapState('trusts', { trusts: 'trusts' }),

    retirementAge() {
      return this.profile?.target_retirement_age || 67;
    },

    dcPensionWealth() {
      return this.totalPensionWealth || 0;
    },

    // Convert DC pension wealth to estimated annual income using 4% safe withdrawal rate
    potentialRetirementIncome() {
      return this.dcPensionWealth * 0.04;
    },

    dbPensionIncome() {
      if (!this.dbPensions || this.dbPensions.length === 0) return 0;
      return this.dbPensions.reduce((sum, pension) => {
        return sum + parseFloat(pension.accrued_annual_pension || 0);
      }, 0);
    },

    // Whether user has entered any pension data
    hasPensions() {
      const hasDC = this.dcPensions && this.dcPensions.length > 0;
      const hasDB = this.dbPensions && this.dbPensions.length > 0;
      const hasState = !!this.statePension;
      return hasDC || hasDB || hasState;
    },

    // State pension - use configured amount or default to full UK state pension
    // Only returns a value if user has entered pension data
    statePensionIncome() {
      if (!this.hasPensions) return 0;
      const configured = parseFloat(this.statePension?.annual_amount || 0);
      return configured > 0 ? configured : DEFAULT_STATE_PENSION;
    },

    // Total projected annual income from all sources
    projectedIncome() {
      return this.potentialRetirementIncome + this.dbPensionIncome + this.statePensionIncome;
    },

    // Check if user has entered a target income
    isUserEnteredTarget() {
      const userTarget = this.profile?.target_retirement_income;
      return userTarget && userTarget > 0;
    },

    // Target income - user configured or default to 75% of current income
    retirementTargetIncome() {
      if (this.isUserEnteredTarget) {
        return this.profile.target_retirement_income;
      }
      // Default to 75% of current annual income
      const currentIncome = this.totalAnnualIncome || 0;
      return currentIncome * 0.75;
    },

    // Check if projected income meets target
    hasIncomeGap() {
      return this.projectedIncome < this.retirementTargetIncome;
    },

    incomeGap() {
      return Math.max(0, this.retirementTargetIncome - this.projectedIncome);
    },

    // Trusts computed properties
    hasTrusts() {
      return this.trusts && this.trusts.length > 0;
    },

    displayedTrusts() {
      if (!this.trusts) return [];
      // Show up to 3 trusts, prioritizing active ones
      return [...this.trusts]
        .sort((a, b) => {
          if (a.is_active && !b.is_active) return -1;
          if (!a.is_active && b.is_active) return 1;
          return parseFloat(b.current_value || 0) - parseFloat(a.current_value || 0);
        })
        .slice(0, 3);
    },

    hasRelevantPropertyTrusts() {
      return this.trusts && this.trusts.some(t => t.is_relevant_property_trust);
    },

    nextChargeDate() {
      if (!this.trusts) return '';
      // Find the earliest next 10-year charge date from relevant property trusts
      const rptTrusts = this.trusts.filter(t => t.is_relevant_property_trust);
      if (rptTrusts.length === 0) return '';

      // Get next charge dates, calculating from trust creation if not set
      const chargeDates = rptTrusts.map(trust => {
        if (trust.next_10_year_charge_date) {
          return new Date(trust.next_10_year_charge_date);
        }
        // Calculate from trust creation date if available
        if (trust.trust_creation_date || trust.created_at) {
          const creationDate = new Date(trust.trust_creation_date || trust.created_at);
          const nextCharge = new Date(creationDate);
          // Find next 10-year anniversary
          const now = new Date();
          while (nextCharge <= now) {
            nextCharge.setFullYear(nextCharge.getFullYear() + 10);
          }
          return nextCharge;
        }
        return null;
      }).filter(d => d !== null);

      if (chargeDates.length === 0) return 'next anniversary';

      // Return earliest date
      const earliest = chargeDates.reduce((a, b) => a < b ? a : b);
      return earliest.toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' });
    },
  },

  methods: {
    navigateToRetirement() {
      this.$router.push('/net-worth/retirement');
    },

    navigateToTrusts() {
      this.$router.push('/trusts');
    },

    formatTrustType(type) {
      const types = {
        bare: 'Bare Trust',
        interest_in_possession: 'Interest in Possession',
        discretionary: 'Discretionary Trust',
        accumulation_maintenance: 'Accumulation & Maintenance',
        life_insurance: 'Life Insurance Trust',
        discounted_gift: 'Discounted Gift Trust',
        loan: 'Loan Trust',
        mixed: 'Mixed Trust',
        settlor_interested: 'Settlor-Interested Trust',
      };
      return types[type] || type;
    },
  },
};
</script>

<style scoped>
/* Trust Sections */
.trust-sections {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.trust-item {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  padding-bottom: 12px;
  border-bottom: 1px solid #f3f4f6;
}

.trust-item:last-of-type {
  border-bottom: none;
  padding-bottom: 0;
}

.trust-info {
  flex: 1;
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.trust-name-row {
  display: flex;
  align-items: center;
  gap: 8px;
}

.trust-name {
  font-weight: 600;
  font-size: 14px;
  color: #111827;
}

.trust-details {
  font-size: 12px;
  color: #6b7280;
}

.trust-value {
  font-weight: 600;
  font-size: 14px;
  color: #7c3aed;
  white-space: nowrap;
  margin-left: 12px;
}

/* Info Banner */
.info-banner {
  margin-top: 20px;
  padding: 12px;
  border-radius: 8px;
  background-color: #ffffff;
  border: 2px solid #f59e0b;
  display: flex;
  align-items: center;
  gap: 8px;
}

.info-icon {
  width: 20px;
  height: 20px;
  color: #92400e;
  flex-shrink: 0;
}

.info-text {
  font-size: 13px;
  font-weight: 500;
  color: #92400e;
}
</style>
