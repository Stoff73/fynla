<template>
  <div
    class="bg-white rounded-lg shadow-md border border-light-gray p-6 transition-all duration-200"
    :class="clickable ? 'cursor-pointer hover:shadow-lg hover:-translate-y-0.5 hover:border-violet-500' : 'opacity-70'"
    @click="clickable ? $emit('click') : null"
  >
    <div class="flex items-center mb-4">
      <div class="p-3 rounded-lg" :class="iconBgClass">
        <slot name="icon">
          <svg class="w-8 h-8" :class="iconColorClass" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" :d="iconPath" />
          </svg>
        </slot>
      </div>
      <div class="ml-4 flex-1 min-w-0">
        <div class="flex items-center justify-between">
          <h3 class="text-lg font-semibold text-horizon-500">{{ title }}</h3>
          <span
            v-if="statusLabel"
            class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium"
            :class="statusClasses"
          >
            {{ statusLabel }}
          </span>
        </div>
      </div>
    </div>

    <p class="text-neutral-500 text-sm mb-4">{{ description }}</p>

    <!-- Progress bar -->
    <div v-if="completeness !== null && completeness !== undefined" class="mb-4">
      <div class="flex items-center justify-between text-xs text-neutral-500 mb-1">
        <span>Data readiness</span>
        <span>{{ completeness }}%</span>
      </div>
      <div class="w-full bg-horizon-200 rounded-full h-1.5">
        <div
          class="h-1.5 rounded-full transition-all duration-300"
          :class="completeness >= 80 ? 'bg-spring-500' : completeness >= 50 ? 'bg-violet-500' : 'bg-horizon-400'"
          :style="{ width: `${completeness}%` }"
        />
      </div>
    </div>

    <div v-if="clickable" class="flex items-center text-violet-600 font-medium text-sm">
      <span>View Plan</span>
      <svg class="w-4 h-4 ml-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
      </svg>
    </div>
  </div>
</template>

<script>
export default {
  name: 'PlanDashboardCard',

  props: {
    title: { type: String, required: true },
    description: { type: String, required: true },
    iconPath: { type: String, default: 'M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z' },
    color: { type: String, default: 'violet' },
    completeness: { type: Number, default: null },
    clickable: { type: Boolean, default: true },
    status: { type: String, default: null },
  },

  emits: ['click'],

  computed: {
    iconBgClass() {
      const map = {
        violet: 'bg-violet-100',
        spring: 'bg-spring-100',
        raspberry: 'bg-raspberry-100',
        horizon: 'bg-horizon-100',
      };
      return map[this.color] || map.violet;
    },

    iconColorClass() {
      const map = {
        violet: 'text-violet-600',
        spring: 'text-spring-600',
        raspberry: 'text-raspberry-600',
        horizon: 'text-horizon-500',
      };
      return map[this.color] || map.violet;
    },

    statusLabel() {
      if (this.status) return this.status;
      if (this.completeness === null || this.completeness === undefined) return null;
      if (this.completeness >= 80) return 'Ready';
      if (this.completeness >= 50) return 'Partial Data';
      return 'Needs Data';
    },

    statusClasses() {
      if (this.completeness >= 80) return 'bg-spring-100 text-spring-800';
      if (this.completeness >= 50) return 'bg-violet-100 text-violet-800';
      return 'bg-savannah-100 text-neutral-500';
    },
  },
};
</script>
