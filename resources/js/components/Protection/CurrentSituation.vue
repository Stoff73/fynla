<template>
  <div class="current-situation">
    <!-- No Protection Notice -->
    <div v-if="hasNoPolicies" class="bg-amber-50 border border-amber-200 rounded-lg p-6 mb-8">
      <div class="flex">
        <div class="flex-shrink-0">
          <svg class="h-6 w-6 text-amber-600" fill="currentColor" viewBox="0 0 20 20">
            <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
          </svg>
        </div>
        <div class="ml-3 flex-1">
          <h3 class="text-lg font-medium text-amber-800 mb-2">No Protection Coverage</h3>
          <p class="text-sm text-amber-700 mb-4">
            You currently have no protection policies recorded. Without adequate life insurance and protection coverage, your family may face financial difficulties if something unexpected happens.
          </p>
          <div class="bg-white rounded-lg p-4 border border-amber-300 mb-4">
            <h4 class="text-sm font-semibold text-gray-900 mb-2">Why Protection is Important:</h4>
            <ul class="text-sm text-gray-700 space-y-1 list-disc list-inside">
              <li>Replaces lost income if you're unable to work</li>
              <li>Covers outstanding debts and mortgages</li>
              <li>Provides financial security for dependents</li>
              <li>Protects your family's lifestyle and future plans</li>
            </ul>
          </div>
          <div class="space-y-4">
            <div class="flex gap-3">
              <button
                @click="$router.push('/protection')"
                class="px-4 py-2 bg-amber-600 text-white rounded-md hover:bg-amber-700 transition-colors font-medium text-sm"
              >
                View Gap Analysis →
              </button>
              <button
                @click="$emit('add-policy')"
                class="px-4 py-2 bg-white text-amber-600 border border-amber-600 rounded-md hover:bg-amber-50 transition-colors font-medium text-sm"
              >
                I Have Protection to Add
              </button>
            </div>

            <!-- I Don't Have Protection Checkbox -->
            <div class="flex items-start pt-2 border-t border-amber-200">
              <div class="flex items-center h-5">
                <input
                  id="has_no_policies"
                  v-model="hasNoPoliciesChecked"
                  type="checkbox"
                  class="h-4 w-4 text-amber-600 border-gray-300 rounded focus:ring-amber-500"
                  @change="updateHasNoPoliciesFlag"
                />
              </div>
              <div class="ml-3 text-sm">
                <label for="has_no_policies" class="font-medium text-gray-700 cursor-pointer">
                  I currently have no protection policies
                </label>
                <p class="text-gray-600 text-xs mt-1">
                  Check this box if you don't have any life insurance or protection coverage. This will mark your protection profile as complete, but we strongly recommend considering protection for your family's financial security.
                </p>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Header with Add Button and Filters -->
    <div v-else class="mb-6">
      <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
        <div>
          <h3 class="text-lg font-semibold text-gray-900">{{ totalPolicyCount === 1 ? 'Policy' : 'Policies' }}</h3>
        </div>

        <div class="flex gap-3">
          <button
            @click="$emit('add-policy')"
            class="px-4 py-2 bg-blue-600 text-white font-medium rounded-lg hover:bg-blue-700 transition-colors flex items-center gap-2"
          >
            <svg
              xmlns="http://www.w3.org/2000/svg"
              class="h-5 w-5"
              fill="none"
              viewBox="0 0 24 24"
              stroke="currentColor"
            >
              <path
                stroke-linecap="round"
                stroke-linejoin="round"
                stroke-width="2"
                d="M12 4v16m8-8H4"
              />
            </svg>
            Add New Policy
          </button>
          <button
            @click="showUploadModal = true"
            class="inline-flex items-center px-4 py-2 border-2 border-blue-600 text-blue-600 bg-white rounded-lg hover:bg-blue-50 transition-colors font-medium"
          >
            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
            </svg>
            Upload Document
          </button>
        </div>
      </div>

    </div>

    <!-- Policy Cards Grid -->
    <div v-if="filteredPolicies.length > 0" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 mb-8">
      <PolicyCard
        v-for="policy in filteredPolicies"
        :key="`${policy.policy_type}-${policy.id}`"
        :policy="policy"
        @edit="handleEditPolicy"
      />
    </div>


    <!-- Coverage Summary -->
    <div v-if="!hasNoPolicies" class="bg-white rounded-lg border border-gray-200 p-4 sm:p-6">
      <h3 class="text-lg font-semibold text-gray-900 mb-4">Coverage Summary</h3>
      <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-4 sm:gap-6">
        <div class="text-center">
          <div class="text-xl sm:text-2xl lg:text-3xl font-bold mb-1" :class="debtCoverageColour">
            {{ debtCoveragePercent }}%
          </div>
          <div class="text-xs sm:text-sm text-gray-600">Debt Coverage</div>
          <div class="text-xs text-gray-400 hidden sm:block">{{ formatCurrency(debtCoverage) }} / {{ formatCurrency(totalDebt) }}</div>
        </div>
        <div class="text-center">
          <div class="text-xl sm:text-2xl lg:text-3xl font-bold mb-1" :class="incomeProtectedColour">
            {{ incomeProtectedPercent }}%
          </div>
          <div class="text-xs sm:text-sm text-gray-600">Income Protected</div>
          <div class="text-xs text-gray-400 hidden sm:block">{{ formatCurrency(incomeProtected) }} / {{ formatCurrency(annualIncome) }} p.a.</div>
        </div>
        <div class="text-center">
          <div class="text-xl sm:text-2xl lg:text-3xl font-bold text-pink-600 mb-1">
            {{ formatCurrency(criticalIllnessCover) }}
          </div>
          <div class="text-xs sm:text-sm text-gray-600">Critical Illness</div>
          <div class="text-xs text-gray-400 hidden sm:block">lump sum</div>
        </div>
        <div class="text-center">
          <div class="text-xl sm:text-2xl lg:text-3xl font-bold text-purple-600 mb-1">
            {{ formatCurrency(sicknessCover) }}
          </div>
          <div class="text-xs sm:text-sm text-gray-600">Sickness Cover</div>
          <div class="text-xs text-gray-400 hidden sm:block">per year</div>
        </div>
        <div class="text-center">
          <div class="text-xl sm:text-2xl lg:text-3xl font-bold text-amber-600 mb-1">
            {{ formatCurrency(disabilityCover) }}
          </div>
          <div class="text-xs sm:text-sm text-gray-600">Disability Cover</div>
          <div class="text-xs text-gray-400 hidden sm:block">per year</div>
        </div>
      </div>
    </div>

    <!-- Document Upload Modal -->
    <DocumentUploadModal
      v-if="showUploadModal"
      document-type="insurance_policy"
      @close="closeUploadModal"
      @saved="handleDocumentSaved"
      @manual-entry="closeUploadModal(); $emit('add-policy');"
    />
  </div>
</template>

<script>
import { mapState, mapGetters } from 'vuex';
import PolicyCard from './PolicyCard.vue';
import DocumentUploadModal from '@/components/Shared/DocumentUploadModal.vue';
import protectionService from '@/services/protectionService';
import userProfileService from '@/services/userProfileService';
import { currencyMixin } from '@/mixins/currencyMixin';

export default {
  name: 'CurrentSituation',

  mixins: [currencyMixin],

  components: {
    PolicyCard,
    DocumentUploadModal,
  },

  data() {
    return {
      hasNoPoliciesChecked: false,
      showUploadModal: false,
      fetchedTotalDebt: 0,
    };
  },

  async mounted() {
    // Fetch liabilities for debt coverage calculation
    await this.fetchLiabilities();
  },

  computed: {
    ...mapState('protection', ['policies', 'profile', 'analysis']),
    ...mapGetters('protection', [
      'allPolicies',
    ]),

    isPreviewMode() {
      return this.$store.getters['preview/isPreviewMode'];
    },

    // Get user from auth store for fallback income data
    authUser() {
      return this.$store.state.auth?.user || {};
    },

    hasNoPolicies() {
      // Check if all policy types have zero policies
      const totalPolicies =
        (this.policies.life?.length || 0) +
        (this.policies.criticalIllness?.length || 0) +
        (this.policies.incomeProtection?.length || 0) +
        (this.policies.disability?.length || 0) +
        (this.policies.sicknessIllness?.length || 0);
      return totalPolicies === 0;
    },

    totalDebt() {
      // Use fetched liabilities from user profile (same as User Profile page shows)
      return this.fetchedTotalDebt || 0;
    },

    annualIncome() {
      // Gross annual income from coverage gap analysis, or fallback to auth user
      return this.analysis?.data?.needs?.gross_income ||
             this.analysis?.needs?.gross_income ||
             parseFloat(this.profile?.annual_income || 0) ||
             parseFloat(this.authUser?.annual_employment_income || 0) +
             parseFloat(this.authUser?.annual_self_employment_income || 0) ||
             0;
    },

    debtCoverage() {
      // Life insurance coverage for debt protection
      return this.policies.life?.reduce((sum, policy) => {
        return sum + parseFloat(policy.sum_assured || 0);
      }, 0) || 0;
    },

    debtCoveragePercent() {
      if (this.totalDebt === 0) return 0;
      return Math.round((this.debtCoverage / this.totalDebt) * 100);
    },

    debtCoverageColour() {
      if (this.totalDebt === 0) return 'text-green-600';
      if (this.debtCoveragePercent >= 100) return 'text-green-600';
      if (this.debtCoveragePercent >= 75) return 'text-amber-600';
      return 'text-red-600';
    },

    incomeProtected() {
      // Annual benefit from income protection policies
      return this.policies.incomeProtection?.reduce((sum, policy) => {
        const benefit = parseFloat(policy.benefit_amount || 0);
        const frequency = policy.benefit_frequency || 'monthly';
        if (frequency === 'monthly') return sum + (benefit * 12);
        if (frequency === 'weekly') return sum + (benefit * 52);
        return sum + benefit;
      }, 0) || 0;
    },

    incomeProtectedPercent() {
      if (this.annualIncome === 0) return 0;
      return Math.round((this.incomeProtected / this.annualIncome) * 100);
    },

    incomeProtectedColour() {
      if (this.annualIncome === 0) return 'text-gray-600';
      // Target is typically 50-70% of income
      if (this.incomeProtectedPercent >= 50) return 'text-green-600';
      if (this.incomeProtectedPercent >= 25) return 'text-amber-600';
      return 'text-red-600';
    },

    criticalIllnessCover() {
      // Lump sum from critical illness policies
      return this.policies.criticalIllness?.reduce((sum, policy) => {
        return sum + parseFloat(policy.sum_assured || 0);
      }, 0) || 0;
    },

    sicknessCover() {
      // Annual benefit from sickness/illness policies
      return this.policies.sicknessIllness?.reduce((sum, policy) => {
        const benefit = parseFloat(policy.benefit_amount || 0);
        const frequency = policy.benefit_frequency || 'monthly';
        if (frequency === 'monthly') return sum + (benefit * 12);
        if (frequency === 'weekly') return sum + (benefit * 52);
        return sum + benefit;
      }, 0) || 0;
    },

    disabilityCover() {
      // Annual benefit from disability policies
      return this.policies.disability?.reduce((sum, policy) => {
        const benefit = parseFloat(policy.benefit_amount || 0);
        const frequency = policy.benefit_frequency || 'monthly';
        if (frequency === 'monthly') return sum + (benefit * 12);
        if (frequency === 'weekly') return sum + (benefit * 52);
        return sum + benefit;
      }, 0) || 0;
    },

    totalPolicyCount() {
      return this.allPolicies?.length || 0;
    },

    filteredPolicies() {
      const policies = [...(this.allPolicies || [])];
      // Sort by coverage (high to low)
      policies.sort((a, b) => {
        const aValue = a.sum_assured || a.benefit_amount || 0;
        const bValue = b.sum_assured || b.benefit_amount || 0;
        return bValue - aValue;
      });
      return policies;
    },
  },

  watch: {
    profile: {
      handler(newProfile) {
        // Sync checkbox state with profile data when it loads or changes
        if (newProfile && typeof newProfile.has_no_policies !== 'undefined') {
          this.hasNoPoliciesChecked = newProfile.has_no_policies;
        }
      },
      immediate: true,
    },
  },

  methods: {
    async fetchLiabilities() {
      try {
        const response = await userProfileService.getProfile();
        // API returns { success: true, data: { liabilities_summary: { total: ... } } }
        this.fetchedTotalDebt = response.data?.liabilities_summary?.total || 0;
      } catch (error) {
        console.warn('Failed to fetch liabilities for coverage summary:', error);
        this.fetchedTotalDebt = 0;
      }
    },

    async updateHasNoPoliciesFlag() {
      if (this.isPreviewMode) {
        return;
      }
      try {

        // Call the API directly using protectionService and WAIT for it to complete
        const response = await protectionService.updateHasNoPolicies(this.hasNoPoliciesChecked);


        // Wait a moment to ensure the database transaction is committed
        await new Promise(resolve => setTimeout(resolve, 500));

        // Force page reload to refresh all completeness calculations
        window.location.reload();
      } catch (error) {
        console.error('Failed to update has_no_policies flag:', error);
        alert('Failed to update profile. Please try again.');
        // Revert checkbox on error
        this.hasNoPoliciesChecked = !this.hasNoPoliciesChecked;
      }
    },

    handleEditPolicy(policy) {
      this.$emit('edit-policy', policy);
    },

    closeUploadModal() {
      this.showUploadModal = false;
    },

    handleDocumentSaved(savedData) {
      this.showUploadModal = false;
      // Emit event to parent to refresh data
      this.$emit('refresh-data');
    },
  },
};
</script>

<style scoped>
/* Responsive adjustments */
@media (max-width: 640px) {
  .current-situation .grid {
    gap: 1rem;
  }
}
</style>
