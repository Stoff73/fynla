<template>
  <div
    class="dashboard-card bg-white rounded-lg border border-gray-200 p-6 transition-all duration-200 cursor-pointer hover:shadow-md hover:-translate-y-0.5 hover:border-primary-400"
    @click="$emit('click')"
    role="button"
    tabindex="0"
    @keypress.enter="$emit('click')"
  >
    <!-- Loading state -->
    <div v-if="loading" class="animate-pulse">
      <div class="h-5 bg-gray-200 rounded w-1/3 mb-4"></div>
      <div class="h-8 bg-gray-200 rounded w-2/3 mb-4"></div>
      <div class="h-4 bg-gray-200 rounded w-full mb-2"></div>
      <div class="h-4 bg-gray-200 rounded w-4/5 mb-2"></div>
      <div class="h-4 bg-gray-200 rounded w-3/5"></div>
    </div>

    <!-- Content -->
    <div v-else>
      <!-- Card header with title -->
      <div class="flex items-center justify-between mb-4">
        <h3 class="text-lg font-semibold text-gray-900">{{ title }}</h3>
        <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
        </svg>
      </div>

      <!-- Card content slot -->
      <slot></slot>
    </div>
  </div>
</template>

<script>
export default {
  name: 'DashboardCard',

  props: {
    title: {
      type: String,
      required: true,
    },
    loading: {
      type: Boolean,
      default: false,
    },
  },

  emits: ['click'],
};
</script>

<style scoped>
.dashboard-card {
  height: 100%;
  display: flex;
  flex-direction: column;
}

.dashboard-card > div {
  flex: 1;
}
</style>
