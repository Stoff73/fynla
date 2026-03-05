<template>
  <div class="mb-6">
    <PlanSectionHeader
      title="Recommended Actions"
      :subtitle="enabledCountLabel"
      color="spring"
    />

    <div v-if="actions && actions.length" class="space-y-3">
      <PlanActionCard
        v-for="action in sortedActions"
        :key="action.id"
        :action="action"
        @toggle="$emit('toggle', $event)"
        @update-funding-source="$emit('update-funding-source', $event)"
      />
    </div>

    <div v-else class="bg-eggshell-500 rounded-lg border border-light-gray p-6 text-center">
      <p class="text-neutral-500 text-sm">No recommendations available for this plan.</p>
    </div>
  </div>
</template>

<script>
import PlanSectionHeader from './PlanSectionHeader.vue';
import PlanActionCard from './PlanActionCard.vue';

export default {
  name: 'PlanActionsList',

  components: {
    PlanSectionHeader,
    PlanActionCard,
  },

  props: {
    actions: {
      type: Array,
      default: () => [],
    },
  },

  emits: ['toggle', 'update-funding-source'],

  computed: {
    enabledCount() {
      return this.actions.filter(a => a.enabled).length;
    },

    enabledCountLabel() {
      return `${this.enabledCount} of ${this.actions.length} actions enabled`;
    },

    sortedActions() {
      const priorityOrder = { critical: 0, high: 1, medium: 2, low: 3 };
      return [...this.actions].sort((a, b) => {
        return (priorityOrder[a.priority] ?? 2) - (priorityOrder[b.priority] ?? 2);
      });
    },
  },
};
</script>
