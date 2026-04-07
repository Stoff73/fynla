<template>
  <div v-if="visible" class="fixed inset-0 z-[100] flex items-end justify-center pb-8 px-4">
    <!-- Backdrop -->
    <div class="absolute inset-0 bg-black/30" />

    <!-- Banner card -->
    <div class="relative bg-white rounded-xl shadow-2xl max-w-lg w-full p-6 space-y-4">
      <!-- Initial state -->
      <template v-if="!showWarning">
        <div class="flex items-start gap-3">
          <svg class="w-6 h-6 text-violet-500 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
          </svg>
          <div>
            <h3 class="text-body-sm font-bold text-horizon-500">Cookie Preferences</h3>
            <p class="text-body-sm text-neutral-500 mt-1">
              We use cookies to help analyse how you use our site. You can accept or decline.
              <router-link to="/privacy" class="text-raspberry-500 hover:underline">Privacy Policy</router-link>
            </p>
          </div>
        </div>
        <div class="flex gap-3">
          <button
            class="flex-1 px-4 py-2.5 rounded-lg bg-raspberry-500 text-white text-body-sm font-medium hover:bg-raspberry-600 transition-colors"
            @click="handleAccept"
          >
            Accept Cookies
          </button>
          <button
            class="flex-1 px-4 py-2.5 rounded-lg border border-light-gray text-neutral-500 text-body-sm font-medium hover:bg-eggshell-500 transition-colors"
            @click="showWarning = true"
          >
            Decline Cookies
          </button>
        </div>
      </template>

      <!-- Warning state (after clicking Decline) -->
      <template v-else>
        <div class="flex items-start gap-3">
          <svg class="w-6 h-6 text-violet-500 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
          </svg>
          <div>
            <h3 class="text-body-sm font-bold text-horizon-500">Limited Functionality</h3>
            <p class="text-body-sm text-neutral-500 mt-1">
              Without cookies, some features including registration will be unavailable. Google Analytics has been disabled.
            </p>
          </div>
        </div>
        <div class="flex gap-3">
          <button
            class="flex-1 px-4 py-2.5 rounded-lg bg-raspberry-500 text-white text-body-sm font-medium hover:bg-raspberry-600 transition-colors"
            @click="handleAccept"
          >
            Accept Cookies
          </button>
          <button
            class="flex-1 px-4 py-2.5 rounded-lg border border-light-gray text-neutral-500 text-body-sm font-medium hover:bg-eggshell-500 transition-colors"
            @click="handleDecline"
          >
            Continue Without Cookies
          </button>
        </div>
      </template>
    </div>
  </div>
</template>

<script>
import { ref, onMounted } from 'vue';
import { getConsentStatus, acceptCookies, declineCookies } from '@/utils/cookieConsent';

export default {
  name: 'CookieBanner',

  setup() {
    const visible = ref(false);
    const showWarning = ref(false);

    onMounted(() => {
      visible.value = getConsentStatus() === null;
    });

    const handleAccept = () => {
      acceptCookies();
      visible.value = false;
    };

    const handleDecline = () => {
      declineCookies();
      visible.value = false;
    };

    return { visible, showWarning, handleAccept, handleDecline };
  },
};
</script>
