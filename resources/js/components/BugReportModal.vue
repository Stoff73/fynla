<template>
  <transition name="modal">
    <div
      v-if="show"
      class="fixed inset-0 z-50 overflow-y-auto"
      aria-labelledby="modal-title"
      role="dialog"
      aria-modal="true"
    >
      <!-- Background overlay -->
      <div
        class="fixed inset-0 bg-neutral-500 bg-opacity-75 transition-opacity"
        @click="handleClose"
      ></div>

      <!-- Modal container -->
      <div class="flex items-center justify-center min-h-screen px-4 py-6 text-center">
        <!-- Modal panel -->
        <div
          class="relative inline-block bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:max-w-lg w-full"
        >
          <!-- Header -->
          <div class="bg-raspberry-500 px-6 py-4">
            <div class="flex items-center justify-between">
              <div class="flex items-center">
                <svg class="h-6 w-6 text-white mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                </svg>
                <h3 class="text-lg font-semibold text-white" id="modal-title">
                  Report a Bug
                </h3>
              </div>
              <button
                type="button"
                @click="handleClose"
                class="text-white hover:text-blue-200 transition-colors"
              >
                <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
              </button>
            </div>
          </div>

          <!-- Form -->
          <form @submit.prevent="handleSubmit">
            <div class="bg-white px-6 py-5">
              <!-- Success state -->
              <div v-if="submitted" class="text-center py-4">
                <div class="mx-auto flex items-center justify-center h-16 w-16 rounded-full bg-green-100 mb-4">
                  <svg class="h-10 w-10 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                  </svg>
                </div>
                <h4 class="text-lg font-semibold text-horizon-500 mb-2">Thank you!</h4>
                <p class="text-sm text-neutral-500">Your bug report has been submitted successfully. We'll investigate and get back to you if needed.</p>
              </div>

              <!-- Form fields -->
              <div v-else>
                <!-- Info notice -->
                <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-5">
                  <div class="flex">
                    <svg class="h-5 w-5 text-blue-600 mr-3 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <p class="text-sm text-blue-700">
                      Your report will include technical data (console logs, page URL, browser info) to help us diagnose the issue.
                    </p>
                  </div>
                </div>

                <!-- Description -->
                <div class="mb-4">
                  <label for="description" class="block text-sm font-medium text-neutral-500 mb-1">
                    What went wrong?                  </label>
                  <textarea
                    id="description"
                    v-model="form.description"
                    rows="4"
                    required
                    maxlength="5000"
                    class="w-full rounded-md border border-horizon-300 shadow-sm focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-violet-500 text-sm"
                    placeholder="Describe what happened. Be as specific as possible..."
                  ></textarea>
                  <p class="mt-1 text-xs text-neutral-500">{{ form.description.length }}/5000 characters</p>
                </div>

                <!-- Expected behaviour -->
                <div class="mb-4">
                  <label for="expectedBehaviour" class="block text-sm font-medium text-neutral-500 mb-1">
                    What did you expect to happen? <span class="text-horizon-400">(optional)</span>
                  </label>
                  <textarea
                    id="expectedBehaviour"
                    v-model="form.expectedBehaviour"
                    rows="2"
                    maxlength="2000"
                    class="w-full rounded-md border border-horizon-300 shadow-sm focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-violet-500 text-sm"
                    placeholder="Describe what you expected to happen instead..."
                  ></textarea>
                </div>

                <!-- Error message -->
                <div v-if="error" class="bg-red-50 border border-red-200 rounded-lg p-4 mb-4">
                  <div class="flex">
                    <svg class="h-5 w-5 text-red-600 mr-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <p class="text-sm text-red-700">{{ error }}</p>
                  </div>
                </div>
              </div>
            </div>

            <!-- Footer -->
            <div class="bg-eggshell-500 px-6 py-4 flex justify-end space-x-3">
              <button
                v-if="!submitted"
                type="button"
                @click="handleClose"
                class="px-4 py-2 text-sm font-medium text-neutral-500 bg-white border border-horizon-300 rounded-md shadow-sm hover:bg-savannah-100 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-violet-500"
              >
                Cancel
              </button>
              <button
                v-if="!submitted"
                type="submit"
                :disabled="submitting || !form.description.trim()"
                class="px-4 py-2 text-sm font-medium text-white bg-raspberry-500 border border-transparent rounded-md shadow-sm hover:bg-raspberry-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-violet-500 disabled:opacity-50 disabled:cursor-not-allowed"
              >
                <span v-if="submitting" class="flex items-center">
                  <svg class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                  </svg>
                  Sending...
                </span>
                <span v-else>Send Report</span>
              </button>
              <button
                v-if="submitted"
                type="button"
                @click="handleClose"
                class="px-4 py-2 text-sm font-medium text-white bg-raspberry-500 border border-transparent rounded-md shadow-sm hover:bg-raspberry-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-violet-500"
              >
                Close
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </transition>
</template>

<script>
import { defineComponent, ref, reactive, watch } from 'vue';
import { submitBugReport } from '@/services/bugReportService';

import logger from '@/utils/logger';
export default defineComponent({
  name: 'BugReportModal',

  props: {
    show: {
      type: Boolean,
      default: false,
    },
  },

  emits: ['close'],

  setup(props, { emit }) {
    const form = reactive({
      description: '',
      expectedBehaviour: '',
    });

    const submitting = ref(false);
    const submitted = ref(false);
    const error = ref(null);

    // Reset form when modal is opened
    watch(() => props.show, (newVal) => {
      if (newVal) {
        form.description = '';
        form.expectedBehaviour = '';
        submitting.value = false;
        submitted.value = false;
        error.value = null;
      }
    });

    const handleSubmit = async () => {
      if (!form.description.trim() || submitting.value) return;

      submitting.value = true;
      error.value = null;

      try {
        await submitBugReport({
          description: form.description.trim(),
          expectedBehaviour: form.expectedBehaviour.trim() || null,
        });
        submitted.value = true;
      } catch (err) {
        logger.error('Failed to submit bug report:', err);
        if (err.response?.status === 429) {
          error.value = 'You have submitted too many bug reports. Please try again later.';
        } else {
          error.value = err.response?.data?.message || 'Failed to submit bug report. Please try again.';
        }
      } finally {
        submitting.value = false;
      }
    };

    const handleClose = () => {
      if (!submitting.value) {
        emit('close');
      }
    };

    return {
      form,
      submitting,
      submitted,
      error,
      handleSubmit,
      handleClose,
    };
  },
});
</script>

<style scoped>
.modal-enter-active,
.modal-leave-active {
  transition: opacity 0.3s ease;
}

.modal-enter-from,
.modal-leave-to {
  opacity: 0;
}
</style>
