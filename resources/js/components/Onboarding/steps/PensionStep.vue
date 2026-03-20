<template>
  <OnboardingStep
    title="Your Pensions"
    description="Add your pension schemes so we can project your retirement income"
    :can-go-back="true"
    :can-skip="true"
    :loading="loading"
    :error="error"
    :next-button-text="pensions.length > 0 && !showForm ? 'Continue' : undefined"
    @next="handleNext"
    @back="handleBack"
    @skip="handleSkip"
  >
    <div class="space-y-6">
      <!-- Saved Pensions List -->
      <div v-if="pensions.length > 0 && !showForm" class="space-y-3">
        <h4 class="text-body font-medium text-horizon-500">Your Pensions</h4>
        <div
          v-for="pension in pensions"
          :key="pension.id"
          class="bg-eggshell-500 rounded-lg p-4"
        >
          <div class="flex items-start justify-between">
            <div>
              <div class="flex items-center gap-2 mb-1">
                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-spring-100 text-spring-700">
                  {{ formatPensionType(pension.pension_type) }}
                </span>
              </div>
              <p class="text-body font-medium text-horizon-500">
                {{ pension.scheme_name || 'Pension' }}
              </p>
              <p v-if="pension.provider" class="text-body-sm text-neutral-500">
                {{ pension.provider }}
              </p>
            </div>
            <div class="text-right">
              <p class="text-body font-semibold text-horizon-500">
                {{ formatCurrency(pension.current_value) }}
              </p>
              <p v-if="pension.employee_contribution_percent || pension.monthly_contribution" class="text-body-sm text-neutral-500">
                <template v-if="pension.employee_contribution_percent">
                  {{ pension.employee_contribution_percent }}% + {{ pension.employer_contribution_percent || 0 }}%
                </template>
                <template v-else-if="pension.monthly_contribution">
                  {{ formatCurrency(pension.monthly_contribution) }}/mo
                </template>
              </p>
            </div>
          </div>
        </div>

        <button
          type="button"
          class="flex items-center gap-2 text-body-sm font-medium text-raspberry-500 hover:text-raspberry-600 transition-colors"
          @click="showForm = true"
        >
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
          </svg>
          Add another pension
        </button>
      </div>

      <!-- Pension Form -->
      <div v-if="showForm || pensions.length === 0">
        <DCPensionForm
          context="onboarding"
          @save="handlePensionSaved"
          @close="handleFormClose"
        />
      </div>
    </div>
  </OnboardingStep>
</template>

<script>
import { ref, onMounted } from 'vue';
import { useStore } from 'vuex';
import { currencyMixin } from '@/mixins/currencyMixin';
import OnboardingStep from '../OnboardingStep.vue';
import DCPensionForm from '@/components/Retirement/DCPensionForm.vue';
import retirementService from '@/services/retirementService';

export default {
  name: 'PensionStep',

  components: {
    OnboardingStep,
    DCPensionForm,
  },

  mixins: [currencyMixin],

  emits: ['next', 'back', 'skip'],

  setup(props, { emit }) {
    const store = useStore();
    const pensions = ref([]);
    const showForm = ref(true);
    const loading = ref(false);
    const error = ref(null);

    const pensionTypeLabels = {
      occupational: 'Workplace',
      workplace: 'Workplace',
      sipp: 'Self-Invested Personal Pension',
      personal: 'Personal Pension',
      stakeholder: 'Stakeholder',
    };

    const formatPensionType = (type) => {
      return pensionTypeLabels[type] || type?.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase()) || 'Pension';
    };

    const loadPensions = async () => {
      try {
        const data = await retirementService.getRetirementData();
        const dcPensions = data?.data?.dc_pensions || data?.data?.pensions || [];
        pensions.value = Array.isArray(dcPensions) ? dcPensions : [];
        if (pensions.value.length > 0) {
          showForm.value = false;
        }
      } catch {
        // No existing pensions
      }
    };

    const handlePensionSaved = async (formData) => {
      loading.value = true;
      error.value = null;

      try {
        await retirementService.createDCPension(formData);
        await loadPensions();
        showForm.value = false;

        await store.dispatch('onboarding/saveStepData', {
          stepName: 'pensions',
          data: { pensions_added: pensions.value.length },
        });
      } catch (err) {
        error.value = err.message || 'Failed to save pension. Please try again.';
      } finally {
        loading.value = false;
      }
    };

    const handleFormClose = () => {
      if (pensions.value.length > 0) {
        showForm.value = false;
      }
    };

    const handleNext = () => {
      if (!showForm.value && pensions.value.length > 0) {
        emit('next');
      }
    };

    const handleBack = () => {
      emit('back');
    };

    const handleSkip = () => {
      emit('skip');
    };

    onMounted(async () => {
      await loadPensions();
    });

    return {
      pensions,
      showForm,
      loading,
      error,
      formatPensionType,
      handlePensionSaved,
      handleFormClose,
      handleNext,
      handleBack,
      handleSkip,
    };
  },
};
</script>
