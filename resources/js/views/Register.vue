<template>
  <div class="min-h-screen flex items-center justify-center bg-gray-50 py-12 px-4 sm:px-6 lg:px-8">
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

        <!-- Preview mode indicator -->
        <div v-if="wasInPreview" class="mt-4 bg-amber-50 border border-amber-200 rounded-lg p-3">
          <div class="flex items-center gap-2 text-sm text-amber-800">
            <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
            </svg>
            <span>
              Registering from preview mode.
              <strong>{{ currentPersonaName }}</strong>'s data can be saved after registration.
            </span>
          </div>
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
                First Name <span class="text-error-600">*</span>
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
                Surname <span class="text-error-600">*</span>
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
      :persona="currentPersona"
      @choice="handleKeepDataChoice"
    />
  </div>
</template>

<script>
import { ref, computed, onMounted } from 'vue';
import { useStore } from 'vuex';
import { useRouter } from 'vue-router';
import KeepDataOrFreshModal from '@/components/Preview/KeepDataOrFreshModal.vue';
import api from '@/services/api';
import logoImage from '@/assets/images/logo.png';

export default {
  name: 'Register',

  components: {
    KeepDataOrFreshModal,
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

    const loading = computed(() => store.getters['auth/loading']);

    // Check if user is coming from preview mode
    const wasInPreview = computed(() => store.getters['preview/isPreviewMode']);
    const currentPersona = computed(() => store.getters['preview/currentPersona']);
    const currentPersonaName = computed(() => currentPersona.value?.name || 'Demo User');

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
      errors.value = {};
      errorMessage.value = '';

      try {
        // Include registration source if from preview
        const registrationData = {
          ...form.value,
        };

        if (wasInPreview.value) {
          registrationData.registration_source = 'preview';
          registrationData.preview_persona_id = currentPersona.value?.id;
        }

        await store.dispatch('auth/register', registrationData);

        // Check if coming from preview mode
        if (wasInPreview.value && currentPersona.value) {
          // Show keep/fresh modal
          previewPersonaId.value = currentPersona.value.id;
          showKeepDataModal.value = true;
        } else {
          // Direct registration - go to dashboard with guidance
          await store.dispatch('guidance/showWelcomeModal');
          router.push({ name: 'Dashboard' });
        }
      } catch (error) {
        if (error.errors) {
          errors.value = error.errors;
        } else {
          errorMessage.value = error.message || 'Registration failed. Please try again.';
        }
      }
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

        // Clear preview mode
        await store.dispatch('preview/exitPreview');

        // Start guidance for new user
        await store.dispatch('guidance/showWelcomeModal');

        // Navigate to dashboard
        router.push({ name: 'Dashboard' });
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
      wasInPreview,
      currentPersona,
      currentPersonaName,
      handleRegister,
      handleKeepDataChoice,
      logoImage,
    };
  },
};
</script>
