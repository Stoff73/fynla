<template>
  <div class="min-h-screen flex items-center justify-center bg-gradient-to-br from-horizon-500 to-raspberry-500 py-12 px-4 sm:px-6 lg:px-8">
    <!-- Verification Code Modal -->
    <VerificationCodeModal
      :is-open="showVerificationModal"
      :user-email="pendingEmail"
      :pending-id="pendingId"
      type="registration"
      @verified="handleVerified"
      @close="handleVerificationClose"
    />

    <!-- Restore Account Modal -->
    <RestoreAccountModal
      v-bind="restoreModal"
      @cancel="onRestoreCancel"
      @restored="onRestored"
    />

    <div class="max-w-2xl w-full">
      <div class="auth-card rounded-2xl py-8 px-6 sm:px-12 lg:px-16 space-y-6">
        <!-- Funnel hand-off (from=savetax et al): the user already filled the
             compact register form on the campaign page, so re-showing this
             page's full form (behind the verification modal, and again in the
             gap between verifying and the dashboard redirect) reads as a
             confusing flash of a second registration form. Hold on a quiet
             setup panel instead — the form only returns if they close the
             modal without verifying. -->
        <div v-if="funnelHandoff" class="py-10 flex flex-col items-center gap-5">
          <img :src="logoImage" alt="Fynla" class="h-[75px] w-auto">
          <div class="w-10 h-10 border-4 border-horizon-200 border-t-raspberry-500 rounded-full animate-spin"></div>
          <p class="text-body-sm text-neutral-500 text-center">Setting up your account…</p>
        </div>

        <template v-else>
        <div>
          <div class="flex justify-center">
            <router-link to="/" class="inline-block hover:opacity-85 transition-opacity">
              <img :src="logoImage" alt="Fynla" class="h-[75px] w-auto">
            </router-link>
          </div>
          <h2 class="mt-1 text-center text-h3 text-horizon-500">
            Create your account
          </h2>
          <p class="mt-2 text-center text-body-sm text-neutral-500">
            Already have an account?
            <router-link to="/login" class="font-medium text-raspberry-500 hover:text-raspberry-700">
              Sign in
            </router-link>
          </p>

        </div>

        <!-- Cookie Consent Required for Registration -->
        <div v-if="!cookiesAccepted" class="mt-4 bg-violet-50 border-2 border-violet-300 rounded-lg p-5">
          <div class="flex items-start gap-3">
            <svg class="w-5 h-5 text-violet-500 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
            </svg>
            <div>
              <p class="text-body-sm font-semibold text-horizon-500">Cookies Required</p>
              <p class="text-body-sm text-neutral-500 mt-1">
                Cookies are required to create an account. They allow us to keep you securely signed in.
              </p>
              <button
                type="button"
                class="mt-3 px-5 py-2 rounded-lg bg-raspberry-500 text-white text-body-sm font-medium hover:bg-raspberry-600 transition-colors"
                @click="handleAcceptCookiesForRegistration"
              >
                Accept Cookies & Continue
              </button>
            </div>
          </div>
        </div>

      <form v-if="cookiesAccepted" class="space-y-6" @submit.prevent="handleRegister">
        <div v-if="errorMessage" class="rounded-lg bg-raspberry-50 border border-raspberry-200 p-4">
          <p class="text-body-sm text-raspberry-700">{{ errorMessage }}</p>
          <div v-if="emailExists" class="mt-3 flex flex-col gap-2 text-sm text-center">
            <router-link to="/login" class="font-medium text-raspberry-500 hover:text-raspberry-700 underline">
              Sign in to your account
            </router-link>
          </div>
        </div>

        <div class="space-y-4">
          <!-- First Name -->
          <div>
            <label for="first_name" class="label">
              First Name <span class="text-raspberry-500">*</span>
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

          <!-- Last Name — full width -->
          <div>
            <label for="last_name" class="label">
              Last Name <span class="text-raspberry-500">*</span>
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
            <p v-if="!errors.password" class="mt-1 text-xs text-neutral-500">
              Must be at least 8 characters with one uppercase letter, one lowercase letter, one number, and one special character (@$!%*?&)
            </p>
            <p v-if="errors.password" class="mt-1 text-body-sm text-raspberry-600">
              {{ errors.password[0] }}
            </p>
          </div>

          <div>
            <label for="password_confirmation" class="label">
              Confirm Password <span class="text-raspberry-500">*</span>
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

        <p class="text-center text-xs text-neutral-500 whitespace-nowrap">
          By creating an account, you agree to our <router-link to="/terms" class="text-raspberry-500 hover:text-raspberry-600 underline">Terms of Service</router-link> and <router-link to="/privacy" class="text-raspberry-500 hover:text-raspberry-600 underline">Privacy Policy</router-link>
        </p>
      </form>
        </template>
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
import { useRouter } from 'vue-router';
import VerificationCodeModal from '@/components/Auth/VerificationCodeModal.vue';
import RestoreAccountModal from '@/components/Account/RestoreAccountModal.vue';
import storage from '@/utils/storage';
import api from '@/services/api';
import authService from '@/services/authService';
import { hasConsent, acceptCookies } from '@/utils/cookieConsent';
import { getCapturedSource, clearCapturedSource } from '@/utils/sourceCapture';

export default {
  name: 'RegisterView',

  components: {
    VerificationCodeModal,
    RestoreAccountModal,
  },

  setup() {
    const store = useStore();
    const router = useRouter();
    const cookiesAccepted = ref(hasConsent());

    const handleAcceptCookiesForRegistration = () => {
      acceptCookies();
      cookiesAccepted.value = true;
    };

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
    // Funnel hand-off in progress: hide this page's registration form (the
    // user already registered on the campaign page) and hold on a quiet
    // setup panel until the post-verification redirect fires.
    const funnelHandoff = ref(false);

    const restoreModal = ref({
      visible: false,
      firstName: '',
      deletedAt: '',
      restorationToken: '',
      requiresPasswordVerification: false,
      email: '',
    });

    const loading = computed(() => store.getters['auth/loading'] || isSubmitting.value);

    // Capture plan/billing/referral from query params
    const route = router.currentRoute.value;
    const selectedPlan = route.query.plan || null;
    const selectedBilling = route.query.billing || null;
    const referralCode = route.query.ref || null;

    // Funnel hand-off: /savetax/plan's compact "Register for free" creates the
    // pending account itself, then sends the user here with the pending id +
    // email stashed in sessionStorage (same-origin). Open the verification
    // modal directly so they only enter the emailed code — no re-entering the
    // form. On success, completeRegistration() routes onward (and inside the
    // /m mobile iframe the auth handoff shows the mobile dashboard).
    onMounted(() => {
      let stashed = null;
      try {
        const raw = sessionStorage.getItem('fynla_pending_verify');
        if (raw) {
          stashed = JSON.parse(raw);
          sessionStorage.removeItem('fynla_pending_verify');
        }
      } catch (e) { /* ignore */ }
      if (stashed && stashed.pending_id) {
        pendingId.value = stashed.pending_id;
        pendingEmail.value = stashed.email || '';
        funnelHandoff.value = true;
        showVerificationModal.value = true;
      }
    });

    const handleRegister = async () => {
      // Guard against double submission
      if (isSubmitting.value) {
        return;
      }
      if (typeof gtag === 'function') {
        gtag('event', 'sign_up_start', { method: 'email' });
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
        if (referralCode) {
          payload.referral_code = referralCode;
        }

        // Marketing-channel attribution captured at first page load
        // (e.g. /savetax?utm_source=linkedin) and stashed in
        // sessionStorage by sourceCapture.js. Send it through; backend
        // validates against an allowlist before persisting.
        const capturedSource = getCapturedSource();
        if (capturedSource) {
          payload.signup_source = capturedSource;
        }

        const response = await api.post('/auth/register', payload);

        // Source has been persisted to PendingRegistration; clear so a
        // second registration attempt in the same browser session does
        // not silently inherit it.
        if (capturedSource) clearCapturedSource();

        // Email belongs to a soft-deleted but restorable account — show
        // the restore modal. The modal collects the password and calls
        // /auth/restore/check before /auth/restore.
        if (response.data.account_deleted_restorable) {
          restoreModal.value = {
            visible: true,
            firstName: response.data.first_name || '',
            deletedAt: response.data.deleted_at || '',
            restorationToken: '',
            requiresPasswordVerification: true,
            email: form.value.email,
          };
          return;
        }

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
        gtag('event', 'sign_up_complete', { method: 'email' });
      }
      // Meta Pixel: CompleteRegistration
      if (typeof fbq === 'function') {
        fbq('track', 'CompleteRegistration', { currency: 'GBP', value: 0 });
      }
      // Store the token
      await authService.setToken(data.access_token);
      store.commit('auth/setToken', data.access_token);

      // Fetch user data fresh from API (sets user, role, and permissions)
      await store.dispatch('auth/fetchUser');

      // Clear preview-related localStorage (user is now a real registered user)
      storage.remove('preview_persona_id');
      storage.remove('preview_mode');

      // CRITICAL: Reset aiChat state to prevent any prior user's conversation leaking
      store.dispatch('aiChat/reset', null, { root: true }).catch(() => {});

      // Route based on registration source. Any `from=<id>` (e.g. fyn,
      // savetax, biggerpension) takes the user to the dashboard with the
      // Fyn chat auto-opened, propagating the entry source so the
      // onboarding director can route to the matching campaign or
      // life-stage journey via the campaign_map / journey_map config.
      const fromParam = route.query.from;
      const stageParam = route.query.stage;

      if (fromParam) {
        router.push({
          name: 'Dashboard',
          query: { openFyn: 'journey', newUser: '1', from: fromParam },
        });
      } else if (stageParam) {
        router.push({ name: 'Onboarding', query: { stage: stageParam, newUser: '1' } });
      } else {
        router.push({ name: 'Onboarding', query: { newUser: '1' } });
      }
    };

    const handleVerificationClose = () => {
      showVerificationModal.value = false;
      pendingId.value = null;
      pendingEmail.value = '';
      // Funnel user closed the modal without verifying — surface the normal
      // registration form so they aren't stranded on the setup panel.
      funnelHandoff.value = false;
    };

    const onRestoreCancel = () => {
      restoreModal.value = {
        visible: false,
        firstName: '',
        deletedAt: '',
        restorationToken: '',
        requiresPasswordVerification: false,
        email: '',
      };
    };

    const onRestored = async (result) => {
      // result = { token, user, redirect_to } from POST /api/auth/restore
      await authService.setToken(result.token);
      store.commit('auth/setToken', result.token);

      // Reset cross-user caches so a returning restored user does not
      // inherit any stale chat / dashboard state.
      store.dispatch('aiChat/reset', null, { root: true }).catch(() => {});

      try {
        await store.dispatch('auth/fetchUser');
      } catch (e) {
        // fetchUser failure shouldn't block redirect — token is set.
      }

      restoreModal.value.visible = false;
      router.push(result.redirect_to || '/dashboard?openPricing=1');
    };

    return {
      cookiesAccepted,
      handleAcceptCookiesForRegistration,
      form,
      errors,
      errorMessage,
      emailExists,
      loading,
      showVerificationModal,
      pendingId,
      pendingEmail,
      funnelHandoff,
      restoreModal,
      handleRegister,
      handleVerified,
      handleVerificationClose,
      onRestoreCancel,
      onRestored,
      logoImage: '/images/logos/LogoHiResFynlaDark.png',
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
