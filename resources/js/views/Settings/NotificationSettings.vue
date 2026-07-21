<template>
  <AppLayout>
    <div class="module-gradient py-8">
      <div class="mb-8">
        <h1 class="text-h2 font-display text-horizon-500">Settings</h1>
        <p class="mt-2 text-body-base text-neutral-500">
          Manage your email notification preferences
        </p>
      </div>

      <SettingsTabBar />

      <div class="card">
        <NotificationPreferences />
      </div>
    </div>
  </AppLayout>
</template>

<script>
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsTabBar from '@/components/Settings/SettingsTabBar.vue';
import NotificationPreferences from '@/components/UserProfile/NotificationPreferences.vue';

export default {
  name: 'NotificationSettings',
  components: {
    AppLayout,
    SettingsTabBar,
    NotificationPreferences,
  },
};
</script>
