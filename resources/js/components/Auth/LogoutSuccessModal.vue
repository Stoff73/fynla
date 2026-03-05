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
        class="fixed inset-0 bg-eggshell-5000 bg-opacity-75 transition-opacity"
      ></div>

      <!-- Modal container -->
      <div class="flex items-center justify-center min-h-screen px-4 py-6 text-center">
        <!-- Modal panel -->
        <div
          class="relative inline-block bg-white rounded-lg text-center overflow-hidden shadow-xl transform transition-all sm:max-w-sm w-full"
        >
          <div class="bg-white px-6 pt-8 pb-6">
            <!-- Success Icon -->
            <div class="mx-auto flex items-center justify-center h-16 w-16 rounded-full bg-spring-100 mb-6">
              <svg
                class="h-10 w-10 text-spring-600"
                xmlns="http://www.w3.org/2000/svg"
                fill="none"
                viewBox="0 0 24 24"
                stroke="currentColor"
              >
                <path
                  stroke-linecap="round"
                  stroke-linejoin="round"
                  stroke-width="2"
                  d="M5 13l4 4L19 7"
                />
              </svg>
            </div>

            <!-- Content -->
            <h3 class="text-xl font-semibold text-horizon-500 mb-2" id="modal-title">
              Logged Out Successfully
            </h3>
            <p class="text-sm text-neutral-500 mb-6">
              Your session has been securely terminated and all local data has been cleared.
            </p>

            <!-- Action button -->
            <button
              type="button"
              @click="handleClose"
              class="w-full btn-primary"
            >
              Return to Login
            </button>
          </div>
        </div>
      </div>
    </div>
  </transition>
</template>

<script>
import { defineComponent } from 'vue';

export default defineComponent({
  name: 'LogoutSuccessModal',

  props: {
    show: {
      type: Boolean,
      default: false,
    },
  },

  emits: ['close'],

  methods: {
    handleClose() {
      this.$emit('close');
    },
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
