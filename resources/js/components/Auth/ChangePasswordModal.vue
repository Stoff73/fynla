<template>
  <div v-if="show" class="fixed inset-0 z-50 overflow-y-auto">
    <div class="flex min-h-screen items-center justify-center p-4">
      <!-- Backdrop -->
      <div class="fixed inset-0 bg-horizon-500 bg-opacity-75 transition-opacity"></div>

      <!-- Modal -->
      <div class="relative w-full max-w-md transform rounded-lg bg-white shadow-xl transition-all max-h-[90vh] overflow-y-auto">
        <div class="bg-white px-4 pb-4 pt-5 sm:p-6">
          <div class="mb-4">
            <h3 class="text-lg font-semibold text-horizon-500">
              {{ isRequired ? 'Change Your Password' : 'Update Password' }}
            </h3>
            <p v-if="isRequired" class="mt-2 text-sm text-violet-700 bg-violet-50 rounded p-3 border border-violet-200">
              For security reasons, you must change your password before continuing.
            </p>
            <p v-else class="mt-1 text-sm text-neutral-500">
              Enter your current password and choose a new one.
            </p>
          </div>

          <!-- Error Message -->
          <div v-if="error" class="mb-4 rounded-md bg-raspberry-50 p-3">
            <p class="text-sm font-medium text-raspberry-800 whitespace-pre-line">{{ error }}</p>
          </div>

          <!-- Success Message -->
          <div v-if="success" class="mb-4 rounded-md bg-success-50 p-3">
            <p class="text-sm font-medium text-success-800">{{ success }}</p>
          </div>

          <form @submit.prevent="handleSubmit" class="space-y-4">
            <!-- Current Password -->
            <div>
              <label for="current_password" class="block text-sm font-medium text-horizon-500 mb-1">
                Current Password
              </label>
              <input
                id="current_password"
                v-model="form.current_password"
                type="password"
                required
                class="input-field w-full"
                :disabled="submitting"
              />
            </div>

            <!-- New Password -->
            <div>
              <label for="new_password" class="block text-sm font-medium text-horizon-500 mb-1">
                New Password
              </label>
              <input
                id="new_password"
                v-model="form.new_password"
                type="password"
                required
                minlength="8"
                class="input-field w-full"
                :disabled="submitting"
              />
              <p class="mt-1 text-xs text-neutral-500">
                Must be at least 8 characters with one uppercase letter, one lowercase letter, one number, and one special character (@$!%*?&)
              </p>
            </div>

            <!-- Confirm New Password -->
            <div>
              <label for="new_password_confirmation" class="block text-sm font-medium text-horizon-500 mb-1">
                Confirm New Password
              </label>
              <input
                id="new_password_confirmation"
                v-model="form.new_password_confirmation"
                type="password"
                required
                minlength="8"
                class="input-field w-full"
                :disabled="submitting"
              />
            </div>

            <!-- Action Buttons -->
            <div class="mt-6 flex justify-end gap-3">
              <button
                v-if="!isRequired"
                type="button"
                @click="$emit('close')"
                class="btn-secondary"
                :disabled="submitting"
              >
                Cancel
              </button>
              <button
                type="submit"
                class="btn-primary"
                :disabled="submitting"
              >
                {{ submitting ? 'Changing Password...' : 'Change Password' }}
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import { ref, reactive, onBeforeUnmount } from 'vue';
import authService from '../../services/authService';

import logger from '@/utils/logger';
export default {
  name: 'ChangePasswordModal',

  props: {
    show: {
      type: Boolean,
      required: true,
    },
    isRequired: {
      type: Boolean,
      default: false,
    },
  },

  emits: ['close', 'success'],

  setup(props, { emit }) {
    const submitting = ref(false);
    const error = ref('');
    const success = ref('');
    let successTimeout = null;

    const form = reactive({
      current_password: '',
      new_password: '',
      new_password_confirmation: '',
    });

    const handleSubmit = async () => {
      error.value = '';
      success.value = '';
      submitting.value = true;

      try {
        // Validate passwords match
        if (form.new_password !== form.new_password_confirmation) {
          error.value = 'New passwords do not match';
          submitting.value = false;
          return;
        }

        const response = await authService.changePassword(form);

        if (response.success) {
          success.value = response.message;

          // Reset form
          form.current_password = '';
          form.new_password = '';
          form.new_password_confirmation = '';

          // Emit success event after a short delay to show the success message
          if (successTimeout) clearTimeout(successTimeout);
          successTimeout = setTimeout(() => {
            emit('success');
          }, 1500);
        } else {
          error.value = response.message || 'Failed to change password';
        }
      } catch (err) {
        logger.error('Password change error:', err);
        logger.error('Validation errors:', err.response?.data?.errors);

        // Display validation errors if available
        if (err.response?.data?.errors) {
          const errors = err.response.data.errors;
          const errorMessages = Object.values(errors).flat();
          error.value = errorMessages.join('\n');
        } else {
          error.value = err.response?.data?.message || 'An error occurred while changing password';
        }
      } finally {
        submitting.value = false;
      }
    };

    onBeforeUnmount(() => {
      if (successTimeout) clearTimeout(successTimeout);
    });

    return {
      submitting,
      error,
      success,
      form,
      handleSubmit,
    };
  },
};
</script>
