<template>
  <AppLayout>
    <div class="trust-detail-view">
      <!-- Back Button -->
      <button @click="goBack" class="back-btn">
        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
        </svg>
        Back to Trusts
      </button>

      <!-- Loading State -->
      <div v-if="loading" class="loading-state">
        <div class="w-12 h-12 border-[3px] border-horizon-200 border-t-violet-600 rounded-full animate-spin mb-4"></div>
        <p>Loading trust details...</p>
      </div>

      <!-- Error State -->
      <div v-else-if="error" class="error-state">
        <p>{{ error }}</p>
        <button @click="loadTrust" class="retry-btn">
          Try Again
        </button>
      </div>

      <!-- Trust Details -->
      <div v-else-if="trust" class="trust-content">
        <!-- Header Card -->
        <div class="header-card">
          <div class="header-top">
            <div class="header-info">
              <div class="header-badges">
                <span :class="['status-badge', trust.is_active ? 'active' : 'inactive']">
                  {{ trust.is_active ? 'Active' : 'Inactive' }}
                </span>
                <span v-if="trust.is_relevant_property_trust" class="rpt-badge">
                  RPT
                </span>
              </div>
              <h1 class="trust-name">{{ trust.trust_name }}</h1>
              <p class="trust-type">{{ formatTrustType(trust.trust_type, trust.other_type_description) }}</p>
              <p v-if="trust.trust_type === 'other' && trust.country" class="trust-country">{{ trust.country }}</p>
            </div>
            <div class="header-actions">
              <button @click="editTrust" class="edit-btn">
                Edit
              </button>
            </div>
          </div>

          <!-- Key Metrics -->
          <div class="metrics-grid">
            <div class="metric-card primary">
              <p class="metric-label">Current Value</p>
              <p class="metric-value">{{ formatCurrency(trust.current_value) }}</p>
            </div>
            <div class="metric-card">
              <p class="metric-label">Initial Value</p>
              <p class="metric-value">{{ formatCurrency(trust.initial_value) }}</p>
            </div>
            <div class="metric-card">
              <p class="metric-label">Growth</p>
              <p :class="['metric-value', growthClass]">
                {{ formatCurrency(growth) }}
                <span class="metric-percent">({{ growthPercentage }}%)</span>
              </p>
            </div>
            <div class="metric-card">
              <p class="metric-label">Creation Date</p>
              <p class="metric-value date">{{ formatDate(trust.trust_creation_date) }}</p>
            </div>
          </div>
        </div>

        <!-- Details Grid -->
        <div class="details-grid">
          <!-- Parties Card -->
          <div class="detail-card">
            <h2 class="card-title">Parties</h2>
            <dl class="detail-list">
              <div v-if="trust.beneficiaries" class="detail-row">
                <dt class="detail-label">Beneficiaries</dt>
                <dd class="detail-value">{{ trust.beneficiaries }}</dd>
              </div>
              <div v-if="trust.trustees" class="detail-row">
                <dt class="detail-label">Trustees</dt>
                <dd class="detail-value">{{ trust.trustees }}</dd>
              </div>
              <div v-if="!trust.beneficiaries && !trust.trustees" class="empty-detail">
                <p>No party information recorded</p>
              </div>
            </dl>
          </div>

          <!-- Tax Treatment Card -->
          <div class="detail-card">
            <h2 class="card-title">Tax Treatment</h2>
            <dl class="detail-list">
              <div class="detail-row">
                <dt class="detail-label">Income Tax</dt>
                <dd class="detail-value">{{ getIncomeTaxDisplay(trust.trust_type) }}</dd>
              </div>
              <div class="detail-row">
                <dt class="detail-label">Capital Gains Tax</dt>
                <dd class="detail-value">{{ getCGTDisplay(trust.trust_type) }}</dd>
              </div>
              <div class="detail-row">
                <dt class="detail-label">Inheritance Tax Treatment</dt>
                <dd class="detail-value">{{ getIHTDisplay(trust.trust_type) }}</dd>
              </div>
            </dl>
            <p class="tax-note">{{ getTaxNote(trust.trust_type) }}</p>
          </div>
        </div>

        <!-- Purpose Card -->
        <div v-if="trust.purpose" class="detail-card full-width">
          <h2 class="card-title">Purpose</h2>
          <p class="purpose-text">{{ trust.purpose }}</p>
        </div>

        <!-- Notes Card -->
        <div v-if="trust.notes" class="detail-card full-width">
          <h2 class="card-title">Notes</h2>
          <p class="notes-text">{{ trust.notes }}</p>
        </div>

        <!-- RPT Tax Information -->
        <div v-if="trust.is_relevant_property_trust" class="rpt-info-card">
          <div class="rpt-header">
            <svg class="rpt-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <h2 class="rpt-title">Relevant Property Trust - Tax Implications</h2>
          </div>
          <ul class="rpt-list">
            <li>Subject to 10-year periodic Inheritance Tax charges (maximum 6%)</li>
            <li>Exit charges may apply when assets leave the trust</li>
            <li>Trust income taxed at 45% (39.35% for dividends)</li>
            <li>Capital Gains Tax at 24% with £1,500 annual exemption</li>
          </ul>
          <p v-if="nextPeriodicChargeDate" class="next-charge">
            <strong>Next 10-year anniversary:</strong> {{ nextPeriodicChargeDate }}
          </p>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script>
import { mapState, mapActions, mapGetters } from 'vuex';
import api from '@/services/api';
import AppLayout from '@/layouts/AppLayout.vue';
import { currencyMixin } from '@/mixins/currencyMixin';

import logger from '@/utils/logger';
export default {
  name: 'TrustDetailView',
  mixins: [currencyMixin],

  components: {
    AppLayout,
  },

  data() {
    return {
      trust: null,
      loading: true,
      error: null,
    };
  },

  computed: {
    ...mapState('preview', ['isPreviewMode']),
    ...mapGetters('trusts', { storeTrusts: 'trusts' }),

    trustId() {
      return this.$route.params.id;
    },

    growth() {
      if (!this.trust) return 0;
      return parseFloat(this.trust.current_value || 0) - parseFloat(this.trust.initial_value || 0);
    },

    growthPercentage() {
      if (!this.trust || !this.trust.initial_value) return '0.00';
      const initial = parseFloat(this.trust.initial_value);
      if (initial === 0) return '0.00';
      return ((this.growth / initial) * 100).toFixed(2);
    },

    growthClass() {
      if (this.growth > 0) return 'positive';
      if (this.growth < 0) return 'negative';
      return '';
    },

    nextPeriodicChargeDate() {
      if (!this.trust || !this.trust.trust_creation_date) return null;
      const creationDate = new Date(this.trust.trust_creation_date);
      const now = new Date();

      let years = 10;
      while (true) {
        const anniversary = new Date(creationDate);
        anniversary.setFullYear(creationDate.getFullYear() + years);
        if (anniversary > now) {
          return anniversary.toLocaleDateString('en-GB', {
            day: 'numeric',
            month: 'long',
            year: 'numeric',
          });
        }
        years += 10;
      }
    },
  },

  async mounted() {
    await this.loadTrust();
  },

  methods: {
    ...mapActions('trusts', ['fetchTrusts']),

    async loadTrust() {
      this.loading = true;
      this.error = null;

      try {
        const stored = this.storeTrusts?.find(
          t => t.id === parseInt(this.trustId)
        );

        if (stored) {
          this.trust = stored;
        } else {
          const response = await api.get('/estate/trusts');
          if (response.data.success) {
            const trusts = response.data.data;
            this.trust = trusts.find(t => t.id === parseInt(this.trustId));
            if (!this.trust) {
              this.error = 'Trust not found';
            }
          }
        }
      } catch (err) {
        logger.error('Failed to load trust:', err);
        this.error = err.message || 'Failed to load trust details';
      } finally {
        this.loading = false;
      }
    },

    goBack() {
      this.$router.push('/trusts');
    },

    editTrust() {
      this.$router.push({ path: '/trusts', query: { edit: this.trustId } });
    },

    formatDate(dateString) {
      if (!dateString) return '-';
      return new Date(dateString).toLocaleDateString('en-GB', {
        day: 'numeric',
        month: 'long',
        year: 'numeric',
      });
    },

    formatTrustType(type, otherDescription = null) {
      const types = {
        bare: 'Bare Trust',
        interest_in_possession: 'Interest in Possession',
        discretionary: 'Discretionary Trust',
        accumulation_maintenance: 'Accumulation & Maintenance',
        life_insurance: 'Life Insurance Trust',
        discounted_gift: 'Discounted Gift Trust',
        loan: 'Loan Trust',
        mixed: 'Mixed Trust',
        settlor_interested: 'Settlor-Interested Trust',
        other: otherDescription || 'Other Trust',
      };
      return types[type] || type;
    },

    getIncomeTaxDisplay(type) {
      const rates = {
        bare: 'Beneficiary rates',
        interest_in_possession: '20% / 8.75% (dividends)',
        discretionary: '45% / 39.35% (dividends)',
        accumulation_maintenance: '45% / 39.35% (dividends)',
        life_insurance: 'N/A',
        discounted_gift: 'Settlor rates',
        loan: '45% / 39.35% (dividends)',
        mixed: 'Mixed rates',
        settlor_interested: 'Settlor rates',
        other: 'Varies by jurisdiction',
      };
      return rates[type] || 'N/A';
    },

    getCGTDisplay(type) {
      const rates = {
        bare: 'Beneficiary rates',
        interest_in_possession: '24% (£1,500 exempt)',
        discretionary: '24% (£1,500 exempt)',
        accumulation_maintenance: '24% (£1,500 exempt)',
        life_insurance: 'N/A',
        discounted_gift: '24% (£1,500 exempt)',
        loan: '24% (£1,500 exempt)',
        mixed: '24% (£1,500 exempt)',
        settlor_interested: 'Settlor rates',
        other: 'Varies by jurisdiction',
      };
      return rates[type] || 'N/A';
    },

    getIHTDisplay(type) {
      const treatments = {
        bare: 'Potentially Exempt Transfer (exempt after 7 years)',
        interest_in_possession: 'May be in life tenant\'s estate',
        discretionary: '10-year and exit charges',
        accumulation_maintenance: '10-year and exit charges',
        life_insurance: 'Outside estate',
        discounted_gift: 'Partial Potentially Exempt Transfer (discounted value)',
        loan: 'Loan in estate, growth outside',
        mixed: 'Depends on trust structure',
        settlor_interested: 'In settlor\'s estate',
        other: 'Varies by jurisdiction',
      };
      return treatments[type] || 'N/A';
    },

    getTaxNote(type) {
      const notes = {
        bare: 'Income and gains taxed using beneficiary\'s personal allowances',
        interest_in_possession: 'Lower rates: 20% on other income, 8.75% on dividends',
        discretionary: 'Higher rates apply. £500 tax-free if income below threshold',
        accumulation_maintenance: 'Same rates as discretionary trusts',
        life_insurance: 'No regular income tax - policy proceeds on death',
        discounted_gift: 'Settlor taxed on retained income using their personal rates',
        loan: 'Growth taxed at trust rates; original loan amount not taxable',
        mixed: 'Complex tax treatment - depends on trust structure',
        settlor_interested: 'All income and gains taxed on settlor',
        other: 'Tax treatment depends on the trust jurisdiction and local laws. Consult a specialist adviser.',
      };
      return notes[type] || '';
    },
  },
};
</script>

<style scoped>
.trust-detail-view {
  padding: 8px 0;
  max-width: 1200px;
  margin: 0 auto;
}

/* Back Button */
.back-btn {
  display: flex;
  align-items: center;
  @apply text-neutral-500;
  font-size: 14px;
  font-weight: 500;
  margin-bottom: 24px;
  transition: color 0.2s;
}

.back-btn:hover {
  @apply text-horizon-500;
}

/* Loading State */
.loading-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 64px;
  @apply text-neutral-500;
}

/* Error State */
.error-state {
  @apply bg-red-50 border border-red-200 rounded-xl;
  padding: 24px;
  text-align: center;
  @apply text-red-800;
}

.retry-btn {
  margin-top: 16px;
  padding: 8px 16px;
  @apply bg-red-800 text-white;
  border-radius: 8px;
  font-weight: 500;
  transition: background 0.2s;
}

.retry-btn:hover {
  @apply bg-red-900;
}

/* Trust Content */
.trust-content {
  display: flex;
  flex-direction: column;
  gap: 24px;
}

/* Header Card */
.header-card {
  @apply bg-white rounded-xl shadow-sm border border-light-gray;
  padding: 24px;
}

.header-top {
  display: flex;
  flex-direction: column;
  gap: 16px;
  margin-bottom: 24px;
}

@media (min-width: 640px) {
  .header-top {
    flex-direction: row;
    justify-content: space-between;
    align-items: flex-start;
  }
}

.header-info {
  flex: 1;
}

.header-badges {
  display: flex;
  gap: 8px;
  margin-bottom: 12px;
}

.status-badge {
  padding: 4px 12px;
  font-size: 12px;
  font-weight: 500;
  border-radius: 9999px;
}

.status-badge.active {
  @apply bg-green-100 text-green-800;
}

.status-badge.inactive {
  @apply bg-savannah-100 text-neutral-500;
}

.rpt-badge {
  padding: 4px 12px;
  font-size: 12px;
  font-weight: 600;
  @apply bg-blue-50 text-blue-700;
  border-radius: 9999px;
}

.trust-name {
  font-size: 28px;
  font-weight: 700;
  @apply text-horizon-500;
  margin: 0 0 8px 0;
}

.trust-type {
  font-size: 16px;
  @apply text-neutral-500;
  margin: 0;
}

.trust-country {
  font-size: 14px;
  @apply text-horizon-400;
  margin: 4px 0 0 0;
}

.header-actions {
  display: flex;
  gap: 8px;
}

.edit-btn {
  padding: 10px 20px;
  @apply bg-violet-600 text-white;
  font-weight: 500;
  border-radius: 8px;
  transition: background 0.2s;
}

.edit-btn:hover {
  @apply bg-violet-700;
}

/* Metrics Grid */
.metrics-grid {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 12px;
}

@media (min-width: 768px) {
  .metrics-grid {
    grid-template-columns: repeat(4, 1fr);
    gap: 16px;
  }
}

.metric-card {
  @apply bg-eggshell-500;
  border-radius: 8px;
  padding: 16px;
}

.metric-card.primary {
  @apply bg-violet-100;
}

.metric-label {
  font-size: 12px;
  @apply text-neutral-500;
  margin: 0 0 8px 0;
}

.metric-card.primary .metric-label {
  @apply text-violet-600;
}

.metric-value {
  font-size: 24px;
  font-weight: 700;
  @apply text-horizon-500;
  margin: 0;
}

.metric-card.primary .metric-value {
  @apply text-violet-600;
}

.metric-value.positive {
  @apply text-green-600;
}

.metric-value.negative {
  @apply text-red-600;
}

.metric-value.date {
  font-size: 16px;
}

.metric-percent {
  font-size: 14px;
  font-weight: 500;
}

/* Details Grid */
.details-grid {
  display: grid;
  grid-template-columns: 1fr;
  gap: 24px;
}

@media (min-width: 768px) {
  .details-grid {
    grid-template-columns: repeat(2, 1fr);
  }
}

/* Detail Card */
.detail-card {
  @apply bg-white rounded-xl shadow-sm border border-light-gray;
  padding: 24px;
}

.detail-card.full-width {
  grid-column: 1 / -1;
}

.card-title {
  font-size: 18px;
  font-weight: 600;
  @apply text-horizon-500;
  margin: 0 0 16px 0;
}

.detail-list {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.detail-row {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  padding-bottom: 12px;
  @apply border-b border-savannah-100;
}

.detail-row:last-child {
  border-bottom: none;
  padding-bottom: 0;
}

.detail-label {
  font-size: 14px;
  @apply text-neutral-500;
  flex-shrink: 0;
}

.detail-value {
  font-size: 14px;
  font-weight: 500;
  @apply text-horizon-500;
  text-align: right;
  margin-left: 16px;
}

.empty-detail {
  @apply text-horizon-400;
  font-size: 14px;
  text-align: center;
  padding: 16px 0;
}

.tax-note {
  margin-top: 16px;
  padding-top: 16px;
  @apply border-t border-light-gray;
  font-size: 13px;
  @apply text-neutral-500;
  line-height: 1.5;
}

.purpose-text,
.notes-text {
  font-size: 14px;
  @apply text-horizon-500;
  line-height: 1.6;
  margin: 0;
  white-space: pre-wrap;
}

/* RPT Info Card */
.rpt-info-card {
  @apply bg-blue-50 border border-blue-300;
  border-radius: 12px;
  padding: 24px;
}

.rpt-header {
  display: flex;
  align-items: center;
  gap: 12px;
  margin-bottom: 16px;
}

.rpt-icon {
  width: 24px;
  height: 24px;
  @apply text-blue-600;
  flex-shrink: 0;
}

.rpt-title {
  font-size: 16px;
  font-weight: 600;
  @apply text-blue-800;
  margin: 0;
}

.rpt-list {
  list-style: none;
  padding: 0;
  margin: 0 0 16px 0;
}

.rpt-list li {
  position: relative;
  padding-left: 20px;
  margin-bottom: 8px;
  font-size: 14px;
  @apply text-blue-800;
}

.rpt-list li::before {
  content: '-';
  position: absolute;
  left: 0;
  @apply text-blue-600;
}

.next-charge {
  font-size: 14px;
  @apply text-blue-800;
  margin: 0;
  padding-top: 12px;
  border-top: 1px solid rgba(59, 130, 246, 0.2);
}
</style>
