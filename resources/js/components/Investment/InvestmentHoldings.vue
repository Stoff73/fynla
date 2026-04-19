<template>
  <div class="holdings">
    <div class="flex justify-between items-center mb-6">
      <div>
        <h2 class="text-2xl font-bold text-horizon-500 mb-2">
          Holdings
          <span v-if="selectedAccount" class="text-xl text-neutral-500 font-normal ml-2">
            - {{ selectedAccount.provider }}
          </span>
        </h2>
        <p class="text-neutral-500">
          <template v-if="selectedAccount">
            Viewing holdings for {{ selectedAccount.provider }} account
          </template>
          <template v-else>
            Manage your investment holdings and view detailed performance
          </template>
        </p>
      </div>
      <button
        v-if="selectedAccountId"
        @click="clearFilter"
        class="text-violet-600 hover:text-violet-700 text-sm font-medium"
      >
        View All Holdings
      </button>
    </div>

    <!-- Error Alert -->
    <div v-if="error" class="bg-eggshell-500 rounded-lg p-4 mb-6">
      <div class="flex">
        <svg class="h-5 w-5 text-raspberry-400 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>
        <p class="text-sm text-raspberry-800">{{ error }}</p>
      </div>
    </div>

    <!-- Success Alert -->
    <div v-if="successMessage" class="bg-eggshell-500 rounded-lg p-4 mb-6">
      <div class="flex">
        <svg class="h-5 w-5 text-spring-400 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>
        <p class="text-sm text-spring-800">{{ successMessage }}</p>
      </div>
    </div>

    <!-- Holdings Table -->
    <HoldingsTable
      :holdings="filteredHoldings"
      :accounts="accounts"
      :loading="loading"
      @add-holding="openAddModal"
      @edit-holding="openEditModal"
      @delete-holding="confirmDelete"
    />

    <!-- Holding Form Modal -->
    <HoldingForm
      :show="showModal"
      :holding="selectedHolding"
      :accounts="accounts"
      @save="handleSubmit"
      @close="closeModal"
    />

    <!-- Delete Confirmation Modal -->
    <div v-if="showDeleteModal" class="fixed inset-0 z-50 overflow-y-auto" @click.self="showDeleteModal = false">
      <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
        <div class="fixed inset-0 transition-opacity bg-horizon-500 bg-opacity-75" @click="showDeleteModal = false"></div>
        <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
          <div class="bg-white px-6 py-4">
            <div class="sm:flex sm:items-start">
              <div class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-raspberry-500 sm:mx-0 sm:h-10 sm:w-10">
                <svg class="h-6 w-6 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                </svg>
              </div>
              <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left">
                <h3 class="text-lg font-medium text-horizon-500">Delete Holding</h3>
                <div class="mt-2">
                  <p class="text-sm text-neutral-500">
                    Are you sure you want to delete <strong>{{ holdingToDelete?.security_name }}</strong>? This action cannot be undone.
                  </p>
                </div>
              </div>
            </div>
          </div>
          <div class="bg-eggshell-500 px-6 py-4 flex justify-end gap-3">
            <button
              @click="showDeleteModal = false"
              class="px-4 py-2 border border-horizon-300 rounded-md text-sm font-medium text-neutral-500 hover:bg-savannah-100 transition-colors"
            >
              Cancel
            </button>
            <button
              @click="handleDelete"
              :disabled="deleting"
              class="px-4 py-2 bg-raspberry-500 text-white rounded-button text-sm font-medium hover:bg-raspberry-600 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
            >
              {{ deleting ? 'Deleting...' : 'Delete' }}
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import { mapGetters, mapActions, mapState } from 'vuex';
import HoldingsTable from './HoldingsTable.vue';
import HoldingForm from './HoldingForm.vue';

import logger from '@/utils/logger';
export default {
  name: 'InvestmentHoldings',

  emits: ['clear-filter'],

  components: {
    HoldingsTable,
    HoldingForm,
  },

  props: {
    selectedAccountId: {
      type: Number,
      default: null,
    },
  },

  data() {
    return {
      showModal: false,
      selectedHolding: null,
      showDeleteModal: false,
      holdingToDelete: null,
      error: null,
      successMessage: null,
      deleting: false,
      successTimeout: null,
    };
  },

  computed: {
    ...mapGetters('investment', [
      'allHoldings',
      'accounts',
      'loading',
    ]),
    ...mapState('aiFormFill', ['pendingFill']),

    filteredHoldings() {
      // If a specific account is selected, filter holdings by that account
      if (this.selectedAccountId) {
        return this.allHoldings.filter(
          (holding) => holding.investment_account_id === this.selectedAccountId
        );
      }
      // Otherwise show all holdings
      return this.allHoldings;
    },

    selectedAccount() {
      if (this.selectedAccountId) {
        return this.accounts.find(
          (account) => account.id === this.selectedAccountId
        );
      }
      return null;
    },
  },

  watch: {
    pendingFill: {
      handler(fill) {
        if (fill && fill.entityType === 'investment_holding') {
          this.selectedHolding = null;
          this.showModal = true;
        }
      },
      immediate: true,
    },
  },

  beforeUnmount() {
    if (this.successTimeout) clearTimeout(this.successTimeout);
  },

  methods: {
    ...mapActions('investment', [
      'createHolding',
      'updateHolding',
      'deleteHolding',
      'fetchInvestmentData',
      'analyseInvestment',
    ]),

    openAddModal() {
      this.selectedHolding = null;
      this.showModal = true;
      this.clearMessages();
    },

    openEditModal(holding) {
      this.selectedHolding = holding;
      this.showModal = true;
      this.clearMessages();
    },

    closeModal() {
      this.showModal = false;
      this.selectedHolding = null;
    },

    async handleSubmit(formData) {
      this.clearMessages();

      try {
        if (formData.id) {
          // Update existing holding (store action handles analysis)
          await this.updateHolding({ id: formData.id, data: formData });
          this.successMessage = 'Holding updated successfully';
        } else {
          // Create new holding (store action handles analysis)
          await this.createHolding(formData);
          this.successMessage = 'Holding added successfully';
        }

        // Complete AI fill if this was an AI-driven save
        if (this.$store.state.aiFormFill.pendingFill) {
          this.$store.dispatch('aiFormFill/completeFill');
        }

        // Refresh data to get latest from server
        // Note: analyseInvestment() is already called by the store actions above
        await this.fetchInvestmentData();

        // Auto-hide success message after 5 seconds
        if (this.successTimeout) clearTimeout(this.successTimeout);
        this.successTimeout = setTimeout(() => {
          this.successMessage = null;
        }, 5000);
      } catch (error) {
        logger.error('Error saving holding:', error);
        this.error = error.response?.data?.message || 'Failed to save holding. Please try again.';
      }
    },

    confirmDelete(holding) {
      this.holdingToDelete = holding;
      this.showDeleteModal = true;
      this.clearMessages();
    },

    async handleDelete() {
      if (!this.holdingToDelete) return;

      this.deleting = true;
      this.clearMessages();

      try {
        await this.deleteHolding(this.holdingToDelete.id);
        this.successMessage = `${this.holdingToDelete.security_name} deleted successfully`;
        this.showDeleteModal = false;
        this.holdingToDelete = null;

        // Refresh data (store action already handles analysis)
        await this.fetchInvestmentData();

        // Auto-hide success message after 5 seconds
        if (this.successTimeout) clearTimeout(this.successTimeout);
        this.successTimeout = setTimeout(() => {
          this.successMessage = null;
        }, 5000);
      } catch (error) {
        logger.error('Error deleting holding:', error);
        this.error = error.response?.data?.message || 'Failed to delete holding. Please try again.';
        this.showDeleteModal = false;
      } finally {
        this.deleting = false;
      }
    },

    clearMessages() {
      this.error = null;
      this.successMessage = null;
    },

    clearFilter() {
      // Emit event to parent to clear the selected account
      this.$emit('clear-filter');
    },
  },
};
</script>
