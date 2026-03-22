<template>
  <div class="card p-6">
    <h4 class="text-h5 font-semibold text-horizon-500 mb-4">{{ title }}</h4>
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
      <template v-for="field in fields" :key="field.key">
        <div>
          <label :for="fieldId(field.key)" class="label">{{ field.label }}</label>
          <div class="relative">
            <span class="absolute left-3 top-1/2 transform -translate-y-1/2 text-neutral-500">£</span>
            <input
              v-if="showUserInput"
              :id="fieldId(field.key)"
              :value="displayValue(modelValue[field.key])"
              @input="updateValue(field.key, $event)"
              type="number"
              min="0"
              :step="step"
              class="input-field pl-8"
              :placeholder="field.placeholder || '0'"
            />
            <input
              v-else
              :id="'spouse_' + field.key"
              :value="displayValue(spouseModelValue[field.key])"
              @input="updateSpouseValue(field.key, $event)"
              type="number"
              min="0"
              :step="step"
              class="input-field pl-8"
              :placeholder="field.placeholder || '0'"
            />
          </div>
          <p v-if="field.hint" class="mt-1 text-body-sm text-neutral-500">{{ field.hint }}</p>
        </div>
      </template>
    </div>
  </div>
</template>

<script>
export default {
  name: 'ExpenditureCategoryCard',

  props: {
    title: {
      type: String,
      required: true,
    },
    fields: {
      type: Array,
      required: true,
      // Each field: { key, label, placeholder?, hint? }
    },
    modelValue: {
      type: Object,
      default: () => ({}),
    },
    spouseModelValue: {
      type: Object,
      default: () => ({}),
    },
    isMarried: {
      type: Boolean,
      default: false,
    },
    useSeparateExpenditure: {
      type: Boolean,
      default: false,
    },
    activePersonTab: {
      type: String,
      default: 'user', // 'user' or 'spouse'
    },
    step: {
      type: Number,
      default: 25,
    },
  },

  emits: ['update:modelValue', 'update:spouseModelValue'],

  computed: {
    showUserInput() {
      // Show user input when:
      // - Not married, OR
      // - Not using separate expenditure, OR
      // - Using separate expenditure and viewing user tab
      return !this.isMarried || !this.useSeparateExpenditure || this.activePersonTab === 'user';
    },
  },

  methods: {
    fieldId(key) {
      return this.showUserInput ? key : `spouse_${key}`;
    },

    displayValue(val) {
      const num = parseFloat(val) || 0;
      // Show whole numbers without decimals (0 not 0.00, 150 not 150.00)
      return num % 1 === 0 ? Math.round(num) : num;
    },

    updateValue(key, event) {
      const value = parseFloat(event.target.value) || 0;
      this.$emit('update:modelValue', {
        ...this.modelValue,
        [key]: value,
      });
    },

    updateSpouseValue(key, event) {
      const value = parseFloat(event.target.value) || 0;
      this.$emit('update:spouseModelValue', {
        ...this.spouseModelValue,
        [key]: value,
      });
    },
  },
};
</script>
