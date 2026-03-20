<template>
  <div class="decision-type-step">
    <h3 class="text-lg font-bold text-horizon-500 mb-1">How Should Your Attorneys Make Decisions?</h3>
    <p class="text-sm text-neutral-500 mb-6">
      Since you have appointed {{ primaryAttorneyCount }} primary attorneys, you must decide how they make decisions together.
    </p>

    <div class="space-y-3">
      <label
        v-for="option in options"
        :key="option.value"
        class="flex items-start p-4 border rounded-lg cursor-pointer transition-colors"
        :class="modelValue.attorney_decision_type === option.value ? 'border-violet-500 bg-violet-50' : 'border-light-gray hover:border-savannah-300'"
      >
        <input
          type="radio"
          :value="option.value"
          :checked="modelValue.attorney_decision_type === option.value"
          @change="update('attorney_decision_type', option.value)"
          class="mt-1 mr-3 text-violet-500 focus:ring-violet-500"
        />
        <div>
          <p class="text-sm font-medium text-horizon-500">{{ option.label }}</p>
          <p class="text-xs text-neutral-500 mt-1">{{ option.description }}</p>
        </div>
      </label>
    </div>

    <!-- Jointly for some details -->
    <div v-if="modelValue.attorney_decision_type === 'jointly_for_some'" class="mt-4">
      <label class="block text-sm font-medium text-horizon-500 mb-1">
        Specify which decisions      </label>
      <textarea
        :value="modelValue.jointly_for_some_details"
        @input="update('jointly_for_some_details', $event.target.value)"
        class="w-full border border-light-gray rounded-lg px-3 py-2 text-sm text-horizon-500 focus:outline-none focus:ring-2 focus:ring-violet-500 focus:border-violet-500"
        rows="4"
        placeholder="Describe which decisions require all attorneys to agree and which can be made individually..."
      ></textarea>
      <p v-if="errors.jointly_for_some_details" class="text-xs text-raspberry-500 mt-1">{{ errors.jointly_for_some_details[0] }}</p>
    </div>
  </div>
</template>

<script>
export default {
  name: 'DecisionTypeStep',

  props: {
    modelValue: { type: Object, required: true },
    primaryAttorneyCount: { type: Number, default: 0 },
    errors: { type: Object, default: () => ({}) },
  },

  emits: ['update:modelValue'],

  data() {
    return {
      options: [
        {
          value: 'jointly_and_severally',
          label: 'Jointly and severally',
          description: 'Your attorneys can make decisions together or individually. This is the most flexible option and is recommended by most solicitors.',
        },
        {
          value: 'jointly',
          label: 'Jointly',
          description: 'All attorneys must agree on every decision. If one attorney can no longer act, the Lasting Power of Attorney may become invalid (unless you have replacement attorneys).',
        },
        {
          value: 'jointly_for_some',
          label: 'Jointly for some decisions, severally for others',
          description: 'You specify which decisions require all attorneys to agree and which can be made individually.',
        },
      ],
    };
  },

  methods: {
    update(field, value) {
      this.$emit('update:modelValue', { ...this.modelValue, [field]: value });
    },
  },
};
</script>
