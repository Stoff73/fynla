<template>
  <div class="min-h-screen flex items-center justify-center bg-gray-50 py-12 px-4 sm:px-6 lg:px-8">
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
      :user-id="pendingUserId"
      type="login"
      @verified="handleVerified"
      @close="handleVerificationClose"
    />

    <div class="max-w-md w-full space-y-8">
      <div>
        <div class="text-center">
          <img :src="logoUrl" alt="Fynla" class="h-48 w-auto mx-auto" />
        </div>
        <h2 class="mt-6 text-center text-h3 text-gray-900">
          Sign in to your account
        </h2>
        <p class="mt-2 text-center text-body-sm text-gray-600">
          Or
          <router-link to="/register" class="font-medium text-primary-600 hover:text-primary-700">
            create a new account
          </router-link>
        </p>
      </div>

      <form class="mt-8 space-y-6" @submit.prevent="handleLogin">
        <div v-if="errorMessage" class="rounded bg-error-50 p-4">
          <p class="text-body-sm text-error-700">{{ errorMessage }}</p>
        </div>

        <div class="space-y-4">
          <div>
            <label for="email" class="label">
              Email address
            </label>
            <input
              id="email"
              v-model="form.email"
              type="email"
              required
              class="input-field"
              :class="{ 'border-error-600': errors.email }"
              placeholder="you@example.com"
            >
            <p v-if="errors.email" class="mt-1 text-body-sm text-error-600">
              {{ errors.email[0] }}
            </p>
          </div>

          <div>
            <label for="password" class="label">
              Password
            </label>
            <input
              id="password"
              v-model="form.password"
              type="password"
              required
              class="input-field"
              :class="{ 'border-error-600': errors.password }"
              placeholder="••••••••"
            >
            <p v-if="errors.password" class="mt-1 text-body-sm text-error-600">
              {{ errors.password[0] }}
            </p>
          </div>
        </div>

        <div class="flex items-center justify-between">
          <div class="flex items-center">
            <input
              id="remember-me"
              v-model="form.remember"
              type="checkbox"
              class="h-4 w-4 text-primary-600 focus:ring-primary-600 border-gray-300 rounded"
            >
            <label for="remember-me" class="ml-2 block text-body-sm text-gray-900">
              Remember me
            </label>
          </div>

          <div class="text-body-sm">
            <a href="#" class="font-medium text-primary-600 hover:text-primary-700">
              Forgot your password?
            </a>
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
  </div>
</template>

<script>
import { ref, computed } from 'vue';
import { useStore } from 'vuex';
import { useRouter } from 'vue-router';
import ChangePasswordModal from '../components/Auth/ChangePasswordModal.vue';
import VerificationCodeModal from '../components/Auth/VerificationCodeModal.vue';
import authService from '../services/authService';
import api from '../services/api';
import logoImage from '@/assets/logo.png';

export default {
  name: 'Login',

  components: {
    ChangePasswordModal,
    VerificationCodeModal,
  },

  setup() {
    const store = useStore();
    const router = useRouter();

    const form = ref({
      email: '',
      password: '',
      remember: false,
    });

    const errors = ref({});
    const errorMessage = ref('');
    const showPasswordModal = ref(false);
    const showVerificationModal = ref(false);
    const pendingUserId = ref(null);
    const pendingEmail = ref('');
    const isSubmitting = ref(false);

    const loading = computed(() => store.getters['auth/loading'] || isSubmitting.value);

    const handleLogin = async () => {
      errors.value = {};
      errorMessage.value = '';
      isSubmitting.value = true;

      try {
        // Call login API directly to handle verification response
        const response = await api.post('/auth/login', {
          email: form.value.email,
          password: form.value.password,
        });

        // Check if verification is required
        if (response.data.requires_verification) {
          pendingUserId.value = response.data.data.user_id;
          pendingEmail.value = response.data.data.email;
          showVerificationModal.value = true;
          return;
        }

        // No verification needed (preview user) - proceed with token
        if (response.data.data?.access_token) {
          authService.setToken(response.data.data.access_token);
          store.commit('auth/setToken', response.data.data.access_token);
          await store.dispatch('auth/fetchUser');

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
      authService.setToken(data.access_token);
      store.commit('auth/setToken', data.access_token);
      store.commit('auth/setUser', data.user);

      // Check if user must change password
      if (data.must_change_password) {
        showPasswordModal.value = true;
      } else {
        router.push({ name: 'Dashboard' });
      }
    };

    const handleVerificationClose = () => {
      showVerificationModal.value = false;
      pendingUserId.value = null;
      pendingEmail.value = '';
    };

    const handlePasswordChanged = () => {
      showPasswordModal.value = false;

      // Update user data to reflect password change
      authService.getUser();

      // Redirect to dashboard
      router.push({ name: 'Dashboard' });
    };

    return {
      form,
      errors,
      errorMessage,
      loading,
      showPasswordModal,
      showVerificationModal,
      pendingUserId,
      pendingEmail,
      logoUrl: logoImage,
      handleLogin,
      handleVerified,
      handleVerificationClose,
      handlePasswordChanged,
    };
  },
};
</script>
