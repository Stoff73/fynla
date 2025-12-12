<template>
  <div class="pension-card bg-white rounded-lg shadow border border-gray-200 hover:shadow-md hover:-translate-y-0.5 hover:border-primary-500 transition-all duration-200 cursor-pointer" @click="viewDetails">
    <!-- Card Header -->
    <div class="p-5 border-b border-gray-200">
      <div class="flex items-center justify-between">
        <div class="flex-1">
          <h4 class="text-lg font-semibold text-gray-900">{{ pension.scheme_name || pension.employer_name }}</h4>
          <p class="text-sm text-gray-500 mt-1">{{ typeLabel }}</p>
          <p v-if="pension.provider" class="text-xs text-gray-400 mt-1">{{ pension.provider }}</p>
        </div>
        <div class="flex items-center space-x-2">
          <button
            @click.stop="$emit('edit', pension)"
            class="p-2 text-gray-400 hover:text-indigo-600 transition-colors duration-200"
            title="Edit"
          >
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
            </svg>
          </button>
          <button
            @click.stop="$emit('delete', pension.id)"
            class="p-2 text-gray-400 hover:text-red-600 transition-colors duration-200"
            title="Delete"
          >
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
            </svg>
          </button>
          <button
            @click.stop="toggleExpand"
            class="p-2 text-gray-400 hover:text-gray-600 transition-colors duration-200"
            :title="expanded ? 'Collapse' : 'Expand'"
          >
            <svg class="w-5 h-5 transform transition-transform duration-200" :class="{ 'rotate-180': expanded }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
            </svg>
          </button>
        </div>
      </div>
    </div>

    <!-- Card Body -->
    <div class="p-5">
      <!-- DC Pension Summary -->
      <div v-if="type === 'dc'" class="space-y-3">
        <div class="flex items-center justify-between">
          <span class="text-sm text-gray-600">Current Fund Value</span>
          <span class="text-lg font-bold text-gray-900">{{ formatCurrency(pension.current_fund_value) }}</span>
        </div>

        <!-- Workplace Pension: Show Employee & Employer Contributions -->
        <template v-if="isWorkplacePension">
          <div class="flex items-center justify-between">
            <span class="text-sm text-gray-600">Employee Contribution</span>
            <span class="text-sm font-semibold text-gray-900">{{ parseFloat(pension.employee_contribution_percent || 0) }}%</span>
          </div>
          <div class="flex items-center justify-between">
            <span class="text-sm text-gray-600">Employer Contribution</span>
            <span class="text-sm font-semibold text-gray-900">{{ parseFloat(pension.employer_contribution_percent || 0) }}%</span>
          </div>
        </template>

        <!-- SIPP/Personal Pension: Show Monthly Contribution -->
        <template v-else-if="isPersonalPension">
          <div class="flex items-center justify-between">
            <span class="text-sm text-gray-600">Monthly Contribution</span>
            <span class="text-sm font-semibold text-gray-900">{{ formatCurrency(pension.monthly_contribution_amount) }}</span>
          </div>
        </template>
      </div>

      <!-- DB Pension Summary -->
      <div v-if="type === 'db'" class="space-y-3">
        <div class="flex items-center justify-between">
          <span class="text-sm text-gray-600">Annual Income</span>
          <span class="text-lg font-bold text-gray-900">{{ formatCurrency(pension.annual_income) }}<span class="text-sm text-gray-500">/year</span></span>
        </div>
        <div class="flex items-center justify-between">
          <span class="text-sm text-gray-600">Service Years</span>
          <span class="text-sm font-semibold text-gray-900">{{ parseFloat(pension.service_years || 0) }} years</span>
        </div>
        <div class="flex items-center justify-between">
          <span class="text-sm text-gray-600">Scheme Type</span>
          <span class="text-sm font-semibold text-gray-900">{{ pension.accrual_rate ? `1/${pension.accrual_rate}` : 'Final Salary' }}</span>
        </div>
      </div>
    </div>

    <!-- Expanded Details -->
    <transition name="expand">
      <div v-if="expanded" class="border-t border-gray-200 p-5 bg-gray-50">
        <!-- DC Expanded Details -->
        <div v-if="type === 'dc'" class="grid grid-cols-2 gap-4 text-sm">
          <div>
            <p class="text-gray-600 mb-1">Provider</p>
            <p class="font-medium text-gray-900">{{ pension.provider || 'N/A' }}</p>
          </div>
          <div>
            <p class="text-gray-600 mb-1">Policy Number</p>
            <p class="font-medium text-gray-900">{{ pension.policy_number || 'N/A' }}</p>
          </div>
          <div>
            <p class="text-gray-600 mb-1">Expected Return</p>
            <p class="font-medium text-gray-900">{{ parseFloat(pension.expected_return_percent || 0) }}% p.a.</p>
          </div>
          <div>
            <p class="text-gray-600 mb-1">Retirement Age</p>
            <p class="font-medium text-gray-900">{{ pension.retirement_age || 'N/A' }}</p>
          </div>
          <div class="col-span-2" v-if="pension.projected_fund_value">
            <p class="text-gray-600 mb-1">Projected Fund Value at Retirement</p>
            <p class="text-xl font-bold text-indigo-600">{{ formatCurrency(pension.projected_fund_value) }}</p>
          </div>
        </div>

        <!-- DB Expanded Details -->
        <div v-if="type === 'db'" class="grid grid-cols-2 gap-4 text-sm">
          <div>
            <p class="text-gray-600 mb-1">Scheme Status</p>
            <p class="font-medium text-gray-900">{{ pension.scheme_status || 'Active' }}</p>
          </div>
          <div>
            <p class="text-gray-600 mb-1">Normal Retirement Age</p>
            <p class="font-medium text-gray-900">{{ pension.normal_retirement_age || 'N/A' }}</p>
          </div>
          <div>
            <p class="text-gray-600 mb-1">Revaluation Rate</p>
            <p class="font-medium text-gray-900">{{ parseFloat(pension.revaluation_rate || 0) }}%</p>
          </div>
          <div>
            <p class="text-gray-600 mb-1">Pensionable Salary</p>
            <p class="font-medium text-gray-900">{{ formatCurrency(pension.final_salary) }}</p>
          </div>
          <div class="col-span-2">
            <p class="text-gray-600 mb-1">PCLS Available</p>
            <p class="font-medium text-gray-900">{{ pension.pcls_available ? formatCurrency(pension.pcls_available) : 'N/A' }}</p>
          </div>
        </div>
      </div>
    </transition>
  </div>
</template>

<script>
export default {
  name: 'PensionCard',

  props: {
    pension: {
      type: Object,
      required: true,
    },
    type: {
      type: String,
      required: true,
      validator: (value) => ['dc', 'db'].includes(value),
    },
  },

  data() {
    return {
      expanded: false,
    };
  },

  computed: {
    typeLabel() {
      if (this.type === 'db') {
        return 'Defined Benefit';
      }

      // For DC pensions, show the specific pension type
      if (this.pension.pension_type) {
        const typeMap = {
          'occupational': 'Occupational',
          'sipp': 'SIPP',
          'personal': 'Personal',
          'stakeholder': 'Stakeholder'
        };
        return typeMap[this.pension.pension_type] || 'Defined Contribution';
      }

      // Fallback to scheme_type if pension_type not available
      const schemeTypeMap = {
        'workplace': 'Occupational',
        'sipp': 'SIPP',
        'personal': 'Personal'
      };
      return schemeTypeMap[this.pension.scheme_type] || 'Defined Contribution';
    },

    isWorkplacePension() {
      return this.type === 'dc' && (this.pension.pension_type === 'occupational' || this.pension.scheme_type === 'workplace');
    },

    isPersonalPension() {
      // Include stakeholder pensions as personal pensions
      if (this.pension.pension_type) {
        return ['sipp', 'personal', 'stakeholder'].includes(this.pension.pension_type);
      }
      return this.pension.scheme_type === 'sipp' || this.pension.scheme_type === 'personal';
    },
  },

  methods: {
    viewDetails() {
      // Navigate to pension detail view similar to PropertyDetail
      this.$router.push(`/pension/${this.type}/${this.pension.id}`);
    },

    toggleExpand() {
      this.expanded = !this.expanded;
    },

    formatCurrency(value) {
      if (value === null || value === undefined) return '£0';
      return new Intl.NumberFormat('en-GB', {
        style: 'currency',
        currency: 'GBP',
        minimumFractionDigits: 0,
        maximumFractionDigits: 0,
      }).format(value);
    },
  },
};
</script>

<style scoped>
/* Expand/Collapse Animation */
.expand-enter-active,
.expand-leave-active {
  transition: all 0.3s ease;
  max-height: 500px;
  overflow: hidden;
}

.expand-enter-from,
.expand-leave-to {
  max-height: 0;
  opacity: 0;
}
</style>
