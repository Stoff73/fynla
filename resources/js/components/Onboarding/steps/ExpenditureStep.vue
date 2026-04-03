<template>
  <OnboardingStep
    title="Household Expenditure"
    description="Help us understand your spending patterns for accurate financial planning"
    :can-go-back="true"
    :can-skip="false"
    :loading="loading"
    :error="error"
    @next="handleNext"
    @back="handleBack"
  >
    <!-- Shared Expenditure Form -->
    <ExpenditureForm
      ref="formRef"
      :initial-data="initialData"
      :spouse-data="spouseData"
      :spouse-name="spouseName"
      :is-married="isMarried"
      :always-show-tabs="false"
      :show-buttons="false"
      :start-in-edit-mode="true"
      :show-budget-tabs="false"
      :is-onboarding="true"
      @save="handleFormSave"
    />


    <!-- Skip Section Modal -->
    <div v-if="showSkipModal" class="fixed inset-0 z-50 overflow-y-auto">
      <div class="flex min-h-screen items-center justify-center p-4">
        <div class="fixed inset-0 bg-eggshell-5000 bg-opacity-75 transition-opacity" @click="showSkipModal = false"></div>
        <div class="relative bg-white rounded-lg shadow-xl max-w-lg w-full p-6">
          <div class="mb-4">
            <div class="flex items-center justify-center w-12 h-12 mx-auto bg-violet-100 rounded-full mb-4">
              <svg class="w-6 h-6 text-violet-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
              </svg>
            </div>
            <h3 class="text-lg font-semibold text-horizon-500 text-center">Skip Expenditure Section?</h3>
          </div>

          <div class="space-y-4 text-body-sm text-neutral-500">
            <p>No expenditure information has been entered. This section will be skipped for now.</p>

            <p class="font-medium text-horizon-500">Your expenditure data helps us provide accurate analysis for:</p>
            <ul class="list-disc list-inside space-y-1 ml-2">
              <li>Affordability assessments and budget planning</li>
              <li>Risk tolerance evaluation</li>
              <li>Investment, retirement and savings strategies</li>
              <li>Inheritance tax planning</li>
              <li>Protection needs analysis</li>
            </ul>

            <p>You can always add this information later through the Valuable Info section.</p>
          </div>

          <div class="mt-6 flex justify-end space-x-3">
            <button
              type="button"
              @click="showSkipModal = false"
              class="btn-secondary"
            >
              Go Back
            </button>
            <button
              type="button"
              @click="confirmSkip"
              class="btn-primary"
            >
              Skip & Continue
            </button>
          </div>
        </div>
      </div>
    </div>
  </OnboardingStep>
</template>

<script>
// DEPRECATED: Will be replaced by unified form with context="onboarding". See life-stage-journey-design.md §11.7
import { ref, computed, onMounted } from 'vue';
import { useStore } from 'vuex';
import OnboardingStep from '../OnboardingStep.vue';
import UsefulResources from '../UsefulResources.vue';
import { STEP_RESOURCES } from '@/constants/onboardingLinks';
import ExpenditureForm from '../../UserProfile/ExpenditureForm.vue';

import logger from '@/utils/logger';
export default {
  name: 'ExpenditureStep',

  components: {
    OnboardingStep,
    UsefulResources,
    ExpenditureForm,
  },

  emits: ['next', 'back', 'skip', 'sidebar-update'],

  setup(props, { emit }) {
    const store = useStore();
    const loading = ref(false);
    const error = ref(null);
    const initialData = ref({});
    const spouseData = ref({});
    const showSkipModal = ref(false);

    const user = computed(() => store.getters['auth/currentUser']);

    const isMarried = computed(() => {
      return user.value?.marital_status === 'married' && !!user.value?.spouse_id;
    });

    const spouseName = computed(() => {
      if (!spouseData.value || !spouseData.value.name) return 'Spouse';
      return spouseData.value.name.split(' ')[0]; // Get first name only
    });

    const fetchSpouseData = async () => {
      if (!user.value?.spouse_id) return;

      try {
        const response = await store.dispatch('auth/fetchUserById', user.value.spouse_id);
        spouseData.value = response || {};
      } catch (err) {
        logger.error('Failed to fetch spouse data:', err);
        spouseData.value = {};
      }
    };

    const formRef = ref(null);

    const handleFormSave = async (formData) => {
      error.value = null;

      // Check if this is separate mode data (has userData and spouseData)
      if (formData.userData && formData.spouseData) {
        // Validate that at least one spouse has entered expenditure
        const userHasData = formData.userData.monthly_expenditure > 0 || formData.userData.annual_expenditure > 0;
        const spouseHasData = formData.spouseData.monthly_expenditure > 0 || formData.spouseData.annual_expenditure > 0;

        if (!userHasData && !spouseHasData) {
          // Show skip modal instead of error
          showSkipModal.value = true;
          return;
        }

        await saveAndProceed(formData);
      } else {
        // Joint mode or single user
        if (formData.monthly_expenditure === 0 && formData.annual_expenditure === 0) {
          // Show skip modal instead of error
          showSkipModal.value = true;
          return;
        }

        await saveAndProceed(formData);
      }
    };

    const saveAndProceed = async (formData) => {
      loading.value = true;
      error.value = null;

      try {
        await store.dispatch('onboarding/saveStepData', {
          stepName: 'expenditure',
          data: formData,
        });

        emit('next');
      } catch (err) {
        error.value = err.message || 'Failed to save expenditure information. Please try again.';
      } finally {
        loading.value = false;
      }
    };

    const handleNext = async () => {
      error.value = null;

      // Check if we need to cycle through tabs (user → spouse in separate mode)
      if (formRef.value && formRef.value.advanceToNextTab) {
        const hasMoreTabs = formRef.value.advanceToNextTab();
        if (hasMoreTabs) {
          // Scroll to top so spouse form is visible
          window.scrollTo({ top: 0, behavior: 'smooth' });
          return;
        }
      }

      // All tabs viewed or not using tabs - trigger form save
      if (formRef.value && formRef.value.handleSave) {
        formRef.value.handleSave();
      }
    };

    const handleBack = () => {
      emit('back');
    };

    const confirmSkip = async () => {
      showSkipModal.value = false;
      loading.value = true;

      try {
        // Mark the step as skipped in the store
        await store.dispatch('onboarding/skipStep', 'expenditure');
        // Emit next to let the wizard advance
        emit('next');
      } catch (err) {
        error.value = err.message || 'Failed to skip step. Please try again.';
      } finally {
        loading.value = false;
      }
    };

    onMounted(async () => {
      // Load existing data from backend API
      try {
        const stepData = await store.dispatch('onboarding/fetchStepData', 'expenditure');
        if (stepData && Object.keys(stepData).length > 0) {
          // Check if this is separate mode data (has userData and spouseData keys)
          if (stepData.userData && stepData.spouseData) {
            // Separate mode: extract user and spouse data
            initialData.value = stepData.userData;
            // Load spouse data from saved data instead of fetching from API
            spouseData.value = stepData.spouseData;
          } else {
            // Joint mode: use data directly
            initialData.value = stepData;
          }
        }
      } catch (err) {
        // No existing data, start with empty form
      }

      // Fetch spouse data if married and not already loaded from saved data
      if (isMarried.value && (!spouseData.value || Object.keys(spouseData.value).length === 0)) {
        await fetchSpouseData();
      }
    });

    return {
      formRef,
      initialData,
      spouseData,
      spouseName,
      isMarried,
      loading,
      error,
      showSkipModal,
      handleFormSave,
      handleNext,
      handleBack,
      confirmSkip,
      STEP_RESOURCES,
    };
  },
};
</script>
