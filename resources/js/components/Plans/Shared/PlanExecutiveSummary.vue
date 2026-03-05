<template>
  <div class="bg-white rounded-lg shadow-sm border border-light-gray p-6 mb-6">
    <p class="text-horizon-500 leading-relaxed whitespace-pre-line">{{ summary.narrative }}</p>
  </div>
</template>

<script>
export default {
  name: 'PlanExecutiveSummary',
  props: {
    summary: {
      type: Object,
      required: true,
    },
  },
};
</script>
