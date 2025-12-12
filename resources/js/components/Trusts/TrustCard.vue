<template>
  <div class="card border border-gray-200 hover:shadow-lg hover:-translate-y-0.5 hover:border-purple-500 transition-all duration-200 cursor-pointer">
    <!-- Header -->
    <div class="flex items-start justify-between mb-4">
      <div class="flex-1">
        <h3 class="text-lg font-semibold text-gray-900">{{ trust.trust_name }}</h3>
        <p class="text-sm text-gray-600 mt-1">{{ formatTrustType(trust.trust_type) }}</p>
      </div>
      <span
        :class="[
          'px-3 py-1 text-xs font-medium rounded-full',
          trust.is_active
            ? 'bg-green-100 text-green-800'
            : 'bg-gray-100 text-gray-800'
        ]"
      >
        {{ trust.is_active ? 'Active' : 'Inactive' }}
      </span>
    </div>

    <!-- Key Info -->
    <div class="grid grid-cols-2 gap-4 mb-4">
      <div>
        <p class="text-xs text-gray-600">Creation Date</p>
        <p class="text-sm font-medium text-gray-900">{{ formatDate(trust.trust_creation_date) }}</p>
      </div>
      <div>
        <p class="text-xs text-gray-600">Current Value</p>
        <p class="text-sm font-medium text-gray-900">{{ formatCurrency(trust.total_asset_value || trust.current_value) }}</p>
      </div>
    </div>

    <!-- RPT Badge -->
    <div v-if="trust.is_relevant_property_trust" class="mb-4">
      <span class="inline-flex items-center px-2 py-1 text-xs font-medium text-amber-800 bg-amber-100 rounded-full">
        <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>
        Relevant Property Trust (10-year charges apply)
      </span>
    </div>

    <!-- Periodic Charge Info -->
    <div v-if="trust.is_relevant_property_trust && trust.last_periodic_charge_date" class="bg-gray-50 rounded-lg p-3 mb-4">
      <p class="text-xs text-gray-600 mb-1">Last Periodic Charge</p>
      <p class="text-sm font-medium text-gray-900">
        {{ formatDate(trust.last_periodic_charge_date) }} - {{ formatCurrency(trust.last_periodic_charge_amount) }}
      </p>
    </div>

    <!-- Actions -->
    <div class="flex items-center justify-between pt-4 border-t border-gray-200">
      <button
        @click="$emit('view', trust)"
        class="text-sm text-purple-600 hover:text-purple-700 font-medium"
      >
        View Details
      </button>
      <div class="flex items-center space-x-2">
        <button
          @click="$emit('calculate-iht', trust)"
          class="p-2 text-gray-600 hover:text-purple-600 hover:bg-purple-50 rounded-lg transition-colors"
          title="Calculate inheritance tax impact"
        >
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
          </svg>
        </button>
        <button
          @click="$emit('edit', trust)"
          class="p-2 text-gray-600 hover:text-purple-600 hover:bg-purple-50 rounded-lg transition-colors"
          title="Edit Trust"
        >
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
          </svg>
        </button>
      </div>
    </div>
  </div>
</template>

<script>
export default {
  name: 'TrustCard',

  props: {
    trust: {
      type: Object,
      required: true,
    },
  },

  methods: {
    formatCurrency(value) {
      if (!value) return '£0';
      return new Intl.NumberFormat('en-GB', {
        style: 'currency',
        currency: 'GBP',
        minimumFractionDigits: 0,
        maximumFractionDigits: 0,
      }).format(value);
    },

    formatDate(dateString) {
      if (!dateString) return '-';
      return new Date(dateString).toLocaleDateString('en-GB', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
      });
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
