<template>
  <div class="chattel-detail-inline">
    <!-- Back Button -->
    <button @click="$emit('back')" class="detail-inline-back mb-4">
      <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
        <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18" />
      </svg>
      Back to Personal Valuables
    </button>

    <!-- Loading State -->
    <div v-if="loading" class="text-center py-12">
      <div class="inline-block animate-spin rounded-full h-12 w-12 border-b-2 border-pink-600"></div>
      <p class="mt-4 text-neutral-500">Loading details...</p>
    </div>

    <!-- Chattel Content -->
    <div v-else-if="chattel" class="space-y-6">
      <!-- Header -->
      <div class="bg-white rounded-lg shadow-md p-6">
        <div class="flex flex-col sm:flex-row sm:justify-between sm:items-start gap-4">
          <div>
            <div class="flex flex-wrap items-center gap-2 sm:gap-3 mb-2">
              <span :class="['badge', getTypeBadgeClass(chattel.chattel_type)]">
                {{ formatChattelType(chattel.chattel_type) }}
              </span>
              <span v-if="chattel.is_wasting_asset" class="badge badge-green">
                Capital Gains Tax Exempt
              </span>
              <span v-if="chattel.is_shared" class="badge badge-indigo">
                {{ chattel.ownership_percentage }}% Ownership
              </span>
            </div>
            <h1 class="text-xl sm:text-2xl lg:text-3xl font-bold text-horizon-500">{{ chattel.name }}</h1>
            <p v-if="vehicleDescription" class="text-base sm:text-lg text-neutral-500 mt-1">{{ vehicleDescription }}</p>
          </div>
          <div class="flex space-x-2 w-full sm:w-auto">
            <button
              v-if="chattel.is_primary_owner !== false"
              v-preview-disabled="'edit'"
              @click="$emit('edit', chattel)"
              class="px-4 py-2 bg-pink-600 text-white rounded-button hover:bg-pink-700 transition-colors"
            >
              Edit
            </button>
            <button
              v-if="chattel.is_primary_owner !== false"
              v-preview-disabled="'delete'"
              @click="confirmDelete"
              class="px-4 py-2 bg-raspberry-600 text-white rounded-button hover:bg-raspberry-700 transition-colors"
            >
              Delete
            </button>
          </div>
        </div>

        <!-- Key Metrics -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-6">
          <div class="bg-savannah-100 rounded-lg p-4">
            <p class="text-sm text-neutral-500">Current Value</p>
            <p class="text-2xl font-bold text-pink-600">{{ formatCurrency(chattel.full_value || chattel.current_value) }}</p>
            <p v-if="chattel.is_shared" class="text-sm text-pink-600 mt-1">
              Your {{ chattel.ownership_percentage }}% share: {{ formatCurrency(chattel.user_share) }}
            </p>
          </div>
          <div class="bg-savannah-100 rounded-lg p-4">
            <p class="text-sm text-neutral-500">Purchase Price</p>
            <p class="text-2xl font-bold text-horizon-500">
              {{ chattel.purchase_price ? formatCurrency(chattel.purchase_price) : 'Not recorded' }}
            </p>
          </div>
          <div class="bg-savannah-100 rounded-lg p-4">
            <p class="text-sm text-neutral-500">Unrealised Gain/Loss</p>
            <p class="text-2xl font-bold" :class="gainLossClass">
              {{ unrealisedGainLoss !== null ? formatCurrency(unrealisedGainLoss) : 'N/A' }}
            </p>
          </div>
        </div>

        <!-- CGT Status Notice -->
        <div v-if="chattel.is_wasting_asset" class="mt-4 bg-savannah-100 rounded-lg p-4">
          <div class="flex items-center">
            <svg class="w-5 h-5 text-spring-600 mr-2" fill="currentColor" viewBox="0 0 20 20">
              <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
            </svg>
            <p class="text-sm font-medium text-spring-800">Wasting Asset - This item is exempt from Capital Gains Tax regardless of sale price</p>
          </div>
        </div>
        <div v-else-if="chattel.current_value && chattel.current_value <= 6000" class="mt-4 bg-savannah-100 rounded-lg p-4">
          <div class="flex items-center">
            <svg class="w-5 h-5 text-violet-600 mr-2" fill="currentColor" viewBox="0 0 20 20">
              <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
            </svg>
            <p class="text-sm font-medium text-violet-800">Below £6,000 threshold - Currently exempt from Capital Gains Tax if sold at this value</p>
          </div>
        </div>
      </div>

      <!-- Tabs -->
      <div class="bg-white rounded-lg shadow-md">
        <div class="border-b border-light-gray">
          <nav class="flex -mb-px overflow-x-auto">
            <button
              v-for="tab in tabs"
              :key="tab.id"
              @click="activeTab = tab.id"
              class="px-6 py-3 border-b-2 font-medium text-sm transition-colors whitespace-nowrap"
              :class="
                activeTab === tab.id
                  ? 'border-pink-600 text-pink-600'
                  : 'border-transparent text-neutral-500 hover:text-neutral-500 hover:border-horizon-300'
              "
            >
              {{ tab.label }}
            </button>
          </nav>
        </div>

        <div class="p-6">
          <!-- Overview Tab -->
          <div v-if="activeTab === 'overview'" class="space-y-6">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
              <div>
                <h3 class="text-lg font-semibold text-horizon-500 mb-4">Item Details</h3>
                <dl class="space-y-3">
                  <div class="flex justify-between">
                    <dt class="text-sm text-neutral-500">Type</dt>
                    <dd class="text-sm font-medium text-horizon-500">{{ formatChattelType(chattel.chattel_type) }}</dd>
                  </div>
                  <div v-if="chattel.description" class="flex justify-between">
                    <dt class="text-sm text-neutral-500">Description</dt>
                    <dd class="text-sm font-medium text-horizon-500 text-right max-w-xs">{{ chattel.description }}</dd>
                  </div>
                  <div class="flex justify-between">
                    <dt class="text-sm text-neutral-500">Ownership</dt>
                    <dd class="text-sm font-medium text-horizon-500">{{ formatOwnership(chattel) }}</dd>
                  </div>
                  <div v-if="chattel.valuation_date" class="flex justify-between">
                    <dt class="text-sm text-neutral-500">Last Valued</dt>
                    <dd class="text-sm font-medium text-horizon-500">{{ formatDate(chattel.valuation_date) }}</dd>
                  </div>
                </dl>
              </div>

              <div>
                <h3 class="text-lg font-semibold text-horizon-500 mb-4">Acquisition</h3>
                <dl class="space-y-3">
                  <div class="flex justify-between">
                    <dt class="text-sm text-neutral-500">Purchase Price</dt>
                    <dd class="text-sm font-medium text-horizon-500">
                      {{ chattel.purchase_price ? formatCurrency(chattel.purchase_price) : 'Not recorded' }}
                    </dd>
                  </div>
                  <div v-if="chattel.purchase_date" class="flex justify-between">
                    <dt class="text-sm text-neutral-500">Purchase Date</dt>
                    <dd class="text-sm font-medium text-horizon-500">{{ formatDate(chattel.purchase_date) }}</dd>
                  </div>
                  <div class="flex justify-between">
                    <dt class="text-sm text-neutral-500">Country</dt>
                    <dd class="text-sm font-medium text-horizon-500">{{ chattel.country || 'United Kingdom' }}</dd>
                  </div>
                </dl>
              </div>
            </div>

            <!-- Vehicle Details -->
            <div v-if="chattel.chattel_type === 'vehicle'" class="mt-6">
              <h3 class="text-lg font-semibold text-horizon-500 mb-4">Vehicle Details</h3>
              <dl class="grid grid-cols-2 md:grid-cols-4 gap-4">
                <div v-if="chattel.make" class="bg-savannah-100 rounded-lg p-3">
                  <dt class="text-xs text-neutral-500">Make</dt>
                  <dd class="text-sm font-medium text-horizon-500">{{ chattel.make }}</dd>
                </div>
                <div v-if="chattel.model" class="bg-savannah-100 rounded-lg p-3">
                  <dt class="text-xs text-neutral-500">Model</dt>
                  <dd class="text-sm font-medium text-horizon-500">{{ chattel.model }}</dd>
                </div>
                <div v-if="chattel.year" class="bg-savannah-100 rounded-lg p-3">
                  <dt class="text-xs text-neutral-500">Year</dt>
                  <dd class="text-sm font-medium text-horizon-500">{{ chattel.year }}</dd>
                </div>
                <div v-if="chattel.registration_number" class="bg-savannah-100 rounded-lg p-3">
                  <dt class="text-xs text-neutral-500">Registration</dt>
                  <dd class="text-sm font-medium text-horizon-500">{{ chattel.registration_number }}</dd>
                </div>
              </dl>
            </div>
          </div>

          <!-- CGT Calculator Tab -->
          <div v-if="activeTab === 'cgt'" class="space-y-6">
            <div v-if="chattel.is_wasting_asset" class="bg-savannah-100 rounded-lg p-6">
              <div class="flex items-start">
                <svg class="w-6 h-6 text-spring-600 mr-3 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
                <div>
                  <h3 class="text-lg font-semibold text-spring-800">Wasting Asset - Capital Gains Tax Exempt</h3>
                  <p class="text-sm text-spring-700 mt-2">
                    Vehicles are classified as wasting assets (predictable life of 50 years or less) and are completely exempt from Capital Gains Tax.
                    No Capital Gains Tax will be due regardless of the sale price.
                  </p>
                </div>
              </div>
            </div>

            <div v-else>
              <h3 class="text-lg font-semibold text-horizon-500 mb-4">Calculate Capital Gains Tax on Disposal</h3>
              <p class="text-sm text-neutral-500 mb-6">
                Enter the expected sale price to calculate potential Capital Gains Tax liability.
                Capital Gains Tax applies to personal valuables sold for over £6,000, with marginal relief available for sales between £6,000 and £15,000.
              </p>

              <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                <div>
                  <label class="block text-sm font-medium text-horizon-500 mb-1">Disposal Price</label>
                  <div class="relative">
                    <span class="absolute left-3 top-2 text-neutral-500">£</span>
                    <input
                      v-model.number="cgtForm.disposal_price"
                      type="number"
                      min="0"
                      step="0.01"
                      class="w-full pl-7 pr-3 py-2 border border-horizon-300 rounded-md focus:outline-none focus:ring-2 focus:ring-pink-500"
                      placeholder="0.00"
                    />
                  </div>
                </div>
                <div>
                  <label class="block text-sm font-medium text-horizon-500 mb-1">Disposal Costs</label>
                  <div class="relative">
                    <span class="absolute left-3 top-2 text-neutral-500">£</span>
                    <input
                      v-model.number="cgtForm.disposal_costs"
                      type="number"
                      min="0"
                      step="0.01"
                      class="w-full pl-7 pr-3 py-2 border border-horizon-300 rounded-md focus:outline-none focus:ring-2 focus:ring-pink-500"
                      placeholder="0.00"
                    />
                  </div>
                  <p class="text-xs text-neutral-500 mt-1">Agent fees, auction costs, etc.</p>
                </div>
              </div>

              <button
                @click="calculateCGT"
                :disabled="!cgtForm.disposal_price || calculatingCGT"
                class="px-4 py-2 bg-pink-600 text-white rounded-button hover:bg-pink-700 transition-colors disabled:opacity-50"
              >
                {{ calculatingCGT ? 'Calculating...' : 'Calculate Capital Gains Tax' }}
              </button>

              <!-- CGT Result -->
              <div v-if="cgtResult" class="mt-6">
                <div v-if="cgtResult.is_exempt" class="bg-savannah-100 rounded-lg p-6">
                  <h4 class="text-lg font-semibold text-spring-800">Capital Gains Tax Exempt</h4>
                  <p class="text-sm text-spring-700 mt-2">{{ cgtResult.exemption_reason }}</p>
                  <p class="text-2xl font-bold text-spring-600 mt-3">Capital Gains Tax Liability: £0</p>
                </div>

                <div v-else-if="cgtResult.is_loss" class="bg-savannah-100 rounded-lg p-6">
                  <h4 class="text-lg font-semibold text-violet-800">Capital Loss</h4>
                  <dl class="mt-4 space-y-2">
                    <div class="flex justify-between">
                      <dt class="text-sm text-neutral-500">Actual Loss</dt>
                      <dd class="text-sm font-medium text-raspberry-600">{{ formatCurrency(cgtResult.actual_loss) }}</dd>
                    </div>
                    <div v-if="cgtResult.loss_restriction_applied" class="flex justify-between">
                      <dt class="text-sm text-neutral-500">Allowable Loss (restricted)</dt>
                      <dd class="text-sm font-medium text-violet-600">{{ formatCurrency(cgtResult.allowable_loss) }}</dd>
                    </div>
                  </dl>
                  <p class="text-xs text-violet-700 mt-3">
                    Losses can be offset against other capital gains in the same tax year.
                  </p>
                </div>

                <div v-else class="bg-savannah-100 rounded-lg p-6 border border-light-gray">
                  <h4 class="text-lg font-semibold text-horizon-500 mb-4">Capital Gains Tax Calculation Breakdown</h4>
                  <dl class="space-y-3">
                    <div class="flex justify-between pb-2 border-b border-light-gray">
                      <dt class="text-sm text-neutral-500">Disposal Price</dt>
                      <dd class="text-sm font-medium text-horizon-500">{{ formatCurrency(cgtResult.disposal_price) }}</dd>
                    </div>
                    <div class="flex justify-between">
                      <dt class="text-sm text-neutral-500">Less: Acquisition Cost</dt>
                      <dd class="text-sm font-medium text-horizon-500">{{ formatCurrency(cgtResult.acquisition_cost) }}</dd>
                    </div>
                    <div class="flex justify-between">
                      <dt class="text-sm text-neutral-500">Less: Disposal Costs</dt>
                      <dd class="text-sm font-medium text-horizon-500">{{ formatCurrency(cgtResult.disposal_costs) }}</dd>
                    </div>
                    <div class="flex justify-between pt-2 border-t border-light-gray">
                      <dt class="text-sm text-neutral-500">Raw Gain</dt>
                      <dd class="text-sm font-bold text-horizon-500">{{ formatCurrency(cgtResult.raw_gain) }}</dd>
                    </div>
                    <div v-if="cgtResult.marginal_relief_applied" class="flex justify-between bg-savannah-100 p-2 rounded">
                      <dt class="text-sm text-violet-700">Marginal Relief Applied</dt>
                      <dd class="text-sm font-medium text-violet-700">Max gain: {{ formatCurrency(cgtResult.marginal_relief_max_gain) }}</dd>
                    </div>
                    <div class="flex justify-between">
                      <dt class="text-sm text-neutral-500">Less: Annual Exempt Amount</dt>
                      <dd class="text-sm font-medium text-horizon-500">{{ formatCurrency(cgtResult.annual_exempt_amount) }}</dd>
                    </div>
                    <div class="flex justify-between pt-2 border-t border-light-gray">
                      <dt class="text-sm text-neutral-500">Taxable Gain</dt>
                      <dd class="text-sm font-bold text-horizon-500">{{ formatCurrency(cgtResult.taxable_gain) }}</dd>
                    </div>
                    <div class="flex justify-between">
                      <dt class="text-sm text-neutral-500">Capital Gains Tax Rate</dt>
                      <dd class="text-sm font-medium text-horizon-500">{{ cgtResult.cgt_rate }}%</dd>
                    </div>
                    <div class="flex justify-between pt-3 border-t-2 border-horizon-300 mt-3">
                      <dt class="text-base font-semibold text-horizon-500">Capital Gains Tax Liability</dt>
                      <dd class="text-xl font-bold text-pink-600">{{ formatCurrency(cgtResult.cgt_liability) }}</dd>
                    </div>
                  </dl>
                </div>
              </div>
            </div>
          </div>

          <!-- Notes Tab -->
          <div v-if="activeTab === 'notes'" class="space-y-4">
            <h3 class="text-lg font-semibold text-horizon-500">Notes</h3>
            <div v-if="chattel.notes" class="bg-savannah-100 rounded-lg p-4">
              <p class="text-neutral-500 whitespace-pre-wrap">{{ chattel.notes }}</p>
            </div>
            <div v-else class="text-center py-8 text-neutral-500">
              No notes recorded for this item.
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Delete Confirmation -->
    <ConfirmDialog
      :show="showDeleteConfirm"
      title="Delete Valuable"
      message="Are you sure you want to delete this item? This action cannot be undone."
      @confirm="handleDelete"
      @cancel="showDeleteConfirm = false"
    />
  </div>
</template>

<script>
import { mapActions } from 'vuex';
import ConfirmDialog from '@/components/Common/ConfirmDialog.vue';
import { currencyMixin } from '@/mixins/currencyMixin';
import chattelService from '@/services/chattelService';

import logger from '@/utils/logger';
export default {
  name: 'ChattelDetailInline',

  mixins: [currencyMixin],

  components: {
    ConfirmDialog,
  },

  props: {
    chattelId: {
      type: [Number, String],
      required: true,
    },
  },

  emits: ['back', 'edit', 'deleted'],

  data() {
    return {
      chattel: null,
      loading: true,
      activeTab: 'overview',
      showDeleteConfirm: false,
      cgtForm: {
        disposal_price: null,
        disposal_costs: 0,
      },
      cgtResult: null,
      calculatingCGT: false,
      tabs: [
        { id: 'overview', label: 'Overview' },
        { id: 'cgt', label: 'Capital Gains Tax Calculator' },
        { id: 'notes', label: 'Notes' },
      ],
    };
  },

  computed: {
    vehicleDescription() {
      if (this.chattel?.chattel_type !== 'vehicle') return null;
      const parts = [];
      if (this.chattel.year) parts.push(this.chattel.year);
      if (this.chattel.make) parts.push(this.chattel.make);
      if (this.chattel.model) parts.push(this.chattel.model);
      return parts.length > 0 ? parts.join(' ') : null;
    },

    unrealisedGainLoss() {
      if (!this.chattel?.purchase_price || !this.chattel?.current_value) return null;
      return this.chattel.current_value - this.chattel.purchase_price;
    },

    gainLossClass() {
      if (this.unrealisedGainLoss === null) return 'text-horizon-400';
      return this.unrealisedGainLoss >= 0 ? 'text-spring-600' : 'text-raspberry-600';
    },
  },

  watch: {
    chattelId: {
      immediate: true,
      handler() {
        this.loadChattel();
      },
    },
  },

  methods: {
    ...mapActions('chattels', ['deleteChattel']),

    async loadChattel() {
      this.loading = true;
      this.cgtResult = null;

      try {
        const response = await chattelService.getChattel(this.chattelId);
        this.chattel = response.data || response;

        // Pre-fill CGT form with current value
        this.cgtForm.disposal_price = this.chattel.current_value;
      } catch (error) {
        logger.error('Failed to load chattel:', error);
      } finally {
        this.loading = false;
      }
    },

    formatChattelType(type) {
      const types = {
        vehicle: 'Vehicle',
        art: 'Art',
        antique: 'Antique',
        jewelry: 'Jewellery',
        collectible: 'Collectible',
        other: 'Other',
      };
      return types[type] || type;
    },

    getTypeBadgeClass(type) {
      const classes = {
        vehicle: 'badge-blue',
        art: 'badge-pink',
        antique: 'badge-blue',
        jewelry: 'badge-purple',
        collectible: 'badge-green',
        other: 'badge-gray',
      };
      return classes[type] || 'badge-gray';
    },

    formatOwnership(chattel) {
      if (chattel.ownership_type === 'joint' && chattel.joint_owner) {
        return `Joint with ${chattel.joint_owner.first_name || 'spouse'} (${chattel.ownership_percentage}%)`;
      }
      return 'Individual (100%)';
    },

    formatDate(date) {
      if (!date) return 'N/A';
      return new Date(date).toLocaleDateString('en-GB', {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
      });
    },

    confirmDelete() {
      this.showDeleteConfirm = true;
    },

    async handleDelete() {
      try {
        await this.deleteChattel(this.chattelId);
        this.showDeleteConfirm = false;
        this.$emit('deleted');
      } catch (error) {
        logger.error('Failed to delete chattel:', error);
      }
    },

    async calculateCGT() {
      if (!this.cgtForm.disposal_price) return;

      this.calculatingCGT = true;
      this.cgtResult = null;

      try {
        const response = await chattelService.calculateCGT(this.chattelId, {
          disposal_price: this.cgtForm.disposal_price,
          disposal_costs: this.cgtForm.disposal_costs || 0,
        });
        this.cgtResult = response.data || response;
      } catch (error) {
        logger.error('Failed to calculate CGT:', error);
      } finally {
        this.calculatingCGT = false;
      }
    },
  },
};
</script>

<style scoped>
.chattel-detail-inline {
  padding: 24px;
}

.badge {
  display: inline-block;
  padding: 4px 12px;
  border-radius: 6px;
  font-size: 12px;
  font-weight: 600;
}

.badge-blue {
  @apply bg-violet-100;
  @apply text-violet-800;
}

.badge-pink {
  @apply bg-pink-100;
  @apply text-rose-800;
}

.badge-blue {
  @apply bg-violet-100;
  @apply text-violet-800;
}

.badge-purple {
  @apply bg-purple-50;
  @apply text-purple-800;
}

.badge-green {
  @apply bg-spring-100;
  @apply text-spring-800;
}

.badge-gray {
  @apply bg-savannah-100;
  @apply text-neutral-500;
}

.badge-indigo {
  @apply bg-indigo-100;
  @apply text-indigo-800;
}
</style>
