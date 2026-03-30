<template>
  <div class="tax-status-panel bg-white rounded-lg shadow-md p-6">
    <!-- Loading State -->
    <div v-if="loading" class="flex flex-col items-center justify-center py-12">
      <div class="animate-spin rounded-full h-10 w-10 border-b-2 border-indigo-600"></div>
      <p class="mt-4 text-neutral-500">Loading tax information...</p>
    </div>

    <!-- Error State -->
    <div v-else-if="error" class="bg-raspberry-50 border border-raspberry-200 rounded-lg p-6 text-center">
      <p class="text-raspberry-600">{{ error }}</p>
      <button
        @click="loadTaxInfo"
        class="mt-4 px-4 py-2 bg-raspberry-600 text-white rounded-button hover:bg-raspberry-700 transition-colors"
      >
        Retry
      </button>
    </div>

    <!-- Tax Information -->
    <div v-else-if="taxInfo" class="space-y-6">
      <!-- Header with Tax Year -->
      <div class="flex items-center justify-between border-b border-light-gray pb-4">
        <div>
          <h3 class="text-lg font-semibold text-horizon-500">Tax Treatment</h3>
          <p class="text-sm text-neutral-500">{{ taxInfo.product_type_label }}</p>
        </div>
        <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-indigo-100 text-indigo-800">
          Tax Year {{ taxInfo.tax_year }}
        </span>
      </div>

      <!-- Tax Items Grid -->
      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div
          v-for="item in taxInfo.tax_items"
          :key="item.aspect"
          class="tax-item rounded-lg p-4 border"
          :class="getStatusBorderClass(item.status)"
        >
          <div class="flex items-start gap-3">
            <span
              class="flex-shrink-0 w-8 h-8 rounded-full flex items-center justify-center text-sm font-bold"
              :class="getStatusIconClass(item.status)"
            >
              {{ getStatusIcon(item.status) }}
            </span>
            <div class="flex-1 min-w-0">
              <h4 class="text-sm font-semibold text-horizon-500">{{ item.title }}</h4>
              <p class="mt-1 text-sm text-neutral-500">{{ item.summary }}</p>
            </div>
          </div>
        </div>
      </div>

      <!-- Status Legend -->
      <div class="flex flex-wrap gap-4 text-xs">
        <div class="flex items-center gap-1">
          <span class="w-4 h-4 rounded-full bg-spring-100 flex items-center justify-center text-spring-600 text-xs font-bold">&#10003;</span>
          <span class="text-neutral-500">Tax-Free</span>
        </div>
        <div class="flex items-center gap-1">
          <span class="w-4 h-4 rounded-full bg-raspberry-100 flex items-center justify-center text-raspberry-600 text-xs font-bold">!</span>
          <span class="text-neutral-500">Taxable</span>
        </div>
        <div class="flex items-center gap-1">
          <span class="w-4 h-4 rounded-full bg-violet-100 flex items-center justify-center text-violet-600 text-xs font-bold">&#8987;</span>
          <span class="text-neutral-500">Tax-Deferred</span>
        </div>
        <div class="flex items-center gap-1">
          <span class="w-4 h-4 rounded-full bg-purple-100 flex items-center justify-center text-purple-600 text-xs font-bold">&#8595;</span>
          <span class="text-neutral-500">Relief Available</span>
        </div>
      </div>

      <!-- Disclaimer -->
      <div class="bg-violet-50 border border-violet-200 rounded-lg p-4">
        <p class="text-xs text-violet-800">
          <strong>Important:</strong> Tax treatment depends on individual circumstances and may change.
          This is general information only and should not be considered tax advice.
          Please consult a qualified tax adviser for advice specific to your situation.
        </p>
      </div>
    </div>

    <!-- Empty State -->
    <div v-else class="text-center py-12">
      <p class="text-neutral-500">No tax information available for this product type.</p>
    </div>
  </div>
</template>

<script>
import api from '@/services/api';
import { currencyMixin } from '@/mixins/currencyMixin';

import logger from '@/utils/logger';
export default {
  name: 'TaxStatusPanel',
  mixins: [currencyMixin],

  props: {
    /**
     * Product category: 'investment' or 'savings'
     */
    productCategory: {
      type: String,
      required: true,
      validator: (v) => ['investment', 'savings'].includes(v),
    },
    /**
     * Product type (e.g., 'isa', 'gia', 'cash_isa', etc.)
     */
    productType: {
      type: String,
      required: true,
    },
    /**
     * Whether the account is an ISA (for savings accounts)
     */
    isIsa: {
      type: Boolean,
      default: false,
    },
  },

  data() {
    return {
      taxInfo: null,
      loading: true,
      error: null,
    };
  },

  mounted() {
    this.loadTaxInfo();
  },

  watch: {
    productType() {
      this.loadTaxInfo();
    },
    isIsa() {
      this.loadTaxInfo();
    },
  },

  methods: {
    async loadTaxInfo() {
      this.loading = true;
      this.error = null;

      try {
        let endpoint;
        if (this.productCategory === 'investment') {
          endpoint = `/tax-info/investment/${this.productType}`;
        } else {
          endpoint = `/tax-info/savings/${this.productType}?is_isa=${this.isIsa}`;
        }

        const response = await api.get(endpoint);
        this.taxInfo = response.data.data;
      } catch (err) {
        logger.error('Failed to load tax information:', err);
        this.error = 'Failed to load tax information. Please try again.';
      } finally {
        this.loading = false;
      }
    },

    getStatusBorderClass(status) {
      const classes = {
        exempt: 'border-spring-200 bg-spring-50',
        taxable: 'border-violet-200 bg-violet-50',
        deferred: 'border-violet-200 bg-violet-50',
        relief: 'border-purple-200 bg-purple-50',
        limit: 'border-light-gray bg-savannah-100',
      };
      return classes[status] || 'border-light-gray bg-savannah-100';
    },

    getStatusIconClass(status) {
      const classes = {
        exempt: 'bg-spring-100 text-spring-600',
        taxable: 'bg-violet-100 text-violet-600',
        deferred: 'bg-violet-100 text-violet-600',
        relief: 'bg-purple-100 text-purple-600',
        limit: 'bg-savannah-100 text-neutral-500',
      };
      return classes[status] || 'bg-savannah-100 text-neutral-500';
    },

    getStatusIcon(status) {
      const icons = {
        exempt: '\u2713',      // Check mark
        taxable: '!',          // Exclamation
        deferred: '\u23F1',    // Stopwatch
        relief: '\u2193',      // Down arrow
        limit: '\u2298',       // Circled division slash
      };
      return icons[status] || '\u2022';  // Bullet
    },
  },
};
</script>

<style scoped>
.tax-status-panel {
  animation: fadeIn 0.3s ease-out;
}

.tax-item {
  transition: transform 0.2s ease, box-shadow 0.2s ease;
}

.tax-item:hover {
  transform: translateY(-2px);
  box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
}
</style>
