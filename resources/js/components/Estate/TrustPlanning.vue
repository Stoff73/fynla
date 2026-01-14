<template>
  <div class="space-y-6">
    <!-- Back to Dashboard Link -->
    <button
      @click="$emit('switch-tab', 'iht')"
      class="inline-flex items-center text-sm text-blue-600 hover:text-blue-800 mb-4"
    >
      <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
      </svg>
      Back to Estate Dashboard
    </button>
    <!-- Planned Trust Strategy Section -->
    <div class="mb-8">
      <div class="flex items-center justify-between mb-4">
        <div>
          <h2 class="text-xl sm:text-2xl font-bold text-gray-900">Planned Trust Strategy</h2>
          <p class="text-sm sm:text-base text-gray-600 mt-1">Explore trust planning options with CLT taxation rules</p>
        </div>
      </div>

      <!-- Trust Planning Strategy Component -->
      <TrustPlanningStrategy @navigate-to-assets="navigateToEstateAssets" />
    </div>

    <!-- Divider -->
    <div class="border-t-2 border-gray-200 my-8"></div>

    <!-- Actual Trusts Section Header -->
    <div class="mb-6">
      <h2 class="text-xl sm:text-2xl font-bold text-gray-900 mb-2">Trusts Created (Actual)</h2>
      <p class="text-sm sm:text-base text-gray-600">Track trusts you've actually established and monitor their Inheritance Tax impact</p>
    </div>

    <!-- Trust List -->
    <div class="bg-white shadow rounded-lg p-4 sm:p-6">
      <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center gap-3 sm:gap-0 mb-4">
        <h3 class="text-lg font-medium text-gray-900">Your Trusts</h3>
        <button
          @click="showTrustForm = true"
          class="w-full sm:w-auto px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded-md hover:bg-blue-700"
        >
          + Add Trust
        </button>
      </div>

      <div v-if="loading" class="text-center py-8">
        <p class="text-gray-500">Loading trusts...</p>
      </div>

      <div v-else-if="trusts.length === 0" class="text-center py-8">
        <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
        </svg>
        <p class="mt-2 text-sm text-gray-500">No trusts recorded yet</p>
        <p class="text-xs text-gray-400 mt-1">Click "Add Trust" to record your first trust</p>
      </div>

      <div v-else class="space-y-4">
        <div
          v-for="trust in trusts"
          :key="trust.id"
          class="border border-gray-200 rounded-lg p-4 hover:border-blue-300 transition-colors"
        >
          <div class="flex justify-between items-start">
            <div class="flex-1">
              <div class="flex items-center space-x-3">
                <h4 class="text-base font-semibold text-gray-900">{{ trust.trust_name }}</h4>
                <span :class="getTrustTypeBadgeClass(trust.trust_type)" class="px-2 py-1 text-xs font-medium rounded">
                  {{ getTrustTypeName(trust.trust_type) }}
                </span>
                <span v-if="!trust.is_active" class="px-2 py-1 text-xs font-medium bg-gray-200 text-gray-600 rounded">
                  Inactive
                </span>
              </div>
              <p class="text-sm text-gray-600 mt-1">Created: {{ formatDate(trust.trust_creation_date) }}</p>
            </div>
            <div class="flex items-center space-x-2">
              <button
                @click="editTrust(trust)"
                class="text-blue-600 hover:text-blue-800 text-sm font-medium"
              >
                Edit
              </button>
              <button
                @click="deleteTrustConfirm(trust)"
                class="text-red-600 hover:text-red-800 text-sm font-medium"
              >
                Delete
              </button>
            </div>
          </div>

          <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 sm:gap-4 mt-4">
            <div>
              <p class="text-xs text-gray-500">Initial Value</p>
              <p class="text-sm font-medium text-gray-900">{{ formatCurrency(trust.initial_value) }}</p>
            </div>
            <div>
              <p class="text-xs text-gray-500">Current Value</p>
              <p class="text-sm font-medium text-gray-900">{{ formatCurrency(trust.current_value) }}</p>
            </div>
            <div>
              <p class="text-xs text-gray-500">Growth</p>
              <p class="text-sm font-medium" :class="getGrowthClass(trust)">
                {{ formatCurrency(trust.current_value - trust.initial_value) }}
              </p>
            </div>
            <div>
              <p class="text-xs text-gray-500">Inheritance Tax Value in Estate</p>
              <p class="text-sm font-medium text-gray-900">{{ formatCurrency(getTrustIHTValue(trust)) }}</p>
            </div>
          </div>

          <!-- Type-specific details -->
          <div v-if="trust.trust_type === 'discounted_gift' && trust.discount_amount" class="mt-3 pt-3 border-t border-gray-200">
            <p class="text-xs text-amber-700">
              <strong>Discount:</strong> {{ formatCurrency(trust.discount_amount) }}
              | <strong>Annual Income:</strong> {{ formatCurrency(trust.retained_income_annual) }}
            </p>
          </div>

          <div v-if="trust.trust_type === 'loan' && trust.loan_amount" class="mt-3 pt-3 border-t border-gray-200">
            <p class="text-xs text-green-700">
              <strong>Loan Balance:</strong> {{ formatCurrency(trust.loan_amount) }}
              <span v-if="trust.loan_interest_bearing">| Interest Rate: {{ parseFloat(trust.loan_interest_rate).toFixed(2) }}%</span>
            </p>
          </div>

          <div v-if="trust.trust_type === 'life_insurance' && trust.sum_assured" class="mt-3 pt-3 border-t border-gray-200">
            <p class="text-xs text-purple-700">
              <strong>Sum Assured:</strong> {{ formatCurrency(trust.sum_assured) }}
              | <strong>Annual Premium:</strong> {{ formatCurrency(trust.annual_premium) }}
            </p>
          </div>

          <div v-if="trust.beneficiaries || trust.trustees" class="mt-3 pt-3 border-t border-gray-200">
            <p v-if="trust.beneficiaries" class="text-xs text-gray-600"><strong>Beneficiaries:</strong> {{ trust.beneficiaries }}</p>
            <p v-if="trust.trustees" class="text-xs text-gray-600 mt-1"><strong>Trustees:</strong> {{ trust.trustees }}</p>
          </div>
        </div>
      </div>
    </div>

    <!-- Trust Recommendations -->
    <div v-if="recommendations.length > 0" class="bg-white shadow rounded-lg p-6">
      <h3 class="text-lg font-medium text-gray-900 mb-4">Trust Recommendations</h3>
      <p class="text-sm text-gray-600 mb-4">
        Based on your estate value of {{ formatCurrency(estateValue) }} and Inheritance Tax liability of {{ formatCurrency(ihtLiability) }}, consider these trust strategies:
      </p>

      <div class="space-y-4">
        <div
          v-for="(rec, index) in recommendations"
          :key="index"
          class="border rounded-lg p-4"
          :class="getPriorityBorderClass(rec.priority)"
        >
          <div class="flex items-start space-x-3">
            <div :class="getPriorityBadgeClass(rec.priority)" class="px-2 py-1 text-xs font-semibold rounded uppercase">
              {{ rec.priority }}
            </div>
            <div class="flex-1">
              <h4 class="text-sm font-semibold text-gray-900">{{ getTrustTypeName(rec.trust_type) }}</h4>
              <p class="text-xs text-gray-600 mt-1">{{ rec.reason }}</p>
              <p class="text-xs text-gray-500 mt-1 italic">{{ rec.description }}</p>

              <div v-if="rec.benefits" class="mt-3">
                <p class="text-xs font-medium text-gray-700">Benefits:</p>
                <ul class="list-disc list-inside text-xs text-gray-600 mt-1">
                  <li v-for="(benefit, bIndex) in rec.benefits" :key="bIndex">{{ benefit }}</li>
                </ul>
              </div>

              <div v-if="rec.drawbacks" class="mt-3">
                <p class="text-xs font-medium text-red-700">Important Considerations:</p>
                <ul class="list-disc list-inside text-xs text-red-600 mt-1">
                  <li v-for="(drawback, dIndex) in rec.drawbacks" :key="dIndex">{{ drawback }}</li>
                </ul>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Trust Form Modal -->
    <TrustForm
      v-if="showTrustForm"
      :trust="selectedTrust"
      @save="handleSaveTrust"
      @close="closeTrustForm"
    />

    <!-- Delete Confirmation -->
    <div v-if="trustToDelete" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50" @click.self="trustToDelete = null">
      <div class="relative top-20 mx-4 sm:mx-auto p-5 border max-w-sm sm:w-96 shadow-lg rounded-md bg-white">
        <h3 class="text-lg font-medium text-gray-900 mb-4">Confirm Delete</h3>
        <p class="text-sm text-gray-600 mb-4">
          Are you sure you want to delete "{{ trustToDelete.trust_name }}"? This action cannot be undone.
        </p>
        <div class="flex justify-end space-x-3">
          <button
            @click="trustToDelete = null"
            class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50"
          >
            Cancel
          </button>
          <button
            @click="deleteTrust"
            class="px-4 py-2 text-sm font-medium text-white bg-red-600 border border-transparent rounded-md hover:bg-red-700"
          >
            Delete
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import { mapState, mapActions } from 'vuex';
import TrustForm from './TrustForm.vue';
import TrustPlanningStrategy from './TrustPlanningStrategy.vue';
import estateService from '@/services/estateService';
import { currencyMixin } from '@/mixins/currencyMixin';

export default {
  name: 'TrustPlanning',
  mixins: [currencyMixin],

  components: {
    TrustForm,
    TrustPlanningStrategy,
  },

  data() {
    return {
      loading: false,
      showTrustForm: false,
      selectedTrust: null,
      trustToDelete: null,
      recommendations: [],
      estateValue: 0,
      ihtLiability: 0,
    };
  },

  computed: {
    ...mapState('estate', ['trusts']),
    isPreviewMode() {
      return this.$store.getters['preview/isPreviewMode'];
    },
  },

  mounted() {
    // Preview users are real DB users - use normal API to fetch their data
    this.loadTrusts();
    this.loadRecommendations();
  },

  methods: {
    ...mapActions('estate', ['fetchTrusts', 'createTrust', 'updateTrust', 'removeTrust']),

    async loadTrusts() {
      this.loading = true;
      try {
        await this.fetchTrusts();
      } catch (error) {
        console.error('Error loading trusts:', error);
      } finally {
        this.loading = false;
      }
    },

    async loadRecommendations() {
      try {
        const response = await estateService.getTrustRecommendations();
        if (response.success) {
          this.recommendations = response.data.recommendations;
          this.estateValue = response.data.estate_value;
          this.ihtLiability = response.data.iht_liability;
        }
      } catch (error) {
        console.error('Error loading recommendations:', error);
      }
    },

    editTrust(trust) {
      this.selectedTrust = trust;
      this.showTrustForm = true;
    },

    closeTrustForm() {
      this.showTrustForm = false;
      this.selectedTrust = null;
    },

    async handleSaveTrust(trustData) {
      try {
        if (this.selectedTrust) {
          // Update existing trust
          await this.updateTrust({ id: this.selectedTrust.id, data: trustData });
        } else {
          // Create new trust
          await this.createTrust(trustData);
        }
        this.closeTrustForm();
        await this.loadRecommendations(); // Refresh recommendations
      } catch (error) {
        console.error('Error saving trust:', error);
      }
    },

    deleteTrustConfirm(trust) {
      this.trustToDelete = trust;
    },

    async deleteTrust() {
      try {
        await this.removeTrust(this.trustToDelete.id);
        this.trustToDelete = null;
        await this.loadRecommendations(); // Refresh recommendations
      } catch (error) {
        console.error('Error deleting trust:', error);
      }
    },

    getTrustTypeName(type) {
      const names = {
        bare: 'Bare Trust',
        interest_in_possession: 'Interest in Possession',
        discretionary: 'Discretionary',
        accumulation_maintenance: 'A&M Trust',
        life_insurance: 'Life Insurance',
        discounted_gift: 'Discounted Gift',
        loan: 'Loan Trust',
        mixed: 'Mixed',
        settlor_interested: 'Settlor-Interested',
      };
      return names[type] || type;
    },

    getTrustTypeBadgeClass(type) {
      const classes = {
        bare: 'bg-blue-100 text-blue-800',
        interest_in_possession: 'bg-green-100 text-green-800',
        discretionary: 'bg-purple-100 text-purple-800',
        accumulation_maintenance: 'bg-indigo-100 text-indigo-800',
        life_insurance: 'bg-red-100 text-red-800',
        discounted_gift: 'bg-amber-100 text-amber-800',
        loan: 'bg-teal-100 text-teal-800',
        mixed: 'bg-gray-100 text-gray-800',
        settlor_interested: 'bg-pink-100 text-pink-800',
      };
      return classes[type] || 'bg-gray-100 text-gray-800';
    },

    getPriorityBadgeClass(priority) {
      const classes = {
        high: 'bg-red-100 text-red-800',
        medium: 'bg-amber-100 text-amber-800',
        low: 'bg-blue-100 text-blue-800',
      };
      return classes[priority] || 'bg-gray-100 text-gray-800';
    },

    getPriorityBorderClass(priority) {
      const classes = {
        high: 'border-red-300 bg-red-50',
        medium: 'border-amber-300 bg-amber-50',
        low: 'border-blue-300 bg-blue-50',
      };
      return classes[priority] || 'border-gray-300';
    },

    getGrowthClass(trust) {
      const growth = trust.current_value - trust.initial_value;
      return growth >= 0 ? 'text-green-600' : 'text-red-600';
    },

    getTrustIHTValue(trust) {
      // Simplified calculation - should match backend logic
      switch (trust.trust_type) {
        case 'discounted_gift':
          return trust.discount_amount || 0;
        case 'loan':
          return trust.loan_amount || 0;
        case 'interest_in_possession':
          return trust.current_value;
        default:
          return 0;
      }
    },

    formatDate(date) {
      return new Date(date).toLocaleDateString('en-GB', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
      });
    },

    navigateToEstateAssets() {
      // Navigate to estate planning assets section
      this.$router.push({ path: '/estate', query: { tab: 'assets' } });
    },
  },
};
</script>

<style scoped>
/* Additional styles if needed */
</style>
