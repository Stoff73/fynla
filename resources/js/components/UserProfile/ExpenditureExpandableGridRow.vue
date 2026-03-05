<template>
  <!-- Expandable grid row for commitment categories -->
  <!-- Header row - clickable to expand -->
  <div
    :class="['col-label text-body-sm py-1 cursor-pointer select-none', indent ? 'pl-7' : '']"
    @click="toggleExpanded"
  >
    <div class="flex items-center gap-1">
      <svg
        v-if="hasItems"
        :class="['h-4 w-4 text-horizon-400 transition-transform', isExpanded ? 'rotate-90' : '']"
        fill="none"
        viewBox="0 0 24 24"
        stroke="currentColor"
      >
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
      </svg>
      <span v-else class="w-4"></span>
      <span class="text-neutral-500">{{ label }}</span>
    </div>
  </div>
  <div :class="['col-value text-body-sm py-1 text-horizon-500', hasItems ? 'cursor-pointer' : '']" @click="toggleExpanded">
    {{ formatCurrency(value) }}
  </div>
  <div v-if="isMarried" :class="['col-value-mid text-body-sm py-1 text-horizon-500', hasItems ? 'cursor-pointer' : '']" @click="toggleExpanded">
    {{ formatCurrency(spouseValue) }}
  </div>
  <div v-if="isMarried" :class="['col-total text-body-sm py-1 font-medium text-horizon-500', hasItems ? 'cursor-pointer' : '']" @click="toggleExpanded">
    {{ formatCurrency(householdValue) }}
  </div>

  <!-- Expanded items -->
  <template v-if="isExpanded && hasItems">
    <template v-for="item in mergedItems" :key="item.id">
      <!-- Item row - expandable if has breakdown -->
      <div
        :class="['col-label text-body-sm py-1 pl-14 text-neutral-500', item.hasBreakdown ? 'cursor-pointer' : '']"
        @click="item.hasBreakdown && toggleItemExpanded(item.id)"
      >
        <div class="flex items-center gap-1">
          <svg
            v-if="item.hasBreakdown"
            :class="['h-3 w-3 text-horizon-400 transition-transform', expandedItems[item.id] ? 'rotate-90' : '']"
            fill="none"
            viewBox="0 0 24 24"
            stroke="currentColor"
          >
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
          </svg>
          <span v-else class="w-3"></span>
          <span>{{ item.name }}</span>
          <span v-if="item.is_joint" class="text-xs text-raspberry-500">({{ item.ownership_percentage || 50 }}%)</span>
        </div>
      </div>
      <div
        :class="['col-value text-body-sm py-1 text-neutral-500', item.hasBreakdown ? 'cursor-pointer' : '']"
        @click="item.hasBreakdown && toggleItemExpanded(item.id)"
      >
        <div>{{ formatCurrency(item.userAmount) }}</div>
        <div v-if="item.userLumpSum > 0" class="text-xs text-horizon-400">+ {{ formatCurrency(item.userLumpSum) }} lump sum</div>
      </div>
      <div
        v-if="isMarried"
        :class="['col-value-mid text-body-sm py-1 text-neutral-500', item.hasBreakdown ? 'cursor-pointer' : '']"
        @click="item.hasBreakdown && toggleItemExpanded(item.id)"
      >
        <div>{{ formatCurrency(item.spouseAmount) }}</div>
        <div v-if="item.spouseLumpSum > 0" class="text-xs text-horizon-400">+ {{ formatCurrency(item.spouseLumpSum) }} lump sum</div>
      </div>
      <div
        v-if="isMarried"
        :class="['col-total text-body-sm py-1 text-neutral-500', item.hasBreakdown ? 'cursor-pointer' : '']"
        @click="item.hasBreakdown && toggleItemExpanded(item.id)"
      >
        <div>{{ formatCurrency(item.userAmount + item.spouseAmount) }}</div>
        <div v-if="item.userLumpSum + item.spouseLumpSum > 0" class="text-xs text-horizon-400">+ {{ formatCurrency(item.userLumpSum + item.spouseLumpSum) }} lump sum</div>
      </div>

      <!-- Breakdown rows (third level) -->
      <template v-if="expandedItems[item.id] && item.hasBreakdown">
        <template v-for="(expense, expenseKey) in item.breakdown" :key="`${item.id}-${expenseKey}`">
          <div class="col-label text-body-sm py-0.5 pl-20 text-horizon-400">
            {{ formatExpenseLabel(expenseKey) }}
          </div>
          <div class="col-value text-body-sm py-0.5 text-neutral-500">
            {{ formatCurrency(expense) }}
          </div>
          <div v-if="isMarried" class="col-value-mid text-body-sm py-0.5 text-neutral-500">
            {{ formatCurrency(getSpouseBreakdownAmount(item, expenseKey)) }}
          </div>
          <div v-if="isMarried" class="col-total text-body-sm py-0.5 text-neutral-500">
            {{ formatCurrency(expense + getSpouseBreakdownAmount(item, expenseKey)) }}
          </div>
        </template>
      </template>
    </template>
  </template>
</template>

<script>
import { ref, computed, reactive } from 'vue';
import { currencyMixin } from '@/mixins/currencyMixin';

export default {
  name: 'ExpenditureExpandableGridRow',

  mixins: [currencyMixin],

  props: {
    label: {
      type: String,
      required: true,
    },
    value: {
      type: Number,
      default: 0,
    },
    spouseValue: {
      type: Number,
      default: 0,
    },
    householdValue: {
      type: Number,
      default: null,
    },
    isMarried: {
      type: Boolean,
      default: false,
    },
    indent: {
      type: Boolean,
      default: false,
    },
    items: {
      type: Array,
      default: () => [],
    },
    spouseItems: {
      type: Array,
      default: () => [],
    },
  },

  setup(props) {
    const isExpanded = ref(false);
    const expandedItems = reactive({});

    const hasItems = computed(() => {
      return (props.items && props.items.length > 0) || (props.spouseItems && props.spouseItems.length > 0);
    });

    const toggleExpanded = () => {
      if (hasItems.value) {
        isExpanded.value = !isExpanded.value;
      }
    };

    const toggleItemExpanded = (itemId) => {
      expandedItems[itemId] = !expandedItems[itemId];
    };

    // Merge user and spouse items into a single list with amounts for each
    const mergedItems = computed(() => {
      const itemMap = new Map();

      // Add user items
      if (props.items) {
        props.items.forEach(item => {
          const hasBreakdown = item.breakdown && Object.keys(item.breakdown).length > 0;
          itemMap.set(item.id, {
            id: item.id,
            name: item.name,
            is_joint: item.is_joint,
            ownership_percentage: item.ownership_percentage,
            userAmount: item.monthly_amount || 0,
            spouseAmount: 0,
            userLumpSum: item.lump_sum_amount || 0,
            spouseLumpSum: 0,
            lumpSumDate: item.lump_sum_date || null,
            breakdown: item.breakdown || null,
            spouseBreakdown: null,
            hasBreakdown,
          });
        });
      }

      // Add/merge spouse items
      if (props.spouseItems) {
        props.spouseItems.forEach(item => {
          if (itemMap.has(item.id)) {
            // Joint item - update spouse amount
            const existing = itemMap.get(item.id);
            existing.spouseAmount = item.monthly_amount || 0;
            existing.spouseLumpSum = item.lump_sum_amount || 0;
            existing.spouseBreakdown = item.breakdown || null;
          } else {
            // Spouse-only item
            const hasBreakdown = item.breakdown && Object.keys(item.breakdown).length > 0;
            itemMap.set(item.id, {
              id: item.id,
              name: item.name,
              is_joint: item.is_joint,
              ownership_percentage: item.ownership_percentage,
              userAmount: 0,
              spouseAmount: item.monthly_amount || 0,
              userLumpSum: 0,
              spouseLumpSum: item.lump_sum_amount || 0,
              lumpSumDate: item.lump_sum_date || null,
              breakdown: null,
              spouseBreakdown: item.breakdown || null,
              hasBreakdown,
            });
          }
        });
      }

      return Array.from(itemMap.values());
    });

    const formatExpenseLabel = (key) => {
      const labels = {
        mortgage: 'Mortgage',
        council_tax: 'Council Tax',
        utilities: 'Utilities',
        maintenance: 'Maintenance',
        ground_rent: 'Ground Rent',
        service_charge: 'Service Charge',
        insurance: 'Insurance',
        management_fees: 'Management Fees',
      };
      return labels[key] || key.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
    };

    const getSpouseBreakdownAmount = (item, expenseKey) => {
      if (item.spouseBreakdown && item.spouseBreakdown[expenseKey]) {
        return item.spouseBreakdown[expenseKey];
      }
      return 0;
    };

    return {
      isExpanded,
      expandedItems,
      hasItems,
      toggleExpanded,
      toggleItemExpanded,
      mergedItems,
      formatExpenseLabel,
      getSpouseBreakdownAmount,
    };
  },
};
</script>
