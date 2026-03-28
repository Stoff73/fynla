<template>
  <div
    class="dashboard-card bg-white rounded-lg border border-light-gray p-4 transition-all duration-200 cursor-pointer hover:shadow-sm hover:border-raspberry-400"
    @click="$emit('click')"
    role="button"
    tabindex="0"
    @keypress.enter="$emit('click')"
  >
    <!-- Loading state -->
    <div v-if="loading" class="animate-pulse">
      <div class="h-4 bg-savannah-200 rounded w-1/3 mb-3"></div>
      <div class="h-6 bg-savannah-200 rounded w-2/3 mb-3"></div>
      <div class="h-3 bg-savannah-200 rounded w-full mb-2"></div>
      <div class="h-3 bg-savannah-200 rounded w-4/5 mb-2"></div>
      <div class="h-3 bg-savannah-200 rounded w-3/5"></div>
    </div>

    <!-- Content -->
    <div v-else>
      <!-- Card header with title -->
      <div class="mb-3">
        <h3 class="text-sm font-semibold text-horizon-500 uppercase tracking-wide">{{ title }}</h3>
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
