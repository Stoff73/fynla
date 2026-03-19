<template>
  <!-- Grid row for displaying expenditure values -->
  <div :class="['col-label text-body-sm py-1', indent ? 'pl-7' : '', bold ? 'font-semibold text-horizon-500' : 'text-neutral-500']">
    {{ label }}
    <slot name="badge" />
  </div>
  <div :class="['col-value text-body-sm py-1', bold ? 'font-semibold' : '', valueClass]">
    {{ formatCurrency(value) }}
    <span
      v-if="changeIndicator !== null && changeIndicator !== 0"
      :class="changeIndicator < 0 ? 'text-success-600' : 'text-raspberry-600'"
      class="text-xs ml-1"
    >
      ({{ changeIndicator < 0 ? '' : '+' }}{{ formatCurrency(changeIndicator) }})
    </span>
  </div>
  <div v-if="isMarried" :class="['col-value-mid text-body-sm py-1', bold ? 'font-semibold' : '', valueClass]">
    {{ formatCurrency(spouseValue) }}
    <span
      v-if="spouseChangeIndicator !== null && spouseChangeIndicator !== 0"
      :class="spouseChangeIndicator < 0 ? 'text-success-600' : 'text-raspberry-600'"
      class="text-xs ml-1"
    >
      ({{ spouseChangeIndicator < 0 ? '' : '+' }}{{ formatCurrency(spouseChangeIndicator) }})
    </span>
  </div>
  <div v-if="isMarried" :class="['col-total text-body-sm py-1 font-medium', valueClass]">
    {{ formatCurrency(householdValue) }}
  </div>
</template>

<script>
import currencyMixin from '@/mixins/currencyMixin';

export default {
  name: 'ExpenditureGridRow',

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
    bold: {
      type: Boolean,
      default: false,
    },
    changeIndicator: {
      type: Number,
      default: null,
    },
    spouseChangeIndicator: {
      type: Number,
      default: null,
    },
    valueClass: {
      type: String,
      default: 'text-horizon-500',
    },
  },

  computed: {
    computedHouseholdValue() {
      if (this.householdValue !== null) {
        return this.householdValue;
      }
      return this.value + (this.isMarried ? this.spouseValue : 0);
    },
  },
};
</script>
