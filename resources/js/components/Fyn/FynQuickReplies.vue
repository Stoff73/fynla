<template>
  <div class="fyn-quick-replies">
    <!-- v-html is safe here: renderFynText escapes the text before converting
         **bold** markers, and prompt text is server-authored (never user HTML). -->
    <p
      v-if="promptText"
      class="text-sm text-horizon-500 mb-3 leading-snug"
      v-html="promptHtml"
    ></p>
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
      <!-- On a multi-select step (M4) a chip toggles and the submit bubble
           sends every pick. A picked chip is filled: colour and weight only,
           plus aria-pressed — no tick or icon (Rule 15). -->
      <button
        v-for="bubble in bubbles"
        :key="bubble.id"
        :disabled="disabled"
        :aria-pressed="multiSelect && isToggleable(bubble) ? String(isSelected(bubble)) : null"
        class="inline-flex items-center justify-center min-w-[140px] h-10 px-5 rounded-full text-sm
               border-2 border-raspberry-500
               hover:bg-raspberry-500 hover:text-white
               active:bg-raspberry-600 active:border-raspberry-600
               disabled:opacity-50 disabled:cursor-not-allowed
               transition-colors whitespace-nowrap"
        :class="isSelected(bubble) ? 'bg-raspberry-500 text-white font-bold' : 'bg-white text-raspberry-500 font-semibold'"
        @click="handleSelect(bubble)"
      >
        {{ bubble.label }}
      </button>
    </div>
  </div>
</template>

<script>
import { renderFynText } from '../../../mobile/utils/fynText.js';
import { isMultiSelectSubmit, isToggleable, multiSelectSubmission, toggleSelection } from '../../../mobile/utils/fynMultiSelect.js';

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
    // The director's `multi_select` flag (live event and stored metadata).
    multiSelect: {
      type: Boolean,
      default: false,
    },
  },

  emits: ['select'],

  data() {
    return { selected: [] };
  },

  computed: {
    hasDescriptions() {
      return Array.isArray(this.bubbles) && this.bubbles.some(b => b && b.description);
    },
    promptHtml() {
      return renderFynText(this.promptText);
    },
  },

  methods: {
    isToggleable,

    isSelected(bubble) {
      return this.multiSelect && this.selected.includes(bubble.id);
    },

    handleSelect(bubble) {
      if (this.disabled) {
        return;
      }
      if (this.multiSelect && isToggleable(bubble)) {
        this.selected = toggleSelection(this.selected, bubble.id);
        return;
      }
      if (this.multiSelect && isMultiSelectSubmit(bubble)) {
        this.$emit('select', multiSelectSubmission(this.bubbles, this.selected, bubble));
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
