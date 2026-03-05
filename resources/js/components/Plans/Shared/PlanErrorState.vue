<template>
  <div class="bg-raspberry-50 border border-raspberry-200 rounded-lg p-6 text-center">
    <svg class="mx-auto h-10 w-10 text-raspberry-500 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z" />
    </svg>
    <p class="text-raspberry-800 font-medium mb-2">Unable to generate plan</p>
    <p class="text-raspberry-600 text-sm mb-4">{{ message }}</p>
    <button
      class="inline-flex items-center px-4 py-2 text-sm font-medium text-white bg-raspberry-600 rounded-lg hover:bg-raspberry-700 transition-colors"
      @click="$emit('retry')"
    >
      <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
      </svg>
      Try Again
    </button>
  </div>
</template>

<script>
export default {
  name: 'PlanErrorState',
  props: {
    message: {
      type: String,
      default: 'Something went wrong. Please try again.',
    },
  },
  emits: ['retry'],
};
</script>
