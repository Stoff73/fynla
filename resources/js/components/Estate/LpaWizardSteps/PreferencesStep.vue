<template>
  <div class="preferences-step">
    <h3 class="text-lg font-bold text-horizon-500 mb-1">Preferences & Instructions</h3>
    <p class="text-sm text-neutral-500 mb-6">
      Tell your attorneys how you would like them to make decisions. Both sections are optional.
    </p>

    <div class="space-y-5">
      <!-- Preferences -->
      <div>
        <label class="block text-sm font-medium text-horizon-500 mb-1">
          Preferences
          <span class="text-xs text-neutral-500 font-normal ml-1">(advisory — your attorneys should consider these)</span>
        </label>
        <textarea
          :value="modelValue.preferences"
          @input="update('preferences', $event.target.value)"
          class="w-full border border-light-gray rounded-lg px-3 py-2 text-sm text-horizon-500 focus:outline-none focus:ring-2 focus:ring-violet-500 focus:border-violet-500"
          rows="4"
          placeholder="e.g. I would prefer to stay in my own home for as long as possible. I prefer ethical investments."
        ></textarea>
        <p class="text-xs text-neutral-500 mt-1">Your attorneys should follow these where possible, but they are not legally binding.</p>
      </div>

      <!-- Instructions -->
      <div>
        <label class="block text-sm font-medium text-horizon-500 mb-1">
          Instructions
          <span class="text-xs text-neutral-500 font-normal ml-1">(binding — your attorneys must follow these)</span>
        </label>
        <textarea
          :value="modelValue.instructions"
          @input="update('instructions', $event.target.value)"
          class="w-full border border-light-gray rounded-lg px-3 py-2 text-sm text-horizon-500 focus:outline-none focus:ring-2 focus:ring-violet-500 focus:border-violet-500"
          rows="4"
          placeholder="e.g. My attorneys must not sell my main residence without agreement from my children."
        ></textarea>
        <p class="text-xs text-neutral-500 mt-1">Your attorneys are legally required to follow these. Be careful not to restrict them too much.</p>
      </div>

      <!-- Life-sustaining treatment (Health & Welfare only) -->
      <div v-if="lpaType === 'health_welfare'" class="border-t border-light-gray pt-5">
        <h4 class="text-sm font-bold text-horizon-500 mb-2">Life-Sustaining Treatment</h4>
        <p class="text-sm text-neutral-500 mb-4">
          This is an important decision about whether your attorneys can give or refuse consent to life-sustaining treatment on your behalf. Life-sustaining treatment includes things like ventilation, cardiopulmonary resuscitation (CPR), and artificial nutrition and hydration.
        </p>

        <div class="space-y-3">
          <label
            class="flex items-start p-4 border rounded-lg cursor-pointer transition-colors"
            :class="modelValue.life_sustaining_treatment === 'can_consent' ? 'border-violet-500 bg-violet-50' : 'border-light-gray hover:border-savannah-300'"
          >
            <input
              type="radio"
              value="can_consent"
              :checked="modelValue.life_sustaining_treatment === 'can_consent'"
              @change="update('life_sustaining_treatment', 'can_consent')"
              class="mt-1 mr-3 text-violet-500 focus:ring-violet-500"
            />
            <div>
              <p class="text-sm font-medium text-horizon-500">My attorneys can give or refuse consent to life-sustaining treatment</p>
              <p class="text-xs text-neutral-500 mt-1">Your attorneys will be able to make decisions about life-sustaining treatment on your behalf.</p>
            </div>
          </label>

          <label
            class="flex items-start p-4 border rounded-lg cursor-pointer transition-colors"
            :class="modelValue.life_sustaining_treatment === 'cannot_consent' ? 'border-violet-500 bg-violet-50' : 'border-light-gray hover:border-savannah-300'"
          >
            <input
              type="radio"
              value="cannot_consent"
              :checked="modelValue.life_sustaining_treatment === 'cannot_consent'"
              @change="update('life_sustaining_treatment', 'cannot_consent')"
              class="mt-1 mr-3 text-violet-500 focus:ring-violet-500"
            />
            <div>
              <p class="text-sm font-medium text-horizon-500">My attorneys cannot give or refuse consent to life-sustaining treatment</p>
              <p class="text-xs text-neutral-500 mt-1">Decisions about life-sustaining treatment will be made by your healthcare team in your best interests.</p>
            </div>
          </label>
        </div>

        <p v-if="errors.life_sustaining_treatment" class="text-xs text-raspberry-500 mt-2">{{ errors.life_sustaining_treatment[0] }}</p>
      </div>
    </div>
  </div>
</template>

<script>
export default {
  name: 'PreferencesStep',

  props: {
    modelValue: { type: Object, required: true },
    lpaType: { type: String, required: true },
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
