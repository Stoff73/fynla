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
        :class="['h-4 w-4 text-gray-400 transition-transform', isExpanded ? 'rotate-90' : '']"
        fill="none"
        viewBox="0 0 24 24"
        stroke="currentColor"
      >
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
      </svg>
      <span v-else class="w-4"></span>
      <span class="text-gray-600">{{ label }}</span>
    </div>
  </div>
  <div :class="['col-value text-body-sm py-1 text-gray-900', hasItems ? 'cursor-pointer' : '']" @click="toggleExpanded">
    {{ formatCurrency(value) }}
  </div>
  <div v-if="isMarried" :class="['col-value-mid text-body-sm py-1 text-gray-900', hasItems ? 'cursor-pointer' : '']" @click="toggleExpanded">
    {{ formatCurrency(spouseValue) }}
  </div>
  <div v-if="isMarried" :class="['col-total text-body-sm py-1 font-medium text-gray-900', hasItems ? 'cursor-pointer' : '']" @click="toggleExpanded">
    {{ formatCurrency(householdValue) }}
  </div>

  <!-- Expanded items -->
  <template v-if="isExpanded && hasItems">
    <template v-for="item in mergedItems" :key="item.id">
      <div class="col-label text-body-sm py-1 pl-14 text-gray-500">
        <div class="flex items-center gap-2">
          <span>{{ item.name }}</span>
          <span v-if="item.is_joint" class="text-xs text-primary-600">({{ item.ownership_percentage || 50 }}%)</span>
        </div>
      </div>
      <div class="col-value text-body-sm py-1 text-gray-600">
        {{ formatCurrency(item.userAmount) }}
      </div>
      <div v-if="isMarried" class="col-value-mid text-body-sm py-1 text-gray-600">
        {{ formatCurrency(item.spouseAmount) }}
      </div>
      <div v-if="isMarried" class="col-total text-body-sm py-1 text-gray-600">
        {{ formatCurrency(item.userAmount + item.spouseAmount) }}
      </div>
    </template>
  </template>
</template>

<script>
import { ref, computed } from 'vue';
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

    const hasItems = computed(() => {
      return (props.items && props.items.length > 0) || (props.spouseItems && props.spouseItems.length > 0);
    });

    const toggleExpanded = () => {
      if (hasItems.value) {
        isExpanded.value = !isExpanded.value;
      }
    };

    // Merge user and spouse items into a single list with amounts for each
    const mergedItems = computed(() => {
      const itemMap = new Map();

      // Add user items
      if (props.items) {
        props.items.forEach(item => {
          itemMap.set(item.id, {
            id: item.id,
            name: item.name,
            is_joint: item.is_joint,
            ownership_percentage: item.ownership_percentage,
            userAmount: item.monthly_amount || 0,
            spouseAmount: 0,
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
          } else {
            // Spouse-only item
            itemMap.set(item.id, {
              id: item.id,
              name: item.name,
              is_joint: item.is_joint,
              ownership_percentage: item.ownership_percentage,
              userAmount: 0,
              spouseAmount: item.monthly_amount || 0,
            });
          }
        });
      }

      return Array.from(itemMap.values());
    });

    return {
      isExpanded,
      hasItems,
      toggleExpanded,
      mergedItems,
    };
  },
};
</script>
