<template>
  <div
    v-if="show"
    class="fixed inset-0 z-50 overflow-y-auto"
    aria-labelledby="modal-title"
    role="dialog"
    aria-modal="true"
  >
    <!-- Backdrop -->
    <div
      class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity"
      @click="handleClose"
    ></div>

    <!-- Modal Dialog -->
    <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:p-0">
      <div
        class="relative inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-2xl sm:w-full"
      >
        <form @submit.prevent="submitForm">
          <!-- Header -->
          <div class="bg-white px-6 pt-6">
            <div class="flex items-center justify-between">
              <h3 class="text-lg font-semibold text-gray-900">
                {{ isEditMode ? 'Edit User' : 'Create New User' }}
              </h3>
              <button
                type="button"
                class="text-gray-400 hover:text-gray-500 focus:outline-none"
                @click="handleClose"
              >
                <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
              </button>
            </div>
          </div>

          <!-- Body -->
          <div class="bg-white px-6 py-4 space-y-4">
            <!-- Error Message -->
            <div
              v-if="error"
              class="rounded-md bg-red-50 border border-red-200 p-4"
            >
              <div class="flex">
                <div class="flex-shrink-0">
                  <svg class="h-5 w-5 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                  </svg>
                </div>
                <div class="ml-3">
                  <p class="text-sm text-red-800">{{ error }}</p>
                </div>
              </div>
            </div>

            <!-- Name Field -->
            <div>
              <label for="name" class="block text-sm font-medium text-gray-700">
                Name
              </label>
              <input
                id="name"
                v-model="formData.name"
                type="text"
                required
                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm"
                :class="{ 'border-red-300': errors.name }"
                placeholder="John Doe"
              />
              <p v-if="errors.name" class="mt-1 text-sm text-red-600">{{ errors.name }}</p>
            </div>

            <!-- Email Field -->
            <div>
              <label for="email" class="block text-sm font-medium text-gray-700">
                Email
              </label>
              <input
                id="email"
                v-model="formData.email"
                type="email"
                required
                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm"
                :class="{ 'border-red-300': errors.email }"
                placeholder="john@example.com"
              />
              <p v-if="errors.email" class="mt-1 text-sm text-red-600">{{ errors.email }}</p>
            </div>

            <!-- Password Field (Create Mode Only) -->
            <div v-if="!isEditMode">
              <label for="password" class="block text-sm font-medium text-gray-700">
                Password
              </label>
              <input
                id="password"
                v-model="formData.password"
                type="password"
                required
                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm"
                :class="{ 'border-red-300': errors.password }"
                placeholder="Minimum 8 characters"
              />
              <p v-if="errors.password" class="mt-1 text-sm text-red-600">{{ errors.password }}</p>
              <p class="mt-1 text-xs text-gray-500">Must be at least 8 characters long</p>
            </div>

            <!-- Password Confirmation (Create Mode Only) -->
            <div v-if="!isEditMode">
              <label for="password_confirmation" class="block text-sm font-medium text-gray-700">
                Confirm Password
              </label>
              <input
                id="password_confirmation"
                v-model="formData.password_confirmation"
                type="password"
                required
                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm"
                :class="{ 'border-red-300': errors.password_confirmation }"
                placeholder="Re-enter password"
              />
              <p v-if="errors.password_confirmation" class="mt-1 text-sm text-red-600">{{ errors.password_confirmation }}</p>
            </div>

            <!-- Admin Role Checkbox -->
            <div class="flex items-start">
              <div class="flex items-center h-5">
                <input
                  id="is_admin"
                  v-model="formData.is_admin"
                  type="checkbox"
                  class="h-4 w-4 rounded border-gray-300 text-primary-600 focus:ring-primary-500"
                />
              </div>
              <div class="ml-3">
                <label for="is_admin" class="font-medium text-gray-700">
                  Administrator
                </label>
                <p class="text-sm text-gray-500">
                  Grant this user administrator privileges (access to admin panel, user management, database backups)
                </p>
              </div>
            </div>

            <!-- Edit Mode: Password Reset Option -->
            <div v-if="isEditMode" class="border-t border-gray-200 pt-4">
              <div class="flex items-start">
                <div class="flex items-center h-5">
                  <input
                    id="reset_password"
                    v-model="formData.reset_password"
                    type="checkbox"
                    class="h-4 w-4 rounded border-gray-300 text-primary-600 focus:ring-primary-500"
                  />
                </div>
                <div class="ml-3">
                  <label for="reset_password" class="font-medium text-gray-700">
                    Reset Password
                  </label>
                  <p class="text-sm text-gray-500">
                    Generate a new random password and require user to change it on next login
                  </p>
                </div>
              </div>
            </div>
          </div>

          <!-- Footer -->
          <div class="bg-gray-50 px-6 py-4 flex justify-end space-x-3">
            <button
              type="button"
              class="btn-secondary"
              :disabled="submitting"
              @click="handleClose"
            >
              Cancel
            </button>
            <button
              type="submit"
              class="btn-primary inline-flex items-center"
              :disabled="submitting"
            >
              <svg
                v-if="submitting"
                class="animate-spin -ml-1 mr-2 h-4 w-4 text-white"
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
                ></circle>
                <path
                  class="opacity-75"
                  fill="currentColor"
                  d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
                ></path>
              </svg>
              {{ submitting ? 'Saving...' : (isEditMode ? 'Update User' : 'Create User') }}
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>

<script>
export default {
  name: 'UserFormModal',

  props: {
    show: {
      type: Boolean,
      required: true,
    },
    user: {
      type: Object,
      default: null,
    },
  },

  data() {
    return {
      formData: {
        name: '',
        email: '',
        password: '',
        password_confirmation: '',
        is_admin: false,
        reset_password: false,
      },
      errors: {},
      error: null,
      submitting: false,
    };
  },

  computed: {
    isEditMode() {
      return this.user !== null && this.user.id;
    },
  },

  watch: {
    show(newVal) {
      if (newVal) {
        this.resetForm();
        if (this.isEditMode) {
          this.loadUserData();
        }
      }
    },
  },

  methods: {
    resetForm() {
      this.formData = {
        name: '',
        email: '',
        password: '',
        password_confirmation: '',
        is_admin: false,
        reset_password: false,
      };
      this.errors = {};
      this.error = null;
      this.submitting = false;
    },

    loadUserData() {
      if (this.user) {
        this.formData.name = this.user.name || '';
        this.formData.email = this.user.email || '';
        this.formData.is_admin = this.user.is_admin || false;
      }
    },

    validateForm() {
      this.errors = {};
      this.error = null;

      // Name validation
      if (!this.formData.name || this.formData.name.trim() === '') {
        this.errors.name = 'Name is required';
      }

      // Email validation
      if (!this.formData.email || this.formData.email.trim() === '') {
        this.errors.email = 'Email is required';
      } else if (!this.isValidEmail(this.formData.email)) {
        this.errors.email = 'Please enter a valid email address';
      }

      // Password validation (create mode only)
      if (!this.isEditMode) {
        if (!this.formData.password) {
          this.errors.password = 'Password is required';
        } else if (this.formData.password.length < 8) {
          this.errors.password = 'Password must be at least 8 characters';
        }

        if (!this.formData.password_confirmation) {
          this.errors.password_confirmation = 'Please confirm your password';
        } else if (this.formData.password !== this.formData.password_confirmation) {
          this.errors.password_confirmation = 'Passwords do not match';
        }
      }

      return Object.keys(this.errors).length === 0;
    },

    isValidEmail(email) {
      const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
      return emailRegex.test(email);
    },

    async submitForm() {
      if (!this.validateForm()) {
        return;
      }

      this.submitting = true;
      this.error = null;

      try {
        const payload = {
          name: this.formData.name.trim(),
          email: this.formData.email.trim(),
          is_admin: this.formData.is_admin,
        };

        if (!this.isEditMode) {
          payload.password = this.formData.password;
          payload.password_confirmation = this.formData.password_confirmation;
        } else if (this.formData.reset_password) {
          payload.reset_password = true;
        }

        this.$emit('save', payload);
      } catch (error) {
        console.error('Form submission error:', error);
        this.error = 'An unexpected error occurred. Please try again.';
        this.submitting = false;
      }
    },

    handleClose() {
      if (!this.submitting) {
        this.$emit('close');
      }
    },
  },
};
</script>
