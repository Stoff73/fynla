<template>
  <div class="detail-page">
    <!-- Header -->
    <div class="page-header">
      <button @click="goBack" class="detail-inline-back">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="back-icon">
          <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18" />
        </svg>
        Back to Investment Detail
      </button>
      <h1 class="page-title">Fees</h1>
      <p class="page-subtitle">Understand the fees impacting your investment returns</p>
    </div>

    <!-- Content -->
    <div class="content">
      <FeeBreakdown />
    </div>
  </div>
</template>

<script>
import FeeBreakdown from '@/components/Investment/FeeBreakdown.vue';

export default {
  name: 'FeesDetail',

  components: {
    FeeBreakdown,
  },

  computed: {
    isPreviewMode() {
      return this.$route.path.startsWith('/preview');
    },
  },

  methods: {
    goBack() {
      const base = this.isPreviewMode ? '/preview' : '';
      this.$router.push(`${base}/net-worth/investment-detail`);
    },
  },
};
</script>

<style scoped>
.detail-page {
  padding: 24px;
  max-width: 1200px;
  margin: 0 auto;
}

.page-header {
  margin-bottom: 32px;
}

.back-icon {
  width: 16px;
  height: 16px;
}

.page-title {
  font-size: 28px;
  font-weight: 700;
  @apply text-horizon-500;
  margin: 0 0 8px 0;
}

.page-subtitle {
  font-size: 16px;
  @apply text-neutral-500;
  margin: 0;
}

.content {
  background: white;
  border-radius: 12px;
  padding: 24px;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
  @apply border border-light-gray;
}
</style>
