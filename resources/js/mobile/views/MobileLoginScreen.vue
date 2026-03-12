<template>
  <div class="min-h-screen bg-eggshell-500 flex flex-col justify-center px-6">
    <!-- Logo -->
    <div class="text-center mb-8">
      <img
        src="/images/logos/favicon.png"
        alt="Fynla"
        class="w-16 h-16 mx-auto mb-3"
      />
      <h1 class="text-2xl font-black text-horizon-500">Fynla</h1>
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

    <!-- Footer -->
    <p class="text-center text-neutral-500 text-xs mt-8">
      Don't have an account?
      <a href="https://fynla.org/register" class="text-raspberry-500 font-semibold">Sign up on web</a>
    </p>
  </div>
</template>

<script>
import authService from '@/services/authService';
import { setToken } from '@/services/tokenStorage';

export default {
  name: 'MobileLoginScreen',

  data() {
    return {
      email: '',
      password: '',
      loading: false,
      error: null,
    };
  },

  methods: {
    async handleLogin() {
      this.loading = true;
      this.error = null;
      console.log('[MobileLogin] Starting login for:', this.email);

      try {
        const result = await authService.login({
          email: this.email,
          password: this.password,
        });
        console.log('[MobileLogin] Login result:', JSON.stringify(result));

        // Check if MFA or verification required (flags are on top-level result)
        if (result.requires_mfa || result.requires_verification) {
          console.log('[MobileLogin] Verification required, navigating to /m/verify');
          this.$router.push({
            path: '/m/verify',
            query: {
              email: this.email,
              userId: result.data?.user_id?.toString() || '',
              mfa: result.requires_mfa ? '1' : '0',
            },
            state: {
              challenge_token: result.data?.challenge_token || '',
              mfa_token: result.data?.mfa_token || '',
            },
          });
          return;
        }

        // Direct login (no verification needed — e.g. preview users)
        const token = result.data?.access_token;
        console.log('[MobileLogin] Direct login, token present:', !!token);
        if (token) {
          await setToken(token);
          this.$store.commit('auth/setToken', token);
          console.log('[MobileLogin] Fetching user...');
          await this.$store.dispatch('auth/fetchUser');
          console.log('[MobileLogin] Navigating to /m/home');
          this.$router.push('/m/home');
        } else {
          console.log('[MobileLogin] No token and no verification required — unexpected response');
        }
      } catch (error) {
        console.log('[MobileLogin] Error caught:', JSON.stringify(error));
        this.error = error.response?.data?.message || error.message || 'Login failed. Please try again.';
      } finally {
        this.loading = false;
      }
    },
  },
};
</script>
