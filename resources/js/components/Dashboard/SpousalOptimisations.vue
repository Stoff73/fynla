<template>
  <div class="card">
    <h3 class="text-lg font-bold text-horizon-500 mb-4">Spousal Optimisations</h3>

    <!-- Loading -->
    <div v-if="loading" class="flex justify-center py-8">
      <div class="w-10 h-10 border-4 border-horizon-200 border-t-raspberry-500 rounded-full animate-spin"></div>
    </div>

    <!-- Error -->
    <div v-else-if="error" class="text-center py-6">
      <p class="text-neutral-500 mb-2">{{ error }}</p>
      <button @click="fetchData" class="text-sm text-raspberry-500 hover:text-raspberry-600">Retry</button>
    </div>

    <!-- No recommendations -->
    <div v-else-if="optimisations.length === 0" class="text-center py-6">
      <p class="text-neutral-500">No spousal optimisations available.</p>
      <p class="text-xs text-neutral-400 mt-1">Optimisations require a linked partner with data sharing enabled.</p>
    </div>

    <!-- Recommendations -->
    <div v-else class="space-y-4">
      <div
        v-for="(opt, index) in optimisations"
        :key="index"
        class="rounded-lg border border-violet-200 bg-violet-50 p-4"
      >
        <div class="flex items-start justify-between gap-3">
          <div class="flex-1">
            <div class="flex items-center gap-2 mb-2">
              <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold bg-violet-100 text-violet-600">
                {{ formatType(opt.type) }}
              </span>
              <span v-if="opt.potential_savings > 0" class="text-sm font-bold text-spring-500">
                Save {{ formatCurrency(opt.potential_savings) }}/year
              </span>
            </div>
            <p class="text-sm text-horizon-500 leading-relaxed">{{ opt.description }}</p>
          </div>
        </div>
        <div class="mt-3 pt-3 border-t border-violet-200">
          <p class="text-xs text-neutral-500">
            <span class="font-semibold">Suggested action:</span> {{ opt.action }}
          </p>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import { currencyMixin } from '@/mixins/currencyMixin';
import householdService from '@/services/householdService';

export default {
  name: 'SpousalOptimisations',
  mixins: [currencyMixin],
  data() {
    return {
      optimisations: [],
      loading: false,
      error: null,
    };
  },
  mounted() {
    this.fetchData();
  },
  methods: {
    async fetchData() {
      this.loading = true;
      this.error = null;
      try {
        const response = await householdService.getOptimisations();
        if (response.success) {
          this.optimisations = response.data || [];
        } else {
          this.error = response.message || 'Failed to load optimisations';
        }
      } catch (err) {
        this.error = 'Unable to load spousal optimisations';
      } finally {
        this.loading = false;
      }
    },
    formatType(type) {
      const labels = {
        isa_allowance: 'ISA Allowance',
        pension_contribution: 'Pension',
        asset_transfer: 'Asset Transfer',
        marriage_allowance: 'Marriage Allowance',
      };
      return labels[type] || type;
    },
  },
};
</script>
