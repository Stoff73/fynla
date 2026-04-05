<template>
  <router-view />
</template>

<script>
import { onMounted } from 'vue';
import { useStore } from 'vuex';
import { removeToken } from '@/services/tokenStorage';

export default {
  name: 'App',

  setup() {
    const store = useStore();

    onMounted(async () => {
      // Token restoration from native Preferences happens in app.js BEFORE mount.
      // Here we just fetch the user profile if we have a valid token.
      if (store.getters['auth/isAuthenticated']) {
        try {
          await store.dispatch('auth/fetchUser');
          // Fetch life stage after user is loaded (drives sidebar, dashboard, onboarding)
          store.dispatch('lifeStage/fetchStage').catch(() => {});
          // Fetch active tax year so all allowance displays align with the
          // admin-selected year (not the calendar year).
          store.dispatch('taxConfig/fetchActive').catch(() => {});
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
