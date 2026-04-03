<template>
  <OnboardingStep
    title="Other Liabilities"
    description="Add details about loans, credit cards, and other debts"
    :can-go-back="true"
    :can-skip="true"
    :loading="loading"
    :error="error"
    @next="handleNext"
    @back="handleBack"
    @skip="handleSkip"
  >
    <div class="space-y-6">
      <!-- Added Liabilities List -->
      <div v-if="liabilities.length > 0" class="space-y-3">
        <h4 class="text-body font-medium text-horizon-500">
          Liabilities ({{ liabilities.length }})
        </h4>

        <div
          v-for="liability in liabilities"
          :key="liability.id"
          class="border border-light-gray rounded-lg p-4 bg-eggshell-500"
        >
          <div class="flex justify-between items-start">
            <div class="flex-1">
              <div class="flex items-center gap-2 mb-2">
                <h5 class="text-body font-medium text-horizon-500 capitalize">
                  {{ liability.liability_type?.replace(/_/g, ' ') }}
                </h5>
              </div>
              <p class="text-body-sm text-neutral-500">{{ liability.liability_name }}</p>
              <div class="mt-2">
                <p class="text-body-sm text-neutral-500">Balance</p>
                <p class="text-body font-medium text-horizon-500">{{ formatCurrency(liability.current_balance) }}</p>
              </div>
            </div>
            <div class="flex gap-2 ml-4">
              <button
                type="button"
                class="text-raspberry-500 hover:text-raspberry-700 text-body-sm"
                @click="editLiability(liability)"
              >
                Edit
              </button>
              <button
                type="button"
                class="text-raspberry-500 hover:text-raspberry-700 text-body-sm"
                @click="deleteLiability(liability.id)"
              >
                Delete
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- Add Liability Button (hidden when form is open) -->
      <div v-if="!showForm">
        <button
          type="button"
          class="inline-flex items-center px-4 py-2 bg-horizon-500 text-white rounded-button hover:bg-horizon-600 transition-colors text-sm font-medium w-full md:w-auto justify-center"
          @click="showAddForm"
        >
          + Add Liability
        </button>
      </div>

    </div>

    <!-- Liability Form (inline) -->
    <div v-if="showForm" class="mt-4">
      <LiabilityForm
        :liability="editingLiability"
        :mode="editingLiability ? 'edit' : 'create'"
        context="onboarding"
        @save="handleLiabilitySave"
        @cancel="closeLiabilityForm"
      />
    </div>
  </OnboardingStep>
</template>

<script>
// DEPRECATED: Will be replaced by unified form with context="onboarding". See life-stage-journey-design.md §11.7
import { ref, onMounted } from 'vue';
import OnboardingStep from '../OnboardingStep.vue';
import UsefulResources from '@/components/Onboarding/UsefulResources.vue';
import { STEP_RESOURCES } from '@/constants/onboardingLinks';
import LiabilityForm from '@/components/Estate/LiabilityForm.vue';
import estateService from '@/services/estateService';
import { formatCurrency } from '@/utils/currency';

import logger from '@/utils/logger';
export default {
  name: 'LiabilitiesStep',

  components: {
    OnboardingStep,
    UsefulResources,
    LiabilityForm,
  },

  emits: ['next', 'back', 'skip', 'sidebar-update'],

  setup(props, { emit }) {
    const liabilities = ref([]);
    const showForm = ref(false);
    const editingLiability = ref(null);
    const loading = ref(false);
    const error = ref(null);

    onMounted(async () => {
      await loadLiabilities();
    });

    async function loadLiabilities() {
      try {
        const response = await estateService.getEstateData();
        liabilities.value = response.data?.liabilities || [];
      } catch (err) {
        logger.error('Failed to load liabilities', err);
      }
    }

    const showAddForm = () => {
      showForm.value = true;
      editingLiability.value = null;
      window.scrollTo({ top: 0, behavior: 'smooth' });
    };

    const handleLiabilitySave = async (formData) => {
      try {
        if (editingLiability.value) {
          // Update existing liability
          await estateService.updateLiability(editingLiability.value.id, formData);
        } else {
          // Create new liability
          await estateService.createLiability(formData);
        }

        closeLiabilityForm();
        await loadLiabilities();
      } catch (err) {
        error.value = 'Failed to save liability';
        logger.error('Failed to save liability:', err);
      }
    };

    const editLiability = (liability) => {
      editingLiability.value = liability;
      showForm.value = true;
      window.scrollTo({ top: 0, behavior: 'smooth' });
    };

    const deleteLiability = async (id) => {
      if (confirm('Are you sure you want to remove this liability?')) {
        try {
          await estateService.deleteLiability(id);
          await loadLiabilities();
        } catch (err) {
          error.value = 'Failed to delete liability';
        }
      }
    };

    const closeLiabilityForm = () => {
      showForm.value = false;
      editingLiability.value = null;
    };

    const handleNext = () => {
      emit('next');
    };

    const handleBack = () => {
      emit('back');
    };

    const handleSkip = () => {
      emit('skip');
    };

    return {
      liabilities,
      showForm,
      editingLiability,
      loading,
      error,
      showAddForm,
      handleLiabilitySave,
      editLiability,
      deleteLiability,
      closeLiabilityForm,
      handleNext,
      handleBack,
      handleSkip,
      formatCurrency,
      STEP_RESOURCES,
    };
  },
};
</script>
