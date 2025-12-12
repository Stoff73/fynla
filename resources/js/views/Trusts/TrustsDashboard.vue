<template>
  <AppLayout>
    <div class="py-2 sm:py-0">
      <!-- Header -->
      <div class="mb-6">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
          <div>
            <h1 class="font-display text-2xl sm:text-h1 text-gray-900">Trusts Dashboard</h1>
            <p class="text-body text-gray-600 mt-2">
              Manage your trusts and track inheritance tax implications
            </p>
          </div>
          <button
            @click="openCreateTrustModal"
            class="btn-primary flex items-center justify-center w-full sm:w-auto flex-shrink-0"
          >
            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
            </svg>
            Create Trust
          </button>
        </div>
      </div>

      <!-- Loading State -->
      <div v-if="loading" class="flex justify-center items-center py-12">
        <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-primary-600"></div>
      </div>

      <!-- Error State -->
      <div v-else-if="error" class="bg-red-50 border border-red-200 rounded-lg p-4 mb-6">
        <p class="text-red-800">{{ error }}</p>
      </div>

      <!-- Content -->
      <div v-else>
        <!-- Summary Cards -->
        <div class="grid grid-cols-2 md:grid-cols-4 gap-3 sm:gap-6 mb-6">
          <div class="card">
            <p class="text-sm text-gray-600 mb-1">Active Trusts</p>
            <p class="text-3xl font-bold text-gray-900">{{ activeTrusts.length }}</p>
            <p class="text-xs text-gray-500 mt-1">{{ inactiveTrusts.length }} inactive</p>
          </div>

          <div class="card">
            <p class="text-sm text-gray-600 mb-1">Total Trust Value</p>
            <p class="text-3xl font-bold text-gray-900">{{ formatCurrency(totalTrustValue) }}</p>
            <p class="text-xs text-gray-500 mt-1">Across all trusts</p>
          </div>

          <div class="card">
            <p class="text-sm text-gray-600 mb-1">Assets in Trusts</p>
            <p class="text-3xl font-bold text-gray-900">{{ totalAssets }}</p>
            <p class="text-xs text-gray-500 mt-1">Properties, investments, cash</p>
          </div>

          <div class="card">
            <p class="text-sm text-gray-600 mb-1">Upcoming Charges</p>
            <p class="text-3xl font-bold text-gray-900">{{ upcomingChargesData.length }}</p>
            <p class="text-xs text-gray-500 mt-1">In next 12 months</p>
          </div>
        </div>

        <!-- Filter Tabs -->
        <div class="border-b border-gray-200 mb-6">
          <nav class="flex overflow-x-auto scrollbar-hide space-x-4 sm:space-x-8 -webkit-overflow-scrolling-touch">
            <button
              v-for="filter in filters"
              :key="filter.id"
              @click="activeFilter = filter.id"
              :class="[
                'py-3 sm:py-4 px-1 border-b-2 font-medium text-xs sm:text-sm transition-colors whitespace-nowrap flex-shrink-0',
                activeFilter === filter.id
                  ? 'border-purple-600 text-purple-600'
                  : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'
              ]"
            >
              {{ filter.label }}
              <span v-if="filter.count !== undefined" class="ml-1 sm:ml-2 px-1.5 sm:px-2 py-0.5 sm:py-1 text-xs rounded-full bg-gray-100">
                {{ filter.count }}
              </span>
            </button>
          </nav>
        </div>

        <!-- Trusts List -->
        <div v-if="filteredTrusts.length > 0" class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
          <TrustCard
            v-for="trust in filteredTrusts"
            :key="trust.id"
            :trust="trust"
            @view="viewTrustDetail"
            @edit="editTrust"
            @calculate-iht="calculateTrustIHT"
          />
        </div>

        <!-- Empty State -->
        <div v-else class="card text-center py-12">
          <svg class="mx-auto h-12 w-12 text-gray-400 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
          </svg>
          <h3 class="text-lg font-medium text-gray-900 mb-2">No trusts found</h3>
          <p class="text-gray-600 mb-4">Get started by creating your first trust</p>
          <button @click="openCreateTrustModal" class="btn-primary">
            Create Trust
          </button>
        </div>

        <!-- Upcoming Tax Returns & Charges -->
        <div v-if="upcomingChargesData.length > 0 || taxReturnsData.length > 0" class="card mb-6">
          <h2 class="text-h2 text-gray-900 mb-4">Upcoming Tax Events</h2>

          <!-- Periodic Charges -->
          <div v-if="upcomingChargesData.length > 0" class="mb-6">
            <h3 class="text-h3 text-gray-900 mb-3">Periodic Charges (10-Year Anniversary)</h3>
            <div class="overflow-x-auto">
              <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                  <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Trust Name</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Charge Date</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Trust Value</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Estimated Charge</th>
                  </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                  <tr v-for="charge in upcomingChargesData" :key="charge.trust_id">
                    <td class="px-6 py-4 text-sm font-medium text-gray-900">{{ charge.trust_name }}</td>
                    <td class="px-6 py-4 text-sm text-gray-600">
                      {{ formatDate(charge.charge_date) }}
                      <span class="text-xs text-gray-500">({{ charge.months_until_charge }} months)</span>
                    </td>
                    <td class="px-6 py-4 text-sm text-gray-900">{{ formatCurrency(charge.trust_value) }}</td>
                    <td class="px-6 py-4 text-sm font-semibold text-red-600">{{ formatCurrency(charge.estimated_charge) }}</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>

          <!-- Tax Returns -->
          <div v-if="taxReturnsData.length > 0">
            <h3 class="text-h3 text-gray-900 mb-3">Tax Return Due Dates</h3>
            <div class="space-y-2">
              <div
                v-for="taxReturn in taxReturnsData"
                :key="taxReturn.trust_id"
                :class="[
                  'flex items-center justify-between p-3 rounded-lg',
                  taxReturn.is_overdue ? 'bg-red-50 border border-red-200' : 'bg-gray-50 border border-gray-200'
                ]"
              >
                <div>
                  <p class="font-medium text-gray-900">{{ taxReturn.trust_name }}</p>
                  <p class="text-sm text-gray-600">{{ taxReturn.trust_type }}</p>
                </div>
                <div class="text-right">
                  <p :class="taxReturn.is_overdue ? 'text-red-600 font-semibold' : 'text-gray-900'">
                    {{ formatDate(taxReturn.return_due_date) }}
                  </p>
                  <p class="text-xs text-gray-500">
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
    </div>
  </AppLayout>
</template>

<script>
import { mapState, mapActions } from 'vuex';
import axios from '@/bootstrap';
import AppLayout from '@/layouts/AppLayout.vue';
import TrustCard from '@/components/Trusts/TrustCard.vue';
import TrustFormModal from '@/components/Trusts/TrustFormModal.vue';

export default {
  name: 'TrustsDashboard',

  components: {
    AppLayout,
    TrustCard,
    TrustFormModal,
  },

  data() {
    return {
      loading: false,
      error: null,
      activeFilter: 'all',
      showTrustModal: false,
      selectedTrust: null,
      upcomingChargesData: [],
      taxReturnsData: [],
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
      // This would be calculated from aggregated assets
      const total = this.safeTrusts.reduce((sum, trust) => {
        const count = parseInt(trust.asset_count || 0);
        return sum + (isNaN(count) ? 0 : count);
      }, 0);
      return isNaN(total) ? 0 : total;
    },

    filters() {
      return [
        { id: 'all', label: 'All Trusts', count: this.safeTrusts.length },
        { id: 'active', label: 'Active', count: this.activeTrusts.length },
        { id: 'rpt', label: 'Relevant Property', count: this.relevantPropertyTrusts.length },
        { id: 'inactive', label: 'Inactive', count: this.inactiveTrusts.length },
      ];
    },

    filteredTrusts() {
      switch (this.activeFilter) {
        case 'active':
          return this.activeTrusts;
        case 'rpt':
          return this.relevantPropertyTrusts;
        case 'inactive':
          return this.inactiveTrusts;
        default:
          return this.safeTrusts;
      }
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
      // Disabled - endpoint returns 500, not ready yet
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
        // Keep modal open so user can see the error and fix it
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
        console.log('[TrustsDashboard] Preview mode - skipping calculateTrustIHT');
        return;
      }
      try {
        const response = await this.$http.post(`/api/estate/trusts/${trust.id}/calculate-iht-impact`);
        if (response.data.success) {
          // Show results in a modal or navigate to detail view
          this.$router.push(`/trusts/${trust.id}?tab=tax`);
        }
      } catch (error) {
        this.error = error.message || 'Failed to calculate IHT impact';
      }
    },

    formatCurrency(value) {
      return new Intl.NumberFormat('en-GB', {
        style: 'currency',
        currency: 'GBP',
        minimumFractionDigits: 0,
        maximumFractionDigits: 0,
      }).format(value);
    },

    formatDate(dateString) {
      if (!dateString) return '-';
      return new Date(dateString).toLocaleDateString('en-GB', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
      });
    },
  },
};
</script>

<style scoped>
/* Hide scrollbar for horizontal tab navigation */
.scrollbar-hide {
  -ms-overflow-style: none;
  scrollbar-width: none;
}
.scrollbar-hide::-webkit-scrollbar {
  display: none;
}

/* Smooth scrolling on iOS */
.-webkit-overflow-scrolling-touch {
  -webkit-overflow-scrolling: touch;
}
</style>
