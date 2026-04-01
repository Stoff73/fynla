<template>
  <div
    class="dashboard-card rounded-lg p-6 transition-all duration-200"
    :class="[
      empty
        ? 'bg-light-blue-100 cursor-pointer order-last border border-light-gray'
        : (clickable
          ? 'bg-white cursor-pointer hover:shadow-md hover:-translate-y-0.5 hover-blue-gradient border-[3px] border-light-gray'
          : 'bg-white border border-light-gray'),
      !loading && !noGradient && !empty ? 'module-gradient' : ''
    ]"
    @click="clickable ? $emit('click') : null"
    :role="clickable ? 'button' : undefined"
    :tabindex="clickable ? 0 : undefined"
    @keypress.enter="clickable ? $emit('click') : null"
  >
    <!-- Loading state -->
    <div v-if="loading" class="animate-pulse">
      <div class="h-5 bg-savannah-200 rounded w-1/3 mb-4"></div>
      <div class="h-8 bg-savannah-200 rounded w-2/3 mb-4"></div>
      <div class="h-4 bg-savannah-200 rounded w-full mb-2"></div>
      <div class="h-4 bg-savannah-200 rounded w-4/5 mb-2"></div>
      <div class="h-4 bg-savannah-200 rounded w-3/5"></div>
    </div>

    <!-- Content -->
    <div v-else>
      <!-- Card header with title -->
      <div class="mb-4 flex items-start justify-between">
        <h3 class="text-lg font-semibold text-horizon-500">{{ title }}</h3>
        <span v-if="subtitle" class="text-xs text-neutral-400 mt-1 flex-shrink-0">{{ subtitle }}</span>
        <svg v-else-if="clickable" class="w-4 h-4 text-neutral-400 flex-shrink-0 mt-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
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
    clickable: {
      type: Boolean,
      default: true,
    },
    empty: {
      type: Boolean,
      default: false,
    },
    noGradient: {
      type: Boolean,
      default: false,
    },
    subtitle: {
      type: String,
      default: '',
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
