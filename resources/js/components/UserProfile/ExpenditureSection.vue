<template>
  <!-- Collapsible section header -->
  <div class="col-label pt-4 pb-2 cursor-pointer select-none" @click="$emit('toggle')">
    <div class="flex items-center gap-2">
      <svg
        :class="['h-5 w-5 text-horizon-400 transition-transform', isExpanded ? 'rotate-90' : '']"
        fill="none"
        viewBox="0 0 24 24"
        stroke="currentColor"
      >
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
      </svg>
      <span class="text-body-base font-semibold text-horizon-500">{{ title }}</span>
      <slot name="badge" />
    </div>
  </div>
  <div class="col-value pt-4 pb-2 text-body-sm text-horizon-500 font-semibold">
    {{ formatCurrency(userTotal) }}
  </div>
  <div v-if="isMarried" class="col-value-mid pt-4 pb-2 text-body-sm text-horizon-500 font-semibold">
    {{ formatCurrency(spouseTotal) }}
  </div>
  <div v-if="isMarried" class="col-total pt-4 pb-2 text-body-sm text-horizon-500 font-semibold">
    {{ formatCurrency(householdTotal) }}
  </div>
  <!-- Expandable content slot -->
  <template v-if="isExpanded">
    <slot />
  </template>
</template>

<script>
import { currencyMixin } from '@/mixins/currencyMixin';

export default {
  name: 'ExpenditureSection',

  mixins: [currencyMixin],

  props: {
    title: {
      type: String,
      required: true,
    },
    isExpanded: {
      type: Boolean,
      default: false,
    },
    userTotal: {
      type: Number,
      default: 0,
    },
    spouseTotal: {
      type: Number,
      default: 0,
    },
    householdTotal: {
      type: Number,
      default: null,
    },
    isMarried: {
      type: Boolean,
      default: false,
    },
  },

  emits: ['toggle'],
};
</script>
