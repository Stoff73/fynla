<template>
  <div class="min-h-screen flex items-center justify-center bg-gray-50 py-12 px-4 sm:px-6 lg:px-8">
    <!-- Verification Code Modal -->
    <VerificationCodeModal
      :is-open="showVerificationModal"
      :user-email="pendingEmail"
      :pending-id="pendingId"
      type="registration"
      @verified="handleVerified"
      @close="handleVerificationClose"
    />

    <div class="max-w-md w-full space-y-8">
      <div>
        <div class="flex justify-center">
          <img :src="logoImage" alt="Fynla" class="h-48 w-auto">
        </div>
        <h2 class="mt-2 text-center text-h3 text-gray-900">
          Create your account
        </h2>
        <p class="mt-2 text-center text-body-sm text-gray-600">
          Or
          <router-link to="/login" class="font-medium text-primary-600 hover:text-primary-700">
            sign in to existing account
          </router-link>
        </p>

        <!-- Beta Warning -->
        <div class="mt-4 bg-amber-200 border-2 border-amber-500 rounded-lg p-4">
          <div class="flex items-start gap-3">
            <svg class="w-5 h-5 text-amber-700 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
            </svg>
            <div>
              <p class="text-sm font-semibold text-amber-900">Beta Version</p>
              <p class="text-sm text-amber-800 mt-1">
                This application is currently in beta. Any information entered may be deleted or altered without notice.
              </p>
            </div>
          </div>
        </div>

        <!-- Wishlist Link -->
        <div class="mt-4 text-center">
          <a
            href="https://docs.google.com/forms/d/e/1FAIpQLSds1-zixuMDTjkBCZ3lEl-q5NzA0pwXyvb8cJIuNrz2fwjSXg/viewform?usp=publish-editor"
            target="_blank"
            rel="noopener noreferrer"
            class="inline-flex items-center gap-2 text-primary-600 hover:text-primary-700 font-medium text-sm"
          >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z" />
            </svg>
            Wishlist for priority access on release
          </a>
        </div>
      </div>

      <form class="mt-8 space-y-6" @submit.prevent="handleRegister">
        <div v-if="errorMessage" class="rounded-button bg-error-50 p-4">
          <p class="text-body-sm text-error-700">{{ errorMessage }}</p>
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
                :class="{ 'border-error-600': errors.first_name }"
                placeholder="John"
              >
              <p v-if="errors.first_name" class="mt-1 text-body-sm text-error-600">
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
                :class="{ 'border-error-600': errors.middle_name }"
                placeholder="David"
              >
              <p v-if="errors.middle_name" class="mt-1 text-body-sm text-error-600">
                {{ errors.middle_name[0] }}
              </p>
            </div>

            <div>
              <label for="surname" class="label">
                Surname
              </label>
              <input
                id="surname"
                v-model="form.surname"
                type="text"
                required
                class="input-field"
                :class="{ 'border-error-600': errors.surname }"
                placeholder="Smith"
              >
              <p v-if="errors.surname" class="mt-1 text-body-sm text-error-600">
                {{ errors.surname[0] }}
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
            <p v-if="!errors.password" class="mt-1 text-xs text-gray-500">
              Must be at least 8 characters with one uppercase letter, one lowercase letter, one number, and one special character (@$!%*?&)
            </p>
            <p v-if="errors.password" class="mt-1 text-body-sm text-error-600">
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

        <p class="text-center text-body-sm text-gray-600">
          By creating an account, you agree to our Terms of Service and Privacy Policy
        </p>
      </form>
    </div>

    <!-- Keep Data or Fresh Modal (for preview mode registrations) -->
    <KeepDataOrFreshModal
      :is-open="showKeepDataModal"
      :persona="personaForModal"
      @choice="handleKeepDataChoice"
    />
  </div>
</template>

<script>
import { ref, computed, onMounted } from 'vue';
import { useStore } from 'vuex';
import { useRouter } from 'vue-router';
import KeepDataOrFreshModal from '@/components/Preview/KeepDataOrFreshModal.vue';
import VerificationCodeModal from '@/components/Auth/VerificationCodeModal.vue';
import api from '@/services/api';
import authService from '@/services/authService';
import logoImage from '@/assets/images/logo.png';

export default {
  name: 'Register',

  components: {
    KeepDataOrFreshModal,
    VerificationCodeModal,
  },

  setup() {
    const store = useStore();
    const router = useRouter();

    const form = ref({
      first_name: '',
      middle_name: '',
      surname: '',
      email: '',
      password: '',
      password_confirmation: '',
    });

    const errors = ref({});
    const errorMessage = ref('');
    const showKeepDataModal = ref(false);
    const previewPersonaId = ref(null);
    const personaForModal = ref(null);  // Cached persona to pass to modal after registration
    const showVerificationModal = ref(false);
    const pendingId = ref(null);
    const pendingEmail = ref('');
    const isSubmitting = ref(false);

    const loading = computed(() => store.getters['auth/loading'] || isSubmitting.value);

    // Check if user is coming from preview mode
    const wasInPreview = computed(() => store.getters['preview/isPreviewMode']);
    const currentPersona = computed(() => store.getters['preview/currentPersona']);
    const currentPersonaName = computed(() => currentPersona.value?.name || 'Demo User');

    // Store preview state before registration clears it
    let cachedWasInPreview = false;
    let cachedCurrentPersona = null;

    // Pre-fill name from persona if in preview mode
    onMounted(() => {
      if (wasInPreview.value && currentPersona.value) {
        // Extract first person's name from persona (e.g., "James & Emily Wilson" -> "James Wilson")
        const personaName = currentPersona.value.name || '';
        if (personaName.includes('&')) {
          // For couples, don't pre-fill - let them enter their own name
        } else {
          // For single personas, optionally pre-fill
          // form.value.name = personaName; // Commented out - let user enter their own name
        }
      }
    });

    const handleRegister = async () => {
      // Guard against double submission
      if (isSubmitting.value) {
        return;
      }
      errors.value = {};
      errorMessage.value = '';
      isSubmitting.value = true;

      // Cache preview state before it gets cleared
      cachedWasInPreview = wasInPreview.value;
      cachedCurrentPersona = currentPersona.value;

      try {
        // Include registration source if from preview
        const registrationData = {
          ...form.value,
        };

        if (cachedWasInPreview) {
          registrationData.registration_source = 'preview';
          registrationData.preview_persona_id = cachedCurrentPersona?.id;
        }

        // Call register API directly to handle verification response
        const response = await api.post('/auth/register', registrationData);

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
        if (error.response?.data?.errors) {
          errors.value = error.response.data.errors;
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
      // Store the token
      authService.setToken(data.access_token);
      store.commit('auth/setToken', data.access_token);
      store.commit('auth/setUser', data.user);

      // Check if coming from preview mode (use cached values)
      if (cachedWasInPreview && cachedCurrentPersona) {
        // Show keep/fresh modal with cached persona (currentPersona is now null after auth change)
        previewPersonaId.value = cachedCurrentPersona.id;
        personaForModal.value = cachedCurrentPersona;
        showKeepDataModal.value = true;
      } else {
        // Direct registration - go to onboarding
        router.push({ name: 'Onboarding' });
      }
    };

    const handleVerificationClose = () => {
      showVerificationModal.value = false;
      pendingId.value = null;
      pendingEmail.value = '';
    };

    const handleKeepDataChoice = async ({ choice, createSpouseAccount, personaId }) => {
      try {
        if (choice === 'keep') {
          // Seed persona data to user's account
          await api.post('/user/seed-persona-data', {
            persona_id: personaId,
            create_spouse_account: createSpouseAccount,
          });
        }

        // Clear preview-related localStorage (user is now a real registered user)
        // Do NOT call exitPreview as it would clear the auth token and redirect to '/'
        localStorage.removeItem('preview_persona_id');
        localStorage.removeItem('preview_mode');

        // Navigate to onboarding
        router.push({ name: 'Onboarding' });
      } catch (error) {
        console.error('Failed to handle keep data choice:', error);
        errorMessage.value = 'Failed to set up your account. Please try again.';
        showKeepDataModal.value = false;
      }
    };

    return {
      form,
      errors,
      errorMessage,
      loading,
      showKeepDataModal,
      showVerificationModal,
      pendingId,
      pendingEmail,
      personaForModal,
      handleRegister,
      handleVerified,
      handleVerificationClose,
      handleKeepDataChoice,
      logoImage,
    };
  },
};
</script>
