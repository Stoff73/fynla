<template>
  <OnboardingStep
    title="Trust Information"
    description="Tell us about any trusts you have created or benefit from"
    :can-go-back="true"
    :can-skip="true"
    :loading="loading"
    :error="error"
    @next="handleNext"
    @back="handleBack"
    @skip="handleSkip"
  >
    <div class="space-y-6">
      <div>
        <label class="label">
          Have you created or do you benefit from any trusts? <span class="q-icon" @click="emitWhyField($event,'has_trusts')" title="Why we ask this">?</span>
        </label>
        <div class="mt-2 space-y-2">
          <label class="inline-flex items-center">
            <input
              v-model="formData.has_trusts"
              type="radio"
              :value="true"
              class="form-radio text-raspberry-500"
            >
            <span class="ml-2 text-body text-horizon-500">Yes</span>
          </label>
          <label class="inline-flex items-center ml-6">
            <input
              v-model="formData.has_trusts"
              type="radio"
              :value="false"
              class="form-radio text-raspberry-500"
            >
            <span class="ml-2 text-body text-horizon-500">No</span>
          </label>
        </div>
      </div>

      <div v-if="formData.has_trusts">
        <label for="trust_count" class="label">
          Number of Trusts <span class="q-icon" @click="emitWhyField($event,'trust_count')" title="Why we ask this">?</span>
        </label>
        <input
          id="trust_count"
          v-model.number="formData.trust_count"
          type="number"
          min="0"
          class="input-field"
          placeholder="0"
          @focus="emitWhyField($event,'trust_count')"
        >
      </div>

      <div v-if="formData.has_trusts" class="bg-violet-50 p-4 rounded-lg border border-violet-200">
        <p class="text-body-sm text-violet-800">
          Trusts can affect your Inheritance Tax calculation due to Potentially Exempt Transfers and Chargeable Lifetime Transfers.
        </p>
      </div>

      <p class="text-body-sm text-neutral-500 italic">
        You can add detailed trust information later in your profile.
      </p>

    </div>
  </OnboardingStep>
</template>

<script>
import { ref, onMounted } from 'vue';
import { useStore } from 'vuex';
import OnboardingStep from '../OnboardingStep.vue';
import UsefulResources from '@/components/Onboarding/UsefulResources.vue';
import { STEP_RESOURCES } from '@/constants/onboardingLinks';

export default {
  name: 'TrustInfoStep',

  components: {
    OnboardingStep,
    UsefulResources,
  },

  emits: ['next', 'back', 'skip', 'sidebar-update'],

  setup(props, { emit }) {
    const store = useStore();

    const WHY_FIELD_DATA = {
      has_trusts: { whyWeAsk: 'Trusts affect your Inheritance Tax calculation through Potentially Exempt Transfers and Chargeable Lifetime Transfers.' },
      trust_count: { whyWeAsk: 'The number of trusts helps us understand the complexity of your estate planning and identify relevant tax implications.' },
    };

    let lastEmittedField = null;

    const emitWhyField = (event, fieldName) => {
      const data = WHY_FIELD_DATA[fieldName];
      if (!data) return;

      if (event?.type === 'focus' && lastEmittedField === fieldName) {
        lastEmittedField = null;
        return;
      }

      const el = event?.target;
      const fieldDiv = el?.closest?.('div') || null;
      const inputEl = fieldDiv?.querySelector('input:not(.prepop-input), select');

      if (event?.type === 'click' && inputEl && !inputEl.disabled) {
        lastEmittedField = fieldName;
        inputEl.focus();
      }

      let fieldOffsetY = 0;
      if (inputEl) {
        const formCol = inputEl.closest('.flex-1');
        if (formCol) {
          const colRect = formCol.getBoundingClientRect();
          const inputRect = inputEl.getBoundingClientRect();
          fieldOffsetY = inputRect.top - colRect.top + (inputRect.height / 2);
        }
      }

      emit('sidebar-update', { whyWeAsk: data.whyWeAsk, fieldOffsetY });
    };

    const formData = ref({
      has_trusts: null,
      trust_count: 0,
    });

    const loading = ref(false);
    const error = ref(null);

    const handleNext = async () => {
      loading.value = true;
      error.value = null;

      try {
        await store.dispatch('onboarding/saveStepData', {
          stepName: 'trust_info',
          data: formData.value,
        });

        emit('next');
      } catch (err) {
        error.value = err.message || 'Failed to save. Please try again.';
      } finally {
        loading.value = false;
      }
    };

    const handleBack = () => {
      emit('back');
    };

    const handleSkip = () => {
      emit('skip', 'trust_info');
    };

    onMounted(async () => {
      const existingData = await store.dispatch('onboarding/fetchStepData', 'trust_info');
      if (existingData) {
        Object.assign(formData.value, existingData);
      }
    });

    return {
      formData,
      loading,
      error,
      handleNext,
      handleBack,
      handleSkip,
      emitWhyField,
      STEP_RESOURCES,
    };
  },
};
</script>
