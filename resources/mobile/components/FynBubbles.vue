<template>
  <!-- Onboarding bubble choices (quick_replies). The one /m renderer for the
       dashboard chat and the chrome chat. A tap emits `choose` with the bubble,
       whose label the director matches back. On a multi-select step (M4) the
       chips toggle locally and the submit bubble emits every pick as one
       message (fynMultiSelect.js). Selected state is colour and weight only,
       plus aria-pressed — no ticks or icons (Rule 15). -->
  <div class="md-fyn__bubbles">
    <button
      v-for="b in bubbles"
      :key="b.id"
      type="button"
      class="md-fyn__bubble"
      :class="{
        'md-fyn__bubble--toggle': multiSelect && isToggleable(b),
        'md-fyn__bubble--selected': multiSelect && selected.includes(b.id),
      }"
      :aria-pressed="multiSelect && isToggleable(b) ? String(selected.includes(b.id)) : null"
      :disabled="disabled"
      @click="tap(b)"
    >{{ b.label }}</button>
  </div>
</template>

<script>
import { isMultiSelectSubmit, isToggleable, multiSelectSubmission, toggleSelection } from '../utils/fynMultiSelect.js';

export default {
  name: 'FynBubbles',

  props: {
    bubbles: { type: Array, required: true },
    disabled: { type: Boolean, default: false },
    multiSelect: { type: Boolean, default: false },
  },

  emits: ['choose'],

  data() {
    return { selected: [] };
  },

  methods: {
    isToggleable,

    tap(bubble) {
      if (this.disabled || !bubble) return;
      if (this.multiSelect && isToggleable(bubble)) {
        this.selected = toggleSelection(this.selected, bubble.id);
        return;
      }
      if (this.multiSelect && isMultiSelectSubmit(bubble)) {
        this.$emit('choose', multiSelectSubmission(this.bubbles, this.selected, bubble));
        return;
      }
      this.$emit('choose', bubble);
    },
  },
};
</script>
