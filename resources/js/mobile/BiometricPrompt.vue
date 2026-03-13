<template>
  <div class="bg-white rounded-xl border border-light-gray p-4 flex items-center gap-3">
    <div class="w-10 h-10 shrink-0 bg-spring-100 rounded-full flex items-center justify-center">
      <svg class="w-5 h-5 text-spring-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
              d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
      </svg>
    </div>
    <div class="flex-1 min-w-0">
      <p class="text-sm font-bold text-horizon-500">Enable {{ biometricName }}</p>
      <p class="text-xs text-neutral-500">Sign in instantly next time</p>
    </div>
    <button
      :disabled="loading"
      class="shrink-0 px-4 py-2 rounded-lg bg-raspberry-500 text-white text-sm font-bold
             active:bg-raspberry-600 disabled:opacity-50 transition-colors"
      @click="enableBiometric"
    >
      <span v-if="loading" class="w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin inline-block"></span>
      <span v-else>Set up</span>
    </button>
    <button
      class="shrink-0 text-neutral-400 p-1"
      @click="skipBiometric"
      aria-label="Dismiss"
    >
      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
      </svg>
    </button>
  </div>
</template>

<script>
import { setItem } from '@/services/tokenStorage';

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

  methods: {
    async enableBiometric() {
      this.loading = true;
      try {
        const { NativeBiometric } = await import('@capgo/capacitor-native-biometric');

        const { isAvailable, biometryType } = await NativeBiometric.isAvailable();
        if (!isAvailable) {
          this.$emit('close');
          return;
        }
        this.biometricType = biometryType === 2 ? 'face' : 'finger';

        // Trigger Face ID scan so the user sees it working
        await NativeBiometric.verifyIdentity({
          reason: 'Confirm Face ID for Fynla',
          title: 'Fynla',
        });

        // Face ID succeeded — store credentials in Keychain
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
          await setItem('biometric_enabled', 'true');
        }

        this.$emit('close');
      } catch {
        // User cancelled Face ID or it failed — keep banner visible
        this.loading = false;
      }
    },

    skipBiometric() {
      this.$emit('close');
    },
  },
};
</script>

