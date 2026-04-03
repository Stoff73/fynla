<template>
  <OnboardingStep
    title="Protection Policies"
    description="Add details about your life insurance and protection coverage"
    :can-go-back="true"
    :can-skip="true"
    :loading="loading"
    :error="error"
    @next="handleNext"
    @back="handleBack"
    @skip="handleSkip"
  >
    <div class="space-y-6">
      <!-- I have no policies checkbox -->
      <div class="border border-light-gray rounded-lg p-4 bg-violet-50">
        <label class="flex items-start gap-3 cursor-pointer">
          <input
            v-model="hasNoPolicies"
            type="checkbox"
            class="mt-1 h-4 w-4 text-violet-600 focus:ring-violet-500 border-horizon-300 rounded"
            @change="handleNoPoliciesChange"
          >
          <div>
            <span class="text-body font-medium text-horizon-500">
              I have no protection policies in place
            </span>
            <p class="text-body-sm text-neutral-500 mt-1">
              Check this if you don't currently have any life insurance, critical illness, income protection, or other protection policies. We'll help you understand what coverage you might need in the Protection module.
            </p>
          </div>
        </label>
      </div>

      <!-- Added Policies List -->
      <div v-if="policies.length > 0" class="space-y-3">
        <h4 class="text-body font-medium text-horizon-500">
          Policies ({{ policies.length }})
        </h4>

        <div
          v-for="policy in policies"
          :key="policy.id"
          class="border border-light-gray rounded-lg p-4 bg-eggshell-500"
        >
          <div class="flex justify-between items-start">
            <div class="flex-1">
              <div class="flex items-center gap-2 mb-2">
                <h5 class="text-body font-medium text-horizon-500">
                  {{ getPolicyTypeLabel(policy.policyType || policy.policy_type) }}
                </h5>
                <span class="text-body-sm px-2 py-0.5 bg-violet-100 text-violet-700 rounded">
                  {{ policy.provider }}
                </span>
                <!-- Life Policy Type Tag (only for life insurance) -->
                <span
                  v-if="isLifeInsurancePolicy(policy) && policy.life_policy_type"
                  class="text-body-sm px-2 py-0.5 bg-spring-100 text-spring-700 rounded"
                >
                  {{ getLifePolicyTypeLabel(policy.life_policy_type) }}
                </span>
              </div>
              <div class="mt-2 grid grid-cols-2 md:grid-cols-3 gap-2">
                <div>
                  <p class="text-body-sm text-neutral-500">{{ getCoverageLabel(policy.policyType || policy.policy_type) }}</p>
                  <p class="text-body font-medium text-horizon-500">{{ formatCurrency(policy.coverage_amount || policy.sum_assured || policy.benefit_amount || 0) }}</p>
                </div>
                <div>
                  <p class="text-body-sm text-neutral-500">Premium</p>
                  <p class="text-body font-medium text-horizon-500">
                    {{ formatCurrency(policy.premium_amount) }} {{ policy.premium_frequency === 'monthly' ? 'pm' : 'pa' }}
                  </p>
                </div>
                <div v-if="policy.policy_number">
                  <p class="text-body-sm text-neutral-500">Policy Number</p>
                  <p class="text-body text-horizon-500">{{ policy.policy_number }}</p>
                </div>
              </div>
            </div>
            <div class="flex gap-2 ml-4">
              <button
                type="button"
                class="text-raspberry-500 hover:text-raspberry-700 text-body-sm"
                @click="editPolicy(policy)"
              >
                Edit
              </button>
              <button
                type="button"
                class="text-raspberry-500 hover:text-raspberry-700 text-body-sm"
                @click="deletePolicy(policy)"
              >
                Remove
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- Add Policy Buttons (hidden when form is open) -->
      <div v-if="!hasNoPolicies && !showForm" class="flex flex-wrap gap-3">
        <button
          type="button"
          class="inline-flex items-center px-4 py-2 bg-horizon-500 text-white rounded-button hover:bg-horizon-600 transition-colors text-sm font-medium"
          @click="showForm = true; $nextTick(() => window.scrollTo({ top: 0, behavior: 'smooth' }))"
        >
          + Add Protection Policy
        </button>
        <button
          v-preview-disabled="'upload'"
          type="button"
          class="inline-flex items-center px-4 py-2 bg-light-blue-200 text-horizon-500 rounded-button hover:bg-light-blue-300 transition-colors text-sm font-medium"
          @click="showUploadModal = true"
        >
          <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
          </svg>
          Upload Document
        </button>
      </div>

      <p v-if="hasNoPolicies" class="text-body-sm text-spring-700 bg-spring-50 p-3 rounded-lg">
        You've indicated you have no protection policies. The Protection module will help you understand your protection needs and recommend suitable coverage.
      </p>

    </div>

    <!-- Policy Form (inline) -->
    <PolicyFormModal
      v-if="showForm"
      :policy="editingPolicy"
      :is-editing="!!editingPolicy"
      context="onboarding"
      @close="closeForm"
      @save="handlePolicySaved"
    />

    <!-- Document Upload Modal -->
    <DocumentUploadModal
      v-if="showUploadModal"
      document-type="insurance_policy"
      @close="closeUploadModal"
      @saved="handleDocumentSaved"
      @manual-entry="closeUploadModal(); showForm = true;"
    />
  </OnboardingStep>
</template>

<script>
import { ref, onMounted } from 'vue';
import OnboardingStep from '../OnboardingStep.vue';
import UsefulResources from '@/components/Onboarding/UsefulResources.vue';
import { STEP_RESOURCES } from '@/constants/onboardingLinks';
import PolicyFormModal from '@/components/Protection/PolicyFormModal.vue';
import DocumentUploadModal from '@/components/Shared/DocumentUploadModal.vue';
import protectionService from '@/services/protectionService';
import { formatCurrency } from '@/utils/currency';

import logger from '@/utils/logger';
export default {
  name: 'ProtectionPoliciesStep',

  components: {
    OnboardingStep,
    UsefulResources,
    PolicyFormModal,
    DocumentUploadModal,
  },

  emits: ['next', 'back', 'skip', 'sidebar-update'],

  setup(props, { emit }) {
    const policies = ref([]);
    const showForm = ref(false);
    const showUploadModal = ref(false);
    const editingPolicy = ref(null);
    const loading = ref(false);
    const error = ref(null);
    const hasNoPolicies = ref(false);

    const getPolicyTypeLabel = (type) => {
      const labels = {
        life: 'Life Insurance',
        criticalIllness: 'Critical Illness',
        incomeProtection: 'Income Protection',
        disability: 'Disability',
        sicknessIllness: 'Sickness/Illness',
      };
      return labels[type] || type;
    };

    const isLifeInsurancePolicy = (policy) => {
      const type = policy.policyType || policy.policy_type;
      return type === 'life';
    };

    const getLifePolicyTypeLabel = (lifePolicyType) => {
      const labels = {
        decreasing_term: 'Decreasing Term',
        level_term: 'Level Term',
        whole_of_life: 'Whole of Life',
      };
      return labels[lifePolicyType] || lifePolicyType;
    };

    const getCoverageLabel = (type) => {
      if (type === 'life' || type === 'criticalIllness') {
        return 'Sum Assured';
      }
      return 'Benefit Amount';
    };

    onMounted(async () => {
      await loadPolicies();
    });

    async function loadPolicies() {
      try {
        const response = await protectionService.getProtectionData();

        // Combine all policy types into single array
        const allPolicies = [];

        // Response structure: response.data.policies contains the policies
        const data = response.data || response;
        const policyData = data.policies || {};

        // API returns snake_case keys: life_insurance, critical_illness, etc.
        if (policyData?.life_insurance && Array.isArray(policyData.life_insurance)) {
          allPolicies.push(...policyData.life_insurance.map(p => ({
            ...p,
            policyType: 'life',
            life_policy_type: p.policy_type, // Preserve the actual life policy type
            policy_type: 'life' // Override for general policy type
          })));
        }
        if (policyData?.critical_illness && Array.isArray(policyData.critical_illness)) {
          allPolicies.push(...policyData.critical_illness.map(p => ({ ...p, policyType: 'criticalIllness', policy_type: 'criticalIllness' })));
        }
        if (policyData?.income_protection && Array.isArray(policyData.income_protection)) {
          allPolicies.push(...policyData.income_protection.map(p => ({ ...p, policyType: 'incomeProtection', policy_type: 'incomeProtection' })));
        }
        if (policyData?.disability && Array.isArray(policyData.disability)) {
          allPolicies.push(...policyData.disability.map(p => ({ ...p, policyType: 'disability', policy_type: 'disability' })));
        }
        if (policyData?.sickness_illness && Array.isArray(policyData.sickness_illness)) {
          allPolicies.push(...policyData.sickness_illness.map(p => ({ ...p, policyType: 'sicknessIllness', policy_type: 'sicknessIllness' })));
        }

        policies.value = allPolicies;

        // Load has_no_policies flag from protection profile
        if (data?.profile) {
          hasNoPolicies.value = data.profile.has_no_policies || false;
        }
      } catch (err) {
        logger.error('Failed to load policies', err);
        error.value = 'Failed to load policies';
      }
    }

    async function handleNoPoliciesChange() {
      try {
        loading.value = true;
        await protectionService.updateHasNoPolicies(hasNoPolicies.value);

        // If user checks "no policies", disable adding policies
        if (hasNoPolicies.value) {
          showForm.value = false;
        }
      } catch (err) {
        error.value = 'Failed to update protection preferences';
        logger.error('Failed to update has_no_policies:', err);
        // Revert checkbox on error
        hasNoPolicies.value = !hasNoPolicies.value;
      } finally {
        loading.value = false;
      }
    }

    function editPolicy(policy) {
      editingPolicy.value = policy;
      showForm.value = true;
      window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    async function deletePolicy(policy) {
      if (!confirm('Are you sure you want to remove this policy?')) {
        return;
      }

      try {
        const policyType = policy.policyType || policy.policy_type;

        switch (policyType) {
          case 'life':
            await protectionService.deleteLifePolicy(policy.id);
            break;
          case 'criticalIllness':
            await protectionService.deleteCriticalIllnessPolicy(policy.id);
            break;
          case 'incomeProtection':
            await protectionService.deleteIncomeProtectionPolicy(policy.id);
            break;
          case 'disability':
            await protectionService.deleteDisabilityPolicy(policy.id);
            break;
          case 'sicknessIllness':
            await protectionService.deleteSicknessIllnessPolicy(policy.id);
            break;
        }

        await loadPolicies();
      } catch (err) {
        error.value = 'Failed to delete policy';
      }
    }

    function closeForm() {
      showForm.value = false;
      editingPolicy.value = null;
    }

    async function handlePolicySaved(policyData) {
      try {
        error.value = null;

        const { policyType, ...actualPolicyData } = policyData;

        // Call the appropriate API endpoint based on policy type
        switch (policyType) {
          case 'life':
            if (editingPolicy.value) {
              await protectionService.updateLifePolicy(editingPolicy.value.id, actualPolicyData);
            } else {
              await protectionService.createLifePolicy(actualPolicyData);
            }
            break;
          case 'criticalIllness':
            if (editingPolicy.value) {
              await protectionService.updateCriticalIllnessPolicy(editingPolicy.value.id, actualPolicyData);
            } else {
              await protectionService.createCriticalIllnessPolicy(actualPolicyData);
            }
            break;
          case 'incomeProtection':
            if (editingPolicy.value) {
              await protectionService.updateIncomeProtectionPolicy(editingPolicy.value.id, actualPolicyData);
            } else {
              await protectionService.createIncomeProtectionPolicy(actualPolicyData);
            }
            break;
          case 'disability':
            if (editingPolicy.value) {
              await protectionService.updateDisabilityPolicy(editingPolicy.value.id, actualPolicyData);
            } else {
              await protectionService.createDisabilityPolicy(actualPolicyData);
            }
            break;
          case 'sicknessIllness':
            if (editingPolicy.value) {
              await protectionService.updateSicknessIllnessPolicy(editingPolicy.value.id, actualPolicyData);
            } else {
              await protectionService.createSicknessIllnessPolicy(actualPolicyData);
            }
            break;
        }

        // If user adds a policy, automatically uncheck "has_no_policies"
        if (!editingPolicy.value && hasNoPolicies.value) {
          hasNoPolicies.value = false;
          await protectionService.updateHasNoPolicies(false);
        }

        closeForm();
        await loadPolicies();
      } catch (err) {
        error.value = 'Failed to save policy';
      }
    }

    const handleNext = () => {
      emit('next');
    };

    const handleBack = () => {
      emit('back');
    };

    const handleSkip = () => {
      emit('skip', 'protection_policies');
    };

    const handleDocumentSaved = async (savedData) => {
      showUploadModal.value = false;

      // If user uploads a policy doc, automatically uncheck "has_no_policies"
      if (hasNoPolicies.value) {
        hasNoPolicies.value = false;
        await protectionService.updateHasNoPolicies(false);
      }

      // Reload policies to show the newly created one
      await loadPolicies();
    };

    const closeUploadModal = () => {
      showUploadModal.value = false;
    };

    return {
      policies,
      showForm,
      showUploadModal,
      editingPolicy,
      loading,
      error,
      hasNoPolicies,
      getPolicyTypeLabel,
      isLifeInsurancePolicy,
      getLifePolicyTypeLabel,
      getCoverageLabel,
      editPolicy,
      deletePolicy,
      closeForm,
      handlePolicySaved,
      handleNoPoliciesChange,
      handleNext,
      handleBack,
      handleSkip,
      handleDocumentSaved,
      closeUploadModal,
      formatCurrency,
      STEP_RESOURCES,
    };
  },
};
</script>
