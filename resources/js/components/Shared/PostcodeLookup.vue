<template>
  <div class="postcode-lookup">
    <label
      v-if="label"
      :for="inputId"
      class="block text-body-sm font-medium text-neutral-500 mb-1"
    >
      {{ label }}
    </label>

    <div>
      <!-- Postcode Input -->
      <div class="relative">
        <input
          :id="inputId"
          v-model="postcodeInput"
          type="text"
          :placeholder="placeholder"
          :disabled="disabled"
          class="input-field uppercase"
          :class="{
            'cursor-not-allowed bg-savannah-100': disabled,
          }"
          @input="clearError"
        />
      </div>
    </div>

  </div>
</template>

<script>
export default {
  name: 'PostcodeLookup',

  props: {
    modelValue: {
      type: String,
      default: '',
    },
    label: {
      type: String,
      default: 'Postcode',
    },
    placeholder: {
      type: String,
      default: 'Enter postcode (e.g., SW1A 1AA)',
    },
    required: {
      type: Boolean,
      default: false,
    },
    disabled: {
      type: Boolean,
      default: false,
    },
  },

  emits: ['update:modelValue', 'address-selected', 'manual-entry'],

  data() {
    return {
      uniqueId: `postcode-lookup-${Math.random().toString(36).substr(2, 9)}`,
      postcodeInput: this.modelValue || '',
    };
  },

  computed: {
    inputId() {
      return this.uniqueId;
    },
  },

  watch: {
    modelValue(newValue) {
      if (newValue !== this.postcodeInput) {
        this.postcodeInput = newValue;
      }
    },

    postcodeInput(newValue) {
      this.$emit('update:modelValue', newValue);
    },
  },

  methods: {
    clearError() {},

    reset() {
      this.postcodeInput = '';
    },
  },
};
</script>

<style scoped>
/* Uppercase postcode input */
.uppercase {
  text-transform: uppercase;
}

.uppercase::placeholder {
  text-transform: none;
}
</style>
