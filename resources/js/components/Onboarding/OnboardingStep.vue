<template>
  <div class="max-w-3xl mx-auto">
    <div class="mb-6">
      <h2 class="text-h2 font-display text-horizon-500">
        {{ title }}
      </h2>
    </div>

    <div class="p-0">
      <slot></slot>
    </div>

    <div v-if="error" class="mt-4 p-4 bg-raspberry-50 border border-raspberry-200 rounded-lg">
      <p class="text-body-sm text-raspberry-700">{{ error }}</p>
    </div>

    <!-- Navigation (hidden when hideNav is true — wizard provides its own styled nav) -->
    <div v-if="!hideNav" class="mt-6 flex items-center justify-between">
      <button
        v-if="canGoBack"
        @click="onBack"
        :disabled="loading"
        type="button"
        class="btn-secondary"
      >
        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
        </svg>
        Back
      </button>
      <div v-else></div>

      <div class="flex items-center gap-3">
        <button
          v-if="canSkip"
          @click="onSkip"
          :disabled="loading"
          type="button"
          class="text-body-sm text-neutral-500 hover:text-horizon-500 underline disabled:opacity-50 disabled:cursor-not-allowed"
        >
          Skip this step
        </button>

        <button
          @click="onNext"
          :disabled="loading || disabled"
          type="button"
          class="btn-primary flex items-center"
        >
          {{ loading ? 'Saving...' : nextButtonText }}
          <svg v-if="!loading" class="w-4 h-4 ml-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
          </svg>
        </button>
      </div>
    </div>
  </div>
</template>

<script>
export default {
  name: 'OnboardingStep',

  props: {
    title: {
      type: String,
      required: true,
    },
    description: {
      type: String,
      required: true,
    },
    canGoBack: {
      type: Boolean,
      default: true,
    },
    canSkip: {
      type: Boolean,
      default: false,
    },
    loading: {
      type: Boolean,
      default: false,
    },
    disabled: {
      type: Boolean,
      default: false,
    },
    error: {
      type: String,
      default: null,
    },
    hideNav: {
      type: Boolean,
      default: true,
    },
    nextButtonText: {
      type: String,
      default: 'Continue',
    },
  },

  emits: ['next', 'back', 'skip'],

  methods: {
    onNext() {
      this.$emit('next');
    },

    onBack() {
      this.$emit('back');
    },

    onSkip() {
      this.$emit('skip');
    },
  },
};
</script>

<style scoped>
/* Override global .label and .input-field styles for all onboarding step children */
:deep(.label) {
  @apply flex items-center gap-1.5 text-sm font-semibold text-horizon-500 mb-1;
}

:deep(.input-field) {
  @apply w-full py-2.5 pr-4 bg-white border border-light-blue-500/40 rounded-lg text-horizon-500 placeholder-neutral-400 transition-all duration-150 focus:outline-none focus:border-horizon-500 focus:ring-2 focus:ring-horizon-500/20;
  padding-left: 1rem;
}

:deep(.input-field.pl-8) {
  padding-left: 2rem;
}

/* Make all two-column grids single column in onboarding */
:deep(.grid.grid-cols-1.md\:grid-cols-2) {
  grid-template-columns: repeat(1, minmax(0, 1fr));
}
</style>
