<template>
  <div class="bg-white rounded-lg shadow-sm border border-light-gray p-6">
    <h2 class="text-h3 font-bold text-horizon-500 mb-2">Digital Assets</h2>
    <p class="text-sm text-neutral-500 mb-6">
      Your digital estate includes email accounts, social media profiles, online banking, cloud storage, cryptocurrency, and any other online accounts. This step is optional.
    </p>

    <div class="bg-raspberry-50 border border-raspberry-200 rounded-lg p-3 mb-6">
      <p class="text-xs text-raspberry-700">
        <strong>Important:</strong> Never include passwords, PIN codes, or seed phrases in your will. Your will becomes a public document during probate. Store credentials separately in a secure location (e.g. a password manager) and note how your executor can access them.
      </p>
    </div>

    <div class="space-y-4">
      <div>
        <label class="block text-sm font-medium text-horizon-500 mb-1">Digital Executor (optional)</label>
        <input
          v-model="digitalExecutor"
          type="text"
          class="w-full px-3 py-2 border border-horizon-300 rounded-md focus:ring-violet-500 focus:border-violet-500"
          placeholder="Name of person to manage your digital accounts (often your main executor)"
        />
      </div>

      <div>
        <label class="block text-sm font-medium text-horizon-500 mb-1">Instructions for Your Executor</label>
        <textarea
          v-model="instructions"
          rows="5"
          class="w-full px-3 py-2 border border-horizon-300 rounded-md focus:ring-violet-500 focus:border-violet-500"
          placeholder="e.g. My passwords are stored in 1Password — the master password is in the sealed envelope in my desk drawer. Please close my social media accounts. My cryptocurrency wallet details are with my solicitor."
        ></textarea>
        <p class="text-xs text-neutral-500 mt-1">Describe where credentials can be found, what accounts to close, and any specific wishes.</p>
      </div>
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
  name: 'WillBuilderDigitalStep',

  props: {
    formData: { type: Object, required: true },
  },

  emits: ['next', 'back'],

  data() {
    return {
      digitalExecutor: this.formData.digital_executor_name || '',
      instructions: this.formData.digital_assets_instructions || '',
    };
  },

  methods: {
    handleSkip() {
      this.$emit('next', { digital_executor_name: '', digital_assets_instructions: '' });
    },

    handleNext() {
      this.$emit('next', {
        digital_executor_name: this.digitalExecutor.trim(),
        digital_assets_instructions: this.instructions.trim(),
      });
    },
  },
};
</script>
