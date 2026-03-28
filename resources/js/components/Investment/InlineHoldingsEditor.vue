<template>
  <div class="border-2 border-violet-300 rounded-lg p-4 mt-4">
    <!-- Header -->
    <div class="flex justify-between items-center mb-3">
      <span class="font-bold text-horizon-500">Holdings</span>
      <span class="text-xs text-neutral-500">
        {{ totalAllocated }}% allocated &bull; {{ remainingPercent }}% remaining
        <span v-if="accountValue > 0">({{ formatCurrency(remainingValue) }})</span>
      </span>
    </div>

    <!-- Column Headers -->
    <div v-if="localHoldings.length > 0" class="grid grid-cols-12 gap-2 mb-2 text-xs text-neutral-500 font-medium">
      <div class="col-span-4">Security Name</div>
      <div class="col-span-2">Type</div>
      <div class="col-span-2">Alloc. %</div>
      <div class="col-span-3">Amount Invested</div>
      <div class="col-span-1"></div>
    </div>

    <!-- Holding Rows -->
    <div
      v-for="(holding, index) in localHoldings"
      :key="index"
      class="mb-2"
    >
      <div class="grid grid-cols-12 gap-2 items-center">
        <!-- Security Name -->
        <div class="col-span-4">
          <input
            v-model="holding.security_name"
            type="text"
            class="w-full border border-horizon-300 rounded-md px-2 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500"
            placeholder="e.g. Vanguard FTSE All-World"
            @input="onFieldChange"
          />
        </div>

        <!-- Asset Type -->
        <div class="col-span-2">
          <select
            v-model="holding.asset_type"
            class="w-full border border-horizon-300 rounded-md px-1 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500"
            @change="onFieldChange"
          >
            <option value="">Select...</option>
            <option v-for="type in assetTypes" :key="type.value" :value="type.value">
              {{ type.label }}
            </option>
          </select>
        </div>

        <!-- Allocation % -->
        <div class="col-span-2">
          <div class="relative">
            <input
              v-model.number="holding.allocation_percent"
              type="number"
              min="0"
              :max="maxAllocation(index)"
              step="0.1"
              class="w-full border border-horizon-300 rounded-md px-2 py-1.5 text-sm text-right pr-6 focus:outline-none focus:ring-2 focus:ring-violet-500"
              placeholder="0"
              @input="onAllocationChange(index)"
            />
            <span class="absolute right-2 top-1/2 -translate-y-1/2 text-xs text-neutral-500">%</span>
          </div>
        </div>

        <!-- Amount Invested (cost_basis) -->
        <div class="col-span-3">
          <div class="relative">
            <span class="absolute left-2 top-1/2 -translate-y-1/2 text-xs text-neutral-500">&pound;</span>
            <input
              v-model.number="holding.cost_basis"
              type="number"
              min="0"
              step="0.01"
              class="w-full border border-horizon-300 rounded-md pl-5 pr-2 py-1.5 text-sm text-right focus:outline-none focus:ring-2 focus:ring-violet-500"
              placeholder="Optional"
              @input="onFieldChange"
            />
          </div>
        </div>

        <!-- Delete -->
        <div class="col-span-1 text-center">
          <button
            type="button"
            @click="removeRow(index)"
            class="text-raspberry-500 hover:text-raspberry-600 text-sm font-bold"
            title="Remove holding"
          >
            &times;
          </button>
        </div>
      </div>

      <!-- Calculated value -->
      <div v-if="holding.allocation_percent > 0" class="text-xs text-neutral-500 mt-0.5 pl-1">
        = {{ formatCurrency(holdingValue(holding)) }}
      </div>

      <!-- Edit mode: Details link for persisted holdings -->
      <div v-if="holding.id && accountId" class="text-right mt-0.5">
        <button
          type="button"
          @click="$emit('open-holding-details', holding)"
          class="text-xs text-violet-600 hover:text-violet-700 hover:underline"
        >
          Details
        </button>
      </div>
    </div>

    <!-- Add Holding Button -->
    <button
      type="button"
      :disabled="!canAddMore"
      @click="addRow"
      class="mt-2 w-full py-2 border border-dashed border-violet-400 text-violet-600 rounded-md text-sm font-medium hover:bg-violet-50 transition-colors disabled:opacity-40 disabled:cursor-not-allowed"
    >
      + Add Holding
    </button>

    <!-- Cash Remainder -->
    <div
      v-if="remainingPercent > 0"
      class="mt-2 px-3 py-2 bg-eggshell-500 rounded-md flex justify-between text-sm text-neutral-500"
    >
      <span>Cash (auto-allocated)</span>
      <span>{{ remainingPercent }}% &mdash; {{ formatCurrency(remainingValue) }}</span>
    </div>

    <!-- Cash Warning -->
    <div
      v-if="showCashWarning"
      class="mt-2 px-3 py-2 bg-violet-50 border border-violet-200 rounded-md text-sm text-violet-700"
    >
      At least 5% cash is advised &mdash; return-producing assets may need to be sold to cover fees
    </div>
  </div>
</template>

<script>
import { currencyMixin } from '@/mixins/currencyMixin';

const ASSET_TYPES = [
    { value: 'equity', label: 'Equity' },
    { value: 'uk_equity', label: 'UK Equity' },
    { value: 'us_equity', label: 'US Equity' },
    { value: 'international_equity', label: 'International Equity' },
    { value: 'fund', label: 'Fund' },
    { value: 'etf', label: 'ETF' },
    { value: 'bond', label: 'Bond' },
    { value: 'cash', label: 'Cash' },
    { value: 'alternative', label: 'Alternative' },
    { value: 'property', label: 'Property' },
];

export default {
  name: 'InlineHoldingsEditor',

  mixins: [currencyMixin],

  emits: ['update:holdings', 'open-holding-details'],

  props: {
    accountValue: {
      type: Number,
      required: true,
    },
    holdings: {
      type: Array,
      default: () => [],
    },
    accountId: {
      type: Number,
      default: null,
    },
  },

  data() {
    return {
      localHoldings: this.initHoldings(),
      assetTypes: ASSET_TYPES,
    };
  },

  computed: {
    totalAllocated() {
      return this.localHoldings.reduce((sum, h) => sum + (parseFloat(h.allocation_percent) || 0), 0);
    },

    remainingPercent() {
      return Math.max(0, 100 - this.totalAllocated);
    },

    remainingValue() {
      return (this.accountValue * this.remainingPercent) / 100;
    },

    showCashWarning() {
      // Calculate effective cash: explicit cash holdings + auto-remainder
      const explicitCash = this.localHoldings
        .filter(h => h.asset_type === 'cash')
        .reduce((sum, h) => sum + (parseFloat(h.allocation_percent) || 0), 0);
      const effectiveCash = explicitCash + this.remainingPercent;
      return this.totalAllocated > 0 && effectiveCash < 5;
    },

    canAddMore() {
      return this.totalAllocated < 100;
    },
  },

  watch: {
    holdings: {
      deep: true,
      handler(newHoldings) {
        // Update local holdings when prop changes (edit mode)
        if (JSON.stringify(newHoldings) !== JSON.stringify(this.stripInternal(this.localHoldings))) {
          this.localHoldings = this.initHoldings(newHoldings);
        }
      },
    },
  },

  methods: {
    initHoldings(holdings) {
      const source = holdings || this.holdings || [];
      return source.map(h => ({
        id: h.id || null,
        security_name: h.security_name || '',
        asset_type: h.asset_type || '',
        allocation_percent: h.allocation_percent ?? null,
        cost_basis: h.cost_basis ?? null,
        _isNew: !h.id,
      }));
    },

    addRow() {
      this.localHoldings.push({
        id: null,
        security_name: '',
        asset_type: '',
        allocation_percent: null,
        cost_basis: null,
        _isNew: true,
      });
      this.onFieldChange();
    },

    removeRow(index) {
      this.localHoldings.splice(index, 1);
      this.onFieldChange();
    },

    onFieldChange() {
      this.$emit('update:holdings', this.stripInternal(this.localHoldings));
    },

    onAllocationChange(index) {
      const holding = this.localHoldings[index];
      if (holding && this.accountValue > 0) {
        const percent = parseFloat(holding.allocation_percent) || 0;
        holding.cost_basis = Math.round((this.accountValue * percent) / 100 * 100) / 100;
      }
      this.onFieldChange();
    },

    stripInternal(holdings) {
      return holdings.map(h => ({
        id: h.id,
        security_name: h.security_name,
        asset_type: h.asset_type,
        allocation_percent: h.allocation_percent,
        cost_basis: h.cost_basis,
      }));
    },

    maxAllocation(index) {
      const otherTotal = this.localHoldings.reduce((sum, h, i) => {
        if (i === index) return sum;
        return sum + (parseFloat(h.allocation_percent) || 0);
      }, 0);
      return Math.max(0, 100 - otherTotal);
    },

    holdingValue(holding) {
      return (this.accountValue * (parseFloat(holding.allocation_percent) || 0)) / 100;
    },
  },
};
</script>
