<template>
  <div
    class="protection-overview-card bg-white rounded-lg shadow-md p-6 cursor-pointer hover:shadow-lg hover:-translate-y-0.5 hover:bg-light-gray transition-all duration-200 border border-light-gray"
    @click="navigateToProtection"
  >
    <!-- Policy Sections - only show sections with policies -->
    <div class="policy-sections">
      <!-- Life Insurance Policies -->
      <div v-if="lifePolicies.length > 0" class="section-breakdown">
        <div class="section-header-with-badge">
          <span class="section-header">Life Insurance</span>
          <span class="policy-count-badge policy-count-badge-blue">
            {{ lifePolicies.length }} {{ lifePolicies.length === 1 ? 'policy' : 'policies' }}
          </span>
        </div>
        <div class="policy-list">
          <div
            v-for="policy in lifePolicies"
            :key="policy.id"
            class="policy-item"
          >
            <div class="policy-info">
              <div class="policy-provider">
                <span class="provider-name">{{ policy.provider || policy.provider_name }}</span>
                <span
                  v-if="policy.is_joint"
                  class="joint-badge joint-badge-blue"
                >
                  Joint
                </span>
              </div>
              <p class="policy-details">{{ formatPolicyType(policy.policy_type) }} • Cover: {{ formatCurrency(policy.sum_assured) }}</p>
            </div>
            <span class="policy-premium policy-premium-blue">{{ formatCurrency(policy.premium_amount) }}/mo</span>
          </div>
        </div>
      </div>

      <!-- Critical Illness Policies -->
      <div v-if="criticalIllnessPolicies.length > 0" class="section-breakdown">
        <div class="section-header-with-badge">
          <span class="section-header">Critical Illness</span>
          <span class="policy-count-badge policy-count-badge-purple">
            {{ criticalIllnessPolicies.length }} {{ criticalIllnessPolicies.length === 1 ? 'policy' : 'policies' }}
          </span>
        </div>
        <div class="policy-list">
          <div
            v-for="policy in criticalIllnessPolicies"
            :key="policy.id"
            class="policy-item"
          >
            <div class="policy-info">
              <div class="policy-provider">
                <span class="provider-name">{{ policy.provider || policy.provider_name }}</span>
                <span
                  v-if="policy.is_joint"
                  class="joint-badge joint-badge-purple"
                >
                  Joint
                </span>
              </div>
              <p class="policy-details">{{ formatCIPolicyType(policy.policy_type) }} • Cover: {{ formatCurrency(policy.sum_assured) }}</p>
            </div>
            <span class="policy-premium policy-premium-purple">{{ formatCurrency(policy.premium_amount) }}/mo</span>
          </div>
        </div>
      </div>

      <!-- Income Protection Policies -->
      <div v-if="incomeProtectionPolicies.length > 0" class="section-breakdown">
        <div class="section-header-with-badge">
          <span class="section-header">Income Protection</span>
          <span class="policy-count-badge policy-count-badge-teal">
            {{ incomeProtectionPolicies.length }} {{ incomeProtectionPolicies.length === 1 ? 'policy' : 'policies' }}
          </span>
        </div>
        <div class="policy-list">
          <div
            v-for="policy in incomeProtectionPolicies"
            :key="policy.id"
            class="policy-item"
          >
            <div class="policy-info">
              <div class="policy-provider">
                <span class="provider-name">{{ policy.provider || policy.provider_name }}</span>
                <span
                  v-if="policy.is_joint"
                  class="joint-badge joint-badge-teal"
                >
                  Joint
                </span>
              </div>
              <p class="policy-details">Benefit: {{ formatCurrency(policy.benefit_amount) }}/mo • {{ policy.deferred_period_weeks || 0 }} weeks waiting</p>
            </div>
            <span class="policy-premium policy-premium-teal">{{ formatCurrency(policy.premium_amount) }}/mo</span>
          </div>
        </div>
      </div>

      <!-- Disability Policies -->
      <div v-if="disabilityPolicies.length > 0" class="section-breakdown">
        <div class="section-header-with-badge">
          <span class="section-header">Disability Insurance</span>
          <span class="policy-count-badge policy-count-badge-blue">
            {{ disabilityPolicies.length }} {{ disabilityPolicies.length === 1 ? 'policy' : 'policies' }}
          </span>
        </div>
        <div class="policy-list">
          <div
            v-for="policy in disabilityPolicies"
            :key="policy.id"
            class="policy-item"
          >
            <div class="policy-info">
              <div class="policy-provider">
                <span class="provider-name">{{ policy.provider || policy.provider_name }}</span>
                <span
                  v-if="policy.is_joint"
                  class="joint-badge joint-badge-blue-alt"
                >
                  Joint
                </span>
              </div>
              <p class="policy-details">Benefit: {{ formatCurrency(policy.benefit_amount) }}/mo • {{ policy.deferred_period_weeks || 0 }} weeks waiting</p>
            </div>
            <span class="policy-premium policy-premium-blue-alt">{{ formatCurrency(policy.premium_amount) }}/mo</span>
          </div>
        </div>
      </div>

      <!-- Sickness/Illness Policies -->
      <div v-if="sicknessIllnessPolicies.length > 0" class="section-breakdown">
        <div class="section-header-with-badge">
          <span class="section-header">Sickness/Illness</span>
          <span class="policy-count-badge policy-count-badge-teal">
            {{ sicknessIllnessPolicies.length }} {{ sicknessIllnessPolicies.length === 1 ? 'policy' : 'policies' }}
          </span>
        </div>
        <div class="policy-list">
          <div
            v-for="policy in sicknessIllnessPolicies"
            :key="policy.id"
            class="policy-item"
          >
            <div class="policy-info">
              <div class="policy-provider">
                <span class="provider-name">{{ policy.provider || policy.provider_name }}</span>
                <span
                  v-if="policy.is_joint"
                  class="joint-badge joint-badge-teal"
                >
                  Joint
                </span>
              </div>
              <p class="policy-details">Benefit: {{ formatCurrency(policy.benefit_amount) }}/mo • {{ policy.deferred_period_weeks || 0 }} weeks waiting</p>
            </div>
            <span class="policy-premium policy-premium-teal">{{ formatCurrency(policy.premium_amount) }}/mo</span>
          </div>
        </div>
      </div>
    </div>

    <!-- Shortfalls Section -->
    <div v-if="hasShortfalls" class="shortfalls-section">
      <div class="section-header shortfalls-header">Shortfalls Identified</div>
      <div class="shortfalls-list">
        <div
          v-for="shortfall in shortfallsList"
          :key="shortfall.label"
          class="shortfall-item"
        >
          <span class="shortfall-icon">!</span>
          <div class="shortfall-content">
            <span class="shortfall-label">{{ shortfall.label }}</span>
            <span v-if="shortfall.noPolicy" class="shortfall-text">No cover</span>
            <span v-else-if="shortfall.description" class="shortfall-amount">
              {{ formatCurrency(shortfall.amount) }} - {{ shortfall.description }}
            </span>
            <span v-else class="shortfall-amount">
              {{ formatCurrency(shortfall.amount) }}{{ shortfall.isAnnual ? '/yr' : '' }} shortfall
            </span>
          </div>
        </div>
      </div>
    </div>

    <!-- Critical Gaps Status Banner -->
    <div
      v-if="criticalGaps > 0"
      class="status-banner status-banner-warning"
    >
      <svg
        xmlns="http://www.w3.org/2000/svg"
        class="status-icon"
        viewBox="0 0 20 20"
        fill="currentColor"
      >
        <path
          fill-rule="evenodd"
          d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z"
          clip-rule="evenodd"
        />
      </svg>
      <span class="status-text">
        {{ criticalGaps }} critical {{ criticalGaps === 1 ? 'gap' : 'gaps' }} identified
      </span>
    </div>
  </div>
</template>

<script>
import { currencyMixin } from '@/mixins/currencyMixin';
import userProfileService from '@/services/userProfileService';
import { SSP_WEEKLY_RATE } from '@/constants/taxConfig';

export default {
  name: 'ProtectionOverviewCard',
  mixins: [currencyMixin],

  props: {
    totalCoverage: {
      type: Number,
      default: 0,
    },
    premiumTotal: {
      type: Number,
      default: 0,
    },
    criticalGaps: {
      type: Number,
      default: 0,
    },
    lifePolicies: {
      type: Array,
      default: () => [],
    },
    criticalIllnessPolicies: {
      type: Array,
      default: () => [],
    },
    incomeProtectionPolicies: {
      type: Array,
      default: () => [],
    },
    disabilityPolicies: {
      type: Array,
      default: () => [],
    },
    sicknessIllnessPolicies: {
      type: Array,
      default: () => [],
    },
  },

  data() {
    return {
      fetchedMortgageDebt: 0,
      fetchedOtherDebt: 0,
      fetchedAnnualIncome: 0,
      fetchedEmploymentIncome: 0,
    };
  },

  async mounted() {
    await this.fetchUserData();
  },

  computed: {
    // ===== DEBT PROTECTION (same as GapAnalysis) =====
    totalDebt() {
      return this.fetchedMortgageDebt + this.fetchedOtherDebt;
    },

    totalLifeCoverage() {
      return this.lifePolicies.reduce((sum, policy) => {
        return sum + parseFloat(policy.sum_assured || 0);
      }, 0);
    },

    debtProtectionGap() {
      return Math.max(0, this.totalDebt - this.totalLifeCoverage);
    },

    // ===== INCOME REPLACEMENT (same as GapAnalysis) =====
    // Excess life cover after debt
    excessLifeCoverAfterDebt() {
      return Math.max(0, this.totalLifeCoverage - this.totalDebt);
    },

    // Sustainable annual income from lump sum at 4.7% draw rate
    humanCapitalCoveredAnnual() {
      return this.excessLifeCoverAfterDebt * 0.047;
    },

    incomeReplacementNeed() {
      return this.fetchedAnnualIncome * 0.75;
    },

    incomeReplacementGap() {
      return Math.max(0, this.incomeReplacementNeed - this.humanCapitalCoveredAnnual);
    },

    // ===== CRITICAL ILLNESS (same as GapAnalysis) =====
    criticalIllnessCoverage() {
      return this.criticalIllnessPolicies.reduce((sum, policy) => {
        return sum + parseFloat(policy.sum_assured || 0);
      }, 0);
    },

    criticalIllnessNeed() {
      return this.fetchedAnnualIncome * 2;
    },

    criticalIllnessGap() {
      return Math.max(0, this.criticalIllnessNeed - this.criticalIllnessCoverage);
    },

    // ===== SICKNESS COVER (same as GapAnalysis) =====
    isEmployee() {
      return this.fetchedEmploymentIncome > 0;
    },

    sspWeeklyRate() {
      return SSP_WEEKLY_RATE;
    },

    sspAnnualEquivalent() {
      if (!this.isEmployee) return 0;
      return this.sspWeeklyRate * 52;
    },

    privateSicknessCover() {
      return this.sicknessIllnessPolicies.reduce((sum, policy) => {
        const benefit = parseFloat(policy.benefit_amount || 0);
        const frequency = policy.benefit_frequency || 'monthly';
        if (frequency === 'monthly') return sum + (benefit * 12);
        if (frequency === 'weekly') return sum + (benefit * 52);
        return sum + benefit;
      }, 0);
    },

    totalSicknessCover() {
      return this.sspAnnualEquivalent + this.privateSicknessCover;
    },

    sicknessNeed() {
      return this.fetchedAnnualIncome * 0.5;
    },

    sicknessGap() {
      return Math.max(0, this.sicknessNeed - this.totalSicknessCover);
    },

    // ===== DISABILITY COVER (same as GapAnalysis) =====
    disabilityCover() {
      return this.disabilityPolicies.reduce((sum, policy) => {
        const benefit = parseFloat(policy.benefit_amount || 0);
        const frequency = policy.benefit_frequency || 'monthly';
        if (frequency === 'monthly') return sum + (benefit * 12);
        if (frequency === 'weekly') return sum + (benefit * 52);
        return sum + benefit;
      }, 0);
    },

    disabilityNeed() {
      return this.fetchedAnnualIncome * 0.5;
    },

    disabilityGap() {
      return Math.max(0, this.disabilityNeed - this.disabilityCover);
    },

    // ===== SHORTFALLS LIST =====
    shortfallsList() {
      const shortfalls = [];

      // Debt Protection shortfall
      if (this.debtProtectionGap > 0) {
        shortfalls.push({
          label: 'Debt Protection',
          amount: this.debtProtectionGap,
        });
      }

      // Income Replacement shortfall (from life cover)
      if (this.incomeReplacementGap > 0) {
        shortfalls.push({
          label: 'Income Replacement',
          amount: this.incomeReplacementGap,
          isAnnual: true,
        });
      }

      // Critical Illness shortfall
      if (this.criticalIllnessGap > 0) {
        shortfalls.push({
          label: 'Critical Illness',
          amount: this.criticalIllnessGap,
        });
      } else if (this.criticalIllnessPolicies.length === 0) {
        shortfalls.push({
          label: 'Critical Illness',
          noPolicy: true,
        });
      }

      // Sickness Cover shortfall
      if (this.sicknessGap > 0) {
        shortfalls.push({
          label: 'Sickness Cover',
          amount: this.sicknessGap,
          isAnnual: true,
        });
      }

      // Disability Cover shortfall
      if (this.disabilityGap > 0) {
        shortfalls.push({
          label: 'Disability Cover',
          amount: this.disabilityGap,
          isAnnual: true,
        });
      }

      return shortfalls;
    },

    hasShortfalls() {
      return this.shortfallsList.length > 0;
    },
  },

  methods: {
    async fetchUserData() {
      try {
        const response = await userProfileService.getProfile();
        const data = response.data || response;

        // Get liabilities breakdown
        const liabilities = data.liabilities_summary || {};
        this.fetchedMortgageDebt = liabilities.mortgages?.total || 0;
        this.fetchedOtherDebt = liabilities.other?.total || 0;

        // Get annual income from income_occupation
        const income = data.income_occupation || {};
        this.fetchedEmploymentIncome = parseFloat(income.annual_employment_income || 0);
        const selfEmploymentIncome = parseFloat(income.annual_self_employment_income || 0);
        this.fetchedAnnualIncome = this.fetchedEmploymentIncome + selfEmploymentIncome;
      } catch (error) {
        console.warn('Failed to fetch user data for protection card:', error);
      }
    },

    navigateToProtection() {
      this.$router.push('/protection');
    },

    formatPolicyType(type) {
      const typeMap = {
        'term': 'Term Life',
        'whole_of_life': 'Whole of Life',
        'decreasing_term': 'Decreasing Term',
        'family_income_benefit': 'Family Income Benefit',
        'level_term': 'Level Term',
      };
      return typeMap[type] || type;
    },

    formatCIPolicyType(type) {
      const typeMap = {
        'standalone': 'Standalone',
        'accelerated': 'Accelerated',
        'additional': 'Additional',
      };
      return typeMap[type] || type;
    },
  },
};
</script>

<style scoped>
.protection-overview-card {
  min-width: 280px;
  max-width: 100%;
  display: flex;
  flex-direction: column;
  gap: 0;
}

/* Card Header */
.card-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 20px;
}

.card-title {
  font-size: 20px;
  font-weight: 600;
  @apply text-horizon-500;
}

.card-icon {
  display: flex;
  align-items: center;
  @apply text-horizon-400;
}

/* Policy Sections Container */
.policy-sections {
  display: flex;
  flex-direction: column;
  gap: 0;
}

/* Section Breakdown (with grey dividers) */
.section-breakdown {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

/* First section - no margin needed */
.section-breakdown:first-of-type {
  margin-top: 0;
}

/* Subsequent sections - margin, padding, AND border */
.section-breakdown + .section-breakdown {
  margin-top: 16px;
  padding-top: 16px;
  @apply border-t border-light-gray;
}

.section-header-with-badge {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 8px;
}

.section-header {
  font-size: 14px;
  font-weight: 600;
  @apply text-neutral-500;
}

/* Policy Count Badges */
.policy-count-badge {
  display: inline-flex;
  align-items: center;
  padding: 2px 8px;
  border-radius: 9999px;
  font-size: 12px;
  font-weight: 500;
}

.policy-count-badge-blue {
  @apply bg-white text-violet-800 border-2 border-violet-600;
}

.policy-count-badge-purple {
  @apply bg-white text-purple-800 border-2 border-violet-500;
}

.policy-count-badge-teal {
  @apply bg-white text-teal-800 border-2 border-teal-500;
}

.policy-count-badge-blue {
  @apply bg-white text-violet-800 border-2 border-violet-500;
}

/* Policy List */
.policy-list {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.policy-item {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  font-size: 12px;
}

.policy-info {
  flex: 1;
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.policy-provider {
  display: flex;
  align-items: center;
  gap: 8px;
}

.provider-name {
  font-weight: 600;
  @apply text-horizon-500;
}

.joint-badge {
  display: inline-flex;
  align-items: center;
  padding: 2px 8px;
  border-radius: 9999px;
  font-size: 10px;
  font-weight: 500;
  color: white;
}

.joint-badge-blue {
  @apply bg-raspberry-500;
}

.joint-badge-purple {
  @apply bg-purple-600;
}

.joint-badge-teal {
  @apply bg-teal-500;
}

.joint-badge-blue-alt {
  @apply bg-violet-500;
}

.policy-details {
  @apply text-neutral-500;
  font-size: 12px;
}

.policy-premium {
  font-weight: 600;
  margin-left: 8px;
  white-space: nowrap;
}

.policy-premium-blue {
  @apply text-violet-800;
}

.policy-premium-purple {
  @apply text-purple-800;
}

.policy-premium-teal {
  @apply text-teal-800;
}

.policy-premium-blue-alt {
  @apply text-violet-800;
}

/* Shortfalls Section */
.shortfalls-section {
  margin-top: 16px;
  padding-top: 16px;
  @apply border-t border-light-gray;
}

.shortfalls-header {
  @apply text-raspberry-600;
  margin-bottom: 8px;
}

.shortfalls-list {
  display: grid;
  grid-template-columns: 1fr;
  gap: 8px;
}

@media (min-width: 640px) {
  .shortfalls-list {
    grid-template-columns: 1fr 1fr;
    gap: 8px 16px;
  }
}

.shortfall-item {
  display: flex;
  align-items: flex-start;
  gap: 8px;
  font-size: 13px;
}

.shortfall-icon {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 18px;
  height: 18px;
  min-width: 18px;
  @apply bg-raspberry-50;
  @apply border border-raspberry-200;
  border-radius: 50%;
  @apply text-raspberry-600;
  font-size: 11px;
  font-weight: 700;
  margin-top: 2px;
}

.shortfall-content {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.shortfall-label {
  font-weight: 600;
  @apply text-neutral-500;
}

.shortfall-text {
  @apply text-neutral-500;
  font-size: 12px;
}

.shortfall-amount {
  @apply text-raspberry-600;
  font-size: 12px;
  font-weight: 500;
}

/* Status Banner */
.status-banner {
  margin-top: 16px;
  padding-top: 16px;
  @apply border-t border-light-gray;
  display: flex;
  align-items: center;
  padding: 12px;
  border-radius: 6px;
}

.status-banner-warning {
  @apply bg-violet-500;
}

.status-banner-success {
  @apply bg-spring-500;
}

.status-icon {
  height: 20px;
  width: 20px;
  color: white;
  margin-right: 8px;
  flex-shrink: 0;
}

.status-text {
  font-size: 14px;
  font-weight: 500;
  color: white;
}

@media (min-width: 640px) {
  .protection-overview-card {
    min-width: 320px;
  }
}

@media (min-width: 1024px) {
  .protection-overview-card {
    min-width: 360px;
  }
}
</style>
