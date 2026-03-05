<template>
  <div class="text-center py-8">
    <!-- Animated Spinner -->
    <div class="mx-auto w-16 h-16 mb-6">
      <svg
        class="animate-spin text-violet-600"
        fill="none"
        viewBox="0 0 24 24"
      >
        <circle
          class="opacity-25"
          cx="12"
          cy="12"
          r="10"
          stroke="currentColor"
          stroke-width="4"
        />
        <path
          class="opacity-75"
          fill="currentColor"
          d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
        />
      </svg>
    </div>

    <!-- Status Message -->
    <h3 class="text-lg font-medium text-horizon-500 mb-2">
      {{ currentMessage }}
    </h3>

    <!-- Progress Bar -->
    <div
      v-if="showProgress"
      class="max-w-xs mx-auto bg-savannah-200 rounded-full h-2 mb-4"
    >
      <div
        class="bg-raspberry-600 h-2 rounded-full transition-all duration-500"
        :style="{ width: progressWidth }"
      />
    </div>

    <!-- Step Indicator -->
    <p class="text-sm text-neutral-500">
      {{ stepDescription }}
    </p>
  </div>
</template>

<script>
export default {
  name: 'ProcessingState',

  props: {
    /**
     * Current step: 'uploading', 'analysing', 'extracting', 'mapping'
     */
    step: {
      type: String,
      default: 'uploading',
    },

    /**
     * Upload progress percentage (0-100)
     */
    uploadProgress: {
      type: Number,
      default: 0,
    },
  },

  computed: {
    currentMessage() {
      const messages = {
        uploading: 'Uploading document...',
        analysing: 'Analysing your document...',
        extracting: 'Extracting data fields...',
        mapping: 'Preparing form data...',
      };
      return messages[this.step] || 'Processing...';
    },

    stepDescription() {
      const steps = ['uploading', 'analysing', 'extracting', 'mapping'];
      const currentIndex = steps.indexOf(this.step) + 1;
      const stepLabels = {
        uploading: 'Uploading document',
        analysing: 'AI analysis in progress',
        extracting: 'Extracting financial data',
        mapping: 'Mapping to form fields',
      };
      return `Step ${currentIndex} of ${steps.length}: ${stepLabels[this.step] || 'Processing'}`;
    },

    showProgress() {
      return this.step === 'uploading';
    },

    progressWidth() {
      return `${this.uploadProgress}%`;
    },
  },
};
</script>
