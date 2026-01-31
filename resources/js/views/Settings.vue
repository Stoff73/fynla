<template>
  <AppLayout>
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
      <div class="mb-8">
        <h1 class="text-h2 font-display text-gray-900">Settings</h1>
        <p class="mt-2 text-body-base text-gray-600">
          Manage your account settings and preferences
        </p>
      </div>

      <!-- Your Information Card -->
      <div class="card mb-6">
        <div class="border-b border-gray-200 pb-4 mb-6">
          <h2 class="text-h4 font-semibold text-gray-900">Your Information</h2>
          <p class="mt-1 text-body-sm text-gray-600">
            Personal details associated with your account
          </p>
        </div>

        <div v-if="user" class="space-y-4">
          <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
              <label class="block text-body-sm font-medium text-gray-700 mb-1">
                Full Name
              </label>
              <p class="text-body-base text-gray-900">{{ user.name }}</p>
            </div>

            <div>
              <label class="block text-body-sm font-medium text-gray-700 mb-1">
                Email Address
              </label>
              <p class="text-body-base text-gray-900">{{ user.email }}</p>
            </div>

            <div>
              <label class="block text-body-sm font-medium text-gray-700 mb-1">
                Date of Birth
              </label>
              <p class="text-body-base text-gray-900">{{ formatDateOfBirth(user.date_of_birth) }}</p>
            </div>

            <div>
              <label class="block text-body-sm font-medium text-gray-700 mb-1">
                Gender
              </label>
              <p class="text-body-base text-gray-900 capitalize">{{ formatGender(user.gender) }}</p>
            </div>

            <div>
              <label class="block text-body-sm font-medium text-gray-700 mb-1">
                Marital Status
              </label>
              <p class="text-body-base text-gray-900 capitalize">{{ formatMaritalStatus(user.marital_status) }}</p>
            </div>

            <div>
              <label class="block text-body-sm font-medium text-gray-700 mb-1">
                Account Created
              </label>
              <p class="text-body-base text-gray-900">{{ formatDate(user.created_at) }}</p>
            </div>
          </div>
        </div>

        <div v-else class="text-center py-8">
          <p class="text-body-base text-gray-500">Loading your information...</p>
        </div>
      </div>

      <!-- Account Actions Card -->
      <div class="card">
        <div class="border-b border-gray-200 pb-4 mb-6">
          <h2 class="text-h4 font-semibold text-gray-900">Account Actions</h2>
          <p class="mt-1 text-body-sm text-gray-600">
            Manage your account security and preferences
          </p>
        </div>

        <div class="space-y-4">
          <div class="flex items-center justify-between py-4 border-b border-gray-200">
            <div>
              <h3 class="text-body-base font-medium text-gray-900">Security Settings</h3>
              <p class="text-body-sm text-gray-600">Manage two-factor authentication, active sessions, and password</p>
            </div>
            <router-link to="/settings/security" class="btn-primary">
              Manage Security
            </router-link>
          </div>

          <div class="flex items-center justify-between py-4 border-b border-gray-200">
            <div>
              <h3 class="text-body-base font-medium text-gray-900">Privacy & Data</h3>
              <p class="text-body-sm text-gray-600">Manage consent preferences and data export requests</p>
            </div>
            <router-link to="/settings/privacy" class="btn-secondary">
              Manage Privacy
            </router-link>
          </div>

          <div class="flex items-center justify-between py-4 border-b border-gray-200">
            <div>
              <h3 class="text-body-base font-medium text-gray-900">Planning Assumptions</h3>
              <p class="text-body-sm text-gray-600">Configure assumptions for pension and investment projections</p>
            </div>
            <router-link to="/settings/assumptions" class="btn-secondary">
              Manage Assumptions
            </router-link>
          </div>

          <div class="flex items-center justify-between py-4 border-b border-gray-200">
            <div>
              <h3 class="text-body-base font-medium text-gray-900">Email Notifications</h3>
              <p class="text-body-sm text-gray-600">Manage your email notification preferences</p>
            </div>
            <button class="btn-secondary" disabled>
              Coming Soon
            </button>
          </div>

          <div class="flex items-center justify-between py-4">
            <div>
              <h3 class="text-body-base font-medium text-error-700">Sign Out</h3>
              <p class="text-body-sm text-gray-600">Sign out of your account on this device</p>
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
import { computed, ref } from 'vue';
import { useStore } from 'vuex';
import { useRouter } from 'vue-router';
import AppLayout from '@/layouts/AppLayout.vue';

export default {
  name: 'Settings',

  components: {
    AppLayout,
  },

  setup() {
    const store = useStore();
    const router = useRouter();
    const loading = ref(false);

    const user = computed(() => store.getters['auth/currentUser']);

    const formatDateOfBirth = (dateString) => {
      if (!dateString) return 'Not set';
      const date = new Date(dateString);
      const year = date.getFullYear();
      const month = String(date.getMonth() + 1).padStart(2, '0');
      const day = String(date.getDate()).padStart(2, '0');
      return `${day}/${month}/${year}`;
    };

    const formatDate = (dateString) => {
      if (!dateString) return 'Not available';
      const date = new Date(dateString);
      return date.toLocaleDateString('en-GB', {
        day: 'numeric',
        month: 'long',
        year: 'numeric',
      });
    };

    const formatGender = (gender) => {
      if (!gender) return 'Not specified';
      if (gender === 'prefer_not_to_say') return 'Prefer not to say';
      return gender;
    };

    const formatMaritalStatus = (status) => {
      if (!status) return 'Not specified';
      if (status === 'civil_partnership') return 'Civil Partnership';
      return status;
    };

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
      user,
      loading,
      formatDateOfBirth,
      formatDate,
      formatGender,
      formatMaritalStatus,
      handleSignOut,
    };
  },
};
</script>
