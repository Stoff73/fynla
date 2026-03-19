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
      @click="handleCancel"
    ></div>

    <!-- Modal Dialog -->
    <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:p-0">
      <div
        class="relative inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full"
      >
        <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
          <div class="sm:flex sm:items-start">
            <!-- Icon -->
            <div
              :class="[
                'mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full sm:mx-0 sm:h-10 sm:w-10',
                iconBgClass
              ]"
            >
              <svg
                :class="['h-6 w-6', iconColourClass]"
                fill="none"
                stroke="currentColor"
                viewBox="0 0 24 24"
              >
                <path
                  v-if="type === 'danger'"
                  stroke-linecap="round"
                  stroke-linejoin="round"
                  stroke-width="2"
                  d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"
                />
                <path
                  v-else-if="type === 'warning'"
                  stroke-linecap="round"
                  stroke-linejoin="round"
                  stroke-width="2"
                  d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"
                />
                <path
                  v-else-if="type === 'info'"
                  stroke-linecap="round"
                  stroke-linejoin="round"
                  stroke-width="2"
                  d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"
                />
                <path
                  v-else
                  stroke-linecap="round"
                  stroke-linejoin="round"
                  stroke-width="2"
                  d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"
                />
              </svg>
            </div>

            <!-- Content -->
            <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left flex-1">
              <h3
                id="modal-title"
                class="text-lg leading-6 font-medium text-horizon-500"
              >
                {{ title }}
              </h3>
              <div class="mt-2">
                <p class="text-sm text-neutral-500">
                  {{ message }}
                </p>
              </div>
            </div>
          </div>
        </div>

        <!-- Actions -->
        <div class="bg-savannah-100 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
          <button
            type="button"
            :class="[
              'w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 text-base font-medium text-white focus:outline-none focus:ring-2 focus:ring-offset-2 sm:ml-3 sm:w-auto sm:text-sm',
              confirmButtonClass
            ]"
            :disabled="loading"
            @click="handleConfirm"
          >
            <svg
              v-if="loading"
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
            {{ loading ? loadingText : confirmText }}
          </button>
          <button
            type="button"
            class="mt-3 w-full inline-flex justify-center rounded-md border border-horizon-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-neutral-500 hover:bg-savannah-100 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-violet-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm"
            :disabled="loading"
            @click="handleCancel"
          >
            {{ cancelText }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
export default {
  name: 'ConfirmDialog',
  emits: ['confirm', 'cancel'],

  props: {
    show: {
      type: Boolean,
      required: true,
    },
    title: {
      type: String,
      default: 'Confirm Action',
    },
    message: {
      type: String,
      default: 'Are you sure you want to proceed?',
    },
    type: {
      type: String,
      default: 'danger', // danger, warning, info, success
      validator: (value) => ['danger', 'warning', 'info', 'success'].includes(value),
    },
    confirmText: {
      type: String,
      default: 'Confirm',
    },
    cancelText: {
      type: String,
      default: 'Cancel',
    },
    loadingText: {
      type: String,
      default: 'Processing...',
    },
    loading: {
      type: Boolean,
      default: false,
    },
  },

  computed: {
    iconBgClass() {
      const classes = {
        danger: 'bg-raspberry-100',
        warning: 'bg-violet-100',
        info: 'bg-violet-100',
        success: 'bg-spring-100',
      };
      return classes[this.type] || classes.danger;
    },

    iconColourClass() {
      const classes = {
        danger: 'text-raspberry-600',
        warning: 'text-violet-600',
        info: 'text-violet-600',
        success: 'text-spring-600',
      };
      return classes[this.type] || classes.danger;
    },

    confirmButtonClass() {
      const classes = {
        danger: 'bg-raspberry-600 hover:bg-raspberry-700 focus:ring-raspberry-500',
        warning: 'bg-violet-600 hover:bg-violet-700 focus:ring-violet-500',
        info: 'bg-raspberry-600 hover:bg-raspberry-700 focus:ring-violet-500',
        success: 'bg-spring-600 hover:bg-spring-700 focus:ring-spring-500',
      };
      return classes[this.type] || classes.danger;
    },
  },

  methods: {
    handleConfirm() {
      if (!this.loading) {
        this.$emit('confirm');
      }
    },

    handleCancel() {
      if (!this.loading) {
        this.$emit('cancel');
      }
    },
  },
};
</script>
