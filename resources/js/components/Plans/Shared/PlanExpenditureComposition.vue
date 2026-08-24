<template>
  <div v-if="rows.length" class="space-y-3 pl-4 border-l border-light-gray">
    <div v-for="row in rows" :key="row.key" class="flex justify-between">
      <span class="text-caption text-neutral-500">{{ row.label }}:</span>
      <span class="text-caption text-horizon-500 text-right">{{ row.text || formatCurrency(row.amount) }}</span>
    </div>
    <p v-if="note" class="text-caption text-neutral-500">{{ note }}</p>
  </div>
</template>

<script>
import { currencyMixin } from '@/mixins/currencyMixin';
import { expenditureCompositionNote, expenditureCompositionRows } from '@/utils/expenditureComposition';

export default {
  name: 'PlanExpenditureComposition',
  mixins: [currencyMixin],
  props: {
    composition: { type: Object, default: null },
  },
  computed: {
    rows() {
      return expenditureCompositionRows(this.composition);
    },
    note() {
      return expenditureCompositionNote(this.composition);
    },
  },
};
</script>
