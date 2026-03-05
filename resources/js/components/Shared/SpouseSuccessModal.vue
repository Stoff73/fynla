<template>
  <div v-if="show" class="fixed z-50 inset-0 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
    <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
      <!-- Background overlay -->
      <div class="fixed inset-0 bg-savannah-1000 bg-opacity-75 transition-opacity" @click="$emit('close')"></div>

      <!-- Centre modal -->
      <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>

      <div class="inline-block align-bottom bg-white rounded-lg px-4 pt-5 pb-4 text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full sm:p-6">
        <div>
          <!-- Icon -->
          <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full" :class="isCreated ? 'bg-spring-100' : 'bg-violet-100'">
            <svg v-if="isCreated" class="h-6 w-6 text-spring-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
            </svg>
            <svg v-else class="h-6 w-6 text-violet-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1" />
            </svg>
          </div>

          <!-- Title -->
          <div class="mt-3 text-center sm:mt-5">
            <h3 class="text-lg leading-6 font-medium text-horizon-500" id="modal-title">
              {{ title }}
            </h3>
            <div class="mt-2">
              <p class="text-sm text-neutral-500">
                {{ message }}
              </p>
            </div>
          </div>

          <!-- Email Sent Confirmation (for created accounts) -->
          <div v-if="isCreated && spouseEmail" class="mt-4 bg-spring-50 border border-spring-200 rounded-lg p-4">
            <div class="flex">
              <div class="flex-shrink-0">
                <svg class="h-5 w-5 text-spring-400" viewBox="0 0 20 20" fill="currentColor">
                  <path d="M2.003 5.884L10 9.882l7.997-3.998A2 2 0 0016 4H4a2 2 0 00-1.997 1.884z" />
                  <path d="M18 8.118l-8 4-8-4V14a2 2 0 002 2h12a2 2 0 002-2V8.118z" />
                </svg>
              </div>
              <div class="ml-3 flex-1">
                <h3 class="text-sm font-medium text-spring-800">
                  Login details sent
                </h3>
                <div class="mt-2 text-sm text-spring-700">
                  <p>
                    An email has been sent to <span class="font-semibold">{{ spouseEmail }}</span> with their login credentials and instructions.
                  </p>
                  <p class="mt-2 text-xs text-spring-600">
                    Your spouse will be required to change their temporary password when they first log in.
                  </p>
                </div>
              </div>
            </div>
          </div>

          <!-- Linked Account Info -->
          <div v-if="!isCreated" class="mt-4 bg-violet-50 border border-violet-200 rounded-lg p-4">
            <div class="flex">
              <div class="flex-shrink-0">
                <svg class="h-5 w-5 text-violet-400" viewBox="0 0 20 20" fill="currentColor">
                  <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
                </svg>
              </div>
              <div class="ml-3 flex-1">
                <p class="text-sm text-violet-700">
                  Your accounts are now linked. You can both view and manage shared family information.
                  An email notification has been sent to your spouse.
                </p>
              </div>
            </div>
          </div>
        </div>

        <!-- Actions -->
        <div class="mt-5 sm:mt-6">
          <button
            type="button"
            class="inline-flex justify-center w-full rounded-md border border-transparent shadow-sm px-4 py-2 bg-raspberry-600 text-base font-medium text-white hover:bg-raspberry-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-violet-500 sm:text-sm"
            @click="$emit('close')"
          >
            {{ isCreated ? 'Got it!' : 'Close' }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
export default {
  name: 'SpouseSuccessModal',

  props: {
    show: {
      type: Boolean,
      required: true,
    },
    isCreated: {
      type: Boolean,
      default: false,
    },
    spouseEmail: {
      type: String,
      default: null,
    },
    temporaryPassword: {
      type: String,
      default: null,
    },
  },

  emits: ['close'],

  computed: {
    title() {
      return this.isCreated
        ? 'Spouse Account Created'
        : 'Spouse Account Linked';
    },
    message() {
      return this.isCreated
        ? 'A new account has been created for your spouse and login details have been sent to their email address.'
        : 'Your spouse\'s existing account has been successfully linked to yours.';
    },
  },
};
</script>
