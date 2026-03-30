<template>
  <div class="policy-card bg-white rounded-lg border border-light-gray shadow-sm hover:shadow-md hover:-translate-y-0.5 hover:bg-light-gray transition-all duration-200 cursor-pointer" @click="viewDetails">
    <!-- Card Header (Always Visible) -->
    <div class="p-4">
      <div class="flex items-start justify-between">
        <div class="flex-1">
          <div class="flex items-center gap-3 mb-2">
            <span class="text-sm font-medium text-neutral-500">
              {{ policyTypeLabel }}
            </span>
            <!-- Life Policy Type Tag (only for life insurance) -->
            <span
              v-if="isLifePolicy && lifePolicyTypeLabel"
              class="px-2 py-1 text-xs font-medium bg-spring-100 text-spring-700 rounded"
            >
              {{ lifePolicyTypeLabel }}
            </span>
          </div>

          <h4 class="text-lg font-semibold text-horizon-500 mb-1">
            {{ policy.provider || 'Unknown Provider' }}
          </h4>

          <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 sm:gap-4 text-sm">
            <div>
              <span class="text-neutral-500">{{ coverageLabel }}:</span>
              <span class="font-semibold text-horizon-500 ml-1">
                {{ formatCurrency(coverageAmount) }}
              </span>
            </div>
            <div>
              <span class="text-neutral-500">Premium:</span>
              <span class="font-semibold text-horizon-500 ml-1">
                {{ formatCurrency(policy.premium_amount) }}/{{ policy.premium_frequency || 'month' }}
              </span>
            </div>
          </div>
        </div>

        <svg
          class="ml-4 w-5 h-5 text-horizon-400 flex-shrink-0"
          fill="none"
          viewBox="0 0 24 24"
          stroke="currentColor"
        >
          <path
            stroke-linecap="round"
            stroke-linejoin="round"
            stroke-width="2"
            d="M9 5l7 7-7 7"
          />
        </svg>
      </div>
    </div>
  </div>
</template>

<script>
import { currencyMixin } from '@/mixins/currencyMixin';

export default {
  name: 'PolicyCard',
  mixins: [currencyMixin],

  props: {
    policy: {
      type: Object,
      required: true,
    },
  },

  computed: {
    policyTypeLabel() {
      const labels = {
        life: 'Life Insurance',
        criticalIllness: 'Critical Illness',
        incomeProtection: 'Income Protection',
        disability: 'Disability',
        sicknessIllness: 'Sickness/Illness',
      };
      return labels[this.policy.policy_type] || 'Policy';
    },

    policyTypeBadgeClass() {
      const classes = {
        life: 'bg-violet-100 text-violet-800',
        criticalIllness: 'bg-purple-100 text-purple-800',
        incomeProtection: 'bg-spring-100 text-spring-800',
        disability: 'bg-indigo-100 text-indigo-800',
        sicknessIllness: 'bg-raspberry-100 text-raspberry-800',
      };
      return classes[this.policy.policy_type] || 'bg-savannah-100 text-horizon-500';
    },

    coverageLabel() {
      const type = this.policy.policy_type;
      if (type === 'life' || type === 'criticalIllness') {
        return 'Sum Assured';
      }
      return 'Benefit Amount';
    },

    coverageAmount() {
      return this.policy.sum_assured || this.policy.benefit_amount || 0;
    },

    isActive() {
      // Policy is active if it has a start date and hasn't expired
      const startDateField = this.policy.policy_start_date || this.policy.start_date;
      if (!startDateField) return false;

      const startDate = new Date(startDateField);
      const now = new Date();

      if (startDate > now) return false; // Not started yet

      const termYears = this.policy.policy_term_years || this.policy.term_years;
      if (termYears) {
        const endDate = new Date(startDate);
        endDate.setFullYear(endDate.getFullYear() + termYears);
        return endDate > now;
      }

      if (this.policy.benefit_period_months) {
        const endDate = new Date(startDate);
        endDate.setMonth(endDate.getMonth() + this.policy.benefit_period_months);
        return endDate > now;
      }

      return true; // No end date specified, assume active
    },

    isLifePolicy() {
      return this.policy.policy_type === 'life';
    },

    lifePolicyTypeLabel() {
      if (!this.isLifePolicy || !this.policy.policy_subtype) return null;

      const labels = {
        decreasing_term: 'Decreasing Term',
        level_term: 'Level Term',
        whole_of_life: 'Whole of Life',
        term: 'Term',
        family_income_benefit: 'Family Income Benefit',
      };
      return labels[this.policy.policy_subtype] || this.policy.policy_subtype;
    },
  },

  methods: {
    viewDetails() {
      // Navigate to policy detail page with policy type and id
      this.$router.push(`/protection/policy/${this.policy.policy_type}/${this.policy.id}`);
    },

    formatDate(dateString) {
      return new Date(dateString).toLocaleDateString('en-GB');
    },

    formatCoverageType(type) {
      return type.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
    },

    formatBenefitFrequency(frequency) {
      const map = {
        monthly: 'Monthly',
        weekly: 'Weekly',
        lump_sum: 'Lump Sum',
      };
      return map[frequency] || frequency;
    },

    parseConditions(conditions) {
      if (Array.isArray(conditions)) {
        return conditions;
      }
      try {
        return JSON.parse(conditions);
      } catch {
        return [];
      }
    },
  },
};
</script>

<style scoped>
.policy-card {
  transition: all 0.2s ease;
}

@media (max-width: 640px) {
  .policy-card .flex.gap-3 {
    flex-direction: column;
  }

  .policy-card .flex-1 {
    width: 100%;
  }
}
</style>
