<template>
  <transition name="modal">
    <div
      v-if="show"
      class="fixed inset-0 z-50 overflow-y-auto"
      :aria-labelledby="state + '-modal-title'"
      role="dialog"
      aria-modal="true"
    >
      <div
        class="fixed inset-0 bg-neutral-500 bg-opacity-75 transition-opacity"
        @click="handleClose"
      ></div>

      <div class="flex items-center justify-center min-h-screen px-4 py-6 text-center">
        <div
          class="relative inline-block bg-white rounded-2xl text-left overflow-hidden shadow-xl transform transition-all sm:max-w-md w-full"
        >
          <!-- Header -->
          <div
            class="px-6 py-4 flex items-center justify-between"
            :class="state === 'unsubscribed' ? 'bg-horizon-500' : 'bg-raspberry-500'"
          >
            <div class="flex items-center gap-3">
              <svg v-if="state === 'unsubscribed'" class="w-6 h-6 text-white" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
              </svg>
              <svg v-else class="w-6 h-6 text-white" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
              </svg>
              <h3 :id="state + '-modal-title'" class="text-lg font-bold text-white">
                {{ heading }}
              </h3>
            </div>
            <button
              type="button"
              @click="handleClose"
              class="text-white hover:text-white/80 transition-colors"
              aria-label="Close"
            >
              <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
              </svg>
            </button>
          </div>

          <!-- Body -->
          <div class="bg-white px-6 py-6">
            <p class="text-sm text-horizon-500 leading-relaxed">{{ body }}</p>
            <p v-if="subBody" class="text-sm text-neutral-500 leading-relaxed mt-3">{{ subBody }}</p>
          </div>

          <!-- Footer -->
          <div class="bg-eggshell-500 px-6 py-4 flex justify-end">
            <button
              type="button"
              @click="handleClose"
              class="px-4 py-2 text-sm font-semibold text-white bg-raspberry-500 border border-transparent rounded-md shadow-sm hover:bg-raspberry-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-violet-500"
            >
              Close
            </button>
          </div>
        </div>
      </div>
    </div>
  </transition>
</template>

<script>
export default {
  name: 'NewsletterStatusModal',

  props: {
    state: {
      type: String,
      required: true,
      validator: (value) => ['subscribed', 'already_subscribed', 'unsubscribed'].includes(value),
    },
    show: {
      type: Boolean,
      default: true,
    },
  },

  emits: ['close'],

  computed: {
    heading() {
      if (this.state === 'unsubscribed') return "You've unsubscribed";
      if (this.state === 'already_subscribed') return "You're already subscribed";
      return "You're subscribed";
    },
    body() {
      if (this.state === 'unsubscribed') {
        return "We've removed you from the Fynla news list. You won't receive any more announcements from us.";
      }
      if (this.state === 'already_subscribed') {
        return "Your subscription was already confirmed — we'll keep you in the loop.";
      }
      return "Thanks for confirming. You're now on the Fynla news list.";
    },
    subBody() {
      if (this.state === 'unsubscribed') {
        return 'Changed your mind? Just sign up again from the news page.';
      }
      return '';
    },
  },

  methods: {
    handleClose() {
      this.$emit('close');
    },
  },
};
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
