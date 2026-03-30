<template>
  <AppLayout>
    <div class="protection-dashboard module-gradient py-2 sm:py-6">
      <ModuleStatusBar />
      <!-- Profile Completeness Alert -->
      <ProfileCompletenessAlert
        v-if="profileCompleteness && !loadingCompleteness"
        :completenessData="profileCompleteness"
        :dismissible="true"
      />
      <div class="max-w-7xl mx-auto">
      <!-- Header -->
      <div class="mb-6 sm:mb-8">
        <h1 class="text-xl sm:text-2xl lg:text-3xl font-bold text-horizon-500 mb-2">Protection Planning</h1>
        <p class="text-neutral-500">
          Analyse your protection coverage and identify gaps in your insurance portfolio
        </p>
      </div>

      <!-- Loading State -->
      <div v-if="loading" class="flex justify-center items-center py-12">
        <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-violet-600"></div>
      </div>

      <!-- Error State -->
      <div
        v-else-if="error"
        class="bg-raspberry-50 border-l-4 border-raspberry-500 p-4 mb-6"
      >
        <div class="flex">
          <div class="flex-shrink-0">
            <svg
              class="h-5 w-5 text-raspberry-400"
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
            <p class="text-sm text-raspberry-700">{{ error }}</p>
          </div>
        </div>
      </div>

      <!-- Life Events (protection-relevant events like redundancy/medical) -->
      <div v-else>
        <ModuleLifeEvents
          class="mb-6"
          module="protection"
          :events="lifeEvents"
          :impact-summary="lifeEventImpact"
        />

      <div class="bg-white rounded-lg border border-light-gray p-6">
        <CurrentSituation
          @add-policy="handleAddPolicy"
          @edit-policy="handleEditPolicy"
        />
      </div>
      </div> <!-- v-else -->

      <!-- Policy Form Modal -->
      <PolicyFormModal
        v-if="showForm"
        :policy="editingPolicy"
        :is-editing="!!editingPolicy"
        @close="closeForm"
        @save="handlePolicySaved"
      />
    </div>
    </div>
  </AppLayout>
</template>

<script>
import { mapState, mapActions } from 'vuex';
import AppLayout from '@/layouts/AppLayout.vue';
import CurrentSituation from '@/components/Protection/CurrentSituation.vue';
import PolicyFormModal from '@/components/Protection/PolicyFormModal.vue';
import ModuleLifeEvents from '@/components/Shared/ModuleLifeEvents.vue';
import ModuleStatusBar from '@/components/Shared/ModuleStatusBar.vue';
import protectionService from '@/services/protectionService';

import logger from '@/utils/logger';
export default {
  name: 'ProtectionDashboard',

  components: {
    AppLayout,
    CurrentSituation,
    PolicyFormModal,
    ModuleLifeEvents,
    ModuleStatusBar,
  },

  data() {
    return {
      showForm: false,
      editingPolicy: null,
    };
  },

  computed: {
    ...mapState('protection', ['loading', 'error', 'lifeEvents', 'lifeEventImpact']),

    isPreviewMode() {
      return this.$store.getters['preview/isPreviewMode'];
    },
  },

  watch: {
    '$store.state.aiFormFill.pendingFill'(fill) {
      if (fill && fill.entityType === 'protection_policy') {
        if (fill.mode === 'edit' && fill.entityId) {
          const policy = this.findPolicyById(fill.entityId);
          if (policy) {
            this.handleEditPolicy(policy);
          }
        } else {
          this.handleAddPolicy();
        }
      }
    },
  },

  mounted() {
    // Check for pending AI form fill (in case pendingFill was set before mount)
    const fill = this.$store.state.aiFormFill?.pendingFill;
    if (fill && fill.entityType === 'protection_policy') {
      if (fill.mode === 'edit' && fill.entityId) {
        const policy = this.findPolicyById(fill.entityId);
        if (policy) {
          this.handleEditPolicy(policy);
        }
      } else {
        this.handleAddPolicy();
      }
    }

    this.loadProtectionData();
  },

  methods: {
    ...mapActions('protection', ['fetchProtectionData']),

    async loadProtectionData() {
      try {
        await this.fetchProtectionData();
      } catch (error) {
        logger.error('Failed to load protection data:', error);
      }
    },

    findPolicyById(id) {
      const policies = this.$store.state.protection?.policies || {};
      const allPolicies = [
        ...(policies.life || []),
        ...(policies.criticalIllness || []),
        ...(policies.incomeProtection || []),
        ...(policies.disability || []),
        ...(policies.sicknessIllness || []),
      ];
      return allPolicies.find(p => p.id === id) || null;
    },

    findPolicyById(id) {
      const policies = this.$store.state.protection?.policies || {};
      const allPolicies = [
        ...(policies.life || []),
        ...(policies.criticalIllness || []),
        ...(policies.incomeProtection || []),
        ...(policies.disability || []),
        ...(policies.sicknessIllness || []),
      ];
      return allPolicies.find(p => p.id === id) || null;
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

        // Complete AI fill if this was an AI-driven save
        if (this.$store.state.aiFormFill.pendingFill) {
          this.$store.dispatch('aiFormFill/completeFill');
        }
        // Reload protection data to show the new/updated policy
        await this.fetchProtectionData();
        this.closeForm();
      } catch (error) {
        logger.error('Failed to save policy:', error);
        alert('Failed to save policy. Please try again.');
      }
    },
  },
};
</script>
