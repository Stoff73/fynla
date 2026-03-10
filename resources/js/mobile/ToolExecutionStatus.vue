<template>
  <div class="flex justify-start">
    <img
      src="/images/logos/favicon.png"
      alt="Fyn"
      class="w-7 h-7 rounded-full flex-shrink-0 mt-1 mr-2"
    />
    <div class="bg-white rounded-2xl rounded-bl-sm shadow-sm px-4 py-3 flex items-center gap-2.5">
      <div class="w-4 h-4 border-2 border-horizon-200 border-t-raspberry-500 rounded-full animate-spin"></div>
      <span class="text-sm text-neutral-500">
        {{ displayMessage }}
      </span>
    </div>
  </div>
</template>

<script>
export default {
  name: 'ToolExecutionStatus',

  props: {
    message: {
      type: String,
      default: '',
    },
  },

  computed: {
    displayMessage() {
      return this.message || 'Fyn is analysing your portfolio...';
    },
  },
};
</script>
