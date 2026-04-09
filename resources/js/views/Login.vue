<template>
  <div class="min-h-screen flex items-center justify-center bg-gradient-to-br from-horizon-500 to-raspberry-500 py-12 px-4 sm:px-6 lg:px-8">
    <!-- Change Password Modal -->
    <ChangePasswordModal
      :show="showPasswordModal"
      :is-required="true"
      @success="handlePasswordChanged"
    />

    <!-- Verification Code Modal -->
    <VerificationCodeModal
      :is-open="showVerificationModal"
      :user-email="pendingEmail"
      :challenge-token="pendingChallengeToken"
      type="login"
      @verified="handleVerified"
      @close="handleVerificationClose"
    />

    <!-- MFA Verification Modal -->
    <MFAVerifyModal
      :is-open="showMFAModal"
      :mfa-token="pendingMfaToken"
      @verified="handleMFAVerified"
      @close="handleMFAClose"
    />

    <!-- Forgot Password Modal -->
    <ForgotPasswordModal
      :is-open="showForgotPasswordModal"
      @close="showForgotPasswordModal = false"
      @success="handlePasswordResetSuccess"
    />

    <div class="max-w-2xl w-full">
      <div class="auth-card rounded-2xl py-8 pb-12 px-6 sm:px-12 lg:px-16 space-y-6">
        <div>
          <div class="text-center">
            <router-link to="/" class="inline-block hover:opacity-85 transition-opacity">
              <img :src="logoUrl" alt="Fynla" class="h-[75px] w-auto mx-auto" />
            </router-link>
          </div>
          <h2 class="mt-1 text-center text-h3 text-horizon-500">
            Sign in to your account
          </h2>
          <p class="mt-2 text-center text-sm text-neutral-500">
            Not registered? You can
            <router-link to="/register" class="text-raspberry-500 hover:text-raspberry-600 font-medium">register a new account</router-link>.
          </p>

        </div>

      <form class="space-y-6" @submit.prevent="handleLogin">
        <!-- Inactivity Message -->
        <div v-if="inactivityMessage" class="rounded-lg bg-violet-500 border border-violet-600 p-4 text-center">
          <div class="flex items-start gap-3 justify-center">
            <svg class="w-5 h-5 text-white flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <p class="text-sm text-white font-medium">{{ inactivityMessage }}</p>
          </div>
        </div>

        <!-- Error Message -->
        <div v-if="errorMessage" class="rounded-lg bg-raspberry-50 border border-raspberry-200 p-4 text-center">
          <p class="text-sm text-raspberry-700">{{ errorMessage }}</p>
          <div v-if="showLoginHints" class="mt-3 flex flex-col gap-2 text-sm">
            <button
              type="button"
              @click="showForgotPasswordModal = true"
              class="text-raspberry-500 hover:text-raspberry-700 font-medium underline"
            >
              Forgot your password?
            </button>
            <p class="text-raspberry-700">
              Don't have an account?
              <router-link to="/register" class="font-medium text-raspberry-500 hover:text-raspberry-700 underline">
                Register here
              </router-link>
            </p>
          </div>
        </div>

        <div class="space-y-4">
          <div>
            <label for="email" class="label">
              Email address <span class="text-raspberry-500">*</span>
            </label>
            <input
              id="email"
              v-model="form.email"
              type="email"
              required
              class="input-field"
              :class="{ 'border-raspberry-600': errors.email }"
              placeholder="you@example.com"
            >
            <p v-if="errors.email" class="mt-1 text-body-sm text-raspberry-600">
              {{ errors.email[0] }}
            </p>
          </div>

          <div>
            <label for="password" class="label">
              Password <span class="text-raspberry-500">*</span>
            </label>
            <input
              id="password"
              v-model="form.password"
              type="password"
              required
              class="input-field"
              :class="{ 'border-raspberry-600': errors.password }"
              placeholder="••••••••"
            >
            <p v-if="errors.password" class="mt-1 text-body-sm text-raspberry-600">
              {{ errors.password[0] }}
            </p>
          </div>
        </div>

        <div class="flex items-center justify-end">
          <div class="text-body-sm">
            <button
              type="button"
              @click="showForgotPasswordModal = true"
              class="font-medium text-raspberry-500 hover:text-raspberry-700"
            >
              Forgot your password?
            </button>
          </div>
        </div>

        <div>
          <button
            type="submit"
            :disabled="loading"
            class="w-full btn-primary"
            :class="{ 'opacity-50 cursor-not-allowed': loading }"
          >
            <span v-if="!loading">Sign in</span>
            <span v-else>Signing in...</span>
          </button>
        </div>
      </form>
      </div>

      <!-- Links below the box -->
      <div class="mt-6 text-center space-y-3">
        <router-link to="/" class="block text-sm font-medium text-white/85 hover:text-white transition-colors">
          Go to Fynla homepage
        </router-link>
      </div>
    </div>
  </div>
</template>

<script>
import { ref, computed, onMounted } from 'vue';
import { useStore } from 'vuex';
import { useRouter, useRoute } from 'vue-router';
import ChangePasswordModal from '../components/Auth/ChangePasswordModal.vue';
import VerificationCodeModal from '../components/Auth/VerificationCodeModal.vue';
import MFAVerifyModal from '../components/Auth/MFAVerifyModal.vue';
import ForgotPasswordModal from '../components/Auth/ForgotPasswordModal.vue';
import authService from '../services/authService';
import api from '../services/api';

export default {
  name: 'LoginView',

  components: {
    ChangePasswordModal,
    VerificationCodeModal,
    MFAVerifyModal,
    ForgotPasswordModal,
  },

  setup() {
    const store = useStore();
    const router = useRouter();
    const route = useRoute();

    const form = ref({
      email: '',
      password: '',
    });

    const errors = ref({});
    const errorMessage = ref('');
    const inactivityMessage = ref('');
    const showPasswordModal = ref(false);
    const showVerificationModal = ref(false);
    const showMFAModal = ref(false);
    const showForgotPasswordModal = ref(false);
    const pendingChallengeToken = ref(null);
    const pendingMfaToken = ref(null);
    const pendingEmail = ref('');
    const isSubmitting = ref(false);

    const loading = computed(() => store.getters['auth/loading'] || isSubmitting.value);

    // Check for inactivity logout reason on mount
    onMounted(() => {
      document.title = 'Sign In — Fynla';
      const meta = document.querySelector('meta[name="description"]');
      if (meta) meta.setAttribute('content', 'Sign in to your Fynla account to manage your financial plan, track investments, and monitor your net worth.');

      if (route.query.reason === 'inactivity') {
        inactivityMessage.value = 'Your session has expired due to inactivity. Please sign in again.';
        // Clean up the URL parameter
        router.replace({ path: route.path, query: {} });
      }
    });

    const showLoginHints = computed(() => {
      if (!errorMessage.value) return false;
      const msg = errorMessage.value.toLowerCase();
      return msg.includes('invalid') || msg.includes('no account') || msg.includes('incorrect');
    });

    const handleLogin = async () => {
      if (typeof gtag === 'function') {
        gtag('event', 'login_attempt', { event_label: 'sign_in' });
      }
      errors.value = {};
      errorMessage.value = '';
      isSubmitting.value = true;

      try {
        // Call login API directly to handle verification response
        const response = await api.post('/auth/login', {
          email: form.value.email,
          password: form.value.password,
        });

        // Check if MFA verification is required
        if (response.data.requires_mfa) {
          pendingMfaToken.value = response.data.data.mfa_token;
          pendingEmail.value = response.data.data.email;
          showMFAModal.value = true;
          return;
        }

        // Check if email verification is required
        if (response.data.requires_verification) {
          pendingChallengeToken.value = response.data.data.challenge_token;
          pendingEmail.value = response.data.data.email;
          showVerificationModal.value = true;
          return;
        }

        // No verification needed (preview user) - proceed with token
        if (response.data.data?.access_token) {
          await authService.setToken(response.data.data.access_token);
          store.commit('auth/setToken', response.data.data.access_token);

          // CRITICAL: Reset aiChat state to prevent prior user's conversation leaking
          store.dispatch('aiChat/reset', null, { root: true }).catch(() => {});

          await store.dispatch('auth/fetchUser');
          if (typeof gtag === 'function') {
            gtag('event', 'login_success');
          }

          // Check if user must change password
          if (response.data.data.must_change_password) {
            showPasswordModal.value = true;
          } else {
            router.push({ name: 'Dashboard' });
          }
        }
      } catch (error) {
        if (error.response?.data?.errors) {
          errors.value = error.response.data.errors;
        } else {
          errorMessage.value = error.response?.data?.message || error.message || 'Invalid credentials. Please try again.';
        }
      } finally {
        isSubmitting.value = false;
      }
    };

    const handleVerified = async (data) => {
      showVerificationModal.value = false;

      // Store the token
      await authService.setToken(data.access_token);
      store.commit('auth/setToken', data.access_token);

      // CRITICAL: Reset aiChat state to prevent prior user's conversation leaking
      store.dispatch('aiChat/reset', null, { root: true }).catch(() => {});

      // Fetch user data fresh from API (sets user, role, and permissions)
      await store.dispatch('auth/fetchUser');
      if (typeof gtag === 'function') {
        gtag('event', 'login_success');
      }

      // Check if user must change password
      if (data.must_change_password) {
        showPasswordModal.value = true;
      } else {
        router.push({ name: 'Dashboard' });
      }
    };

    const handleVerificationClose = () => {
      showVerificationModal.value = false;
      pendingChallengeToken.value = null;
      pendingEmail.value = '';
    };

    const handleMFAVerified = async (data) => {
      showMFAModal.value = false;

      // Store the token
      await authService.setToken(data.access_token);
      store.commit('auth/setToken', data.access_token);

      // CRITICAL: Reset aiChat state to prevent prior user's conversation leaking
      store.dispatch('aiChat/reset', null, { root: true }).catch(() => {});

      // Fetch user data fresh from API (sets user, role, and permissions)
      await store.dispatch('auth/fetchUser');

      // Check if user must change password
      if (data.must_change_password) {
        showPasswordModal.value = true;
      } else {
        router.push({ name: 'Dashboard' });
      }
    };

    const handleMFAClose = () => {
      showMFAModal.value = false;
      pendingMfaToken.value = null;
      pendingEmail.value = '';
    };

    const handlePasswordChanged = () => {
      showPasswordModal.value = false;

      // Update user data to reflect password change
      authService.getUser();

      // Redirect to dashboard
      router.push({ name: 'Dashboard' });
    };

    const handlePasswordResetSuccess = () => {
      showForgotPasswordModal.value = false;
      // User will need to log in with new password
      // The modal shows a success message and closes
    };

    return {
      form,
      errors,
      errorMessage,
      inactivityMessage,
      loading,
      showLoginHints,
      showPasswordModal,
      showVerificationModal,
      showMFAModal,
      showForgotPasswordModal,
      pendingChallengeToken,
      pendingMfaToken,
      pendingEmail,
      logoUrl: '/images/logos/LogoHiResFynlaDark.png',
      handleLogin,
      handleVerified,
      handleVerificationClose,
      handleMFAVerified,
      handleMFAClose,
      handlePasswordChanged,
      handlePasswordResetSuccess,
    };
  },
};
</script>

<style scoped>
.auth-card {
  background: linear-gradient(180deg, #FFFFFF 0%, #F3F3F3 100%);
  border: 1px solid theme('colors.light-gray');
  box-shadow: 0 4px 24px rgba(0, 0, 0, 0.12), 0 8px 40px rgba(0, 0, 0, 0.08);
}

.auth-card :deep(.label) {
  @apply text-horizon-500 mb-1;
}

.auth-card :deep(.input-field) {
  @apply border-light-blue-500/40 focus:border-horizon-500 focus:ring-horizon-500 focus:ring-opacity-20;
}
</style>
