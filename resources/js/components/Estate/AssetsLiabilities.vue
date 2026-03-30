<template>
  <div class="assets-liabilities-tab">
    <!-- Success Message -->
    <div v-if="successMessage" class="mb-6 bg-spring-50 border border-spring-200 p-4">
      <div class="flex">
        <div class="flex-shrink-0">
          <svg class="h-5 w-5 text-spring-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
          </svg>
        </div>
        <div class="ml-3">
          <p class="text-sm text-spring-700">{{ successMessage }}</p>
        </div>
      </div>
    </div>

    <!-- Error Message -->
    <div v-if="errorMessage" class="mb-6 bg-raspberry-50 border border-raspberry-200 p-4">
      <div class="flex">
        <div class="flex-shrink-0">
          <svg class="h-5 w-5 text-raspberry-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
          </svg>
        </div>
        <div class="ml-3">
          <p class="text-sm text-raspberry-700">{{ errorMessage }}</p>
        </div>
      </div>
    </div>

    <!-- Summary Cards -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
      <div class="bg-spring-50 rounded-lg p-6">
        <p class="text-sm text-spring-600 font-medium mb-2">Total Assets</p>
        <p class="text-3xl font-bold text-horizon-500">{{ formattedTotalAssets }}</p>
        <p class="text-sm text-neutral-500 mt-1">
          {{ assets.length }} items
          <span v-if="investmentAccountsCount > 0" class="text-xs">
            ({{ investmentAccountsCount }} from investments)
          </span>
        </p>
      </div>
      <div class="bg-raspberry-50 rounded-lg p-6">
        <p class="text-sm text-raspberry-600 font-medium mb-2">Total Liabilities</p>
        <p class="text-3xl font-bold text-horizon-500">{{ formattedTotalLiabilities }}</p>
        <p class="text-sm text-neutral-500 mt-1">{{ liabilities.length }} items</p>
      </div>
      <div class="bg-violet-50 rounded-lg p-6">
        <p class="text-sm text-violet-600 font-medium mb-2">Net Worth</p>
        <p class="text-3xl font-bold text-horizon-500">{{ formattedNetWorth }}</p>
      </div>
    </div>

    <!-- Assets Section -->
    <div class="bg-white rounded-lg border border-light-gray mb-8">
      <div class="px-6 py-4 border-b border-light-gray flex justify-between items-center">
        <h3 class="text-lg font-semibold text-horizon-500">Assets</h3>
        <button
          @click="showAssetForm = true"
          class="inline-flex items-center px-3 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-spring-600 hover:bg-spring-700"
        >
          <svg
            class="-ml-1 mr-2 h-4 w-4"
            xmlns="http://www.w3.org/2000/svg"
            viewBox="0 0 20 20"
            fill="currentColor"
          >
            <path
              fill-rule="evenodd"
              d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z"
              clip-rule="evenodd"
            />
          </svg>
          Add Asset
        </button>
      </div>
      <div v-if="assets.length === 0" class="px-6 py-8 text-center text-neutral-500">
        No assets recorded yet
      </div>
      <div v-else class="overflow-x-auto">
        <table class="min-w-full divide-y divide-light-gray">
          <thead class="bg-eggshell-500">
            <tr>
              <th class="px-6 py-3 text-left text-xs font-medium text-neutral-500 uppercase tracking-wider">
                Asset Type
              </th>
              <th class="px-6 py-3 text-left text-xs font-medium text-neutral-500 uppercase tracking-wider">
                Name
              </th>
              <th class="px-6 py-3 text-left text-xs font-medium text-neutral-500 uppercase tracking-wider">
                Current Value
              </th>
              <th class="px-6 py-3 text-left text-xs font-medium text-neutral-500 uppercase tracking-wider">
                Inheritance Tax Status
              </th>
              <th class="px-6 py-3 text-right text-xs font-medium text-neutral-500 uppercase tracking-wider">
                Actions
              </th>
            </tr>
          </thead>
          <tbody class="bg-white divide-y divide-light-gray">
            <tr v-for="asset in assets" :key="asset.id" :class="{'bg-violet-50': asset.source === 'investment_module'}">
              <td class="px-6 py-4 whitespace-nowrap text-sm text-horizon-500">
                <div class="flex items-center">
                  {{ asset.asset_type }}
                  <span v-if="asset.source === 'investment_module'" class="ml-2 px-2 py-0.5 text-xs bg-violet-100 text-violet-700 rounded">
                    Investment Module
                  </span>
                </div>
              </td>
              <td class="px-6 py-4 whitespace-nowrap text-sm text-horizon-500">
                {{ asset.asset_name }}
              </td>
              <td class="px-6 py-4 whitespace-nowrap text-sm text-horizon-500">
                {{ formatCurrency(asset.current_value) }}
              </td>
              <td class="px-6 py-4 whitespace-nowrap text-sm">
                <span
                  :class="[
                    'px-2 py-1 text-xs font-medium rounded-full',
                    asset.is_iht_exempt ? 'bg-spring-100 text-spring-800' : 'bg-savannah-100 text-horizon-500',
                  ]"
                >
                  {{ asset.is_iht_exempt ? 'Exempt' : 'Taxable' }}
                </span>
                <span v-if="asset.exemption_reason" class="ml-2 text-xs text-neutral-500" :title="asset.exemption_reason">ℹ️</span>
              </td>
              <td class="px-6 py-4 whitespace-nowrap text-right text-sm">
                <!-- Investment module assets are read-only -->
                <span v-if="asset.source === 'investment_module'" class="text-horizon-400 text-xs">
                  Managed in Investment Module
                </span>
                <template v-else>
                  <button
                    @click="editAsset(asset)"
                    class="text-violet-600 hover:text-violet-900 mr-3"
                  >
                    Edit
                  </button>
                  <button
                    @click="deleteAssetConfirm(asset.id)"
                    class="text-raspberry-600 hover:text-raspberry-900"
                  >
                    Delete
                  </button>
                </template>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Liabilities Section -->
    <div class="bg-white rounded-lg border border-light-gray">
      <div class="px-6 py-4 border-b border-light-gray flex justify-between items-center">
        <h3 class="text-lg font-semibold text-horizon-500">Liabilities</h3>
        <button
          @click="showLiabilityForm = true"
          class="inline-flex items-center px-3 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-raspberry-500 hover:bg-raspberry-600"
        >
          <svg
            class="-ml-1 mr-2 h-4 w-4"
            xmlns="http://www.w3.org/2000/svg"
            viewBox="0 0 20 20"
            fill="currentColor"
          >
            <path
              fill-rule="evenodd"
              d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z"
              clip-rule="evenodd"
            />
          </svg>
          Add Liability
        </button>
      </div>
      <div v-if="liabilities.length === 0" class="px-6 py-8 text-center text-neutral-500">
        No liabilities recorded yet
      </div>
      <div v-else class="overflow-x-auto">
        <table class="min-w-full divide-y divide-light-gray">
          <thead class="bg-eggshell-500">
            <tr>
              <th class="px-6 py-3 text-left text-xs font-medium text-neutral-500 uppercase tracking-wider">
                Liability Type
              </th>
              <th class="px-6 py-3 text-left text-xs font-medium text-neutral-500 uppercase tracking-wider">
                Name
              </th>
              <th class="px-6 py-3 text-left text-xs font-medium text-neutral-500 uppercase tracking-wider">
                Balance
              </th>
              <th class="px-6 py-3 text-left text-xs font-medium text-neutral-500 uppercase tracking-wider">
                Monthly Payment
              </th>
              <th class="px-6 py-3 text-right text-xs font-medium text-neutral-500 uppercase tracking-wider">
                Actions
              </th>
            </tr>
          </thead>
          <tbody class="bg-white divide-y divide-light-gray">
            <tr v-for="liability in liabilities" :key="liability.id">
              <td class="px-6 py-4 whitespace-nowrap text-sm text-horizon-500">
                {{ liability.liability_type }}
              </td>
              <td class="px-6 py-4 whitespace-nowrap text-sm text-horizon-500">
                {{ liability.liability_name }}
              </td>
              <td class="px-6 py-4 whitespace-nowrap text-sm text-horizon-500">
                {{ formatCurrency(liability.current_balance) }}
              </td>
              <td class="px-6 py-4 whitespace-nowrap text-sm text-horizon-500">
                {{ formatCurrency(liability.monthly_payment) }}
              </td>
              <td class="px-6 py-4 whitespace-nowrap text-right text-sm">
                <button
                  @click="editLiability(liability)"
                  class="text-violet-600 hover:text-violet-900 mr-3"
                >
                  Edit
                </button>
                <button
                  @click="deleteLiabilityConfirm(liability.id)"
                  class="text-raspberry-600 hover:text-raspberry-900"
                >
                  Delete
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Asset Form Modal -->
    <div v-if="showAssetForm" class="fixed inset-0 bg-eggshell-5000 bg-opacity-75 flex items-center justify-center z-50 p-4">
      <div class="bg-white rounded-lg max-w-3xl w-full max-h-[90vh] overflow-y-auto">
        <AssetForm
          :asset="editingAsset"
          :mode="editingAsset ? 'edit' : 'create'"
          @save="handleAssetSave"
          @cancel="closeAssetForm"
        />
      </div>
    </div>

    <!-- Liability Form Modal -->
    <div v-if="showLiabilityForm" class="fixed inset-0 bg-eggshell-5000 bg-opacity-75 flex items-center justify-center z-50 p-4">
      <div class="bg-white rounded-lg max-w-3xl w-full max-h-[90vh] overflow-y-auto">
        <LiabilityForm
          :liability="editingLiability"
          :mode="editingLiability ? 'edit' : 'create'"
          @save="handleLiabilitySave"
          @cancel="closeLiabilityForm"
        />
      </div>
    </div>
  </div>
</template>

<script>
import { mapState, mapGetters, mapActions } from 'vuex';
import AssetForm from './AssetForm.vue';
import LiabilityForm from './LiabilityForm.vue';
import { currencyMixin } from '@/mixins/currencyMixin';

import logger from '@/utils/logger';
export default {
  name: 'AssetsLiabilities',
  mixins: [currencyMixin],

  components: {
    AssetForm,
    LiabilityForm,
  },

  data() {
    return {
      showAssetForm: false,
      showLiabilityForm: false,
      editingAsset: null,
      editingLiability: null,
      successMessage: '',
      errorMessage: '',
      successTimeout: null,
      errorTimeout: null,
    };
  },

  computed: {
    ...mapState('estate', ['liabilities']),
    ...mapGetters('estate', ['allAssets', 'totalAssets', 'totalLiabilities', 'netWorthValue']),

    // Use allAssets instead of just assets
    assets() {
      return this.allAssets;
    },

    investmentAccountsCount() {
      if (!Array.isArray(this.assets)) return 0;
      return this.assets.filter(asset => asset && asset.source === 'investment_module').length;
    },

    formattedTotalAssets() {
      return this.formatCurrency(this.totalAssets);
    },

    formattedTotalLiabilities() {
      return this.formatCurrency(this.totalLiabilities);
    },

    formattedNetWorth() {
      return this.formatCurrency(this.netWorthValue);
    },
  },

  watch: {
    '$store.state.aiFormFill.pendingFill'(fill) {
      if (fill && fill.entityType === 'estate_asset') {
        if (fill.mode === 'edit' && fill.entityId) {
          const record = this.assets.find(a => a.id === fill.entityId);
          if (record) {
            this.editAsset(record);
          }
        } else {
          this.editingAsset = null;
          this.showAssetForm = true;
        }
      }
    },
  },

  beforeUnmount() {
    if (this.successTimeout) clearTimeout(this.successTimeout);
    if (this.errorTimeout) clearTimeout(this.errorTimeout);
  },

  methods: {
    ...mapActions('estate', ['createAsset', 'updateAsset', 'deleteAsset', 'createLiability', 'updateLiability', 'deleteLiability']),

    // Asset methods
    editAsset(asset) {
      this.editingAsset = { ...asset };
      this.showAssetForm = true;
    },

    async handleAssetSave(assetData) {
      try {
        if (assetData.id) {
          // Update existing asset
          await this.updateAsset({ id: assetData.id, assetData });
          this.successMessage = 'Asset updated successfully';
        } else {
          // Create new asset
          await this.createAsset(assetData);
          this.successMessage = 'Asset created successfully';
        }

        // Complete AI fill if this was an AI-driven save
        if (this.$store.state.aiFormFill.pendingFill) {
          this.$store.dispatch('aiFormFill/completeFill');
        }

        this.closeAssetForm();

        // Clear success message after 3 seconds
        if (this.successTimeout) clearTimeout(this.successTimeout);
        this.successTimeout = setTimeout(() => {
          this.successMessage = '';
        }, 3000);
      } catch (error) {
        this.errorMessage = error.message || 'Failed to save asset';
        logger.error('Failed to save asset:', error);

        // Clear error message after 5 seconds
        if (this.errorTimeout) clearTimeout(this.errorTimeout);
        this.errorTimeout = setTimeout(() => {
          this.errorMessage = '';
        }, 5000);
      }
    },

    closeAssetForm() {
      this.showAssetForm = false;
      this.editingAsset = null;
    },

    async deleteAssetConfirm(id) {
      if (confirm('Are you sure you want to delete this asset?')) {
        try {
          await this.deleteAsset(id);
          this.successMessage = 'Asset deleted successfully';

          if (this.successTimeout) clearTimeout(this.successTimeout);
          this.successTimeout = setTimeout(() => {
            this.successMessage = '';
          }, 3000);
        } catch (error) {
          this.errorMessage = error.message || 'Failed to delete asset';
          logger.error('Failed to delete asset:', error);

          if (this.errorTimeout) clearTimeout(this.errorTimeout);
          this.errorTimeout = setTimeout(() => {
            this.errorMessage = '';
          }, 5000);
        }
      }
    },

    // Liability methods
    editLiability(liability) {
      this.editingLiability = { ...liability };
      this.showLiabilityForm = true;
    },

    async handleLiabilitySave(liabilityData) {
      try {
        if (liabilityData.id) {
          // Update existing liability
          await this.updateLiability({ id: liabilityData.id, liabilityData });
          this.successMessage = 'Liability updated successfully';
        } else {
          // Create new liability
          await this.createLiability(liabilityData);
          this.successMessage = 'Liability created successfully';
        }

        this.closeLiabilityForm();

        // Clear success message after 3 seconds
        if (this.successTimeout) clearTimeout(this.successTimeout);
        this.successTimeout = setTimeout(() => {
          this.successMessage = '';
        }, 3000);
      } catch (error) {
        this.errorMessage = error.message || 'Failed to save liability';
        logger.error('Failed to save liability:', error);

        // Clear error message after 5 seconds
        if (this.errorTimeout) clearTimeout(this.errorTimeout);
        this.errorTimeout = setTimeout(() => {
          this.errorMessage = '';
        }, 5000);
      }
    },

    closeLiabilityForm() {
      this.showLiabilityForm = false;
      this.editingLiability = null;
    },

    async deleteLiabilityConfirm(id) {
      if (confirm('Are you sure you want to delete this liability?')) {
        try {
          await this.deleteLiability(id);
          this.successMessage = 'Liability deleted successfully';

          if (this.successTimeout) clearTimeout(this.successTimeout);
          this.successTimeout = setTimeout(() => {
            this.successMessage = '';
          }, 3000);
        } catch (error) {
          this.errorMessage = error.message || 'Failed to delete liability';
          logger.error('Failed to delete liability:', error);

          if (this.errorTimeout) clearTimeout(this.errorTimeout);
          this.errorTimeout = setTimeout(() => {
            this.errorMessage = '';
          }, 5000);
        }
      }
    },
  },
};
</script>
