<template>
  <div class="min-h-screen bg-eggshell-500 flex flex-col justify-center px-6">
    <!-- Logo -->
    <div class="text-center mb-8">
      <img
        src="/images/logos/LogoHiResFynlaDark.png"
        alt="Fynla"
        class="h-20 mx-auto mb-3"
      />
      <p class="text-neutral-500 text-sm mt-1">Your financial planning companion</p>
    </div>

    <!-- Login Form -->
    <form @submit.prevent="handleLogin" class="space-y-4">
      <div>
        <label for="email" class="block text-sm font-semibold text-horizon-500 mb-1">
          Email address
        </label>
        <input
          id="email"
          v-model="email"
          type="email"
          autocomplete="email"
          required
          :disabled="loading"
          class="w-full px-4 py-3 rounded-xl border border-light-gray bg-white text-horizon-500
                 focus:outline-none focus:ring-2 focus:ring-violet-500 focus:border-transparent
                 disabled:opacity-50"
          placeholder="you@example.com"
        />
      </div>

      <div>
        <label for="password" class="block text-sm font-semibold text-horizon-500 mb-1">
          Password
        </label>
        <input
          id="password"
          v-model="password"
          type="password"
          autocomplete="current-password"
          required
          :disabled="loading"
          class="w-full px-4 py-3 rounded-xl border border-light-gray bg-white text-horizon-500
                 focus:outline-none focus:ring-2 focus:ring-violet-500 focus:border-transparent
                 disabled:opacity-50"
          placeholder="Enter your password"
        />
      </div>

      <!-- Error -->
      <p v-if="error" class="text-raspberry-500 text-sm">{{ error }}</p>

      <!-- Submit -->
      <button
        type="submit"
        :disabled="loading || !email || !password"
        class="w-full py-3 rounded-xl bg-raspberry-500 text-white font-bold text-base
               active:bg-raspberry-600 disabled:opacity-50 transition-colors"
      >
        <span v-if="loading" class="flex items-center justify-center gap-2">
          <span class="w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin"></span>
          Signing in...
        </span>
        <span v-else>Sign in</span>
      </button>
    </form>

    <!-- Biometric login -->
    <div v-if="hasBiometricCredentials" class="mt-6">
      <div class="relative flex items-center mb-4">
        <div class="flex-grow border-t border-light-gray"></div>
        <span class="px-3 text-neutral-500 text-xs">or</span>
        <div class="flex-grow border-t border-light-gray"></div>
      </div>
      <button
        :disabled="loading"
        class="w-full py-3 rounded-xl border-2 border-horizon-500 text-horizon-500 font-bold text-base
               active:bg-horizon-500 active:text-white disabled:opacity-50 transition-colors
               flex items-center justify-center gap-2"
        @click="handleBiometricLogin"
      >
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
        </svg>
        Sign in with {{ biometricName }}
      </button>
    </div>

    <!-- Footer -->
    <p class="text-center text-neutral-500 text-xs mt-8">
      Don't have an account?
      <a href="https://fynla.org/register" class="text-raspberry-500 font-semibold">Sign up on web</a>
    </p>
  </div>
</template>

<script>
import authService from '@/services/authService';
import { setToken, getItem } from '@/services/tokenStorage';
import { platform } from '@/utils/platform';
import logger from '@/utils/logger';

export default {
  name: 'MobileLoginScreen',

  data() {
    return {
      email: '',
      password: '',
      loading: false,
      error: null,
      hasBiometricCredentials: false,
      biometricName: 'Face ID',
    };
  },

  async mounted() {
    await this.checkBiometricCredentials();
  },

  methods: {
    async checkBiometricCredentials() {
      if (!platform.canUseBiometrics()) return;

      // Only check native biometric APIs if the user has previously enabled Face ID.
      // This avoids triggering the iOS system permission dialog on first app launch.
      const biometricFlag = await getItem('biometric_enabled');
      if (biometricFlag !== 'true') return;

      try {
        const { NativeBiometric } = await import('@capgo/capacitor-native-biometric');
        const { isAvailable, biometryType } = await NativeBiometric.isAvailable();
        if (!isAvailable) return;

        this.biometricName = biometryType === 2 ? 'Face ID' : 'Touch ID';

        // Check if credentials are stored
        const credentials = await NativeBiometric.getCredentials({ server: 'fynla.org' });
        this.hasBiometricCredentials = !!(credentials?.password);

        // Auto-trigger Face ID login — skip the login screen entirely
        if (this.hasBiometricCredentials) {
          await this.handleBiometricLogin();
        }
      } catch {
        // No stored credentials or biometric not available
        this.hasBiometricCredentials = false;
      }
    },

    async handleBiometricLogin() {
      this.loading = true;
      this.error = null;
      try {
        const { NativeBiometric } = await import('@capgo/capacitor-native-biometric');

        // Prompt for biometric verification
        await NativeBiometric.verifyIdentity({
          reason: 'Sign in to Fynla',
          title: 'Fynla',
        });

        // Retrieve stored token
        const credentials = await NativeBiometric.getCredentials({ server: 'fynla.org' });
        if (!credentials?.password) {
          this.error = 'No saved credentials found. Please sign in with your email and password.';
          this.hasBiometricCredentials = false;
          return;
        }

        // Restore token and validate with server
        await setToken(credentials.password);
        this.$store.commit('auth/setToken', credentials.password);

        try {
          await this.$store.dispatch('auth/fetchUser');
          this.$router.push('/m/home');
        } catch {
          // Token expired — clear and ask user to login manually
          this.$store.commit('auth/clearAuth');
          // Clear the stale biometric credentials
          await NativeBiometric.deleteCredentials({ server: 'fynla.org' }).catch(() => {});
          this.hasBiometricCredentials = false;
          this.error = 'Your session has expired. Please sign in again.';
        }
      } catch (e) {
        // User cancelled biometric or it failed
        if (e?.message?.includes('cancel') || e?.code === 'userCancel') {
          // User cancelled — no error message needed
        } else {
          this.error = 'Biometric authentication failed. Please sign in with your email and password.';
        }
      } finally {
        this.loading = false;
      }
    },

    async handleLogin() {
      this.loading = true;
      this.error = null;
      logger.info('[MobileLogin] Starting login');

      try {
        const result = await authService.login({
          email: this.email,
          password: this.password,
        });
        logger.info('[MobileLogin] Login result received');

        // Check if MFA or verification required (flags are on top-level result)
        if (result.requires_mfa || result.requires_verification) {
          logger.info('[MobileLogin] Verification required, navigating to /m/verify');
          this.$router.push({
            path: '/m/verify',
            query: {
              email: this.email,
              challengeToken: result.data?.challenge_token || '',
              mfaToken: result.data?.mfa_token || '',
              mfa: result.requires_mfa ? '1' : '0',
            },
          });
          return;
        }

        // Direct login (no verification needed — e.g. preview users)
        const token = result.data?.access_token;
        logger.info('[MobileLogin] Direct login, token present:', !!token);
        if (token) {
          await setToken(token);
          this.$store.commit('auth/setToken', token);
          logger.info('[MobileLogin] Fetching user...');
          await this.$store.dispatch('auth/fetchUser');
          logger.info('[MobileLogin] Navigating to /m/home');
          this.$router.push('/m/home');
        } else {
          logger.info('[MobileLogin] No token and no verification required — unexpected response');
        }
      } catch (error) {
        logger.error('[MobileLogin] Login failed', error.message);
        this.error = error.response?.data?.message || error.message || 'Login failed. Please try again.';
      } finally {
        this.loading = false;
      }
    },
  },
};
</script>
