<template>
  <!-- Free calculator: button that emits select -->
  <button
    v-if="type === 'free'"
    class="w-full flex items-center gap-4 bg-white rounded-lg border border-light-gray p-4 text-left transition-all duration-200 hover:shadow-sm hover:border-raspberry-400 group"
    :class="{ 'border-raspberry-500 shadow-sm': active }"
    @click="$emit('select', calculatorId)"
  >
    <div class="w-10 h-10 rounded-lg bg-savannah-50 flex items-center justify-center text-lg flex-shrink-0">
      {{ icon }}
    </div>
    <div class="flex-1 min-w-0">
      <p class="text-sm font-semibold text-horizon-500 group-hover:text-raspberry-500 transition-colors">{{ name }}</p>
      <p class="text-xs text-neutral-500 mt-0.5">{{ description }}</p>
    </div>
    <svg class="w-4 h-4 text-neutral-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
    </svg>
  </button>

  <!-- Gated free-account: link to /register -->
  <router-link
    v-else-if="type === 'gated-free'"
    to="/register"
    class="w-full flex items-center gap-4 bg-white rounded-lg border border-light-gray p-4 text-left transition-all duration-200 hover:shadow-sm hover:border-light-blue-500 group"
  >
    <div class="w-10 h-10 rounded-lg bg-savannah-50 flex items-center justify-center text-lg flex-shrink-0">
      {{ icon }}
    </div>
    <div class="flex-1 min-w-0">
      <p class="text-sm font-semibold text-horizon-500 group-hover:text-light-blue-500 transition-colors">{{ name }}</p>
      <p class="text-xs text-neutral-500 mt-0.5">{{ description }}</p>
    </div>
    <div class="flex items-center gap-2 flex-shrink-0">
      <span class="inline-flex items-center px-2 py-0.5 rounded-full bg-light-blue-100 text-light-blue-600 text-xs font-medium">Start free trial</span>
      <svg class="w-4 h-4 text-neutral-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
      </svg>
    </div>
  </router-link>

  <!-- Gated paid-plan: link to /register -->
  <router-link
    v-else-if="type === 'gated-paid'"
    to="/register"
    class="w-full flex items-center gap-4 bg-white/80 rounded-lg border border-light-gray p-4 text-left transition-all duration-200 hover:shadow-sm hover:border-violet-400 group"
  >
    <div class="w-10 h-10 rounded-lg bg-neutral-100 flex items-center justify-center text-lg flex-shrink-0 opacity-60">
      {{ icon }}
    </div>
    <div class="flex-1 min-w-0">
      <p class="text-sm font-semibold text-neutral-400 group-hover:text-violet-500 transition-colors">{{ name }}</p>
      <p class="text-xs text-neutral-400 mt-0.5">{{ description }}</p>
    </div>
    <div class="flex items-center gap-2 flex-shrink-0">
      <span class="inline-flex items-center px-2 py-0.5 rounded-full bg-violet-100 text-violet-600 text-xs font-medium">Start free trial</span>
      <svg class="w-4 h-4 text-neutral-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
      </svg>
    </div>
  </router-link>
</template>

<script>
export default {
  name: 'CalculatorCard',
  props: {
    name: { type: String, required: true },
    description: { type: String, required: true },
    icon: { type: String, required: true },
    type: { type: String, default: 'free', validator: v => ['free', 'gated-free', 'gated-paid'].includes(v) },
    calculatorId: { type: String, default: null },
    active: { type: Boolean, default: false },
  },
  emits: ['select'],
};
</script>
