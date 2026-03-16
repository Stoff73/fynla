<template>
  <div class="bg-white rounded-lg shadow-sm border border-light-gray p-6">
    <h2 class="text-h3 font-bold text-horizon-500 mb-2">Funeral Wishes</h2>
    <p class="text-sm text-neutral-500 mb-6">
      You can record your preferences for your funeral. While not legally binding, these wishes provide valuable guidance to your family and executor. This step is optional.
    </p>

    <div class="mb-6">
      <label class="block text-sm font-medium text-horizon-500 mb-3">Preference</label>
      <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
        <button
          v-for="option in options"
          :key="option.value"
          type="button"
          @click="preference = option.value"
          class="p-3 rounded-lg border-2 text-center transition-all"
          :class="preference === option.value
            ? 'border-raspberry-500 bg-raspberry-50'
            : 'border-light-gray hover:border-savannah-300'"
        >
          <span class="text-sm font-medium text-horizon-500">{{ option.label }}</span>
        </button>
      </div>
    </div>

    <div>
      <label class="block text-sm font-medium text-horizon-500 mb-1">Additional Wishes (optional)</label>
      <textarea
        v-model="notes"
        rows="4"
        class="w-full px-3 py-2 border border-horizon-300 rounded-md focus:ring-violet-500 focus:border-violet-500"
        placeholder="e.g. I would like a woodland burial at... / I would like my ashes scattered at..."
      ></textarea>
    </div>

    <!-- Navigation -->
    <div class="flex justify-between pt-6 border-t border-light-gray mt-6">
      <button @click="$emit('back')" class="px-4 py-2 text-neutral-500 hover:text-horizon-500 transition-colors">
        Back
      </button>
      <div class="flex gap-3">
        <button
          @click="handleSkip"
          class="px-4 py-2 text-neutral-500 hover:text-horizon-500 transition-colors text-sm"
        >
          Skip This Step
        </button>
        <button
          @click="handleNext"
          class="px-6 py-2.5 bg-raspberry-500 text-white rounded-lg font-medium hover:bg-raspberry-600 transition-colors"
        >
          Continue
        </button>
      </div>
    </div>
  </div>
</template>

<script>
export default {
  name: 'WillBuilderFuneralStep',

  props: {
    formData: { type: Object, required: true },
  },

  emits: ['next', 'back'],

  data() {
    return {
      preference: this.formData.funeral_preference || null,
      notes: this.formData.funeral_wishes_notes || '',
      options: [
        { value: 'burial', label: 'Burial' },
        { value: 'cremation', label: 'Cremation' },
        { value: 'no_preference', label: 'No Preference' },
      ],
    };
  },

  methods: {
    handleSkip() {
      this.$emit('next', { funeral_preference: null, funeral_wishes_notes: '' });
    },

    handleNext() {
      this.$emit('next', {
        funeral_preference: this.preference,
        funeral_wishes_notes: this.notes.trim(),
      });
    },
  },
};
</script>
