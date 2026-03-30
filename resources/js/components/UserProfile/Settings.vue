<template>
  <div class="space-y-6">
    <!-- Spouse Data Sharing -->
    <SpouseDataSharing />

    <!-- Account Settings Section -->
    <div class="bg-white rounded-lg border border-light-gray overflow-hidden">
      <div class="px-6 py-4 border-b border-light-gray">
        <h3 class="text-h4 font-semibold text-horizon-500">Account Settings</h3>
        <p class="mt-1 text-body-sm text-neutral-500">
          Manage your account preferences and security
        </p>
      </div>

      <div class="px-6 py-4 space-y-4">
        <!-- User Information Display -->
        <div class="flex items-center space-x-4 pb-4 border-b border-light-gray">
          <div class="flex-shrink-0">
            <div class="h-12 w-12 rounded-full bg-raspberry-100 flex items-center justify-center">
              <span class="text-h4 font-semibold text-raspberry-700">
                {{ userInitials }}
              </span>
            </div>
          </div>
          <div>
            <h4 class="text-body-base font-semibold text-horizon-500">{{ currentUser?.name }}</h4>
            <p class="text-body-sm text-neutral-500">{{ currentUser?.email }}</p>
          </div>
        </div>

        <!-- Session Information -->
        <div class="pt-4">
          <h4 class="text-body-sm font-semibold text-horizon-500 mb-3">Session Information</h4>
          <div class="bg-savannah-100 rounded-md px-4 py-3">
            <div class="grid grid-cols-1 gap-2 text-body-sm">
              <div class="flex justify-between">
                <span class="text-neutral-500">Account Type:</span>
                <span class="font-medium text-horizon-500">{{ accountType }}</span>
              </div>
              <div class="flex justify-between">
                <span class="text-neutral-500">Member Since:</span>
                <span class="font-medium text-horizon-500">{{ formatDate(currentUser?.created_at) }}</span>
              </div>
              <div class="flex justify-between">
                <span class="text-neutral-500">Status:</span>
                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-success-100 text-success-800">
                  Active
                </span>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Security Section -->
    <div class="bg-white rounded-lg border border-light-gray overflow-hidden">
      <div class="px-6 py-4 border-b border-light-gray">
        <h3 class="text-h4 font-semibold text-horizon-500">Security</h3>
        <p class="mt-1 text-body-sm text-neutral-500">
          Manage your session and account access
        </p>
      </div>

      <div class="px-6 py-4 space-y-4">
        <!-- Logout Button -->
        <div class="flex items-center justify-between py-3">
          <div>
            <h4 class="text-body-base font-medium text-horizon-500">Sign out of your account</h4>
            <p class="text-body-sm text-neutral-500 mt-1">
              This will end your current session and return you to the login page
            </p>
          </div>
          <button
            @click="handleLogout"
            :disabled="loggingOut"
            class="btn-secondary"
            :class="{ 'opacity-50 cursor-not-allowed': loggingOut }"
          >
            <span v-if="!loggingOut">Logout</span>
            <span v-else>Logging out...</span>
          </button>
        </div>
      </div>
    </div>

    <!-- Danger Zone -->
    <div class="bg-white rounded-lg border border-raspberry-200 overflow-hidden">
      <div class="px-6 py-4 border-b border-raspberry-200 bg-raspberry-50">
        <h3 class="text-h4 font-semibold text-raspberry-900">Danger Zone</h3>
        <p class="mt-1 text-body-sm text-raspberry-700">
          Irreversible actions - proceed with caution
        </p>
      </div>

      <div class="px-6 py-4">
        <div class="flex items-center justify-between py-3">
          <div>
            <h4 class="text-body-base font-medium text-horizon-500">Clear all data</h4>
            <p class="text-body-sm text-neutral-500 mt-1">
              This will remove all your financial data but keep your account active
            </p>
          </div>
          <button
            class="px-4 py-2 border border-raspberry-600 text-raspberry-700 rounded-button text-body-sm font-medium hover:bg-raspberry-50 transition-colors"
            disabled
          >
            Clear Data
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import { ref, computed } from 'vue';
import { useStore } from 'vuex';
import { useRouter } from 'vue-router';
import SpouseDataSharing from './SpouseDataSharing.vue';

import logger from '@/utils/logger';
export default {
  name: 'Settings',

  components: {
    SpouseDataSharing,
  },

  setup() {
    const store = useStore();
    const router = useRouter();
    const loggingOut = ref(false);

    const currentUser = computed(() => store.getters['auth/currentUser']);

    const accountType = computed(() => {
      const role = store.getters['auth/role'];
      if (role === 'admin') return 'Administrator';
      if (role === 'support') return 'Support';
      return 'User';
    });

    const userInitials = computed(() => {
      if (!currentUser.value?.name) return 'U';
      const names = currentUser.value.name.split(' ');
      return names.length > 1
        ? names[0][0] + names[names.length - 1][0]
        : names[0][0];
    });

    const formatDate = (dateString) => {
      if (!dateString) return 'N/A';
      const date = new Date(dateString);
      return date.toLocaleDateString('en-GB', {
        day: 'numeric',
        month: 'long',
        year: 'numeric',
      });
    };

    const handleLogout = async () => {
      if (loggingOut.value) return;

      if (confirm('Are you sure you want to logout?')) {
        loggingOut.value = true;
        try {
          await store.dispatch('auth/logout');
          router.push({ name: 'Login' });
        } catch (error) {
          logger.error('Logout failed:', error);
          // Still redirect to login even if API call fails
          router.push({ name: 'Login' });
        } finally {
          loggingOut.value = false;
        }
      }
    };

    return {
      currentUser,
      accountType,
      userInitials,
      loggingOut,
      formatDate,
      handleLogout,
    };
  },
};
</script>
