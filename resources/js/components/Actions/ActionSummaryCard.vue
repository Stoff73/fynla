<template>
  <div
    class="flex items-center justify-between p-3 rounded-lg cursor-pointer hover:bg-savannah-100 transition-colors border border-transparent hover:border-light-gray"
    @click="goToDetail"
  >
    <div class="flex items-center gap-3 min-w-0">
      <span
        class="inline-flex items-center px-2.5 py-0.5 rounded-full text-caption font-medium flex-shrink-0 capitalize"
        :class="priorityClass"
      >
        {{ action.priority }}
      </span>
      <div class="min-w-0">
        <p class="text-body-sm font-semibold text-horizon-500 truncate">{{ action.title }}</p>
        <span class="text-caption text-neutral-500">{{ action.category }}</span>
      </div>
    </div>
    <span
      v-if="action.estimated_impact"
      class="text-body-sm font-semibold text-spring-600 flex-shrink-0 ml-4"
    >
      {{ formatCurrency(action.estimated_impact) }}
    </span>
  </div>
</template>

<script>
import { currencyMixin } from '@/mixins/currencyMixin';

export default {
  name: 'ActionSummaryCard',
  mixins: [currencyMixin],
  props: {
    action: { type: Object, required: true },
    planType: { type: String, required: true },
  },
  computed: {
    priorityClass() {
      const classes = {
        critical: 'bg-raspberry-100 text-raspberry-700',
        high: 'bg-raspberry-50 text-raspberry-600',
        medium: 'bg-violet-100 text-violet-700',
        low: 'bg-eggshell-500 text-neutral-500',
      };
      return classes[this.action.priority] || classes.medium;
    },
  },
  methods: {
    goToDetail() {
      this.$router.push(`/actions/${this.planType}/${this.action.id}`);
    },
  },
};
</script>
