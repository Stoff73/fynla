<template>
  <div class="min-h-screen flex items-center justify-center bg-eggshell-500 py-12 px-4 sm:px-6 lg:px-8">
    <!-- Verification Code Modal -->
    <VerificationCodeModal
      :is-open="showVerificationModal"
      :user-email="pendingEmail"
      :pending-id="pendingId"
      type="registration"
      @verified="handleVerified"
      @close="handleVerificationClose"
    />

    <div class="max-w-2xl w-full">
      <div class="bg-light-blue-100 rounded-2xl py-8 px-6 sm:px-12 lg:px-32 space-y-6">
        <div>
          <div class="flex justify-center">
            <img :src="logoImage" alt="Fynla" class="h-[100px] w-auto">
          </div>
          <h2 class="mt-2 text-center text-h3 text-horizon-500">
            Create your account
          </h2>
          <p class="mt-2 text-center text-body-sm text-neutral-500">
            Already have an account?
            <router-link to="/login" class="font-medium text-raspberry-500 hover:text-raspberry-700">
              Sign in
            </router-link>
          </p>

          <!-- Beta Warning -->
          <div class="mt-4 bg-spring-200 border-2 border-spring-500 rounded-lg p-4">
            <div class="flex items-start gap-3">
              <svg class="w-5 h-5 text-spring-700 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
              </svg>
              <div>
                <p class="text-sm font-semibold text-spring-900">Beta Version</p>
                <p class="text-sm text-spring-800 mt-1">
                  This application is currently in beta. Any information entered may be deleted or altered without notice.
                </p>
              </div>
            </div>
          </div>
        </div>

      <form class="space-y-6" @submit.prevent="handleRegister">
        <div v-if="errorMessage" class="rounded-lg bg-raspberry-50 border border-raspberry-200 p-4">
          <p class="text-body-sm text-raspberry-700">{{ errorMessage }}</p>
          <div v-if="emailExists" class="mt-3 flex flex-col gap-2 text-sm text-center">
            <router-link to="/login" class="font-medium text-raspberry-500 hover:text-raspberry-700 underline">
              Sign in to your account
            </router-link>
          </div>
        </div>

        <div class="space-y-4">
          <!-- Name Fields Row -->
          <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div>
              <label for="first_name" class="label">
                First Name
              </label>
              <input
                id="first_name"
                v-model="form.first_name"
                type="text"
                required
                class="input-field"
                :class="{ 'border-raspberry-600': errors.first_name }"
                placeholder="John"
              >
              <p v-if="errors.first_name" class="mt-1 text-body-sm text-raspberry-600">
                {{ errors.first_name[0] }}
              </p>
            </div>

            <div>
              <label for="middle_name" class="label">
                Middle Name
              </label>
              <input
                id="middle_name"
                v-model="form.middle_name"
                type="text"
                class="input-field"
                :class="{ 'border-raspberry-600': errors.middle_name }"
                placeholder="David"
              >
              <p v-if="errors.middle_name" class="mt-1 text-body-sm text-raspberry-600">
                {{ errors.middle_name[0] }}
              </p>
            </div>

            <div>
              <label for="last_name" class="label">
                Last Name
              </label>
              <input
                id="last_name"
                v-model="form.last_name"
                type="text"
                required
                class="input-field"
                :class="{ 'border-raspberry-600': errors.last_name }"
                placeholder="Smith"
              >
              <p v-if="errors.last_name" class="mt-1 text-body-sm text-raspberry-600">
                {{ errors.last_name[0] }}
              </p>
            </div>
          </div>

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
              :class="{ 'border-raspberry-600': errors.email }"
              placeholder="you@example.com"
            >
            <p v-if="errors.email" class="mt-1 text-body-sm text-raspberry-600">
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
              :class="{ 'border-raspberry-600': errors.password }"
              placeholder="••••••••"
            >
            <p v-if="!errors.password" class="mt-1 text-xs text-neutral-500">
              Must be at least 8 characters with one uppercase letter, one lowercase letter, one number, and one special character (@$!%*?&)
            </p>
            <p v-if="errors.password" class="mt-1 text-body-sm text-raspberry-600">
              {{ errors.password[0] }}
            </p>
          </div>

          <div>
            <label for="password_confirmation" class="label">
              Confirm Password
            </label>
            <input
              id="password_confirmation"
              v-model="form.password_confirmation"
              type="password"
              required
              class="input-field"
              placeholder="••••••••"
            >
          </div>

        </div>

        <div>
          <button
            type="submit"
            :disabled="loading"
            class="w-full btn-primary"
            :class="{ 'opacity-50 cursor-not-allowed': loading }"
          >
            <span v-if="!loading">Create Account</span>
            <span v-else>Creating Account...</span>
          </button>
        </div>

        <p class="text-center text-body-sm text-neutral-500">
          By creating an account, you agree to our <router-link to="/terms" class="text-raspberry-500 hover:text-raspberry-600 underline">Terms of Service</router-link> and <router-link to="/privacy" class="text-raspberry-500 hover:text-raspberry-600 underline">Privacy Policy</router-link>
        </p>
      </form>
      </div>

      <!-- Links below the box -->
      <div class="mt-6 text-center space-y-3">
        <a href="https://fynla.org" class="block text-sm font-medium text-horizon-500 hover:text-raspberry-500 transition-colors">
          Go to Fynla homepage
        </a>
        <a
          href="https://docs.google.com/forms/d/e/1FAIpQLSds1-zixuMDTjkBCZ3lEl-q5NzA0pwXyvb8cJIuNrz2fwjSXg/viewform?usp=publish-editor"
          target="_blank"
          rel="noopener noreferrer"
          class="inline-flex items-center gap-2 text-raspberry-500 hover:text-raspberry-700 font-medium text-sm"
        >
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z" />
          </svg>
          Wishlist for priority access on release
        </a>
      </div>
    </div>

  </div>
</template>

<script>
import { ref, computed, onMounted } from 'vue';
import { useStore } from 'vuex';
import { useRouter } from 'vue-router';
import VerificationCodeModal from '@/components/Auth/VerificationCodeModal.vue';
import storage from '@/utils/storage';
import api from '@/services/api';
import authService from '@/services/authService';

export default {
  name: 'Register',

  components: {
    VerificationCodeModal,
  },

  setup() {
    const store = useStore();
    const router = useRouter();

    onMounted(() => {
      document.title = 'Create Account — Fynla';
      const meta = document.querySelector('meta[name="description"]');
      if (meta) meta.setAttribute('content', 'Create your free Fynla account to start planning your finances. Track savings, investments, pensions, and estate planning in one place.');
    });

    const form = ref({
      first_name: '',
      middle_name: '',
      last_name: '',
      email: '',
      password: '',
      password_confirmation: '',
    });

    const errors = ref({});
    const errorMessage = ref('');
    const emailExists = ref(false);
    const showVerificationModal = ref(false);
    const pendingId = ref(null);
    const pendingEmail = ref('');
    const isSubmitting = ref(false);

    const loading = computed(() => store.getters['auth/loading'] || isSubmitting.value);

    // Capture plan/billing from query params (from PricingPage)
    const route = router.currentRoute.value;
    const selectedPlan = route.query.plan || null;
    const selectedBilling = route.query.billing || null;

    const handleRegister = async () => {
      // Guard against double submission
      if (isSubmitting.value) {
        return;
      }
      errors.value = {};
      errorMessage.value = '';
      emailExists.value = false;
      isSubmitting.value = true;

      try {
        // Call register API directly to handle verification response
        // Map last_name to surname for backend compatibility
        const payload = {
          ...form.value,
          surname: form.value.last_name,
        };
        delete payload.last_name;

        // Include plan/billing if coming from pricing page
        if (selectedPlan) {
          payload.plan = selectedPlan;
        }
        if (selectedBilling) {
          payload.billing_cycle = selectedBilling;
        }

        const response = await api.post('/auth/register', payload);

        // Check if verification is required
        if (response.data.requires_verification) {
          pendingId.value = response.data.data.pending_id;
          pendingEmail.value = response.data.data.email;
          showVerificationModal.value = true;
          return;
        }

        // No verification needed - proceed with token (shouldn't happen but handle it)
        if (response.data.data?.access_token) {
          await completeRegistration(response.data.data);
        }
      } catch (error) {
        if (error.response?.data?.email_exists) {
          emailExists.value = true;
          errorMessage.value = error.response.data.message;
        } else if (error.response?.data?.errors) {
          // Map surname errors to last_name for frontend display
          const backendErrors = error.response.data.errors;
          if (backendErrors.surname) {
            backendErrors.last_name = backendErrors.surname;
            delete backendErrors.surname;
          }
          errors.value = backendErrors;
        } else {
          errorMessage.value = error.response?.data?.message || error.message || 'Registration failed. Please try again.';
        }
      } finally {
        isSubmitting.value = false;
      }
    };

    const handleVerified = async (data) => {
      showVerificationModal.value = false;
      await completeRegistration(data);
    };

    const completeRegistration = async (data) => {
      if (typeof gtag === 'function') {
        gtag('event', 'sign_up', { method: 'email' });
      }
      // Store the token
      await authService.setToken(data.access_token);
      store.commit('auth/setToken', data.access_token);

      // Fetch user data fresh from API (sets user, role, and permissions)
      await store.dispatch('auth/fetchUser');

      // Clear preview-related localStorage (user is now a real registered user)
      storage.remove('preview_persona_id');
      storage.remove('preview_mode');

      // Go to onboarding — preserve stage from query if user came from a stage page
      const stageParam = route.query.stage;
      if (stageParam) {
        router.push({ name: 'Onboarding', query: { stage: stageParam } });
      } else {
        router.push({ name: 'Onboarding' });
      }
    };

    const handleVerificationClose = () => {
      showVerificationModal.value = false;
      pendingId.value = null;
      pendingEmail.value = '';
    };

    return {
      form,
      errors,
      errorMessage,
      emailExists,
      loading,
      showVerificationModal,
      pendingId,
      pendingEmail,
      handleRegister,
      handleVerified,
      handleVerificationClose,
      logoImage: '/images/logos/LogoHiResFynlaDark.png',
    };
  },
};
</script>
