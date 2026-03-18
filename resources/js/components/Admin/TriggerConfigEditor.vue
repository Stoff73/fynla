<template>
  <div class="bg-eggshell-500 rounded-lg p-3 mt-2">
    <label class="block text-body-sm font-medium text-neutral-500 mb-2">Trigger Configuration</label>

    <!-- Condition selector -->
    <div class="flex gap-2 items-center mb-2 text-[13px]">
      <span class="text-violet-500 font-bold text-xs min-w-[24px] text-center">IF</span>
      <select
        :value="localConfig.condition"
        @change="updateField('condition', $event.target.value)"
        class="px-2.5 py-1.5 border border-light-gray rounded-md bg-white text-[13px] text-horizon-500 flex-1"
      >
        <option value="">Select condition...</option>
        <option
          v-for="opt in conditionOptions"
          :key="opt.value"
          :value="opt.value"
        >
          {{ opt.label }}
        </option>
      </select>
    </div>

    <!-- Combinator selector (AND/OR) between condition rows -->
    <div v-if="activeTriggerFields.length > 0" class="flex gap-2 items-center mb-2 text-[13px]">
      <select
        :value="localConfig.combinator || 'and'"
        @change="updateField('combinator', $event.target.value)"
        class="text-violet-500 font-bold text-xs bg-transparent border-none cursor-pointer min-w-[50px] text-center"
      >
        <option value="and">AND</option>
        <option value="or">OR</option>
      </select>
      <span class="text-neutral-500 text-xs">conditions below</span>
    </div>

    <!-- Dynamic trigger fields based on selected condition -->
    <template v-if="activeTriggerFields.length > 0">
      <div
        v-for="(field, index) in activeTriggerFields"
        :key="field"
        class="flex gap-2 items-center mb-2 text-[13px]"
      >
        <span class="text-violet-500 font-bold text-xs min-w-[24px] text-center">
          {{ index === 0 ? '' : (localConfig.combinator || 'and').toUpperCase() }}
        </span>
        <label class="text-neutral-500 text-xs min-w-[80px]">{{ formatFieldLabel(field) }}</label>
        <input
          type="text"
          :value="localConfig[field] || ''"
          @input="updateField(field, $event.target.value)"
          class="px-2.5 py-1.5 border border-light-gray rounded-md bg-white text-[13px] text-horizon-500 flex-1"
          :placeholder="field"
        />
      </div>
    </template>

    <!-- Raw JSON fallback for unknown conditions -->
    <div v-if="!localConfig.condition" class="mt-2">
      <textarea
        :value="JSON.stringify(localConfig, null, 2)"
        @input="handleRawInput($event.target.value)"
        class="px-2.5 py-1.5 border border-light-gray rounded-md bg-white text-[13px] text-horizon-500 w-full font-mono min-h-[60px] resize-y"
        placeholder='{"condition": "..."}'
      />
    </div>
  </div>
</template>

<script>
export default {
  name: 'TriggerConfigEditor',

  props: {
    modelValue: {
      type: Object,
      default: () => ({ condition: '' }),
    },
    moduleConfig: {
      type: Object,
      default: () => ({}),
    },
  },

  emits: ['update:modelValue'],

  computed: {
    localConfig() {
      return this.modelValue || { condition: '' };
    },

    conditionOptions() {
      return this.moduleConfig?.conditionOptions || [];
    },

    activeTriggerFields() {
      const condition = this.localConfig.condition;
      if (!condition || !this.moduleConfig?.triggerFields) return [];
      return this.moduleConfig.triggerFields[condition] || [];
    },
  },

  methods: {
    updateField(field, value) {
      const updated = { ...this.localConfig, [field]: value };
      this.$emit('update:modelValue', updated);
    },

    handleRawInput(raw) {
      try {
        const parsed = JSON.parse(raw);
        this.$emit('update:modelValue', parsed);
      } catch {
        // Invalid JSON, ignore
      }
    },

    formatFieldLabel(field) {
      return field
        .replace(/_/g, ' ')
        .replace(/\b\w/g, (c) => c.toUpperCase());
    },
  },
};
</script>
