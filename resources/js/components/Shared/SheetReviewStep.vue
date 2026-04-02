<template>
  <div class="space-y-4">
    <!-- Header -->
    <div class="bg-spring-50 border border-spring-200 rounded-lg p-4">
      <div class="flex items-start">
        <svg class="w-5 h-5 text-spring-500 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
          <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
        </svg>
        <div class="ml-3">
          <p class="text-sm font-medium text-spring-800">
            Workbook analysed — {{ activeSheets.length }} sheet{{ activeSheets.length !== 1 ? 's' : '' }} with data found
          </p>
          <p class="text-sm text-spring-700 mt-1">
            Review each sheet below, adjust the category or account match if needed, then confirm.
          </p>
        </div>
      </div>
    </div>

    <!-- Sheet Cards -->
    <div
      v-for="(sheet, index) in localSheets"
      :key="index"
      class="bg-white border border-light-gray rounded-xl overflow-hidden"
    >
      <!-- Sheet Header -->
      <div class="px-4 py-3 flex items-center justify-between bg-savannah-100">
        <div class="flex items-center gap-3">
          <span class="text-horizon-700 font-bold text-sm">
            {{ sheet.sheet_name }}
          </span>
          <span class="text-xs text-neutral-500">
            {{ sheet.row_count }} row{{ sheet.row_count !== 1 ? 's' : '' }}
          </span>
        </div>

        <!-- Category Dropdown -->
        <select
          v-model="localSheets[index].category"
          class="text-sm border-horizon-300 rounded-md shadow-sm focus:border-violet-500 focus:ring-violet-500"
        >
          <option value="investment_holdings">Investment Holdings</option>
          <option value="pension_holdings">Pension Holdings</option>
          <option value="cash_savings">Cash & Savings</option>
          <option value="property">Property</option>
          <option value="protection">Protection</option>
          <option value="ignore">Skip this Sheet</option>
        </select>
      </div>

      <!-- Sheet Body (not shown for ignored/error sheets) -->
      <div v-if="sheet.category !== 'ignore' && sheet.category !== 'error'" class="px-4 py-3 space-y-3">
        <!-- Account Match (investment/pension only) -->
        <div v-if="isHoldingsCategory(sheet.category)" class="flex items-center gap-3">
          <label class="text-sm text-neutral-500 whitespace-nowrap">Match to:</label>
          <select
            v-model="localSheets[index].selectedAccountId"
            class="flex-1 text-sm border-horizon-300 rounded-md shadow-sm focus:border-violet-500 focus:ring-violet-500"
          >
            <option :value="null">Create new account</option>
            <option
              v-if="sheet.matched_account"
              :value="sheet.matched_account.id"
            >
              {{ sheet.matched_account.name }} ({{ sheet.matched_account.type }})
            </option>
          </select>
        </div>

        <!-- Account metadata summary -->
        <div v-if="sheet.account && sheet.account.provider" class="text-xs text-neutral-500">
          Provider: {{ sheet.account.provider }}
          <span v-if="sheet.account.account_type"> | Type: {{ formatAccountType(sheet.account.account_type) }}</span>
          <span v-if="sheet.account.total_value"> | Value: {{ formatCurrency(sheet.account.total_value) }}</span>
        </div>

        <!-- Holdings table (expandable) -->
        <div v-if="isHoldingsCategory(sheet.category) && sheet.holdings && sheet.holdings.length > 0">
          <button
            type="button"
            class="flex items-center gap-1 text-sm text-violet-600 hover:text-violet-700 font-medium"
            @click="toggleExpanded(index)"
          >
            <svg
              class="w-4 h-4 transition-transform"
              :class="{ 'rotate-90': expandedSheets.has(index) }"
              fill="currentColor"
              viewBox="0 0 20 20"
            >
              <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd" />
            </svg>
            {{ sheet.holdings.length }} holding{{ sheet.holdings.length !== 1 ? 's' : '' }}
          </button>

          <div v-if="expandedSheets.has(index)" class="mt-2">
            <HoldingsReviewTable
              :holdings="sheet.holdings"
              @update:holdings="updateSheetHoldings(index, $event)"
            />
          </div>
        </div>

        <!-- Property list -->
        <div v-if="sheet.category === 'property' && sheet.properties && sheet.properties.length > 0" class="space-y-2">
          <div
            v-for="(prop, pi) in sheet.properties"
            :key="pi"
            class="text-sm text-horizon-500 bg-savannah-100 rounded-lg p-2"
          >
            <span class="font-medium">{{ prop.address || 'Property' }}</span>
            <span v-if="prop.current_value"> — {{ formatCurrency(prop.current_value) }}</span>
            <span v-if="prop.property_type" class="text-xs text-neutral-500 ml-2">{{ formatPropertyType(prop.property_type) }}</span>
          </div>
        </div>

        <!-- Policies list -->
        <div v-if="sheet.category === 'protection' && sheet.policies && sheet.policies.length > 0" class="space-y-2">
          <div
            v-for="(policy, pi) in sheet.policies"
            :key="pi"
            class="text-sm text-horizon-500 bg-savannah-100 rounded-lg p-2"
          >
            <span class="font-medium">{{ policy.provider || 'Policy' }}</span>
            <span v-if="policy.sum_assured"> — {{ formatCurrency(policy.sum_assured) }}</span>
            <span v-if="policy.policy_type" class="text-xs text-neutral-500 ml-2">{{ formatPolicyType(policy.policy_type) }}</span>
          </div>
        </div>

        <!-- Cash/savings summary -->
        <div v-if="sheet.category === 'cash_savings' && sheet.account" class="text-sm text-horizon-500">
          <span v-if="sheet.account.institution" class="font-medium">{{ sheet.account.institution }}</span>
          <span v-if="sheet.account.balance"> — {{ formatCurrency(sheet.account.balance) }}</span>
          <span v-if="sheet.account.interest_rate" class="text-xs text-neutral-500 ml-2">{{ (sheet.account.interest_rate * 100).toFixed(2) }}% AER</span>
        </div>

        <!-- Warnings -->
        <div v-if="sheet.warnings && sheet.warnings.length > 0" class="text-xs text-violet-600">
          <span v-for="warning in sheet.warnings" :key="warning" class="block">{{ warning }}</span>
        </div>
      </div>

      <!-- Error state -->
      <div v-else-if="sheet.category === 'error'" class="px-4 py-3">
        <p class="text-sm text-raspberry-600">{{ sheet.error || 'Failed to process this sheet' }}</p>
      </div>

      <!-- Ignored state -->
      <div v-else-if="sheet.category === 'ignore'" class="px-4 py-2">
        <p class="text-xs text-neutral-500 italic">This sheet will be skipped</p>
      </div>
    </div>

    <!-- Import Button -->
    <div class="flex justify-end gap-3 pt-2">
      <button
        type="button"
        class="inline-flex items-center px-4 py-2 border border-horizon-300 text-sm font-medium rounded-md text-neutral-500 bg-white hover:bg-savannah-100"
        @click="$emit('close')"
      >
        Cancel
      </button>
      <button
        type="button"
        class="inline-flex items-center px-5 py-2 border border-transparent text-sm font-bold rounded-md text-white bg-raspberry-600 hover:bg-raspberry-700 transition-colors"
        :disabled="activeSheets.length === 0"
        @click="handleConfirm"
      >
        Import {{ activeSheets.length }} Sheet{{ activeSheets.length !== 1 ? 's' : '' }}
      </button>
    </div>
  </div>
</template>

<script>
import { currencyMixin } from '@/mixins/currencyMixin';
import HoldingsReviewTable from './HoldingsReviewTable.vue';

export default {
  name: 'SheetReviewStep',

  components: {
    HoldingsReviewTable,
  },

  mixins: [currencyMixin],

  props: {
    sheets: {
      type: Array,
      required: true,
    },
    documentId: {
      type: Number,
      required: true,
    },
  },

  emits: ['confirm', 'close'],

  data() {
    return {
      localSheets: [],
      expandedSheets: new Set(),
    };
  },

  computed: {
    activeSheets() {
      return this.localSheets.filter(s => s.category !== 'ignore' && s.category !== 'error');
    },
  },

  created() {
    // Deep copy sheets for local editing
    this.localSheets = this.sheets.map(sheet => ({
      ...sheet,
      selectedAccountId: sheet.matched_account?.id ?? null,
    }));

    // Auto-expand first holdings sheet
    const firstHoldingsIndex = this.localSheets.findIndex(s => this.isHoldingsCategory(s.category));
    if (firstHoldingsIndex >= 0) {
      this.expandedSheets.add(firstHoldingsIndex);
    }
  },

  methods: {
    isHoldingsCategory(category) {
      return category === 'investment_holdings' || category === 'pension_holdings';
    },

    toggleExpanded(index) {
      if (this.expandedSheets.has(index)) {
        this.expandedSheets.delete(index);
      } else {
        this.expandedSheets.add(index);
      }
      // Force reactivity
      this.expandedSheets = new Set(this.expandedSheets);
    },

    updateSheetHoldings(index, updatedHoldings) {
      this.localSheets[index].holdings = updatedHoldings;
    },

    formatAccountType(type) {
      const labels = {
        isa: 'Stocks & Shares Individual Savings Account',
        gia: 'General Investment Account',
        sipp: 'Self-Invested Personal Pension',
        savings: 'Savings Account',
        current: 'Current Account',
      };
      return labels[type] || type;
    },

    formatPropertyType(type) {
      const labels = {
        main_residence: 'Main Residence',
        secondary_residence: 'Secondary Residence',
        buy_to_let: 'Buy to Let',
      };
      return labels[type] || type;
    },

    formatPolicyType(type) {
      const labels = {
        term: 'Term Life',
        whole_of_life: 'Whole of Life',
        critical_illness: 'Critical Illness',
        income_protection: 'Income Protection',
      };
      return labels[type] || type;
    },

    handleConfirm() {
      const confirmedSheets = this.localSheets
        .filter(s => s.category !== 'ignore' && s.category !== 'error')
        .map(sheet => ({
          sheet_name: sheet.sheet_name,
          category: sheet.category,
          matched_account_id: sheet.selectedAccountId,
          create_new: !sheet.selectedAccountId,
          account: sheet.account || {},
          holdings: sheet.holdings || [],
          properties: sheet.properties || [],
          policies: sheet.policies || [],
        }));

      this.$emit('confirm', confirmedSheets);
    },
  },
};
</script>
