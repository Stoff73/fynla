<template>
  <div class="fixed inset-0 z-50 flex items-end" @click.self="dismiss">
    <div class="w-full bg-white rounded-t-2xl shadow-xl p-6 animate-fade-in-slide">
      <!-- Drag handle -->
      <div class="w-10 h-1 bg-neutral-300 rounded-full mx-auto mb-4"></div>

      <div class="flex items-start gap-3 mb-4">
        <img src="/images/logos/favicon.png" alt="Fyn" class="w-10 h-10 rounded-full flex-shrink-0" />
        <div>
          <p class="text-base font-bold text-horizon-500">Stay in the loop</p>
          <p class="text-sm text-neutral-500 mt-1 leading-relaxed">{{ message }}</p>
        </div>
      </div>

      <button
        class="w-full py-3 rounded-xl bg-raspberry-500 text-white font-bold text-base
               active:bg-raspberry-600 transition-colors mb-2"
        @click="enable"
      >
        Enable notifications
      </button>

      <button
        class="w-full py-3 text-neutral-500 font-semibold text-sm"
        @click="dismiss"
      >
        Not now
      </button>
    </div>
  </div>
</template>

<script>
export default {
  name: 'PushPermissionPrompt',
  props: {
    triggerType: { type: String, required: true },
    message: { type: String, default: 'Get notified about important updates to your financial plan.' },
  },
  emits: ['enable', 'dismiss'],
  methods: {
    enable() {
      this.$emit('enable');
    },
    dismiss() {
      this.$store.dispatch('mobileNotifications/dismissPrompt', this.triggerType);
      this.$emit('dismiss');
    },
  },
};
</script>
