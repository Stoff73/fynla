<template>
  <AppLayout>
    <div class="module-gradient py-8 px-4 sm:px-6">
      <ModuleStatusBar />
      <!-- Was a tab on the old Estate dashboard; its back button still emits switch-tab. -->
      <LifePolicyStrategy @switch-tab="$router.push('/estate')" />
    </div>
  </AppLayout>
</template>

<script>
import AppLayout from '@/layouts/AppLayout.vue';
import ModuleStatusBar from '@/components/Shared/ModuleStatusBar.vue';
import LifePolicyStrategy from '@/components/Estate/LifePolicyStrategy.vue';

export default {
  name: 'LifePolicyView',
  components: { AppLayout, ModuleStatusBar, LifePolicyStrategy },
};
</script>
