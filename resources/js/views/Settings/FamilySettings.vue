<template>
  <AppLayout>
    <div class="module-gradient py-8">
      <div class="mb-8">
        <h1 class="text-h2 font-display text-horizon-500">Settings</h1>
        <p class="mt-2 text-body-base text-neutral-500">
          Manage household members for shared protection, estate, and goal planning
        </p>
      </div>

      <SettingsTabBar />

      <div class="card">
        <div v-if="loading" class="flex justify-center items-center py-12">
          <div class="text-center">
            <div class="w-10 h-10 border-4 border-horizon-200 border-t-raspberry-500 rounded-full animate-spin mx-auto"></div>
            <p class="mt-4 text-body-base text-neutral-500">Loading profile...</p>
          </div>
        </div>
        <div v-else-if="error" class="rounded-md bg-raspberry-50 p-4">
          <h3 class="text-body-sm font-medium text-raspberry-800">Error loading profile</h3>
          <p class="mt-2 text-body-sm text-raspberry-700">{{ error }}</p>
          <button @click="loadProfile" class="btn-secondary mt-4">Try Again</button>
        </div>
        <FamilyMembers v-else />
      </div>
    </div>
  </AppLayout>
</template>

<script>
import { computed, onMounted } from 'vue';
import { useStore } from 'vuex';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsTabBar from '@/components/Settings/SettingsTabBar.vue';
import FamilyMembers from '@/components/UserProfile/FamilyMembers.vue';

export default {
  name: 'FamilySettings',
  components: { AppLayout, SettingsTabBar, FamilyMembers },
  setup() {
    const store = useStore();
    const loading = computed(() => store.getters['userProfile/loading']);
    const error = computed(() => store.getters['userProfile/error']);
    const loadProfile = () => store.dispatch('userProfile/fetchProfile');
    onMounted(loadProfile);
    return { loading, error, loadProfile };
  },
};
</script>
