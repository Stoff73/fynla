<template>
  <transition name="fade">
    <div class="fixed inset-0 z-40 flex items-end" @click.self="$emit('close')">
      <!-- Backdrop -->
      <div class="absolute inset-0 bg-black bg-opacity-40" @click="$emit('close')"></div>

      <!-- Bottom sheet -->
      <div class="relative w-full bg-white rounded-t-2xl z-50 pb-safe">
        <!-- Drag handle -->
        <div class="flex justify-center pt-3 pb-2">
          <div class="w-10 h-1 bg-neutral-300 rounded-full"></div>
        </div>

        <!-- Content -->
        <div class="px-6 pb-6 max-h-[60vh] overflow-y-auto">
          <h3 class="text-base font-bold text-horizon-500 mb-3">{{ info.title }}</h3>
          <p class="text-sm text-neutral-500 leading-relaxed mb-6">{{ info.detail }}</p>

          <!-- Ask Fyn button -->
          <button
            class="w-full py-2.5 bg-raspberry-500 text-white rounded-xl text-sm font-medium
                   flex items-center justify-center gap-2"
            @click="handleAskFyn"
          >
            <img src="/images/logos/favicon.png" alt="" class="w-4 h-4 rounded-full" />
            Ask Fyn about this
          </button>
        </div>
      </div>
    </div>
  </transition>
</template>

<script>
export default {
  name: 'LearnInfoPopup',

  props: {
    info: {
      type: Object,
      required: true,
    },
  },

  emits: ['close', 'ask-fyn'],

  methods: {
    handleAskFyn() {
      const prompt = `Can you explain more about ${this.info.title}?`;
      this.$store.dispatch('aiChat/prefillPrompt', prompt);
      this.$emit('close');
      this.$router.push('/m/fyn');
    },
  },
};
</script>

<style scoped>
.pb-safe {
  padding-bottom: env(safe-area-inset-bottom, 16px);
}

.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.25s ease;
}
.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}
</style>
