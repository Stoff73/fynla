<template>
  <div class="when-can-act-step">
    <h3 class="text-lg font-bold text-horizon-500 mb-1">When Can Your Attorneys Act?</h3>
    <p class="text-sm text-neutral-500 mb-6">
      For a Property & Financial Affairs Lasting Power of Attorney, you can choose when your attorneys are allowed to start making decisions.
    </p>

    <div class="space-y-3">
      <label
        class="flex items-start p-4 border rounded-lg cursor-pointer transition-colors"
        :class="modelValue.when_attorneys_can_act === 'only_when_lost_capacity' ? 'border-violet-500 bg-violet-50' : 'border-light-gray hover:border-savannah-300'"
      >
        <input
          type="radio"
          value="only_when_lost_capacity"
          :checked="modelValue.when_attorneys_can_act === 'only_when_lost_capacity'"
          @change="update('when_attorneys_can_act', 'only_when_lost_capacity')"
          class="mt-1 mr-3 text-violet-500 focus:ring-violet-500"
        />
        <div>
          <p class="text-sm font-medium text-horizon-500">Only when I have lost mental capacity</p>
          <p class="text-xs text-neutral-500 mt-1">
            Your attorneys can only act once a medical professional has assessed that you lack the mental capacity to make your own decisions. This provides maximum protection of your autonomy.
          </p>
        </div>
      </label>

      <label
        class="flex items-start p-4 border rounded-lg cursor-pointer transition-colors"
        :class="modelValue.when_attorneys_can_act === 'while_has_capacity' ? 'border-violet-500 bg-violet-50' : 'border-light-gray hover:border-savannah-300'"
      >
        <input
          type="radio"
          value="while_has_capacity"
          :checked="modelValue.when_attorneys_can_act === 'while_has_capacity'"
          @change="update('when_attorneys_can_act', 'while_has_capacity')"
          class="mt-1 mr-3 text-violet-500 focus:ring-violet-500"
        />
        <div>
          <p class="text-sm font-medium text-horizon-500">While I still have mental capacity, and also when I have lost it</p>
          <p class="text-xs text-neutral-500 mt-1">
            Your attorneys can act immediately once the Lasting Power of Attorney is registered, even while you are still able to make decisions yourself. This is useful for convenience, such as if you travel frequently or find it difficult to manage day-to-day finances.
          </p>
        </div>
      </label>
    </div>

    <p v-if="errors.when_attorneys_can_act" class="text-xs text-raspberry-500 mt-3">{{ errors.when_attorneys_can_act[0] }}</p>
  </div>
</template>

<script>
export default {
  name: 'WhenCanActStep',

  props: {
    modelValue: { type: Object, required: true },
    errors: { type: Object, default: () => ({}) },
  },

  emits: ['update:modelValue'],

  methods: {
    update(field, value) {
      this.$emit('update:modelValue', { ...this.modelValue, [field]: value });
    },
  },
};
</script>
