<template>
  <AppLayout>
    <div class="module-gradient py-4 sm:py-8">
      <ModuleStatusBar />
      <!-- Header -->
      <div class="mb-8">
        <h1 class="text-h2 font-display text-horizon-500">User Profile</h1>
        <p class="mt-2 text-body-base text-neutral-500">
          Manage your personal information, family, income, assets, and liabilities
        </p>
      </div>

      <!-- Tab Navigation -->
      <div class="bg-white rounded-lg shadow-sm mb-6">
        <div class="border-b border-light-gray">
          <nav class="-mb-px flex overflow-x-auto scrollbar-hide px-3" aria-label="Tabs">
            <button
              v-for="tab in tabs"
              :key="tab.id"
              @click="activeTab = tab.id"
              :class="[
                activeTab === tab.id
                  ? 'border-raspberry-500 text-raspberry-700'
                  : 'border-transparent text-neutral-500 hover:text-horizon-500 hover:border-horizon-300',
                'whitespace-nowrap py-3 px-2 sm:px-3 border-b-2 font-medium text-xs sm:text-sm transition-colors flex-shrink-0',
              ]"
            >
              {{ tab.label }}
            </button>
          </nav>
        </div>

        <!-- Tab Content -->
        <div class="p-6">
          <!-- Loading State -->
          <div v-if="loading" class="flex justify-center items-center py-12">
            <div class="text-center">
              <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-raspberry-500 mx-auto"></div>
              <p class="mt-4 text-body-base text-neutral-500">Loading profile...</p>
            </div>
          </div>

          <!-- Error State -->
          <div v-else-if="error" class="rounded-md bg-raspberry-50 p-4">
            <div class="flex">
              <div class="ml-3">
                <h3 class="text-body-sm font-medium text-raspberry-800">Error loading profile</h3>
                <div class="mt-2 text-body-sm text-raspberry-700">
                  <p>{{ error }}</p>
                </div>
                <div class="mt-4">
                  <button
                    @click="loadProfile"
                    class="btn-secondary"
                  >
                    Try Again
                  </button>
                </div>
              </div>
            </div>
          </div>

          <!-- Tab Content Components -->
          <div v-else>
            <PersonalInformation v-show="activeTab === 'personal'" />
            <HealthInformation v-show="activeTab === 'health'" />
            <FamilyMembers v-show="activeTab === 'family'" />
            <SubscriptionManagement v-show="activeTab === 'subscription'" />
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script>
import { ref, computed, onMounted, watch } from 'vue';
import { useStore } from 'vuex';
import AppLayout from '@/layouts/AppLayout.vue';
import PersonalInformation from '@/components/UserProfile/PersonalInformation.vue';
import HealthInformation from '@/components/UserProfile/HealthInformation.vue';
import FamilyMembers from '@/components/UserProfile/FamilyMembers.vue';
import SubscriptionManagement from '@/components/UserProfile/SubscriptionManagement.vue';
import ModuleStatusBar from '@/components/Shared/ModuleStatusBar.vue';

import logger from '@/utils/logger';
export default {
  name: 'UserProfile',

  components: {
    AppLayout,
    PersonalInformation,
    HealthInformation,
    FamilyMembers,
    SubscriptionManagement,
    ModuleStatusBar,
  },

  setup() {
    const store = useStore();
    const activeTab = ref('personal');

    const loading = computed(() => store.getters['userProfile/loading']);
    const error = computed(() => store.getters['userProfile/error']);

    // Subscription-based tab gating
    const subscriptionData = computed(() => store.state.auth?.subscriptionData);
    const isPreviewMode = computed(() => store.getters['preview/isPreviewMode']);
    const userPlan = computed(() => {
      if (isPreviewMode.value) return 'pro';
      if (!subscriptionData.value) return 'pro'; // Payments disabled = show all
      if (subscriptionData.value.status === 'trialing') return 'pro';
      return subscriptionData.value.plan || 'student';
    });

    // Define all tabs
    const allTabs = [
      { id: 'personal', label: 'Personal Info' },
      { id: 'health', label: 'Health' },
      { id: 'family', label: 'Family', requiredPlan: 'family' },
      { id: 'subscription', label: 'Subscription' },
    ];

    const planOrder = ['student', 'standard', 'family', 'pro'];
    const tabs = computed(() => allTabs.filter(tab => {
      if (!tab.requiredPlan) return true;
      const userIndex = planOrder.indexOf(userPlan.value);
      const requiredIndex = planOrder.indexOf(tab.requiredPlan);
      if (userIndex === -1 || requiredIndex === -1) return true;
      return userIndex >= requiredIndex;
    }));

    const loadProfile = async () => {
      try {
        await store.dispatch('userProfile/fetchProfile');
      } catch (err) {
        logger.error('Failed to load profile:', err);
      }
    };

    onMounted(() => {
      // Load profile for all users (including preview mode)
      // Preview users are real database users and use the same code paths
      loadProfile();

      // Check for section query parameter and set active tab
      const urlParams = new URLSearchParams(window.location.search);
      const section = urlParams.get('section');
      if (section) {
        const validTabIds = allTabs.map(tab => tab.id);
        if (validTabIds.includes(section)) {
          activeTab.value = section;
        }
      }
    });

    // Watch for AI form fill requests targeting family_member
    watch(
      () => store.state.aiFormFill?.pendingFill,
      (fill) => {
        if (fill && fill.entityType === 'family_member') {
          activeTab.value = 'family';
        }
      }
    );

    return {
      activeTab,
      tabs,
      loading,
      error,
      loadProfile,
    };
  },
};
</script>

