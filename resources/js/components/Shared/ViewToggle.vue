<template>
  <div class="inline-flex rounded-md shadow-sm" role="group">
    <button
      v-for="(option, index) in options"
      :key="option"
      type="button"
      @click="selectOption(option)"
      :class="[
        'px-3 py-2 text-sm font-medium border transition-colors',
        index === 0 ? 'rounded-l-md' : '',
        index === options.length - 1 ? 'rounded-r-md' : '',
        index > 0 ? '-ml-px' : '',
        modelValue === option
          ? 'bg-primary-600 text-white border-primary-600 z-10'
          : 'bg-white text-gray-700 border-gray-300 hover:bg-gray-50'
      ]"
    >
      {{ option }}
    </button>
  </div>
</template>

<script>
export default {
  name: 'ViewToggle',

  props: {
    modelValue: {
      type: String,
      required: true,
    },
    options: {
      type: Array,
      required: true,
      validator: (v) => v.length >= 2,
    },
  },

  emits: ['update:modelValue', 'change'],

  methods: {
    selectOption(option) {
      this.$emit('update:modelValue', option);
      this.$emit('change', option);
    },
  },
};
</script>
