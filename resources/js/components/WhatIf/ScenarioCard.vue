<template>
  <div
    class="card p-5 cursor-pointer hover:shadow-md hover:border-raspberry-300 transition-all"
    @click="$emit('select', scenario.id)"
  >
    <div class="flex justify-between items-start mb-3">
      <h3 class="text-sm font-semibold text-horizon-500 truncate flex-1">{{ scenario.name }}</h3>
      <button
        @click.stop="$emit('delete', scenario.id)"
        class="text-neutral-400 hover:text-raspberry-500 ml-2 flex-shrink-0"
      >
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
        </svg>
      </button>
    </div>
    <div class="flex items-center gap-2 mb-2">
      <span class="text-xs px-2 py-0.5 rounded-full bg-savannah-100 text-neutral-500 capitalize">
        {{ scenario.scenario_type }}
      </span>
      <span class="text-xs text-neutral-500">
        {{ scenario.affected_modules?.length || 0 }} modules affected
      </span>
    </div>
    <p class="text-xs text-neutral-500">
      Created {{ formatDate(scenario.created_at) }}
    </p>
  </div>
</template>

<script>
export default {
  name: 'ScenarioCard',
  props: {
    scenario: { type: Object, required: true },
  },
  emits: ['select', 'delete'],
  methods: {
    formatDate(dateString) {
      if (!dateString) return '';
      return new Date(dateString).toLocaleDateString('en-GB', {
        day: 'numeric', month: 'short', year: 'numeric',
      });
    },
  },
};
</script>
