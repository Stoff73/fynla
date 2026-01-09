<template>
  <div
    class="protection-overview-card bg-white rounded-lg shadow-md p-6 cursor-pointer hover:shadow-lg hover:-translate-y-0.5 hover:border-primary-500 transition-all duration-200 border border-gray-200"
    @click="navigateToProtection"
  >
    <!-- Card Header -->
    <div class="card-header">
      <h3 class="card-title">Protection</h3>
      <span class="card-icon">
        <svg
          xmlns="http://www.w3.org/2000/svg"
          class="w-6 h-6"
          fill="none"
          viewBox="0 0 24 24"
          stroke="currentColor"
          stroke-width="1.5"
        >
          <path
            stroke-linecap="round"
            stroke-linejoin="round"
            d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"
          />
        </svg>
      </span>
    </div>

    <!-- Policy Sections -->
    <div class="policy-sections">
      <!-- Life Insurance Policies -->
      <div class="section-breakdown">
        <div class="section-header-with-badge">
          <span class="section-header">Life Insurance</span>
          <span
            v-if="lifePolicies.length > 0"
            class="policy-count-badge policy-count-badge-blue"
          >
            {{ lifePolicies.length }} {{ lifePolicies.length === 1 ? 'policy' : 'policies' }}
          </span>
        </div>
        <div v-if="lifePolicies.length > 0" class="policy-list">
          <div
            v-for="policy in lifePolicies"
            :key="policy.id"
            class="policy-item"
          >
            <div class="policy-info">
              <div class="policy-provider">
                <span class="provider-name">{{ policy.provider_name }}</span>
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
        <div v-else>
          <p class="no-policies-text">No policies</p>
        </div>
      </div>

      <!-- Critical Illness Policies -->
      <div class="section-breakdown">
        <div class="section-header-with-badge">
          <span class="section-header">Critical Illness</span>
          <span
            v-if="criticalIllnessPolicies.length > 0"
            class="policy-count-badge policy-count-badge-purple"
          >
            {{ criticalIllnessPolicies.length }} {{ criticalIllnessPolicies.length === 1 ? 'policy' : 'policies' }}
          </span>
        </div>
        <div v-if="criticalIllnessPolicies.length > 0" class="policy-list">
          <div
            v-for="policy in criticalIllnessPolicies"
            :key="policy.id"
            class="policy-item"
          >
            <div class="policy-info">
              <div class="policy-provider">
                <span class="provider-name">{{ policy.provider_name }}</span>
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
        <div v-else>
          <p class="no-policies-text">No policies</p>
        </div>
      </div>

      <!-- Income Protection Policies -->
      <div class="section-breakdown">
        <div class="section-header-with-badge">
          <span class="section-header">Income Protection</span>
          <span
            v-if="incomeProtectionPolicies.length > 0"
            class="policy-count-badge policy-count-badge-teal"
          >
            {{ incomeProtectionPolicies.length }} {{ incomeProtectionPolicies.length === 1 ? 'policy' : 'policies' }}
          </span>
        </div>
        <div v-if="incomeProtectionPolicies.length > 0" class="policy-list">
          <div
            v-for="policy in incomeProtectionPolicies"
            :key="policy.id"
            class="policy-item"
          >
            <div class="policy-info">
              <div class="policy-provider">
                <span class="provider-name">{{ policy.provider_name }}</span>
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
        <div v-else>
          <p class="no-policies-text">No policies</p>
        </div>
      </div>

      <!-- Disability Policies -->
      <div class="section-breakdown">
        <div class="section-header-with-badge">
          <span class="section-header">Disability Insurance</span>
          <span
            v-if="disabilityPolicies.length > 0"
            class="policy-count-badge policy-count-badge-amber"
          >
            {{ disabilityPolicies.length }} {{ disabilityPolicies.length === 1 ? 'policy' : 'policies' }}
          </span>
        </div>
        <div v-if="disabilityPolicies.length > 0" class="policy-list">
          <div
            v-for="policy in disabilityPolicies"
            :key="policy.id"
            class="policy-item"
          >
            <div class="policy-info">
              <div class="policy-provider">
                <span class="provider-name">{{ policy.provider_name }}</span>
                <span
                  v-if="policy.is_joint"
                  class="joint-badge joint-badge-amber"
                >
                  Joint
                </span>
              </div>
              <p class="policy-details">Disability coverage</p>
            </div>
            <span class="policy-premium policy-premium-amber">{{ formatCurrency(policy.premium_amount) }}/mo</span>
          </div>
        </div>
        <div v-else>
          <p class="no-policies-text">No policies</p>
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

export default {
  name: 'ProtectionOverviewCard',
  mixins: [currencyMixin],

  props: {
    totalCoverage: {
      type: Number,
      required: true,
      default: 0,
    },
    premiumTotal: {
      type: Number,
      required: true,
      default: 0,
    },
    criticalGaps: {
      type: Number,
      required: true,
      default: 0,
    },
    hasLifePoliciesInTrust: {
      type: Boolean,
      default: false,
    },
    hasLifePoliciesNotInTrust: {
      type: Boolean,
      default: false,
    },
    hasIncomeProtection: {
      type: Boolean,
      default: false,
    },
    hasCriticalIllness: {
      type: Boolean,
      default: false,
    },
    hasDisabilityInsurance: {
      type: Boolean,
      default: false,
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
  },

  computed: {
    formattedTotalCoverage() {
      return new Intl.NumberFormat('en-GB', {
        style: 'currency',
        currency: 'GBP',
        minimumFractionDigits: 0,
        maximumFractionDigits: 0,
      }).format(this.totalCoverage);
    },

    formattedPremiumTotal() {
      return new Intl.NumberFormat('en-GB', {
        style: 'currency',
        currency: 'GBP',
        minimumFractionDigits: 0,
        maximumFractionDigits: 0,
      }).format(this.premiumTotal);
    },
  },

  methods: {
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
  color: #1f2937;
}

.card-icon {
  display: flex;
  align-items: center;
  color: #9ca3af;
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
  border-top: 1px solid #e5e7eb;
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
  color: #374151;
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
  background-color: #ffffff;
  color: #1e40af;
  border: 2px solid #2563eb;
}

.policy-count-badge-purple {
  background-color: #ffffff;
  color: #6b21a8;
  border: 2px solid #7c3aed;
}

.policy-count-badge-teal {
  background-color: #ffffff;
  color: #115e59;
  border: 2px solid #14b8a6;
}

.policy-count-badge-amber {
  background-color: #ffffff;
  color: #92400e;
  border: 2px solid #f59e0b;
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
  color: #111827;
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
  background-color: #2563eb;
}

.joint-badge-purple {
  background-color: #7c3aed;
}

.joint-badge-teal {
  background-color: #14b8a6;
}

.joint-badge-amber {
  background-color: #f59e0b;
}

.policy-details {
  color: #6b7280;
  font-size: 12px;
}

.policy-premium {
  font-weight: 600;
  margin-left: 8px;
  white-space: nowrap;
}

.policy-premium-blue {
  color: #1e40af;
}

.policy-premium-purple {
  color: #6b21a8;
}

.policy-premium-teal {
  color: #115e59;
}

.policy-premium-amber {
  color: #92400e;
}

.no-policies-text {
  font-size: 14px;
  font-weight: 500;
  color: #dc2626;
}

/* Status Banner */
.status-banner {
  margin-top: 16px;
  padding-top: 16px;
  border-top: 1px solid #e5e7eb;
  display: flex;
  align-items: center;
  padding: 12px;
  border-radius: 6px;
}

.status-banner-warning {
  background-color: #f59e0b;
}

.status-banner-success {
  background-color: #10b981;
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
