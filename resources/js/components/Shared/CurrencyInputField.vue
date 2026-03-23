<template>
  <div>
    <div class="relative">
      <span class="absolute left-3 top-1/2 transform -translate-y-1/2 text-neutral-500">£</span>
      <input
        :value="displayValue"
        @input="handleInput"
        type="number"
        min="0"
        :step="step"
        :placeholder="placeholder"
        :disabled="disabled"
        :id="id"
        class="input-field pl-8"
      />
    </div>
    <p v-if="hint" class="mt-1 text-body-sm text-neutral-500">{{ hint }}</p>
  </div>
</template>

<script>
export default {
  name: 'CurrencyInputField',

  props: {
    modelValue: {
      type: Number,
      default: 0,
    },
    hint: {
      type: String,
      default: '',
    },
    placeholder: {
      type: String,
      default: '0',
    },
    step: {
      type: Number,
      default: 25,
    },
    disabled: {
      type: Boolean,
      default: false,
    },
    id: {
      type: String,
      default: null,
    },
  },

  emits: ['update:modelValue'],

  computed: {
    displayValue() {
      const num = parseFloat(this.modelValue) || 0;
      return num % 1 === 0 ? Math.round(num) : num;
    },
  },

  methods: {
    handleInput(event) {
      const value = parseFloat(event.target.value) || 0;
      this.$emit('update:modelValue', value);
    },
  },
};
</script>
