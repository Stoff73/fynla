<template>
  <AppLayout>
    <div class="trusts-dashboard">
      <!-- Header -->
      <div class="list-header">
        <h2 class="list-title">Trusts</h2>
        <div class="header-buttons">
          <button @click="openCreateTrustModal" class="add-trust-button">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="button-icon">
              <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
            </svg>
            Add Trust
          </button>
          <button v-preview-disabled="'upload'" @click="showUploadModal = true" class="upload-button">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="button-icon">
              <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5m-13.5-9L12 3m0 0l4.5 4.5M12 3v13.5" />
            </svg>
            Upload Document
          </button>
        </div>
      </div>

      <!-- Loading State -->
      <div v-if="loading" class="loading-state">
        <div class="spinner"></div>
        <p>Loading your trusts...</p>
      </div>

      <!-- Error State -->
      <div v-else-if="error" class="error-state">
        <p>{{ error }}</p>
      </div>

      <!-- Content -->
      <div v-else>
        <!-- Trusts List -->
        <div v-if="safeTrusts.length > 0" class="trusts-grid">
          <TrustCard
            v-for="trust in safeTrusts"
            :key="trust.id"
            :trust="trust"
            @view="viewTrustDetail"
          />
        </div>

        <!-- Empty State -->
        <div v-else class="empty-state">
          <div class="empty-icon">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
            </svg>
          </div>
          <h3 class="empty-title">No trusts found</h3>
          <p class="empty-text">Get started by creating your first trust</p>
          <button @click="openCreateTrustModal" class="create-btn">
            Create Trust
          </button>
        </div>

        <!-- Trust Types Guide -->
        <div class="guide-card">
          <div class="guide-header">
            <h2 class="guide-title">UK Trust Types Guide</h2>
            <button
              @click="showTrustTypesGuide = !showTrustTypesGuide"
              class="guide-toggle"
            >
              {{ showTrustTypesGuide ? 'Hide Guide' : 'Show Guide' }}
            </button>
          </div>

          <div v-if="showTrustTypesGuide" class="guide-content">
            <!-- Tax Rates Summary -->
            <div class="tax-rates-summary">
              <h3 class="section-title">2025/26 Trust Tax Rates</h3>
              <div class="tax-rates-grid">
                <div class="tax-rate-item">
                  <p class="rate-label">Income Tax (Discretionary)</p>
                  <p class="rate-value">45% other income, 39.35% dividends</p>
                </div>
                <div class="tax-rate-item">
                  <p class="rate-label">Income Tax (Interest in Possession)</p>
                  <p class="rate-value">20% other income, 8.75% dividends</p>
                </div>
                <div class="tax-rate-item">
                  <p class="rate-label">Capital Gains Tax</p>
                  <p class="rate-value">24% flat rate, £1,500 exempt</p>
                </div>
              </div>
              <p class="tax-note">Tax-free allowance: £500 (if total income below this threshold, no tax is payable)</p>
            </div>

            <!-- Trust Type Cards -->
            <div class="trust-types-grid">
              <div v-for="trustType in trustTypesInfo" :key="trustType.type" class="trust-type-card">
                <h4 class="type-name">{{ trustType.name }}</h4>
                <p class="type-description">{{ trustType.description }}</p>
                <div class="type-details">
                  <p><span class="detail-label">Income Tax:</span> {{ trustType.incomeTax }}</p>
                  <p><span class="detail-label">Inheritance Tax:</span> {{ trustType.iht }}</p>
                </div>
                <span v-if="trustType.isRPT" class="rpt-badge">
                  Relevant Property Trust
                </span>
              </div>
            </div>

            <!-- IHT Charges Info -->
            <div class="iht-charges-info">
              <h3 class="section-title">Inheritance Tax Charges for Relevant Property Trusts</h3>
              <div class="charges-grid">
                <div class="charge-item">
                  <p class="charge-label">Entry Charge</p>
                  <p class="charge-value">20% on gifts exceeding £325,000 Nil Rate Band</p>
                </div>
                <div class="charge-item">
                  <p class="charge-label">Periodic Charge</p>
                  <p class="charge-value">Up to 6% every 10 years</p>
                </div>
                <div class="charge-item">
                  <p class="charge-label">Exit Charge</p>
                  <p class="charge-value">Up to 6% when assets leave trust</p>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Upcoming Tax Returns & Charges -->
        <div v-if="upcomingChargesData.length > 0 || taxReturnsData.length > 0" class="tax-events-card">
          <h2 class="card-title">Upcoming Tax Events</h2>

          <!-- Periodic Charges -->
          <div v-if="upcomingChargesData.length > 0" class="tax-events-section">
            <h3 class="section-subtitle">Periodic Charges (10-Year Anniversary)</h3>
            <div class="table-wrapper">
              <table class="events-table">
                <thead>
                  <tr>
                    <th>Trust Name</th>
                    <th>Charge Date</th>
                    <th>Trust Value</th>
                    <th>Estimated Charge</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="charge in upcomingChargesData" :key="charge.trust_id">
                    <td class="font-medium">{{ charge.trust_name }}</td>
                    <td>
                      {{ formatDate(charge.charge_date) }}
                      <span class="text-xs text-gray-500">({{ charge.months_until_charge }} months)</span>
                    </td>
                    <td>{{ formatCurrency(charge.trust_value) }}</td>
                    <td class="font-semibold text-red-600">{{ formatCurrency(charge.estimated_charge) }}</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>

          <!-- Tax Returns -->
          <div v-if="taxReturnsData.length > 0" class="tax-events-section">
            <h3 class="section-subtitle">Tax Return Due Dates</h3>
            <div class="tax-returns-list">
              <div
                v-for="taxReturn in taxReturnsData"
                :key="taxReturn.trust_id"
                :class="['tax-return-item', { overdue: taxReturn.is_overdue }]"
              >
                <div>
                  <p class="return-name">{{ taxReturn.trust_name }}</p>
                  <p class="return-type">{{ taxReturn.trust_type }}</p>
                </div>
                <div class="return-due">
                  <p :class="{ 'text-red-600': taxReturn.is_overdue }">
                    {{ formatDate(taxReturn.return_due_date) }}
                  </p>
                  <p class="return-days">
                    {{ taxReturn.is_overdue ? 'OVERDUE' : `${taxReturn.days_until_due} days` }}
                  </p>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Create/Edit Trust Modal -->
      <TrustFormModal
        v-if="showTrustModal"
        :trust="selectedTrust"
        @close="closeTrustModal"
        @save="handleSaveTrust"
      />

      <!-- Document Upload Modal -->
      <DocumentUploadModal
        v-if="showUploadModal"
        document-type="trust_document"
        @close="showUploadModal = false"
        @saved="handleDocumentSaved"
        @manual-entry="showUploadModal = false; showTrustModal = true;"
      />
    </div>
  </AppLayout>
</template>

<script>
import { mapState, mapActions } from 'vuex';
import axios from '@/bootstrap';
import AppLayout from '@/layouts/AppLayout.vue';
import TrustCard from '@/components/Trusts/TrustCard.vue';
import TrustFormModal from '@/components/Trusts/TrustFormModal.vue';
import DocumentUploadModal from '@/components/Shared/DocumentUploadModal.vue';
import { currencyMixin } from '@/mixins/currencyMixin';

export default {
  name: 'TrustsDashboard',

  mixins: [currencyMixin],

  components: {
    AppLayout,
    TrustCard,
    TrustFormModal,
    DocumentUploadModal,
  },

  data() {
    return {
      loading: false,
      error: null,
      showTrustModal: false,
      showUploadModal: false,
      selectedTrust: null,
      upcomingChargesData: [],
      taxReturnsData: [],
      showTrustTypesGuide: false,
      trustTypesInfo: [
        {
          type: 'bare',
          name: 'Bare Trust',
          description: 'Beneficiary has absolute right to capital and income. Simple and tax-efficient.',
          incomeTax: 'Beneficiary\'s personal rates',
          iht: 'Potentially Exempt Transfer (exempt after 7 years)',
          isRPT: false,
        },
        {
          type: 'interest_in_possession',
          name: 'Interest in Possession',
          description: 'Life tenant receives all income, capital passes to remaindermen.',
          incomeTax: '20% / 8.75% (dividends)',
          iht: 'May be in life tenant\'s estate',
          isRPT: false,
        },
        {
          type: 'discretionary',
          name: 'Discretionary Trust',
          description: 'Trustees have full discretion over distributions. Maximum flexibility.',
          incomeTax: '45% / 39.35% (dividends)',
          iht: '10-year and exit charges',
          isRPT: true,
        },
        {
          type: 'life_insurance',
          name: 'Life Insurance Trust',
          description: 'Holds life policy proceeds outside the estate for Inheritance Tax.',
          incomeTax: 'N/A (no regular income)',
          iht: 'Outside estate',
          isRPT: false,
        },
        {
          type: 'discounted_gift',
          name: 'Discounted Gift Trust',
          description: 'Gift capital while retaining income. Immediate Inheritance Tax reduction.',
          incomeTax: 'Settlor\'s rates on retained income',
          iht: 'Partial Potentially Exempt Transfer (discounted value)',
          isRPT: false,
        },
        {
          type: 'loan',
          name: 'Loan Trust',
          description: 'Loan to trust allows growth outside estate while maintaining access.',
          incomeTax: '45% / 39.35% (dividends)',
          iht: 'Loan stays in estate, growth outside',
          isRPT: true,
        },
      ],
    };
  },

  computed: {
    ...mapState('trusts', ['trusts']),

    isPreviewMode() {
      return this.$store.getters['preview/isPreviewMode'];
    },

    safeTrusts() {
      return this.trusts || [];
    },

    activeTrusts() {
      return this.safeTrusts.filter(t => t.is_active);
    },

    inactiveTrusts() {
      return this.safeTrusts.filter(t => !t.is_active);
    },

    relevantPropertyTrusts() {
      return this.safeTrusts.filter(t => t.is_relevant_property_trust);
    },

    totalTrustValue() {
      const total = this.safeTrusts.reduce((sum, trust) => {
        const value = parseFloat(trust.total_asset_value || trust.current_value || 0);
        return sum + (isNaN(value) ? 0 : value);
      }, 0);
      return isNaN(total) ? 0 : total;
    },

    totalAssets() {
      const total = this.safeTrusts.reduce((sum, trust) => {
        const count = parseInt(trust.asset_count || 0);
        return sum + (isNaN(count) ? 0 : count);
      }, 0);
      return isNaN(total) ? 0 : total;
    },
  },

  async mounted() {
    await this.loadData();
  },

  methods: {
    ...mapActions('trusts', ['fetchTrusts', 'createTrust', 'updateTrust', 'deleteTrust']),

    async loadData() {
      this.loading = true;
      this.error = null;

      try {
        await this.fetchTrusts();
        await this.loadUpcomingTaxEvents();
      } catch (error) {
        this.error = error.message || 'Failed to load trusts data';
      } finally {
        this.loading = false;
      }
    },

    async loadUpcomingTaxEvents() {
      this.upcomingChargesData = [];
      this.taxReturnsData = [];
    },

    openCreateTrustModal() {
      this.selectedTrust = null;
      this.showTrustModal = true;
    },

    closeTrustModal() {
      this.showTrustModal = false;
      this.selectedTrust = null;
    },

    async handleSaveTrust(trustData) {
      try {
        if (this.selectedTrust) {
          await this.updateTrust({ id: this.selectedTrust.id, data: trustData });
        } else {
          await this.createTrust(trustData);
        }
        this.closeTrustModal();
        await this.loadData();
      } catch (error) {
        console.error('Error saving trust:', error);
        this.error = error.message || 'Failed to save trust';
      }
    },

    viewTrustDetail(trust) {
      this.$router.push(`/trusts/${trust.id}`);
    },

    editTrust(trust) {
      this.selectedTrust = trust;
      this.showTrustModal = true;
    },

    async calculateTrustIHT(trust) {
      if (this.isPreviewMode) {
        return;
      }
      try {
        const response = await this.$http.post(`/api/estate/trusts/${trust.id}/calculate-iht-impact`);
        if (response.data.success) {
          this.$router.push(`/trusts/${trust.id}?tab=tax`);
        }
      } catch (error) {
        this.error = error.message || 'Failed to calculate Inheritance Tax impact';
      }
    },

    formatDate(dateString) {
      if (!dateString) return '-';
      return new Date(dateString).toLocaleDateString('en-GB', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
      });
    },

    handleDocumentSaved() {
      this.showUploadModal = false;
      this.loadData();
    },
  },
};
</script>

<style scoped>
.trusts-dashboard {
  padding: 24px;
}

/* Header */
.list-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 24px;
  flex-wrap: wrap;
  gap: 16px;
}

.list-title {
  font-size: 24px;
  font-weight: 700;
  color: #111827;
  margin: 0;
}

.header-buttons {
  display: flex;
  gap: 12px;
  flex-wrap: wrap;
}

.add-trust-button {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 10px 16px;
  @apply bg-primary-500;
  color: white;
  border: none;
  border-radius: 8px;
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  transition: background 0.2s;
}

.add-trust-button:hover {
  background: #2563eb;
}

.upload-button {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 10px 16px;
  background: white;
  @apply text-primary-500;
  @apply border-2 border-primary-500;
  border-radius: 8px;
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  transition: background 0.2s;
}

.upload-button:hover {
  background: #eff6ff;
}

.button-icon {
  width: 20px;
  height: 20px;
}

/* Loading State */
.loading-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 48px;
  color: #6b7280;
}

.spinner {
  width: 40px;
  height: 40px;
  border: 3px solid #e5e7eb;
  @apply border-t-primary-500;
  border-radius: 50%;
  animation: spin 1s linear infinite;
  margin-bottom: 16px;
}

@keyframes spin {
  to {
    transform: rotate(360deg);
  }
}

/* Error State */
.error-state {
  background: #fef2f2;
  border: 1px solid #fecaca;
  border-radius: 8px;
  padding: 16px;
  color: #991b1b;
  margin-bottom: 24px;
}

/* Trusts Grid */
.trusts-grid {
  display: grid;
  grid-template-columns: 1fr;
  gap: 20px;
  margin-bottom: 24px;
}

@media (min-width: 1024px) {
  .trusts-grid {
    grid-template-columns: repeat(2, 1fr);
  }
}

/* Empty State */
.empty-state {
  background: white;
  border-radius: 12px;
  padding: 48px 24px;
  text-align: center;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
  border: 1px solid #e5e7eb;
  margin-bottom: 24px;
}

.empty-icon {
  width: 48px;
  height: 48px;
  margin: 0 auto 16px;
  color: #9ca3af;
}

.empty-icon svg {
  width: 100%;
  height: 100%;
}

.empty-title {
  font-size: 18px;
  font-weight: 600;
  color: #111827;
  margin: 0 0 8px 0;
}

.empty-text {
  font-size: 14px;
  color: #6b7280;
  margin: 0 0 24px 0;
}

/* Guide Card */
.guide-card {
  background: white;
  border-radius: 12px;
  padding: 20px;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
  border: 1px solid #e5e7eb;
  margin-bottom: 24px;
}

.guide-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.guide-title {
  font-size: 18px;
  font-weight: 600;
  color: #111827;
  margin: 0;
}

.guide-toggle {
  font-size: 14px;
  color: #7c3aed;
  font-weight: 500;
}

.guide-toggle:hover {
  color: #6d28d9;
}

.guide-content {
  margin-top: 20px;
}

/* Tax Rates Summary */
.tax-rates-summary {
  background: #ede9fe;
  border-radius: 8px;
  padding: 16px;
  margin-bottom: 20px;
}

.section-title {
  font-size: 14px;
  font-weight: 600;
  color: #7c3aed;
  margin: 0 0 12px 0;
}

.tax-rates-grid {
  display: grid;
  grid-template-columns: 1fr;
  gap: 12px;
}

@media (min-width: 768px) {
  .tax-rates-grid {
    grid-template-columns: repeat(3, 1fr);
  }
}

.tax-rate-item {
  padding: 0;
}

.rate-label {
  font-size: 13px;
  font-weight: 600;
  color: #111827;
  margin: 0 0 4px 0;
}

.rate-value {
  font-size: 13px;
  color: #4b5563;
  margin: 0;
}

.tax-note {
  font-size: 12px;
  color: #6b7280;
  margin: 12px 0 0 0;
}

/* Trust Types Grid */
.trust-types-grid {
  display: grid;
  grid-template-columns: 1fr;
  gap: 12px;
  margin-bottom: 20px;
}

@media (min-width: 768px) {
  .trust-types-grid {
    grid-template-columns: repeat(2, 1fr);
  }
}

@media (min-width: 1024px) {
  .trust-types-grid {
    grid-template-columns: repeat(3, 1fr);
  }
}

.trust-type-card {
  background: #f9fafb;
  border-radius: 8px;
  padding: 16px;
}

.type-name {
  font-size: 14px;
  font-weight: 600;
  color: #111827;
  margin: 0 0 8px 0;
}

.type-description {
  font-size: 13px;
  color: #6b7280;
  margin: 0 0 12px 0;
  line-height: 1.4;
}

.type-details {
  font-size: 12px;
}

.type-details p {
  margin: 0 0 4px 0;
  color: #4b5563;
}

.detail-label {
  font-weight: 600;
}

.rpt-badge {
  display: inline-block;
  margin-top: 8px;
  padding: 4px 8px;
  font-size: 11px;
  font-weight: 500;
  background: #fef3c7;
  color: #b45309;
  border-radius: 9999px;
}

/* IHT Charges Info */
.iht-charges-info {
  background: #fef3c7;
  border-radius: 8px;
  padding: 16px;
}

.iht-charges-info .section-title {
  color: #b45309;
}

.charges-grid {
  display: grid;
  grid-template-columns: 1fr;
  gap: 12px;
}

@media (min-width: 768px) {
  .charges-grid {
    grid-template-columns: repeat(3, 1fr);
  }
}

.charge-item {
  padding: 0;
}

.charge-label {
  font-size: 13px;
  font-weight: 600;
  color: #111827;
  margin: 0 0 4px 0;
}

.charge-value {
  font-size: 13px;
  color: #4b5563;
  margin: 0;
}

/* Tax Events Card */
.tax-events-card {
  background: white;
  border-radius: 12px;
  padding: 20px;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
  border: 1px solid #e5e7eb;
  margin-bottom: 24px;
}

.card-title {
  font-size: 18px;
  font-weight: 600;
  color: #111827;
  margin: 0 0 16px 0;
}

.tax-events-section {
  margin-bottom: 24px;
}

.tax-events-section:last-child {
  margin-bottom: 0;
}

.section-subtitle {
  font-size: 14px;
  font-weight: 600;
  color: #374151;
  margin: 0 0 12px 0;
}

.table-wrapper {
  overflow-x: auto;
}

.events-table {
  width: 100%;
  border-collapse: collapse;
}

.events-table th {
  padding: 12px 16px;
  font-size: 12px;
  font-weight: 500;
  color: #6b7280;
  text-transform: uppercase;
  text-align: left;
  background: #f9fafb;
  border-bottom: 1px solid #e5e7eb;
}

.events-table td {
  padding: 12px 16px;
  font-size: 14px;
  color: #4b5563;
  border-bottom: 1px solid #e5e7eb;
}

.events-table tr:last-child td {
  border-bottom: none;
}

/* Tax Returns List */
.tax-returns-list {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.tax-return-item {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 12px;
  background: #f9fafb;
  border: 1px solid #e5e7eb;
  border-radius: 8px;
}

.tax-return-item.overdue {
  background: #fef2f2;
  border-color: #fecaca;
}

.return-name {
  font-size: 14px;
  font-weight: 500;
  color: #111827;
  margin: 0 0 4px 0;
}

.return-type {
  font-size: 13px;
  color: #6b7280;
  margin: 0;
}

.return-due {
  text-align: right;
}

.return-due p:first-child {
  font-size: 14px;
  font-weight: 500;
  color: #111827;
  margin: 0 0 4px 0;
}

.return-days {
  font-size: 12px;
  color: #6b7280;
  margin: 0;
}

/* Mobile responsive */
@media (max-width: 768px) {
  .trusts-dashboard {
    padding: 16px;
  }

  .list-header {
    flex-direction: column;
    align-items: flex-start;
  }

  .header-buttons {
    width: 100%;
  }

  .add-trust-button,
  .upload-button {
    width: 100%;
    justify-content: center;
  }

  .trusts-grid {
    grid-template-columns: 1fr;
  }
}
</style>
