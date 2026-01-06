<template>
  <AppLayout>
    <div class="protection-dashboard py-2 sm:py-6">
      <div class="max-w-7xl mx-auto">
      <!-- Header -->
      <div class="mb-6 sm:mb-8">
        <h1 class="text-xl sm:text-2xl lg:text-3xl font-bold text-gray-900 mb-2">Protection Planning</h1>
        <p class="text-gray-600">
          Analyse your protection coverage and identify gaps in your insurance portfolio
        </p>
      </div>

      <!-- Profile Completeness Alert -->
      <ProfileCompletenessAlert
        v-if="profileCompleteness && !loadingCompleteness"
        :completenessData="profileCompleteness"
        :dismissible="true"
      />

      <!-- Loading State -->
      <div v-if="loading" class="flex justify-center items-center py-12">
        <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-600"></div>
      </div>

      <!-- Error State -->
      <div
        v-else-if="error"
        class="bg-red-50 border-l-4 border-red-500 p-4 mb-6"
      >
        <div class="flex">
          <div class="flex-shrink-0">
            <svg
              class="h-5 w-5 text-red-400"
              xmlns="http://www.w3.org/2000/svg"
              viewBox="0 0 20 20"
              fill="currentColor"
            >
              <path
                fill-rule="evenodd"
                d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z"
                clip-rule="evenodd"
              />
            </svg>
          </div>
          <div class="ml-3">
            <p class="text-sm text-red-700">{{ error }}</p>
          </div>
        </div>
      </div>

      <!-- Main Content -->
      <div v-else class="bg-white rounded-lg shadow">
        <!-- Tab Navigation -->
        <div class="border-b border-gray-200">
          <nav class="-mb-px flex overflow-x-auto" aria-label="Tabs">
            <button
              v-for="tab in tabs"
              :key="tab.id"
              @click="activeTab = tab.id"
              :class="[
                activeTab === tab.id
                  ? 'border-blue-500 text-blue-600'
                  : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300',
                'whitespace-nowrap py-3 sm:py-4 px-3 sm:px-6 border-b-2 font-medium text-xs sm:text-sm transition-colors duration-200 flex-shrink-0',
              ]"
            >
              {{ tab.label }}
            </button>
          </nav>
        </div>

        <!-- Tab Content -->
        <div class="p-6">
          <!-- Current Situation Tab -->
          <CurrentSituation
            v-if="activeTab === 'current'"
            @add-policy="handleAddPolicy"
            @edit-policy="handleEditPolicy"
          />

          <!-- Gap Analysis Tab -->
          <GapAnalysis
            v-else-if="activeTab === 'gaps'"
            @add-policy="handleAddPolicy"
          />

          <!-- Strategy Tab -->
          <Recommendations v-else-if="activeTab === 'recommendations'" />
        </div>
      </div>
      </div>

      <!-- Policy Form Modal -->
      <PolicyFormModal
        v-if="showForm"
        :policy="editingPolicy"
        :is-editing="!!editingPolicy"
        @close="closeForm"
        @save="handlePolicySaved"
      />
    </div>
  </AppLayout>
</template>

<script>
import { mapState, mapActions } from 'vuex';
import AppLayout from '@/layouts/AppLayout.vue';
import CurrentSituation from '@/components/Protection/CurrentSituation.vue';
import GapAnalysis from '@/components/Protection/GapAnalysis.vue';
import Recommendations from '@/components/Protection/Recommendations.vue';
import ProfileCompletenessAlert from '@/components/Shared/ProfileCompletenessAlert.vue';
import PolicyFormModal from '@/components/Protection/PolicyFormModal.vue';
import protectionService from '@/services/protectionService';
import api from '@/services/api';

export default {
  name: 'ProtectionDashboard',

  components: {
    AppLayout,
    CurrentSituation,
    GapAnalysis,
    Recommendations,
    ProfileCompletenessAlert,
    PolicyFormModal,
  },

  data() {
    return {
      activeTab: 'current',
      tabs: [
        { id: 'current', label: 'Policy Overview' },
        { id: 'gaps', label: 'Gap Analysis' },
        { id: 'recommendations', label: 'Strategy' },
      ],
      profileCompleteness: null,
      loadingCompleteness: false,
      showForm: false,
      editingPolicy: null,
    };
  },

  computed: {
    ...mapState('protection', ['loading', 'error']),

    isPreviewMode() {
      return this.$store.getters['preview/isPreviewMode'];
    },
  },

  mounted() {
    this.loadProtectionData();
    // Skip profile completeness in preview mode
    if (!this.isPreviewMode) {
      this.loadProfileCompleteness();
    }
  },

  methods: {
    ...mapActions('protection', ['fetchProtectionData']),

    async loadProtectionData() {
      try {
        await this.fetchProtectionData();
      } catch (error) {
        console.error('Failed to load protection data:', error);
      }
    },

    async loadProfileCompleteness() {
      this.loadingCompleteness = true;
      try {
        const response = await api.get('/user/profile/completeness');
        this.profileCompleteness = response.data.data;
      } catch (error) {
        console.error('Failed to load profile completeness:', error);
      } finally {
        this.loadingCompleteness = false;
      }
    },

    handleAddPolicy() {
      this.editingPolicy = null;
      this.showForm = true;
    },

    handleEditPolicy(policy) {
      this.editingPolicy = policy;
      this.showForm = true;
    },

    closeForm() {
      this.showForm = false;
      this.editingPolicy = null;
    },

    async handlePolicySaved(policyData) {
      try {
        const { policyType, ...actualPolicyData } = policyData;

        // Call the appropriate API endpoint based on policy type
        switch (policyType) {
          case 'life':
            if (this.editingPolicy) {
              await protectionService.updateLifePolicy(this.editingPolicy.id, actualPolicyData);
            } else {
              await protectionService.createLifePolicy(actualPolicyData);
            }
            break;
          case 'criticalIllness':
            if (this.editingPolicy) {
              await protectionService.updateCriticalIllnessPolicy(this.editingPolicy.id, actualPolicyData);
            } else {
              await protectionService.createCriticalIllnessPolicy(actualPolicyData);
            }
            break;
          case 'incomeProtection':
            if (this.editingPolicy) {
              await protectionService.updateIncomeProtectionPolicy(this.editingPolicy.id, actualPolicyData);
            } else {
              await protectionService.createIncomeProtectionPolicy(actualPolicyData);
            }
            break;
          case 'disability':
            if (this.editingPolicy) {
              await protectionService.updateDisabilityPolicy(this.editingPolicy.id, actualPolicyData);
            } else {
              await protectionService.createDisabilityPolicy(actualPolicyData);
            }
            break;
          case 'sicknessIllness':
            if (this.editingPolicy) {
              await protectionService.updateSicknessIllnessPolicy(this.editingPolicy.id, actualPolicyData);
            } else {
              await protectionService.createSicknessIllnessPolicy(actualPolicyData);
            }
            break;
        }

        // Reload protection data to show the new/updated policy
        await this.fetchProtectionData();
        this.closeForm();
      } catch (error) {
        console.error('Failed to save policy:', error);
        alert('Failed to save policy. Please try again.');
      }
    },
  },
};
</script>

<style scoped>
/* Mobile optimization for tab navigation */
@media (max-width: 640px) {
  .protection-dashboard nav[aria-label="Tabs"] button {
    font-size: 0.875rem;
    padding-left: 1rem;
    padding-right: 1rem;
  }
}

/* Smooth scroll for tab navigation on mobile */
nav[aria-label="Tabs"] {
  -webkit-overflow-scrolling: touch;
  scrollbar-width: none;
}

nav[aria-label="Tabs"]::-webkit-scrollbar {
  display: none;
}
</style>
