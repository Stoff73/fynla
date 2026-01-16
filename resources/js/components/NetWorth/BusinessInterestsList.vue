<template>
  <div class="business-interests-list">
    <!-- Detail View -->
    <BusinessInterestDetailInline
      v-if="selectedBusinessId"
      :business-id="selectedBusinessId"
      @back="closeDetail"
      @edit="openEditModal"
      @deleted="handleDeleted"
    />

    <!-- List View -->
    <div v-else>
      <div class="list-header">
        <h2 class="list-title">Business Interests</h2>
        <div class="list-controls">
          <button v-preview-disabled="'add'" @click="openAddModal" class="add-button">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
              <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
            </svg>
            Add Business
          </button>
        </div>
      </div>

      <div v-if="loading" class="loading-state">
        <div class="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-purple-600"></div>
        <p>Loading business interests...</p>
      </div>

      <div v-else-if="error" class="error-state">
        <p>{{ error }}</p>
        <button @click="fetchData" class="retry-button">Retry</button>
      </div>

      <div v-else-if="filteredBusinesses.length === 0" class="empty-state">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="empty-icon">
          <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 21h19.5m-18-18v18m10.5-18v18m6-13.5V21M6.75 6.75h.75m-.75 3h.75m-.75 3h.75m3-6h.75m-.75 3h.75m-.75 3h.75M6.75 21v-3.375c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21M3 3h12m-.75 4.5H21m-3.75 3.75h.008v.008h-.008v-.008zm0 3h.008v.008h-.008v-.008zm0 3h.008v.008h-.008v-.008z" />
        </svg>
        <p class="empty-title">No Business Interests</p>
        <p class="empty-subtitle">Track and manage your business interests including sole trader businesses, partnerships, limited companies and LLPs.</p>
        <button v-preview-disabled="'add'" @click="openAddModal" class="add-first-button">
          <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
          </svg>
          Add Your First Business
        </button>
      </div>

      <div v-else class="businesses-grid">
        <BusinessInterestCard
          v-for="business in filteredBusinesses"
          :key="business.id"
          :business="business"
          @click="openDetail(business.id)"
          @edit="openEditModal(business)"
          @delete="confirmDelete(business)"
        />
      </div>

      <!-- Total Value Summary -->
      <div v-if="filteredBusinesses.length > 0" class="summary-bar">
        <div class="summary-item">
          <span class="summary-label">Total Businesses</span>
          <span class="summary-value">{{ filteredBusinesses.length }}</span>
        </div>
        <div class="summary-item">
          <span class="summary-label">Total Value (Your Share)</span>
          <span class="summary-value text-purple-600">{{ formatCurrency(totalValue) }}</span>
        </div>
      </div>
    </div>

    <!-- Add/Edit Modal -->
    <BusinessInterestForm
      v-if="showFormModal"
      :business="editingBusiness"
      @close="closeFormModal"
      @save="handleSave"
    />

    <!-- Delete Confirmation -->
    <ConfirmationModal
      v-if="showDeleteConfirm"
      title="Delete Business Interest"
      message="Are you sure you want to delete this business interest? This action cannot be undone."
      @confirm="handleDelete"
      @cancel="showDeleteConfirm = false"
    />
  </div>
</template>

<script>
import { mapState, mapGetters, mapActions } from 'vuex';
import BusinessInterestCard from './BusinessInterestCard.vue';
import BusinessInterestForm from './BusinessInterestForm.vue';
import BusinessInterestDetailInline from './BusinessInterestDetailInline.vue';
import ConfirmationModal from '@/components/Common/ConfirmationModal.vue';
import { currencyMixin } from '@/mixins/currencyMixin';

export default {
  name: 'BusinessInterestsList',

  mixins: [currencyMixin],

  components: {
    BusinessInterestCard,
    BusinessInterestForm,
    BusinessInterestDetailInline,
    ConfirmationModal,
  },

  data() {
    return {
      showFormModal: false,
      showDeleteConfirm: false,
      editingBusiness: null,
      deletingBusiness: null,
      selectedBusinessId: null,
    };
  },

  computed: {
    ...mapState('businessInterests', ['businesses', 'loading', 'error']),
    ...mapGetters('businessInterests', ['totalBusinessValue']),

    filteredBusinesses() {
      return [...this.businesses];
    },

    totalValue() {
      return this.filteredBusinesses.reduce((sum, b) => sum + (b.user_share || b.current_valuation || 0), 0);
    },
  },

  mounted() {
    this.fetchData();
  },

  methods: {
    ...mapActions('businessInterests', ['fetchBusinesses', 'createBusiness', 'updateBusiness', 'deleteBusiness']),

    async fetchData() {
      try {
        await this.fetchBusinesses();
      } catch (error) {
        console.error('Failed to fetch business interests:', error);
      }
    },

    openAddModal() {
      this.editingBusiness = null;
      this.showFormModal = true;
    },

    openEditModal(business) {
      this.editingBusiness = business;
      this.showFormModal = true;
    },

    closeFormModal() {
      this.showFormModal = false;
      this.editingBusiness = null;
    },

    openDetail(businessId) {
      this.selectedBusinessId = businessId;
    },

    closeDetail() {
      this.selectedBusinessId = null;
      // Refresh list in case data changed
      this.fetchData();
    },

    confirmDelete(business) {
      this.deletingBusiness = business;
      this.showDeleteConfirm = true;
    },

    async handleSave(formData) {
      try {
        if (this.editingBusiness) {
          await this.updateBusiness({ id: this.editingBusiness.id, data: formData });
        } else {
          await this.createBusiness(formData);
        }
        this.closeFormModal();
      } catch (error) {
        console.error('Failed to save business:', error);
      }
    },

    async handleDelete() {
      if (!this.deletingBusiness) return;

      try {
        await this.deleteBusiness(this.deletingBusiness.id);
        this.showDeleteConfirm = false;
        this.deletingBusiness = null;
      } catch (error) {
        console.error('Failed to delete business:', error);
      }
    },

    handleDeleted() {
      this.fetchData();
    },

  },
};
</script>

<style scoped>
.business-interests-list {
  padding: 24px;
}

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

.list-controls {
  display: flex;
  gap: 12px;
  align-items: center;
}

.add-button {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 8px 16px;
  background: #a21caf;
  color: white;
  border: none;
  border-radius: 8px;
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  transition: background-color 0.2s;
}

.add-button:hover {
  background: #86198f;
}

.businesses-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
  gap: 20px;
}

.loading-state {
  text-align: center;
  padding: 60px 20px;
}

.loading-state p {
  color: #6b7280;
  font-size: 16px;
  margin-top: 16px;
}

.error-state {
  text-align: center;
  padding: 60px 20px;
}

.error-state p {
  color: #ef4444;
  font-size: 16px;
  margin-bottom: 16px;
}

.retry-button {
  padding: 8px 16px;
  background: #f3f4f6;
  color: #374151;
  border: 1px solid #d1d5db;
  border-radius: 8px;
  font-size: 14px;
  cursor: pointer;
}

.retry-button:hover {
  background: #e5e7eb;
}

.empty-state {
  background: white;
  border-radius: 12px;
  padding: 80px 40px;
  border: 2px dashed #d1d5db;
  text-align: center;
}

.empty-icon {
  width: 64px;
  height: 64px;
  color: #9ca3af;
  margin: 0 auto 16px;
}

.empty-title {
  font-size: 20px;
  font-weight: 700;
  color: #374151;
  margin-bottom: 8px;
}

.empty-subtitle {
  color: #6b7280;
  font-size: 14px;
  font-weight: 400;
  max-width: 400px;
  margin: 0 auto 24px;
}

.add-first-button {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 12px 24px;
  background: #a21caf;
  color: white;
  border: none;
  border-radius: 8px;
  font-size: 16px;
  font-weight: 600;
  cursor: pointer;
  transition: background-color 0.2s;
}

.add-first-button:hover {
  background: #86198f;
}

.summary-bar {
  margin-top: 24px;
  padding: 16px 24px;
  background: white;
  border-radius: 12px;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.summary-item {
  display: flex;
  flex-direction: column;
}

.summary-label {
  font-size: 12px;
  color: #6b7280;
  text-transform: uppercase;
  letter-spacing: 0.05em;
}

.summary-value {
  font-size: 20px;
  font-weight: 700;
  color: #111827;
}

@media (max-width: 768px) {
  .business-interests-list {
    padding: 16px;
  }

  .list-header {
    flex-direction: column;
    align-items: flex-start;
  }

  .list-controls {
    width: 100%;
    flex-direction: column;
  }

  .add-button {
    width: 100%;
    justify-content: center;
  }

  .businesses-grid {
    grid-template-columns: 1fr;
  }

  .summary-bar {
    flex-direction: column;
    gap: 16px;
    text-align: center;
  }
}
</style>
