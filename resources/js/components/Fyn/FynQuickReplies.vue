<template>
  <div class="fyn-quick-replies">
    <p
      v-if="promptText"
      class="text-sm text-horizon-500 mb-3 leading-snug"
    >
      {{ promptText }}
    </p>
    <div
      v-if="hasDescriptions"
      class="flex flex-col gap-2"
    >
      <button
        v-for="bubble in bubbles"
        :key="bubble.id"
        :disabled="disabled"
        class="w-full text-left px-4 py-3 rounded-xl
               bg-white border-2 border-raspberry-500 text-raspberry-500
               hover:bg-raspberry-500 hover:text-white
               active:bg-raspberry-600 active:border-raspberry-600
               disabled:opacity-50 disabled:cursor-not-allowed
               transition-colors"
        @click="handleSelect(bubble)"
      >
        <div class="text-sm font-semibold leading-tight">{{ bubble.label }}</div>
        <div
          v-if="bubble.description"
          class="text-xs mt-1 leading-snug opacity-80"
        >
          {{ bubble.description }}
        </div>
      </button>
    </div>
    <div
      v-else
      class="flex flex-wrap gap-2"
    >
      <button
        v-for="bubble in bubbles"
        :key="bubble.id"
        :disabled="disabled"
        class="inline-flex items-center justify-center min-w-[140px] h-10 px-5 rounded-full text-sm font-semibold
               bg-white border-2 border-raspberry-500 text-raspberry-500
               hover:bg-raspberry-500 hover:text-white
               active:bg-raspberry-600 active:border-raspberry-600
               disabled:opacity-50 disabled:cursor-not-allowed
               transition-colors whitespace-nowrap"
        @click="handleSelect(bubble)"
      >
        {{ bubble.label }}
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

  computed: {
    hasDescriptions() {
      return Array.isArray(this.bubbles) && this.bubbles.some(b => b && b.description);
    },
  },

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
