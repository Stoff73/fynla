<template>
  <AppLayout>
    <div class="module-gradient py-8">
      <ModuleStatusBar />
      <div class="mb-8">
        <h1 class="text-h2 font-display text-horizon-500">Settings</h1>
        <p class="mt-2 text-body-base text-neutral-500">
          Manage your account settings and preferences
        </p>
      </div>

      <SettingsTabBar />

      <!-- Account Actions Card -->
      <div class="card">
        <div class="border-b border-light-gray pb-4 mb-6">
          <h2 class="text-h4 font-semibold text-horizon-500">General</h2>
          <p class="mt-1 text-body-sm text-neutral-500">
            General account settings and preferences
          </p>
        </div>

        <div class="space-y-4">
          <div class="flex items-center justify-between py-4 border-b border-light-gray">
            <div>
              <h3 class="text-body-base font-medium text-horizon-500">Email Notifications</h3>
              <p class="text-body-sm text-neutral-500">Manage your email notification preferences</p>
            </div>
            <button class="btn-secondary" disabled>
              Coming Soon
            </button>
          </div>

          <div class="flex items-center justify-between py-4">
            <div>
              <h3 class="text-body-base font-medium text-raspberry-700">Sign Out</h3>
              <p class="text-body-sm text-neutral-500">Sign out of your account on this device</p>
            </div>
            <button
              @click="handleSignOut"
              :disabled="loading"
              class="btn-danger"
              :class="{ 'opacity-50 cursor-not-allowed': loading }"
            >
              <span v-if="!loading">Sign Out</span>
              <span v-else>Signing Out...</span>
            </button>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script>
import { ref } from 'vue';
import { useStore } from 'vuex';
import { useRouter } from 'vue-router';
import AppLayout from '@/layouts/AppLayout.vue';
import ModuleStatusBar from '@/components/Shared/ModuleStatusBar.vue';
import SettingsTabBar from '@/components/Settings/SettingsTabBar.vue';

export default {
  name: 'Settings',

  components: {
    AppLayout,
    ModuleStatusBar,
    SettingsTabBar,
  },

  setup() {
    const store = useStore();
    const router = useRouter();
    const loading = ref(false);

    const handleSignOut = async () => {
      loading.value = true;
      try {
        await store.dispatch('auth/logout');
        router.push({ name: 'Login' });
      } catch (error) {
        console.error('Sign out error:', error);
        // Force logout even if API call fails
        router.push({ name: 'Login' });
      }
    };

    return {
      loading,
      handleSignOut,
    };
  },
};
</script>
