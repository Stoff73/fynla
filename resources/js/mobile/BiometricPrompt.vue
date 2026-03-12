<template>
  <div class="min-h-screen bg-eggshell-500 flex flex-col justify-center px-6">
    <div class="text-center mb-8">
      <div class="w-20 h-20 mx-auto mb-4 bg-spring-100 rounded-full flex items-center justify-center">
        <svg class="w-10 h-10 text-spring-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
        </svg>
      </div>
      <h1 class="text-xl font-bold text-horizon-500">Quick, secure login</h1>
      <p class="text-neutral-500 text-sm mt-2 leading-relaxed">
        Enable {{ biometricName }} so you can sign in instantly next time — no password needed.
      </p>
    </div>

    <button
      :disabled="loading"
      class="w-full py-3 rounded-xl bg-raspberry-500 text-white font-bold text-base
             active:bg-raspberry-600 disabled:opacity-50 transition-colors mb-3"
      @click="enableBiometric"
    >
      Enable {{ biometricName }}
    </button>

    <button
      class="w-full py-3 text-neutral-500 font-semibold text-sm"
      @click="skipBiometric"
    >
      Not now
    </button>
  </div>
</template>

<script>
import { platform } from '@/utils/platform';

export default {
  name: 'BiometricPrompt',

  data() {
    return {
      loading: false,
      biometricType: 'face', // 'face' or 'finger'
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
          this.skipBiometric();
          return;
        }
        // biometryType: 1 = Touch ID, 2 = Face ID
        this.biometricType = biometryType === 2 ? 'face' : 'finger';
      } catch {
        this.skipBiometric();
      }
    } else {
      this.skipBiometric();
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

        this.navigateToDashboard();
      } catch {
        // If biometric setup fails, still proceed
        this.navigateToDashboard();
      } finally {
        this.loading = false;
      }
    },

    skipBiometric() {
      this.navigateToDashboard();
    },

    navigateToDashboard() {
      // Register device for push (will be enhanced in Task 17)
      this.$router.replace('/m/home');
    },
  },
};
</script>
