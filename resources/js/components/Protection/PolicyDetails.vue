<template>
  <div class="policy-details">
    <!-- Header with Add Button -->
    <div class="mb-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
      <div>
        <h3 class="text-lg font-semibold text-gray-900">Policy Portfolio</h3>
        <p class="text-sm text-gray-600 mt-1">
          {{ totalPolicyCount }} {{ totalPolicyCount === 1 ? 'policy' : 'policies' }}
        </p>
      </div>

      <button
        v-preview-disabled="'add'"
        @click="showAddPolicyModal = true"
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
    </div>

    <!-- Filter by Policy Type -->
    <div class="mb-6 flex gap-2 overflow-x-auto pb-2">
      <button
        v-for="filter in policyFilters"
        :key="filter.value"
        @click="selectedFilter = filter.value"
        :class="[
          'px-4 py-2 rounded-lg text-sm font-medium transition-colors whitespace-nowrap',
          selectedFilter === filter.value
            ? 'bg-blue-600 text-white'
            : 'bg-gray-100 text-gray-600 hover:bg-gray-200',
        ]"
      >
        {{ filter.label }}
        <span
          v-if="filter.count > 0"
          class="ml-1.5 px-2 py-0.5 rounded-full text-xs"
          :class="[
            selectedFilter === filter.value
              ? 'bg-blue-500'
              : 'bg-gray-200',
          ]"
        >
          {{ filter.count }}
        </span>
      </button>
    </div>

    <!-- Policy Cards List -->
    <div v-if="filteredPolicies.length > 0" class="space-y-4">
      <PolicyCard
        v-for="policy in filteredPolicies"
        :key="`${policy.policy_type}-${policy.id}`"
        :policy="policy"
        @edit="handleEdit"
        @delete="handleDelete"
      />
    </div>

    <!-- Empty State -->
    <div v-else class="text-center py-12 bg-white rounded-lg border border-gray-200">
      <svg
        class="mx-auto h-12 w-12 text-gray-400"
        fill="none"
        viewBox="0 0 24 24"
        stroke="currentColor"
      >
        <path
          stroke-linecap="round"
          stroke-linejoin="round"
          stroke-width="2"
          d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"
        />
      </svg>
      <h3 class="mt-2 text-sm font-medium text-gray-900">No policies found</h3>
      <p class="mt-1 text-sm text-gray-500">
        {{ emptyStateMessage }}
      </p>
      <button
        v-preview-disabled="'add'"
        @click="showAddPolicyModal = true"
        class="mt-4 px-4 py-2 bg-blue-600 text-white font-medium rounded-lg hover:bg-blue-700 transition-colors"
      >
        Add Your First Policy
      </button>
    </div>

    <!-- Add Policy Modal -->
    <PolicyFormModal
      v-if="showAddPolicyModal"
      @close="closeAddModal"
      @save="handleAddPolicy"
    />

    <!-- Edit Policy Modal -->
    <PolicyFormModal
      v-if="showEditPolicyModal"
      :policy="editingPolicy"
      :is-editing="true"
      @close="showEditPolicyModal = false"
      @save="handleUpdatePolicy"
    />

    <!-- Delete Confirmation Modal -->
    <ConfirmationModal
      v-if="showDeleteModal"
      title="Delete Policy"
      message="Are you sure you want to delete this policy? This action cannot be undone."
      confirm-text="Delete"
      confirm-class="bg-red-600 hover:bg-red-700"
      @confirm="confirmDelete"
      @cancel="showDeleteModal = false"
    />
  </div>
</template>

<script>
import { mapState, mapGetters, mapActions } from 'vuex';
import PolicyCard from './PolicyCard.vue';
import PolicyFormModal from './PolicyFormModal.vue';
import ConfirmationModal from '../Common/ConfirmationModal.vue';

export default {
  name: 'PolicyDetails',

  components: {
    PolicyCard,
    PolicyFormModal,
    ConfirmationModal,
  },

  props: {
    showModal: {
      type: Boolean,
      default: false,
    },
  },

  data() {
    return {
      selectedFilter: 'all',
      showAddPolicyModal: false,
      showEditPolicyModal: false,
      showDeleteModal: false,
      editingPolicy: null,
      deletingPolicy: null,
    };
  },

  watch: {
    showModal(newVal) {
      if (newVal) {
        this.showAddPolicyModal = true;
      }
    },
  },

  computed: {
    ...mapState('protection', ['policies']),
    ...mapGetters('protection', ['allPolicies']),

    policyFilters() {
      return [
        { value: 'all', label: 'All Policies', count: this.allPolicies.length },
        { value: 'life', label: 'Life Insurance', count: this.policies.life?.length || 0 },
        { value: 'criticalIllness', label: 'Critical Illness', count: this.policies.criticalIllness?.length || 0 },
        { value: 'incomeProtection', label: 'Income Protection', count: this.policies.incomeProtection?.length || 0 },
        { value: 'disability', label: 'Disability', count: this.policies.disability?.length || 0 },
        { value: 'sicknessIllness', label: 'Sickness/Illness', count: this.policies.sicknessIllness?.length || 0 },
      ];
    },

    filteredPolicies() {
      if (this.selectedFilter === 'all') {
        return this.allPolicies;
      }

      return this.allPolicies.filter(
        policy => policy.policy_type === this.selectedFilter
      );
    },

    totalPolicyCount() {
      return this.allPolicies.length;
    },

    emptyStateMessage() {
      if (this.selectedFilter === 'all') {
        return 'Get started by adding your first insurance policy.';
      }
      const filterLabel = this.policyFilters.find(f => f.value === this.selectedFilter)?.label;
      return `No ${filterLabel} policies found. Add one to get started.`;
    },
  },

  methods: {
    ...mapActions('protection', ['createPolicy', 'updatePolicy', 'deletePolicy']),

    closeAddModal() {
      this.showAddPolicyModal = false;
      this.$emit('modal-closed'); // Notify parent that modal was closed
    },

    handleEdit(policy) {
      this.editingPolicy = policy;
      this.showEditPolicyModal = true;
    },

    handleDelete(policy) {
      this.deletingPolicy = policy;
      this.showDeleteModal = true;
    },

    async handleAddPolicy(policyData) {
      try {
        // Extract policyType from the data and pass correctly to store action
        const { policyType, ...actualPolicyData } = policyData;
        await this.createPolicy({ policyType, policyData: actualPolicyData });
        this.showAddPolicyModal = false;
        this.$emit('modal-closed'); // Notify parent that modal was closed
        // Show success notification (you can implement a toast notification system)
      } catch (error) {
        console.error('Failed to add policy:', error);
        console.error('Validation errors:', error.response?.data?.errors);
        console.error('Full error:', error.response?.data);
        console.error('Sent data:', policyData);
        // Show error notification
      }
    },

    async handleUpdatePolicy(policyData) {
      try {
        await this.updatePolicy({
          policyType: this.editingPolicy.policy_type,
          id: this.editingPolicy.id,
          policyData,
        });
        this.showEditPolicyModal = false;
        this.editingPolicy = null;
        // Show success notification
      } catch (error) {
        console.error('Failed to update policy:', error);
        // Show error notification
      }
    },

    async confirmDelete() {
      try {
        await this.deletePolicy({
          policyType: this.deletingPolicy.policy_type,
          id: this.deletingPolicy.id,
        });
        this.showDeleteModal = false;
        this.deletingPolicy = null;
        // Show success notification
      } catch (error) {
        console.error('Failed to delete policy:', error);
        // Show error notification
      }
    },
  },
};
</script>

<style scoped>
/* Hide scrollbar but allow scrolling for filter buttons on mobile */
.overflow-x-auto {
  -webkit-overflow-scrolling: touch;
  scrollbar-width: none;
}

.overflow-x-auto::-webkit-scrollbar {
  display: none;
}
</style>
