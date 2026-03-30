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
      class="fixed inset-0 bg-savannah-1000 bg-opacity-75 transition-opacity"
    ></div>

    <!-- Modal Dialog -->
    <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:p-0">
      <div
        class="relative inline-block align-bottom bg-white rounded-lg text-left shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-2xl sm:w-full max-h-[90vh] overflow-y-auto"
      >
        <form @submit.prevent="submitForm">
          <!-- Header -->
          <div class="bg-white px-6 pt-6">
            <div class="flex items-center justify-between">
              <h3 class="text-lg font-semibold text-horizon-500">
                {{ isEditMode ? 'Edit User' : 'Create New User' }}
              </h3>
              <button
                type="button"
                class="text-horizon-400 hover:text-neutral-500 focus:outline-none"
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
              class="rounded-md bg-raspberry-50 border border-raspberry-200 p-4"
            >
              <div class="flex">
                <div class="flex-shrink-0">
                  <svg class="h-5 w-5 text-raspberry-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                  </svg>
                </div>
                <div class="ml-3">
                  <p class="text-sm text-raspberry-800">{{ error }}</p>
                </div>
              </div>
            </div>

            <!-- First Name Field -->
            <div>
              <label for="first_name" class="block text-sm font-medium text-neutral-500">
                First Name
              </label>
              <input
                id="first_name"
                v-model="formData.first_name"
                type="text"
                required
                class="mt-1 block w-full rounded-md border-horizon-300 shadow-sm focus:border-violet-500 focus:ring-violet-500 sm:text-sm"
                :class="{ 'border-raspberry-300': errors.first_name }"
                placeholder="John"
              />
              <p v-if="errors.first_name" class="mt-1 text-sm text-raspberry-600">{{ errors.first_name }}</p>
            </div>

            <!-- Surname Field -->
            <div>
              <label for="surname" class="block text-sm font-medium text-neutral-500">
                Surname
              </label>
              <input
                id="surname"
                v-model="formData.surname"
                type="text"
                required
                class="mt-1 block w-full rounded-md border-horizon-300 shadow-sm focus:border-violet-500 focus:ring-violet-500 sm:text-sm"
                :class="{ 'border-raspberry-300': errors.surname }"
                placeholder="Doe"
              />
              <p v-if="errors.surname" class="mt-1 text-sm text-raspberry-600">{{ errors.surname }}</p>
            </div>

            <!-- Email Field -->
            <div>
              <label for="email" class="block text-sm font-medium text-neutral-500">
                Email
              </label>
              <input
                id="email"
                v-model="formData.email"
                type="email"
                required
                class="mt-1 block w-full rounded-md border-horizon-300 shadow-sm focus:border-violet-500 focus:ring-violet-500 sm:text-sm"
                :class="{ 'border-raspberry-300': errors.email }"
                placeholder="john@example.com"
              />
              <p v-if="errors.email" class="mt-1 text-sm text-raspberry-600">{{ errors.email }}</p>
            </div>

            <!-- Password Field (Create Mode Only) -->
            <div v-if="!isEditMode">
              <label for="password" class="block text-sm font-medium text-neutral-500">
                Password
              </label>
              <input
                id="password"
                v-model="formData.password"
                type="password"
                required
                class="mt-1 block w-full rounded-md border-horizon-300 shadow-sm focus:border-violet-500 focus:ring-violet-500 sm:text-sm"
                :class="{ 'border-raspberry-300': errors.password }"
                placeholder="Minimum 8 characters"
              />
              <p v-if="errors.password" class="mt-1 text-sm text-raspberry-600">{{ errors.password }}</p>
              <p class="mt-1 text-xs text-neutral-500">Must be at least 8 characters long</p>
            </div>

            <!-- Password Confirmation (Create Mode Only) -->
            <div v-if="!isEditMode">
              <label for="password_confirmation" class="block text-sm font-medium text-neutral-500">
                Confirm Password
              </label>
              <input
                id="password_confirmation"
                v-model="formData.password_confirmation"
                type="password"
                required
                class="mt-1 block w-full rounded-md border-horizon-300 shadow-sm focus:border-violet-500 focus:ring-violet-500 sm:text-sm"
                :class="{ 'border-raspberry-300': errors.password_confirmation }"
                placeholder="Re-enter password"
              />
              <p v-if="errors.password_confirmation" class="mt-1 text-sm text-raspberry-600">{{ errors.password_confirmation }}</p>
            </div>

            <!-- Role Dropdown -->
            <div>
              <label for="role_id" class="block text-sm font-medium text-neutral-500">
                Role
              </label>
              <select
                id="role_id"
                v-model="formData.role_id"
                class="mt-1 block w-full rounded-md border-horizon-300 shadow-sm focus:border-violet-500 focus:ring-violet-500 sm:text-sm"
              >
                <option v-for="role in availableRoles" :key="role.id" :value="role.id">
                  {{ role.display_name || role.name }}
                </option>
              </select>
              <p class="mt-1 text-xs text-neutral-500">
                Determines access level and permissions for this user
              </p>
            </div>

            <!-- Edit Mode: Password Reset Option -->
            <div v-if="isEditMode" class="border-t border-light-gray pt-4">
              <div class="flex items-start">
                <div class="flex items-center h-5">
                  <input
                    id="reset_password"
                    v-model="formData.reset_password"
                    type="checkbox"
                    class="h-4 w-4 rounded border-horizon-300 text-raspberry-600 focus:ring-violet-500"
                  />
                </div>
                <div class="ml-3">
                  <label for="reset_password" class="font-medium text-neutral-500">
                    Reset Password
                  </label>
                  <p class="text-sm text-neutral-500">
                    Generate a new random password and require user to change it on next login
                  </p>
                </div>
              </div>
            </div>
          </div>

          <!-- Footer -->
          <div class="bg-savannah-100 px-6 py-4 flex justify-end space-x-3">
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
import logger from '@/utils/logger';

export default {
  name: 'UserFormModal',
  emits: ['save', 'close'],

  props: {
    show: {
      type: Boolean,
      required: true,
    },
    user: {
      type: Object,
      default: null,
    },
    availableRoles: {
      type: Array,
      default: () => [],
    },
  },

  data() {
    return {
      formData: {
        first_name: '',
        surname: '',
        email: '',
        password: '',
        password_confirmation: '',
        role_id: null,
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

    defaultRoleId() {
      const userRole = this.availableRoles.find(r => r.name === 'user');
      return userRole ? userRole.id : null;
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
        first_name: '',
        surname: '',
        email: '',
        password: '',
        password_confirmation: '',
        role_id: this.defaultRoleId,
        reset_password: false,
      };
      this.errors = {};
      this.error = null;
      this.submitting = false;
    },

    loadUserData() {
      if (this.user) {
        this.formData.first_name = this.user.first_name || '';
        this.formData.surname = this.user.surname || '';
        this.formData.email = this.user.email || '';
        this.formData.role_id = this.user.role_id || this.user.role?.id || this.defaultRoleId;
      }
    },

    validateForm() {
      this.errors = {};
      this.error = null;

      // First Name validation
      if (!this.formData.first_name || this.formData.first_name.trim() === '') {
        this.errors.first_name = 'First name is required';
      }

      // Surname validation
      if (!this.formData.surname || this.formData.surname.trim() === '') {
        this.errors.surname = 'Surname is required';
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
          first_name: this.formData.first_name.trim(),
          surname: this.formData.surname.trim(),
          email: this.formData.email.trim(),
          role_id: this.formData.role_id,
        };

        if (!this.isEditMode) {
          payload.password = this.formData.password;
          payload.password_confirmation = this.formData.password_confirmation;
        } else {
          payload.id = this.user.id;
          if (this.formData.reset_password) {
            payload.reset_password = true;
          }
        }

        this.$emit('save', payload);
      } catch (error) {
        logger.error('Form submission error:', error);
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
