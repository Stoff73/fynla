<template>
  <div class="liabilities-list">
    <ModuleStatusBar />
    <!-- Detail View -->
    <LiabilityDetailInline
      v-if="selectedLiabilityId"
      :liability-id="selectedLiabilityId"
      @back="closeDetail"
      @edit="openEditModal"
      @deleted="handleDeleted"
    />

    <!-- List View -->
    <div v-else>
      <div class="list-controls">
        <select v-model="filterType" class="filter-select">
          <option value="all">All Types</option>
          <option value="mortgage">Mortgages</option>
          <option value="student_loan">Student Loans</option>
          <option value="personal_loan">Personal Loans</option>
          <option value="secured_loan">Secured Loans</option>
          <option value="business_loan">Business Loans</option>
          <option value="hire_purchase">Hire Purchase</option>
          <option value="credit_card">Credit Cards</option>
          <option value="overdraft">Overdrafts</option>
          <option value="other">Other</option>
        </select>
      </div>

      <!-- Info banner when external liabilities exist -->
      <div v-if="!loading && hasMortgageLiabilities" class="info-banner">
        <svg class="w-4 h-4 flex-shrink-0 text-horizon-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>
        <span class="text-sm text-neutral-500">Mortgages are managed in <router-link to="/net-worth/property" class="text-raspberry-500 hover:text-raspberry-600 font-medium">Property</router-link> and shown here for a complete view of your liabilities.</span>
      </div>

      <div v-if="loading" class="loading-state">
        <div class="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-raspberry-600"></div>
        <p>Loading liabilities...</p>
      </div>

      <div v-else-if="error" class="error-state">
        <p>{{ error }}</p>
        <button @click="fetchData" class="retry-button">Retry</button>
      </div>

      <div v-else-if="filteredLiabilities.length === 0" class="empty-state">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="empty-icon">
          <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 8.25h19.5M2.25 9h19.5m-16.5 5.25h6m-6 2.25h3m-3.75 3h15a2.25 2.25 0 002.25-2.25V6.75A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25v10.5A2.25 2.25 0 004.5 19.5z" />
        </svg>
        <p class="empty-title">No Liabilities Recorded</p>
        <p class="empty-subtitle">Track your loans, credit cards, and other debts to get a complete picture of your net worth.</p>
        <button v-preview-disabled="'add'" @click="openAddModal" class="add-first-button">
          <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
          </svg>
          Add Your First Liability
        </button>
      </div>

      <div v-else class="liabilities-grid">
        <LiabilityCard
          v-for="liability in filteredLiabilities"
          :key="liability.id"
          :liability="liability"
          @click="openDetail(liability.id)"
        />
      </div>

      <!-- Summary Bar -->
      <div v-if="filteredLiabilities.length > 0" class="summary-bar">
        <div class="summary-item">
          <span class="summary-label">Total Liabilities</span>
          <span class="summary-value">{{ filteredLiabilities.length }}</span>
        </div>
        <div class="summary-item">
          <span class="summary-label">Total Balance Owed</span>
          <span class="summary-value text-raspberry-600">{{ formatCurrency(totalBalance) }}</span>
        </div>
        <div class="summary-item">
          <span class="summary-label">Total Monthly Payments</span>
          <span class="summary-value">{{ formatCurrency(totalMonthlyPayments) }}/mo</span>
        </div>
      </div>
    </div>

    <!-- Add/Edit Modal -->
    <Teleport to="body">
      <div v-if="showFormModal" class="fixed inset-0 bg-horizon-600 bg-opacity-50 overflow-y-auto h-full w-full z-50 flex items-center justify-center" @click.self="closeFormModal">
        <div class="relative bg-white rounded-lg shadow-xl max-w-2xl w-full mx-4 max-h-[90vh] overflow-hidden" @click.stop>
          <div class="overflow-y-auto max-h-[90vh]">
            <!-- Modal Close Button -->
            <button
              @click="closeFormModal"
              class="absolute top-4 right-4 z-20 text-horizon-400 hover:text-neutral-500 transition-colors"
            >
              <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
              </svg>
            </button>

            <LiabilityForm
              :liability="editingLiability"
              :mode="editingLiability ? 'edit' : 'create'"
              @save="handleSave"
              @cancel="closeFormModal"
            />
          </div>
        </div>
      </div>
    </Teleport>

    <!-- Delete Confirmation -->
    <Teleport to="body">
      <ConfirmDialog
        :show="showDeleteConfirm"
        title="Delete Liability"
        message="Are you sure you want to delete this liability? This action cannot be undone."
        @confirm="handleDelete"
        @cancel="showDeleteConfirm = false"
      />
    </Teleport>
  </div>
</template>

<script>
import { mapState, mapActions, mapGetters } from 'vuex';
import LiabilityCard from './LiabilityCard.vue';
import LiabilityDetailInline from './LiabilityDetailInline.vue';
import LiabilityForm from '@/components/Estate/LiabilityForm.vue';
import ConfirmDialog from '@/components/Common/ConfirmDialog.vue';
import ModuleStatusBar from '@/components/Shared/ModuleStatusBar.vue';
import { currencyMixin } from '@/mixins/currencyMixin';

import logger from '@/utils/logger';
export default {
  name: 'LiabilitiesList',

  components: {
    LiabilityCard,
    LiabilityDetailInline,
    LiabilityForm,
    ConfirmDialog,
    ModuleStatusBar,
  },

  mixins: [currencyMixin],

  data() {
    return {
      filterType: 'all',
      showFormModal: false,
      showDeleteConfirm: false,
      editingLiability: null,
      deletingLiability: null,
      selectedLiabilityId: null,
    };
  },

  computed: {
    ...mapState('estate', ['liabilities', 'loading', 'error']),
    ...mapGetters('subNav', ['pendingAction', 'actionCounter']),

    filteredLiabilities() {
      let filtered = [...this.liabilities];

      if (this.filterType !== 'all') {
        filtered = filtered.filter(l => l.liability_type === this.filterType);
      }

      // Sort by balance descending
      filtered.sort((a, b) => (b.current_balance || 0) - (a.current_balance || 0));

      return filtered;
    },

    totalBalance() {
      return this.filteredLiabilities.reduce((sum, l) => sum + parseFloat(l.current_balance || 0), 0);
    },

    totalMonthlyPayments() {
      return this.filteredLiabilities.reduce((sum, l) => sum + parseFloat(l.monthly_payment || 0), 0);
    },

    hasMortgageLiabilities() {
      return this.liabilities.some(l => l.source === 'property_module');
    },
  },

  watch: {
    actionCounter() {
      if (this.pendingAction === 'addLiability') {
        this.openAddModal();
        this.$store.dispatch('subNav/consumeCta');
      }
    },
    '$store.state.aiFormFill.pendingFill'(fill) {
      if (fill && fill.entityType === 'estate_liability') {
        if (fill.mode === 'edit' && fill.entityId) {
          const record = this.liabilities.find(l => l.id === fill.entityId);
          if (record) {
            this.openEditModal(record);
          }
        } else {
          this.openAddModal();
        }
      }
    },
  },

  mounted() {
    // Check for pendingFill that was set before this component mounted
    const fill = this.$store.state.aiFormFill?.pendingFill;
    if (fill && fill.entityType === 'estate_liability' && fill.mode !== 'edit') {
      this.openAddModal();
    }

    this.fetchData();
    this.applyRouteFilter();
  },

  methods: {
    ...mapActions('estate', ['fetchEstateData', 'createLiability', 'updateLiability', 'deleteLiability']),

    async fetchData() {
      try {
        await this.fetchEstateData();
      } catch (error) {
        logger.error('Failed to fetch liabilities:', error);
      }
    },

    applyRouteFilter() {
      const filter = this.$route.query.filter;
      if (filter && ['mortgage', 'student_loan', 'personal_loan', 'secured_loan', 'business_loan', 'hire_purchase', 'credit_card', 'overdraft', 'other'].includes(filter)) {
        this.filterType = filter;
      }
    },

    openAddModal() {
      this.editingLiability = null;
      this.showFormModal = true;
    },

    openEditModal(liability) {
      this.editingLiability = liability;
      this.showFormModal = true;
    },

    closeFormModal() {
      this.showFormModal = false;
      this.editingLiability = null;
    },

    async handleSave(formData) {
      try {
        if (this.editingLiability) {
          await this.updateLiability({ id: this.editingLiability.id, liabilityData: formData });
        } else {
          await this.createLiability(formData);
        }
        // Complete AI fill if this was an AI-driven save
        if (this.$store.state.aiFormFill.pendingFill) {
          this.$store.dispatch('aiFormFill/completeFill');
        }
        this.closeFormModal();
        // Re-fetch to ensure clean data after save
        await this.fetchData();
      } catch (error) {
        logger.error('Failed to save liability:', error);
      }
    },

    confirmDelete(liability) {
      this.deletingLiability = liability;
      this.showDeleteConfirm = true;
    },

    async handleDelete() {
      if (this.deletingLiability) {
        try {
          await this.deleteLiability(this.deletingLiability.id);
          this.showDeleteConfirm = false;
          this.deletingLiability = null;
        } catch (error) {
          logger.error('Failed to delete liability:', error);
        }
      }
    },

    openDetail(id) {
      this.selectedLiabilityId = id;
    },

    closeDetail() {
      this.selectedLiabilityId = null;
    },

    handleDeleted() {
      this.selectedLiabilityId = null;
      this.fetchData();
    },
  },
};
</script>

<style scoped>
.liabilities-list {
  padding: 24px;
  @apply bg-eggshell-500;
}

.list-controls {
  display: flex;
  gap: 12px;
  align-items: center;
  margin-bottom: 24px;
}

.filter-select {
  padding: 8px 12px;
  @apply border border-horizon-300;
  border-radius: 8px;
  font-size: 14px;
  @apply text-neutral-500;
  background: white;
  cursor: pointer;
}

.filter-select:focus {
  outline: none;
  @apply border-raspberry-500;
  box-shadow: 0 0 0 3px rgba(239, 68, 68, 0.1);
}

.add-button {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 8px 16px;
  @apply bg-raspberry-500;
  color: white;
  border: none;
  border-radius: 8px;
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  transition: background 0.2s;
}

.add-button:hover {
  @apply bg-raspberry-600;
}

.liabilities-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
  gap: 20px;
}

/* Summary Bar */
.summary-bar {
  display: flex;
  gap: 24px;
  margin-top: 20px;
  padding: 16px 20px;
  background: white;
  border-radius: 12px;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
  @apply border border-light-gray;
}

.summary-item {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.summary-label {
  font-size: 12px;
  font-weight: 500;
  @apply text-neutral-500;
  text-transform: uppercase;
  letter-spacing: 0.3px;
}

.summary-value {
  font-size: 18px;
  font-weight: 700;
  @apply text-horizon-500;
}

.info-banner {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 10px 16px;
  margin-bottom: 20px;
  border-radius: 8px;
  @apply bg-savannah-50;
  @apply border border-savannah-200;
}

/* States */
.loading-state,
.error-state,
.empty-state {
  text-align: center;
  padding: 60px 20px;
}

.loading-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 16px;
}

.loading-state p,
.error-state p {
  @apply text-neutral-500;
  font-size: 16px;
  margin: 0;
}

.error-state p {
  @apply text-raspberry-500;
}

.retry-button {
  margin-top: 16px;
  padding: 8px 16px;
  @apply bg-savannah-100;
  @apply text-neutral-500;
  @apply border border-horizon-300;
  border-radius: 8px;
  cursor: pointer;
}

.retry-button:hover {
  @apply bg-savannah-200;
}

.empty-state {
  @apply bg-light-blue-100 border border-light-gray;
  border-radius: 12px;
  padding: 80px 40px;
}

.empty-icon {
  width: 64px;
  height: 64px;
  @apply text-horizon-400;
  margin: 0 auto 16px;
}

.empty-title {
  font-size: 20px;
  font-weight: 700;
  @apply text-neutral-500;
  margin-bottom: 8px;
}

.empty-subtitle {
  @apply text-horizon-400;
  font-size: 14px;
  font-weight: 400;
  max-width: 400px;
  margin: 0 auto;
}

.add-first-button {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  margin-top: 24px;
  padding: 12px 24px;
  @apply bg-horizon-500;
  color: white;
  border: none;
  border-radius: 8px;
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  transition: background 0.2s;
}

.add-first-button:hover {
  @apply bg-horizon-600;
}

/* Mobile */
@media (max-width: 768px) {
  .liabilities-list {
    padding: 16px;
  }

  .list-controls {
    width: 100%;
  }

  .filter-select {
    width: 100%;
  }

  .liabilities-grid {
    grid-template-columns: 1fr;
  }

  .summary-bar {
    flex-direction: column;
    gap: 12px;
  }
}
</style>
