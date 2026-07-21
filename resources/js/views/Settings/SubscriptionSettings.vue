<template>
  <AppLayout>
    <div class="module-gradient py-8">
      <div class="mb-8">
        <h1 class="text-h2 font-display text-horizon-500">Settings</h1>
        <p class="mt-2 text-body-base text-neutral-500">
          Manage your plan, billing, invoices, and discount codes
        </p>
      </div>

      <SettingsTabBar />

      <div class="card">
        <SubscriptionManagement />
      </div>
    </div>
  </AppLayout>
</template>

<script>
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsTabBar from '@/components/Settings/SettingsTabBar.vue';
import SubscriptionManagement from '@/components/UserProfile/SubscriptionManagement.vue';

export default {
  name: 'SubscriptionSettings',
  components: { AppLayout, SettingsTabBar, SubscriptionManagement },
};
</script>
