<template>
  <div class="fyn-quick-replies">
    <p
      v-if="promptText"
      class="text-sm text-horizon-500 mb-3 leading-snug"
    >
      {{ promptText }}
    </p>
    <div class="flex flex-wrap gap-2">
      <button
        v-for="bubble in bubbles"
        :key="bubble.id"
        :disabled="disabled"
        class="inline-flex items-center gap-1.5 px-4 py-2 rounded-full text-sm font-semibold
               bg-white border-2 border-raspberry-500 text-raspberry-500
               hover:bg-raspberry-500 hover:text-white
               active:bg-raspberry-600 active:border-raspberry-600
               disabled:opacity-50 disabled:cursor-not-allowed
               transition-colors"
        @click="handleSelect(bubble)"
      >
        <span v-if="bubble.icon" aria-hidden="true">{{ bubble.icon }}</span>
        <span>{{ bubble.label }}</span>
      </button>
    </div>
  </div>
</template>

<script>
export default {
  name: 'FynQuickReplies',

  props: {
    promptText: {
      type: String,
      default: '',
    },
    bubbles: {
      type: Array,
      required: true,
      validator: (value) => Array.isArray(value) && value.every(b => b && typeof b.label === 'string'),
    },
    disabled: {
      type: Boolean,
      default: false,
    },
  },

  emits: ['select'],

  methods: {
    handleSelect(bubble) {
      if (this.disabled) {
        return;
      }
      this.$emit('select', bubble);
    },
  },
};
</script>

<style scoped>
.fyn-quick-replies {
  padding: 8px 0;
}
</style>
