<template>
  <div class="chattels-list">
    <ModuleStatusBar />
    <!-- Detail View -->
    <ChattelDetailInline
      v-if="selectedChattelId"
      :chattel-id="selectedChattelId"
      @back="closeDetail"
      @edit="openEditModal"
      @deleted="handleDeleted"
    />

    <!-- List View -->
    <div v-else>
      <div class="list-controls">
        <select v-model="filterType" class="filter-select">
          <option value="all">All Types</option>
          <option value="vehicle">Vehicles</option>
          <option value="art">Art</option>
          <option value="antique">Antiques</option>
          <option value="jewelry">Jewellery</option>
          <option value="collectible">Collectibles</option>
          <option value="other">Other</option>
        </select>
      </div>

      <div v-if="loading" class="loading-state">
        <div class="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-pink-600"></div>
        <p>Loading personal valuables...</p>
      </div>

      <div v-else-if="error" class="error-state">
        <p>{{ error }}</p>
        <button @click="fetchData" class="retry-button">Retry</button>
      </div>

      <div v-else-if="filteredChattels.length === 0" class="empty-state">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="empty-icon">
          <path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09zM18.259 8.715L18 9.75l-.259-1.035a3.375 3.375 0 00-2.455-2.456L14.25 6l1.036-.259a3.375 3.375 0 002.455-2.456L18 2.25l.259 1.035a3.375 3.375 0 002.456 2.456L21.75 6l-1.035.259a3.375 3.375 0 00-2.456 2.456zM16.894 20.567L16.5 21.75l-.394-1.183a2.25 2.25 0 00-1.423-1.423L13.5 18.75l1.183-.394a2.25 2.25 0 001.423-1.423l.394-1.183.394 1.183a2.25 2.25 0 001.423 1.423l1.183.394-1.183.394a2.25 2.25 0 00-1.423 1.423z" />
        </svg>
        <p class="empty-title">No Personal Valuables Recorded</p>
        <p class="empty-subtitle">Track and value your personal assets including vehicles, art, antiques, jewellery, and collectibles.</p>
        <button v-preview-disabled="'add'" @click="openAddModal" class="add-first-button">
          <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
          </svg>
          Add Your First Valuable
        </button>
      </div>

      <div v-else class="chattels-grid">
        <ChattelCard
          v-for="chattel in filteredChattels"
          :key="chattel.id"
          :chattel="chattel"
          @click="openDetail(chattel.id)"
        />
      </div>
    </div>

    <!-- Add/Edit Modal -->
    <Teleport to="body">
      <ChattelFormModal
        v-if="showFormModal"
        :chattel="editingChattel"
        :is-editing="!!editingChattel"
        @close="closeFormModal"
        @save="handleSave"
      />
    </Teleport>

    <!-- Delete Confirmation -->
    <Teleport to="body">
      <ConfirmDialog
        :show="showDeleteConfirm"
        title="Delete Chattel"
        message="Are you sure you want to delete this item? This action cannot be undone."
        @confirm="handleDelete"
        @cancel="showDeleteConfirm = false"
      />
    </Teleport>
  </div>
</template>

<script>
import { mapState, mapActions, mapGetters } from 'vuex';
import ChattelCard from './ChattelCard.vue';
import ChattelFormModal from './ChattelFormModal.vue';
import ChattelDetailInline from './ChattelDetailInline.vue';
import ConfirmDialog from '@/components/Common/ConfirmDialog.vue';
import ModuleStatusBar from '@/components/Shared/ModuleStatusBar.vue';

import logger from '@/utils/logger';
export default {
  name: 'ChattelsList',

  components: {
    ChattelCard,
    ChattelFormModal,
    ChattelDetailInline,
    ConfirmDialog,
    ModuleStatusBar,
  },

  data() {
    return {
      filterType: 'all',
      showFormModal: false,
      showDeleteConfirm: false,
      editingChattel: null,
      deletingChattel: null,
      selectedChattelId: null,
      showImportDropdown: false,
    };
  },

  watch: {
    actionCounter() {
      if (this.pendingAction === 'addValuable') {
        this.openAddModal();
        this.$store.dispatch('subNav/consumeCta');
      } else if (this.pendingAction === 'importValuables') {
        this.showImportDropdown = !this.showImportDropdown;
        this.$store.dispatch('subNav/consumeCta');
      }
    },
    '$store.state.aiFormFill.pendingFill'(fill) {
      if (fill && fill.entityType === 'chattel') {
        if (fill.mode === 'edit' && fill.entityId) {
          const record = this.chattels.find(c => c.id === fill.entityId);
          if (record) {
            this.editingChattel = record;
          }
        } else {
          this.editingChattel = null;
        }
        this.showFormModal = true;
      }
    },
  },

  computed: {
    ...mapState('chattels', ['chattels', 'loading', 'error']),
    ...mapGetters('subNav', ['pendingAction', 'actionCounter']),

    filteredChattels() {
      let filtered = [...this.chattels];

      if (this.filterType !== 'all') {
        filtered = filtered.filter(c => c.chattel_type === this.filterType);
      }

      // Sort by value descending
      filtered.sort((a, b) => (b.current_value || 0) - (a.current_value || 0));

      return filtered;
    },
  },

  async mounted() {
    // Check for pendingFill that was set before this component mounted
    const fill = this.$store.state.aiFormFill?.pendingFill;
    if (fill && fill.entityType === 'chattel' && fill.mode !== 'edit') {
      this.editingChattel = null;
      this.showFormModal = true;
    }

    this.fetchData();
    // Fetch family members to ensure spouse data is available for joint ownership dropdown
    await this.$store.dispatch('userProfile/fetchFamilyMembers');

    document.addEventListener('click', this.handleClickOutsideImport);
  },

  beforeUnmount() {
    document.removeEventListener('click', this.handleClickOutsideImport);
  },

  methods: {
    ...mapActions('chattels', ['fetchChattels', 'createChattel', 'updateChattel', 'deleteChattel']),

    handleClickOutsideImport(event) {
      if (this.$refs.importDropdown && !this.$refs.importDropdown.contains(event.target)) {
        this.showImportDropdown = false;
      }
    },

    handleImport(format) {
      this.showImportDropdown = false;
      // File input for CSV/Excel upload — backend endpoint TBD
      const input = document.createElement('input');
      input.type = 'file';
      input.accept = format === 'csv' ? '.csv' : '.xlsx,.xls';
      input.onchange = () => {
        // Future: upload file to backend for processing
      };
      input.click();
    },

    downloadTemplate() {
      this.showImportDropdown = false;
      const headers = [
        'Type', 'Name', 'Description', 'Make', 'Model', 'Year',
        'Registration', 'Current Value', 'Valuation Date',
        'Purchase Price', 'Purchase Date', 'Ownership Type',
        'Ownership Percentage', 'Joint Owner', 'Notes',
      ];
      const csvContent = headers.join(',') + '\n';
      const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
      const link = document.createElement('a');
      link.href = URL.createObjectURL(blob);
      link.download = 'personal_valuables_template.csv';
      link.click();
      URL.revokeObjectURL(link.href);
    },

    async fetchData() {
      try {
        await this.fetchChattels();
      } catch (error) {
        logger.error('Failed to fetch chattels:', error);
      }
    },

    openAddModal() {
      this.editingChattel = null;
      this.showFormModal = true;
    },

    openEditModal(chattel) {
      this.editingChattel = chattel;
      this.showFormModal = true;
    },

    closeFormModal() {
      this.showFormModal = false;
      this.editingChattel = null;
    },

    async handleSave(formData) {
      try {
        if (this.editingChattel) {
          await this.updateChattel({ id: this.editingChattel.id, data: formData });
        } else {
          await this.createChattel(formData);
        }
        // Complete AI fill if this was an AI-driven save
        if (this.$store.state.aiFormFill.pendingFill) {
          this.$store.dispatch('aiFormFill/completeFill');
        }
        this.closeFormModal();
      } catch (error) {
        logger.error('Failed to save chattel:', error);
      }
    },

    confirmDelete(chattel) {
      this.deletingChattel = chattel;
      this.showDeleteConfirm = true;
    },

    async handleDelete() {
      if (this.deletingChattel) {
        try {
          await this.deleteChattel(this.deletingChattel.id);
          this.showDeleteConfirm = false;
          this.deletingChattel = null;
        } catch (error) {
          logger.error('Failed to delete chattel:', error);
        }
      }
    },

    openDetail(id) {
      this.selectedChattelId = id;
    },

    closeDetail() {
      this.selectedChattelId = null;
    },

    handleDeleted() {
      this.selectedChattelId = null;
      this.fetchData();
    },
  },
};
</script>

<style scoped>
.chattels-list {
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
  @apply border-pink-500;
  box-shadow: 0 0 0 3px rgba(232, 62, 109, 0.1);
}

.import-button {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 8px 16px;
  @apply bg-horizon-100 text-horizon-500 border border-horizon-300;
  border-radius: 8px;
  font-size: 14px;
  font-weight: 500;
  cursor: pointer;
  transition: background 0.2s;
}

.import-button:hover {
  @apply bg-horizon-200;
}

.import-dropdown {
  position: absolute;
  top: 100%;
  right: 0;
  margin-top: 4px;
  background: white;
  border-radius: 8px;
  @apply border border-light-gray;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
  z-index: 10;
  min-width: 180px;
}

.import-option {
  display: block;
  width: 100%;
  text-align: left;
  padding: 10px 16px;
  font-size: 14px;
  @apply text-neutral-500;
  background: none;
  border: none;
  cursor: pointer;
  transition: background 0.15s;
}

.import-option:hover {
  @apply bg-eggshell-500;
}

.import-option:first-child {
  border-radius: 8px 8px 0 0;
}

.import-option:last-child {
  border-radius: 0 0 8px 8px;
}

.add-button {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 8px 16px;
  @apply bg-pink-500;
  color: white;
  border: none;
  border-radius: 8px;
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  transition: background 0.2s;
}

.add-button:hover {
  @apply bg-pink-600;
}

.chattels-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
  gap: 20px;
}

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
  background: white;
  border-radius: 12px;
  padding: 80px 40px;
  @apply bg-light-blue-100 border border-light-gray;
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

@media (max-width: 768px) {
  .chattels-list {
    padding: 16px;
  }

  .list-controls {
    width: 100%;
  }

  .filter-select {
    width: 100%;
  }

  .chattels-grid {
    grid-template-columns: 1fr;
  }
}
</style>
