<template>
  <div class="inline-flex rounded-md shadow-sm" role="group">
    <button
      type="button"
      @click="$emit('update:modelValue', 'area')"
      :class="[
        'px-3 py-2 text-sm font-medium rounded-l-md border transition-colors',
        modelValue === 'area'
          ? 'bg-primary-600 text-white border-primary-600'
          : 'bg-white text-gray-700 border-gray-300 hover:bg-gray-50'
      ]"
      title="Line Chart"
    >
      <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
        <path stroke-linecap="round" stroke-linejoin="round" d="M7 12l3-3 3 3 4-4M8 21l4-4 4 4M3 4h18M4 4v16" />
      </svg>
    </button>
    <button
      type="button"
      @click="$emit('update:modelValue', 'bar')"
      :class="[
        'px-3 py-2 text-sm font-medium rounded-r-md border-t border-r border-b -ml-px transition-colors',
        modelValue === 'bar'
          ? 'bg-primary-600 text-white border-primary-600'
          : 'bg-white text-gray-700 border-gray-300 hover:bg-gray-50'
      ]"
      title="Bar Chart"
    >
      <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
        <path stroke-linecap="round" stroke-linejoin="round" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
      </svg>
    </button>
  </div>
</template>

<script>
export default {
  name: 'ChartTypeToggle',

  props: {
    modelValue: {
      type: String,
      default: 'area',
      validator: (v) => ['area', 'bar', 'line'].includes(v),
    },
  },

  emits: ['update:modelValue'],
};
</script>
