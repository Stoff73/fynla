<template>
  <div class="fixed inset-0 z-50 flex items-end justify-center bg-black/40" @click.self="skipBiometric">
    <div class="w-full max-w-lg bg-white rounded-t-2xl px-6 pt-6 pb-8 animate-slide-up">
      <div class="text-center mb-6">
        <div class="w-16 h-16 mx-auto mb-3 bg-spring-100 rounded-full flex items-center justify-center">
          <svg class="w-8 h-8 text-spring-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
          </svg>
        </div>
        <h2 class="text-lg font-bold text-horizon-500">Quick, secure login</h2>
        <p class="text-neutral-500 text-sm mt-1 leading-relaxed">
          Enable {{ biometricName }} so you can sign in instantly next time — no password needed.
        </p>
      </div>

      <button
        :disabled="loading"
        class="w-full py-3 rounded-xl bg-raspberry-500 text-white font-bold text-base
               active:bg-raspberry-600 disabled:opacity-50 transition-colors mb-3"
        @click="enableBiometric"
      >
        <span v-if="loading" class="flex items-center justify-center gap-2">
          <span class="w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin"></span>
          Setting up...
        </span>
        <span v-else>Enable {{ biometricName }}</span>
      </button>

      <button
        class="w-full py-3 text-neutral-500 font-semibold text-sm"
        @click="skipBiometric"
      >
        Not now
      </button>
    </div>
  </div>
</template>

<script>
import { platform } from '@/utils/platform';

export default {
  name: 'BiometricPrompt',

  emits: ['close'],

  data() {
    return {
      loading: false,
      biometricType: 'face',
    };
  },

  computed: {
    biometricName() {
      return this.biometricType === 'face' ? 'Face ID' : 'Touch ID';
    },
  },

  async mounted() {
    if (platform.canUseBiometrics()) {
      try {
        const { NativeBiometric } = await import('@capgo/capacitor-native-biometric');
        const { isAvailable, biometryType } = await NativeBiometric.isAvailable();
        if (!isAvailable) {
          this.$emit('close');
          return;
        }
        this.biometricType = biometryType === 2 ? 'face' : 'finger';
      } catch {
        this.$emit('close');
      }
    } else {
      this.$emit('close');
    }
  },

  methods: {
    async enableBiometric() {
      this.loading = true;
      try {
        const { NativeBiometric } = await import('@capgo/capacitor-native-biometric');
        const token = this.$store.getters['auth/isAuthenticated']
          ? this.$store.state.auth.token
          : null;
        const email = this.$store.state.auth.user?.email || '';

        if (token) {
          await NativeBiometric.setCredentials({
            username: email,
            password: token,
            server: 'fynla.org',
          });
        }

        this.$emit('close');
      } catch {
        this.$emit('close');
      } finally {
        this.loading = false;
      }
    },

    skipBiometric() {
      this.$emit('close');
    },
  },
};
</script>

<style scoped>
.animate-slide-up {
  animation: slideUp 0.3s ease-out;
}
@keyframes slideUp {
  from { transform: translateY(100%); }
  to { transform: translateY(0); }
}
</style>
