<template>
  <div
    v-if="show"
    class="fixed inset-0 z-50 overflow-y-auto"
    aria-labelledby="modal-title"
    role="dialog"
    aria-modal="true"
  >
    <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
      <!-- Background overlay -->
      <div
        class="fixed inset-0 bg-horizon-500 bg-opacity-75 transition-opacity"
        aria-hidden="true"
        @click="onContinue"
      ></div>

      <!-- Centre modal -->
      <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>

      <div
        class="inline-block align-bottom bg-white rounded-lg px-4 pt-5 pb-4 text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full sm:p-6"
      >
        <div>
          <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-violet-100">
            <svg
              class="h-6 w-6 text-violet-600"
              fill="none"
              viewBox="0 0 24 24"
              stroke="currentColor"
            >
              <path
                stroke-linecap="round"
                stroke-linejoin="round"
                stroke-width="2"
                d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"
              />
            </svg>
          </div>
          <div class="mt-3 text-center sm:mt-5">
            <h3 id="modal-title" class="text-h3 font-display text-horizon-500">
              Skip to Dashboard?
            </h3>
            <div class="mt-2 space-y-3">
              <p class="text-body text-neutral-500">
                Your dashboard will be incomplete without the remaining information, but you can always complete it later.
              </p>
              <p class="text-body text-neutral-500">
                Look for the <span class="font-medium text-horizon-500">Areas to Complete</span> card on your dashboard, or return to onboarding at any time from your settings.
              </p>
            </div>
          </div>
        </div>
        <div class="mt-5 sm:mt-6 sm:grid sm:grid-cols-2 sm:gap-3 sm:grid-flow-row-dense">
          <button
            type="button"
            @click="onContinue"
            class="w-full inline-flex justify-center btn-primary sm:col-start-1"
          >
            Continue Onboarding
          </button>
          <button
            type="button"
            @click="onSkipToDashboard"
            class="mt-3 w-full inline-flex justify-center btn-secondary sm:mt-0 sm:col-start-2"
          >
            Go to Dashboard
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
export default {
  name: 'SkipToDashboardModal',

  props: {
    show: {
      type: Boolean,
      default: false,
    },
  },

  emits: ['continue', 'skip-to-dashboard'],

  methods: {
    onContinue() {
      this.$emit('continue');
    },

    onSkipToDashboard() {
      this.$emit('skip-to-dashboard');
    },
  },
};
</script>
