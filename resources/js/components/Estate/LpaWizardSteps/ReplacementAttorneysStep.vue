<template>
  <div class="replacement-attorneys-step">
    <AttorneysStep
      :model-value="modelValue"
      :errors="errors"
      attorney-type="replacement"
      title="Replacement Attorneys"
      description="Replacement attorneys step in if your primary attorneys can no longer act. This step is optional but recommended."
      @update:model-value="$emit('update:modelValue', $event)"
    />
  </div>
</template>

<script>
import AttorneysStep from './AttorneysStep.vue';

export default {
  name: 'ReplacementAttorneysStep',
  components: { AttorneysStep },
  props: {
    modelValue: { type: Array, default: () => [] },
    errors: { type: Object, default: () => ({}) },
  },
  emits: ['update:modelValue'],
};
</script>
