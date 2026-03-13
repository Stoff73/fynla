<template>
  <div class="chattels-list">
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
      <div class="list-header">
        <h2 class="list-title">Personal Valuables</h2>
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
          <button v-preview-disabled="'add'" @click="openAddModal" class="add-button">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
              <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
            </svg>
            Add Valuable
          </button>
        </div>
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
    <ChattelFormModal
      v-if="showFormModal"
      :chattel="editingChattel"
      :is-editing="!!editingChattel"
      @close="closeFormModal"
      @save="handleSave"
    />

    <!-- Delete Confirmation -->
    <ConfirmDialog
      :show="showDeleteConfirm"
      title="Delete Chattel"
      message="Are you sure you want to delete this item? This action cannot be undone."
      @confirm="handleDelete"
      @cancel="showDeleteConfirm = false"
    />
  </div>
</template>

<script>
import { mapState, mapActions } from 'vuex';
import ChattelCard from './ChattelCard.vue';
import ChattelFormModal from './ChattelFormModal.vue';
import ChattelDetailInline from './ChattelDetailInline.vue';
import ConfirmDialog from '@/components/Common/ConfirmDialog.vue';

export default {
  name: 'ChattelsList',

  components: {
    ChattelCard,
    ChattelFormModal,
    ChattelDetailInline,
    ConfirmDialog,
  },

  data() {
    return {
      filterType: 'all',
      showFormModal: false,
      showDeleteConfirm: false,
      editingChattel: null,
      deletingChattel: null,
      selectedChattelId: null,
    };
  },

  computed: {
    ...mapState('chattels', ['chattels', 'loading', 'error']),

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
    this.fetchData();
    // Fetch family members to ensure spouse data is available for joint ownership dropdown
    await this.$store.dispatch('userProfile/fetchFamilyMembers');
  },

  methods: {
    ...mapActions('chattels', ['fetchChattels', 'createChattel', 'updateChattel', 'deleteChattel']),

    async fetchData() {
      try {
        await this.fetchChattels();
      } catch (error) {
        console.error('Failed to fetch chattels:', error);
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
        this.closeFormModal();
      } catch (error) {
        console.error('Failed to save chattel:', error);
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
          console.error('Failed to delete chattel:', error);
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
  @apply text-horizon-500;
  margin: 0;
}

.list-controls {
  display: flex;
  gap: 12px;
  align-items: center;
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
  @apply border-2 border-dashed border-horizon-300;
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
  @apply bg-pink-500;
  color: white;
  border: none;
  border-radius: 8px;
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  transition: background 0.2s;
}

.add-first-button:hover {
  @apply bg-pink-600;
}

@media (max-width: 768px) {
  .chattels-list {
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

  .filter-select,
  .add-button {
    width: 100%;
    justify-content: center;
  }

  .chattels-grid {
    grid-template-columns: 1fr;
  }
}
</style>
