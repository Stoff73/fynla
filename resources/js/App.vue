<template>
  <router-view />
  <CookieBanner />
</template>

<script>
import { onMounted } from 'vue';
import { useStore } from 'vuex';
import { removeToken } from '@/services/tokenStorage';
import CookieBanner from '@/components/Shared/CookieBanner.vue';
import { initCookieConsent } from '@/utils/cookieConsent';

export default {
  name: 'App',

  components: { CookieBanner },

  setup() {
    const store = useStore();

    onMounted(async () => {
      // Initialise cookie consent — loads GA if previously accepted
      initCookieConsent();

      // Token restoration from native Preferences happens in app.js BEFORE mount.
      // Here we just fetch the user profile if we have a valid token.
      if (store.getters['auth/isAuthenticated']) {
        try {
          await store.dispatch('auth/fetchUser');
          // Fetch life stage after user is loaded (drives sidebar, dashboard, onboarding)
          store.dispatch('lifeStage/fetchStage').catch(() => {});
          // Hydrate the full tax-config snapshot so every component reads
          // live backend values via taxConfig getters instead of falling
          // back to the hardcoded constants in @/constants/taxConfig.
          store.dispatch('taxConfig/fetchConfig').catch(() => {});
        } catch (error) {
          // Token is invalid, clear it
          store.commit('auth/clearAuth');
          await removeToken();
        }
      }
    });

    return {};
  },
};
</script>
