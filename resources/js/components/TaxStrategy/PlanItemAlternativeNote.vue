<template>
  <p
    v-if="item.conflict_note"
    class="text-caption font-semibold text-horizon-500 bg-eggshell-500 rounded px-2 py-1 mt-2"
  >
    {{ item.conflict_note }}
  </p>
</template>

<script>
/**
 * The composer's note on a tax-plan item that is an alternative to another
 * (the ISA wrap, the spouse gift and the 50/50 split shelter the same
 * interest). StrategyPlanComposer writes it, including whether the item counts
 * in the plan total, so every surface shows the same words (29 Sep 2026,
 * SaveTax matrix L3-5).
 */
export default {
  name: 'PlanItemAlternativeNote',
  props: {
    item: { type: Object, required: true },
  },
};
</script>
