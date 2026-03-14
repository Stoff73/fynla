<template>
  <div class="detail-page">
    <!-- Header -->
    <div class="page-header">
      <button @click="goBack" class="detail-inline-back">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="back-icon">
          <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18" />
        </svg>
        Back to Investments
      </button>
      <h1 class="page-title">Strategy</h1>
      <p class="page-subtitle">Personalised strategies to optimise your portfolio</p>
    </div>

    <!-- Content -->
    <div class="content">
      <InvestmentRecommendationsTracker />
    </div>
  </div>
</template>

<script>
import InvestmentRecommendationsTracker from '@/components/Investment/InvestmentRecommendationsTracker.vue';

export default {
  name: 'StrategyDetail',

  components: {
    InvestmentRecommendationsTracker,
  },

  computed: {
    isPreviewMode() {
      return this.$route.path.startsWith('/preview');
    },
  },

  methods: {
    goBack() {
      const base = this.isPreviewMode ? '/preview' : '';
      this.$router.push(`${base}/net-worth/investments`);
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
