<template>
  <AppLayout>
    <div class="net-worth-dashboard">
      <main class="main-content">
        <router-view></router-view>
      </main>
    </div>
  </AppLayout>
</template>

<script>
import AppLayout from '@/layouts/AppLayout.vue';

export default {
  name: 'NetWorthDashboard',

  components: {
    AppLayout,
  },

  mounted() {
    // Redirect to wealth-summary if at root
    if (this.$route.path === '/net-worth' || this.$route.path === '/net-worth/') {
      this.$router.replace('/net-worth/wealth-summary');
    }
    if (this.$route.path === '/preview/net-worth' || this.$route.path === '/preview/net-worth/') {
      this.$router.replace('/preview/net-worth/wealth-summary');
    }
  },
};
</script>

<style scoped>
.net-worth-dashboard {
  overflow: visible;
}

.main-content {
  min-height: 500px;
  overflow: visible;
}
</style>
