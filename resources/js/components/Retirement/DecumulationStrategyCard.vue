<template>
  <div class="decumulation-strategy-card">
    <!-- Back Button -->
    <button
      @click="$emit('back')"
      class="detail-inline-back"
    >
      <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
      </svg>
      Back to Pensions
    </button>

    <!-- Loading State -->
    <div v-if="loading" class="loading-state">
      <div class="w-12 h-12 border-4 border-light-gray border-t-raspberry-500 rounded-full animate-spin mb-4"></div>
      <p>Analysing drawdown strategies...</p>
    </div>

    <!-- Error State -->
    <div v-else-if="error" class="error-state">
      <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="error-icon">
        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z" />
      </svg>
      <p>{{ error }}</p>
      <button class="retry-button" @click="loadData">Try Again</button>
    </div>

    <!-- No Data State -->
    <div v-else-if="!hasData" class="empty-state">
      <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="empty-icon">
        <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18L9 11.25l4.306 4.307a11.95 11.95 0 015.814-5.519l2.74-1.22m0 0l-5.94-2.28m5.94 2.28l-2.28 5.941" />
      </svg>
      <p>Drawdown analysis not available</p>
      <p class="empty-subtitle">Add Defined Contribution pensions and set a retirement profile to see drawdown strategies</p>
    </div>

    <!-- Main Content -->
    <template v-else>
      <!-- Header -->
      <div class="header-section">
        <h3 class="section-title">Drawdown Strategy Analysis</h3>
        <p class="section-subtitle">
          Strategies for turning your pension pot into retirement income
          <template v-if="context">
            over {{ context.years_in_retirement }} years of retirement
          </template>
        </p>
      </div>

      <!-- Context Summary -->
      <div v-if="context" class="context-grid">
        <div class="context-item">
          <span class="context-label">Total Defined Contribution Pension Value</span>
          <span class="context-value">{{ formatCurrency(context.total_dc_value) }}</span>
        </div>
        <div class="context-item">
          <span class="context-label">Years to Retirement</span>
          <span class="context-value">{{ context.years_to_retirement }}</span>
        </div>
        <div class="context-item">
          <span class="context-label">Life Expectancy</span>
          <span class="context-value">{{ context.life_expectancy }}</span>
        </div>
        <div class="context-item">
          <span class="context-label">Retirement Age</span>
          <span class="context-value">{{ context.retirement_age }}</span>
        </div>
      </div>

      <!-- Sustainable Withdrawal Rates -->
      <div v-if="withdrawalRates" class="card section-card">
        <h4 class="card-title">Sustainable Withdrawal Rate</h4>
        <p class="card-description">
          How much you could safely withdraw each year from your pension pot,
          adjusted for inflation. The recommended rate balances income and longevity.
        </p>

        <div v-if="withdrawalRates.recommended_rate" class="recommended-rate">
          <span class="recommended-label">Recommended Rate</span>
          <span class="recommended-value">{{ withdrawalRates.recommended_rate }}%</span>
          <span class="recommended-income">
            {{ formatCurrency(getRecommendedIncome) }} per year
          </span>
        </div>

        <div class="scenarios-grid">
          <div
            v-for="scenario in withdrawalRates.scenarios"
            :key="scenario.withdrawal_rate"
            :class="['scenario-card', {
              'recommended': scenario.withdrawal_rate === withdrawalRates.recommended_rate,
              'unsustainable': !scenario.survives
            }]"
          >
            <div class="scenario-header">
              <span class="scenario-rate">{{ scenario.withdrawal_rate }}%</span>
              <span :class="['scenario-status', scenario.survives ? 'sustainable' : 'depleted']">
                {{ scenario.survives ? 'Sustainable' : 'Depleted' }}
              </span>
            </div>
            <div class="scenario-details">
              <div class="scenario-detail">
                <span class="detail-label">Annual Income</span>
                <span class="detail-value">{{ formatCurrency(scenario.initial_annual_income) }}</span>
              </div>
              <div v-if="scenario.survives" class="scenario-detail">
                <span class="detail-label">Remaining at End</span>
                <span class="detail-value">{{ formatCurrency(scenario.final_balance) }}</span>
              </div>
              <div v-else class="scenario-detail">
                <span class="detail-label">Lasted</span>
                <span class="detail-value">{{ scenario.years_survived }} years</span>
              </div>
            </div>
            <p class="scenario-recommendation">{{ scenario.recommendation }}</p>
          </div>
        </div>
      </div>

      <!-- Annuity vs Drawdown Comparison -->
      <div v-if="annuityComparison" class="card section-card">
        <h4 class="card-title">Annuity vs Flexible Drawdown</h4>
        <p class="card-description">
          Compare guaranteed income from an annuity with the flexibility of drawdown.
        </p>

        <div class="comparison-grid">
          <!-- Annuity Option -->
          <div class="comparison-card annuity">
            <h5 class="comparison-title">Annuity</h5>
            <div class="comparison-income">
              <span class="income-label">Estimated Annual Income</span>
              <span class="income-value">{{ formatCurrency(annuityComparison.annuity.annual_income) }}</span>
            </div>
            <div class="comparison-badges">
              <span class="badge badge-positive">Guaranteed for Life</span>
              <span class="badge badge-neutral">No Investment Risk</span>
            </div>
            <div class="pros-cons">
              <div class="pros">
                <h6>Advantages</h6>
                <ul>
                  <li v-for="pro in annuityComparison.annuity.pros" :key="pro">{{ pro }}</li>
                </ul>
              </div>
              <div class="cons">
                <h6>Considerations</h6>
                <ul>
                  <li v-for="con in annuityComparison.annuity.cons" :key="con">{{ con }}</li>
                </ul>
              </div>
            </div>
          </div>

          <!-- Drawdown Option -->
          <div class="comparison-card drawdown">
            <h5 class="comparison-title">Flexible Drawdown</h5>
            <div class="comparison-income">
              <span class="income-label">Estimated Annual Income (4% rate)</span>
              <span class="income-value">{{ formatCurrency(annuityComparison.drawdown.annual_income) }}</span>
            </div>
            <div class="comparison-badges">
              <span class="badge badge-violet">Flexible Access</span>
              <span class="badge badge-neutral">Death Benefits</span>
            </div>
            <div class="pros-cons">
              <div class="pros">
                <h6>Advantages</h6>
                <ul>
                  <li v-for="pro in annuityComparison.drawdown.pros" :key="pro">{{ pro }}</li>
                </ul>
              </div>
              <div class="cons">
                <h6>Considerations</h6>
                <ul>
                  <li v-for="con in annuityComparison.drawdown.cons" :key="con">{{ con }}</li>
                </ul>
              </div>
            </div>
          </div>
        </div>

        <div class="recommendation-box">
          <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="rec-icon">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 18v-5.25m0 0a6.01 6.01 0 001.5-.189m-1.5.189a6.01 6.01 0 01-1.5-.189m3.75 7.478a12.06 12.06 0 01-4.5 0m3.75 2.383a14.406 14.406 0 01-3 0M14.25 18v-.192c0-.983.658-1.823 1.508-2.316a7.5 7.5 0 10-7.517 0c.85.493 1.509 1.333 1.509 2.316V18" />
          </svg>
          <p>{{ annuityComparison.recommendation }}</p>
        </div>
      </div>

      <!-- Tax-Free Lump Sum (Pension Commencement Lump Sum) -->
      <div v-if="pclsData" class="card section-card">
        <h4 class="card-title">Tax-Free Lump Sum</h4>
        <p class="card-description">
          You can take up to 25% of your pension as a tax-free lump sum
          (Pension Commencement Lump Sum).
        </p>

        <div class="pcls-grid">
          <div class="pcls-item">
            <span class="pcls-label">Tax-Free Lump Sum</span>
            <span class="pcls-value highlight">{{ formatCurrency(pclsData.pcls_amount) }}</span>
          </div>
          <div class="pcls-item">
            <span class="pcls-label">Remaining Pension Pot</span>
            <span class="pcls-value">{{ formatCurrency(pclsData.remaining_pot) }}</span>
          </div>
          <div class="pcls-item">
            <span class="pcls-label">Estimated Tax Saving</span>
            <span class="pcls-value positive">{{ formatCurrency(pclsData.tax_saving) }}</span>
          </div>
          <div class="pcls-item">
            <span class="pcls-label">Annual Income from Remaining</span>
            <span class="pcls-value">{{ formatCurrency(pclsData.estimated_annual_income) }}</span>
          </div>
        </div>

        <div class="pcls-options">
          <h5>Options for Your Lump Sum</h5>
          <ul>
            <li v-for="option in pclsData.options" :key="option">{{ option }}</li>
          </ul>
          <p class="pcls-recommendation">{{ pclsData.recommendation }}</p>
        </div>
      </div>

      <!-- Income Phasing -->
      <div v-if="incomePhasing" class="card section-card">
        <h4 class="card-title">Income Phasing Strategy</h4>
        <p class="card-description">
          A phased approach to drawing income can help you manage tax efficiently across retirement.
        </p>

        <div class="phasing-timeline">
          <div
            v-for="(phase, index) in incomePhasing.phasing_strategy"
            :key="index"
            class="phase-card"
          >
            <div class="phase-header">
              <span class="phase-number">Phase {{ index + 1 }}</span>
              <span class="phase-age">Ages {{ phase.age_range }}</span>
            </div>
            <h5 class="phase-title">{{ phase.phase }}</h5>
            <div class="phase-sources">
              <span
                v-for="source in phase.income_sources"
                :key="source"
                class="source-badge"
              >
                {{ source }}
              </span>
            </div>
            <p class="phase-strategy">{{ phase.strategy }}</p>
          </div>
        </div>

        <div v-if="incomePhasing.tax_efficiency_tips" class="tips-section">
          <h5 class="tips-title">Tax Efficiency Tips</h5>
          <ul class="tips-list">
            <li v-for="tip in incomePhasing.tax_efficiency_tips" :key="tip">{{ tip }}</li>
          </ul>
        </div>
      </div>
    </template>
  </div>
</template>

<script>
import { mapGetters, mapActions } from 'vuex';
import { currencyMixin } from '@/mixins/currencyMixin';
import { previewModeMixin } from '@/mixins/previewModeMixin';

export default {
  name: 'DecumulationStrategyCard',

  mixins: [currencyMixin, previewModeMixin],

  emits: ['back'],

  data() {
    return {
      error: null,
    };
  },

  computed: {
    ...mapGetters('retirement', [
      'decumulationAnalysis',
      'decumulationLoading',
      'hasDecumulationData',
      'withdrawalRates',
      'annuityVsDrawdown',
      'pclsStrategy',
      'incomePhasing',
    ]),

    loading() {
      return this.decumulationLoading;
    },

    hasData() {
      return this.hasDecumulationData;
    },

    context() {
      return this.decumulationAnalysis?.context || null;
    },

    annuityComparison() {
      return this.annuityVsDrawdown;
    },

    pclsData() {
      return this.pclsStrategy;
    },

    getRecommendedIncome() {
      if (!this.withdrawalRates?.scenarios || !this.withdrawalRates?.recommended_rate) return 0;
      const recommended = this.withdrawalRates.scenarios.find(
        s => s.withdrawal_rate === this.withdrawalRates.recommended_rate
      );
      return recommended?.initial_annual_income || 0;
    },
  },

  mounted() {
    this.loadData();
  },

  methods: {
    ...mapActions('retirement', ['fetchDecumulationAnalysis']),

    async loadData() {
      this.error = null;
      try {
        await this.fetchDecumulationAnalysis();
      } catch (e) {
        this.error = e.response?.data?.message || 'Failed to load drawdown analysis';
      }
    },
  },
};
</script>

<style scoped>
.decumulation-strategy-card {
  @apply space-y-6;
}

.loading-state,
.error-state,
.empty-state {
  @apply flex flex-col items-center justify-center py-12 text-center;
}

.loading-state p,
.empty-state p {
  @apply text-horizon-500 text-sm;
}

.empty-subtitle {
  @apply text-neutral-500 text-xs mt-1;
}

.empty-icon {
  @apply w-12 h-12 text-neutral-400 mb-3;
}

.error-state p {
  @apply text-raspberry-600 text-sm mb-3;
}

.error-icon {
  @apply w-12 h-12 text-raspberry-400 mb-3;
}

.retry-button {
  @apply text-sm text-raspberry-600 hover:text-raspberry-700 underline;
}

.header-section {
  @apply mb-2;
}

.section-title {
  @apply text-lg font-bold text-horizon-500;
}

.section-subtitle {
  @apply text-sm text-neutral-500 mt-1;
}

.context-grid {
  @apply grid grid-cols-2 md:grid-cols-4 gap-3 bg-savannah-100 rounded-lg p-4;
}

.context-item {
  @apply flex flex-col;
}

.context-label {
  @apply text-xs text-neutral-500;
}

.context-value {
  @apply text-sm font-bold text-horizon-500 mt-0.5;
}

.section-card {
  @apply bg-white rounded-lg border border-light-gray p-5;
}

.card-title {
  @apply text-base font-bold text-horizon-500 mb-1;
}

.card-description {
  @apply text-sm text-neutral-500 mb-4;
}

/* Withdrawal Rates */
.recommended-rate {
  @apply flex flex-wrap items-center gap-3 bg-spring-50 border border-spring-200 rounded-lg p-4 mb-4;
}

.recommended-label {
  @apply text-sm font-semibold text-horizon-500;
}

.recommended-value {
  @apply text-xl font-bold text-spring-600;
}

.recommended-income {
  @apply text-sm text-neutral-500;
}

.scenarios-grid {
  @apply grid grid-cols-1 md:grid-cols-3 gap-3;
}

.scenario-card {
  @apply rounded-lg border border-light-gray p-4 transition-all;
}

.scenario-card.recommended {
  @apply border-spring-300 bg-spring-50;
}

.scenario-card.unsustainable {
  @apply border-raspberry-200 bg-raspberry-50;
}

.scenario-header {
  @apply flex justify-between items-center mb-3;
}

.scenario-rate {
  @apply text-lg font-bold text-horizon-500;
}

.scenario-status {
  @apply text-xs font-semibold px-2 py-0.5 rounded-full;
}

.scenario-status.sustainable {
  @apply bg-spring-100 text-spring-700;
}

.scenario-status.depleted {
  @apply bg-raspberry-100 text-raspberry-700;
}

.scenario-details {
  @apply space-y-2 mb-3;
}

.scenario-detail {
  @apply flex justify-between text-sm;
}

.detail-label {
  @apply text-neutral-500;
}

.detail-value {
  @apply font-semibold text-horizon-500;
}

.scenario-recommendation {
  @apply text-xs text-neutral-500 italic;
}

/* Comparison */
.comparison-grid {
  @apply grid grid-cols-1 md:grid-cols-2 gap-4 mb-4;
}

.comparison-card {
  @apply rounded-lg border border-light-gray p-4;
}

.comparison-card.annuity {
  @apply border-spring-200;
}

.comparison-card.drawdown {
  @apply border-violet-200;
}

.comparison-title {
  @apply text-sm font-bold text-horizon-500 mb-3;
}

.comparison-income {
  @apply mb-3;
}

.income-label {
  @apply block text-xs text-neutral-500;
}

.income-value {
  @apply text-lg font-bold text-horizon-500;
}

.comparison-badges {
  @apply flex flex-wrap gap-2 mb-3;
}

.badge {
  @apply text-xs px-2 py-0.5 rounded-full font-medium;
}

.badge-positive {
  @apply bg-spring-100 text-spring-700;
}

.badge-violet {
  @apply bg-violet-100 text-violet-700;
}

.badge-neutral {
  @apply bg-savannah-100 text-horizon-500;
}

.pros-cons {
  @apply space-y-3;
}

.pros h6,
.cons h6 {
  @apply text-xs font-semibold text-horizon-500 mb-1;
}

.pros ul,
.cons ul {
  @apply space-y-1;
}

.pros li {
  @apply text-xs text-spring-700 pl-3 relative;
}

.pros li::before {
  content: '+';
  @apply absolute left-0 font-bold;
}

.cons li {
  @apply text-xs text-neutral-500 pl-3 relative;
}

.cons li::before {
  content: '-';
  @apply absolute left-0;
}

.recommendation-box {
  @apply flex items-start gap-3 bg-violet-50 border border-violet-200 rounded-lg p-4;
}

.rec-icon {
  @apply w-5 h-5 text-violet-500 flex-shrink-0 mt-0.5;
}

.recommendation-box p {
  @apply text-sm text-horizon-500;
}

/* PCLS */
.pcls-grid {
  @apply grid grid-cols-2 md:grid-cols-4 gap-3 mb-4;
}

.pcls-item {
  @apply flex flex-col bg-savannah-100 rounded-lg p-3;
}

.pcls-label {
  @apply text-xs text-neutral-500;
}

.pcls-value {
  @apply text-sm font-bold text-horizon-500 mt-1;
}

.pcls-value.highlight {
  @apply text-spring-600;
}

.pcls-value.positive {
  @apply text-spring-600;
}

.pcls-options h5 {
  @apply text-sm font-semibold text-horizon-500 mb-2;
}

.pcls-options ul {
  @apply space-y-1 mb-3;
}

.pcls-options li {
  @apply text-sm text-neutral-500 pl-4 relative;
}

.pcls-options li::before {
  content: '\2022';
  @apply absolute left-1 text-horizon-300;
}

.pcls-recommendation {
  @apply text-sm text-violet-600 italic;
}

/* Phasing */
.phasing-timeline {
  @apply space-y-3 mb-4;
}

.phase-card {
  @apply border border-light-gray rounded-lg p-4 relative;
}

.phase-header {
  @apply flex justify-between items-center mb-2;
}

.phase-number {
  @apply text-xs font-semibold text-raspberry-500 uppercase tracking-wider;
}

.phase-age {
  @apply text-xs font-medium text-horizon-500 bg-savannah-100 px-2 py-0.5 rounded;
}

.phase-title {
  @apply text-sm font-bold text-horizon-500 mb-2;
}

.phase-sources {
  @apply flex flex-wrap gap-1.5 mb-2;
}

.source-badge {
  @apply text-xs bg-violet-50 text-violet-700 px-2 py-0.5 rounded;
}

.phase-strategy {
  @apply text-xs text-neutral-500;
}

.tips-section {
  @apply bg-savannah-100 rounded-lg p-4;
}

.tips-title {
  @apply text-sm font-semibold text-horizon-500 mb-2;
}

.tips-list {
  @apply space-y-1;
}

.tips-list li {
  @apply text-sm text-neutral-500 pl-4 relative;
}

.tips-list li::before {
  content: '\2713';
  @apply absolute left-0 text-spring-500;
}
</style>
