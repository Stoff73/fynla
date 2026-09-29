<template>
  <div class="current-situation">
    <!-- No Protection Notice -->
    <div v-if="hasNoPolicies" class="bg-eggshell-500 rounded-lg p-6 mb-8">
      <div class="flex">
        <div class="flex-shrink-0">
          <svg class="h-6 w-6 text-violet-600" fill="currentColor" viewBox="0 0 20 20">
            <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
          </svg>
        </div>
        <div class="ml-3 flex-1">
          <h3 class="text-lg font-medium text-violet-800 mb-2">No Protection Coverage</h3>
          <p class="text-sm text-violet-700 mb-4">
            You currently have no protection policies recorded. Without adequate life insurance and protection coverage, your family may face financial difficulties if something unexpected happens.
          </p>
          <div class="bg-white rounded-lg p-4 border border-violet-300 mb-4">
            <h4 class="text-sm font-semibold text-horizon-500 mb-2">Why Protection is Important:</h4>
            <ul class="text-sm text-neutral-500 space-y-1 list-disc list-inside">
              <li>Replaces lost income if you're unable to work</li>
              <li>Covers outstanding debts and mortgages</li>
              <li>Provides financial security for dependents</li>
              <li>Protects your family's lifestyle and future plans</li>
            </ul>
          </div>
          <div>
            <button
              v-preview-disabled="'add'"
              @click="$emit('add-policy')"
              class="px-5 py-2.5 bg-raspberry-500 text-white rounded-button hover:bg-raspberry-600 transition-colors font-medium text-sm"
            >
              Add Protection
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Header with Add Button and Filters -->
    <div v-else class="mb-6">
      <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
        <div>
          <h3 class="text-lg font-semibold text-horizon-500">{{ totalPolicyCount === 1 ? 'Policy' : 'Policies' }}</h3>
        </div>

        <div class="flex gap-3">
          <button
            v-preview-disabled="'add'"
            @click="$emit('add-policy')"
            class="px-4 py-2 bg-raspberry-500 text-white font-medium rounded-button hover:bg-raspberry-600 transition-colors flex items-center gap-2"
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
            v-preview-disabled="'upload'"
            @click="showUploadModal = true"
            class="inline-flex items-center px-4 py-2 border-2 border-violet-600 text-violet-600 bg-white rounded-lg hover:bg-violet-50 transition-colors font-medium"
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

export default {
  name: 'ProtectionModuleOverview',

  emits: ['add-policy', 'edit-policy', 'refresh-data'],

  components: {
    PolicyCard,
    DocumentUploadModal,
  },

  data() {
    return {
      showUploadModal: false,
    };
  },

  computed: {
    ...mapState('protection', ['policies']),
    ...mapGetters('protection', ['allPolicies']),

    isPreviewMode() {
      return this.$store.getters['preview/isPreviewMode'];
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

  methods: {
    handleEditPolicy(policy) {
      this.$emit('edit-policy', policy);
    },

    closeUploadModal() {
      this.showUploadModal = false;
    },

    handleDocumentSaved() {
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
