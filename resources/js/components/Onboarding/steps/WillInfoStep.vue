<template>
  <OnboardingStep
    title="Will Information"
    description="Tell us about your will and estate planning documents"
    :can-go-back="true"
    :can-skip="true"
    :loading="loading"
    :error="error"
    @next="handleNext"
    @back="handleBack"
    @skip="handleSkip"
  >
    <div class="space-y-6">
      <!-- Feature Status Notice -->
      <div class="bg-violet-50 border border-violet-200 rounded-lg p-4">
        <div class="flex">
          <svg class="h-5 w-5 text-violet-400 mt-0.5 mr-3 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
          </svg>
          <div>
            <p class="text-body-sm text-violet-800">
              <strong>Will Module - Enhanced Features Coming Soon</strong>
            </p>
            <p class="text-body-sm text-violet-700 mt-1">
              While you can record your basic will information here, comprehensive will analysis and strategies are currently in development. The full Will Planning module will include automated will reviews, beneficiary analysis, executor guidance, and integration with your estate planning strategy in upcoming releases.
            </p>
          </div>
        </div>
      </div>

      <div>
        <label class="label">
          Do you currently have a valid will?
        </label>
        <div class="mt-2 space-y-2">
          <label class="inline-flex items-center">
            <input
              v-model="formData.has_will"
              type="radio"
              :value="true"
              class="form-radio text-raspberry-500"
            >
            <span class="ml-2 text-body text-horizon-500">Yes</span>
          </label>
          <label class="inline-flex items-center ml-6">
            <input
              v-model="formData.has_will"
              type="radio"
              :value="false"
              class="form-radio text-raspberry-500"
            >
            <span class="ml-2 text-body text-horizon-500">No</span>
          </label>
        </div>
      </div>

      <div v-if="formData.has_will">
        <label for="will_last_updated" class="label">
          When was your will last updated?
        </label>
        <input
          id="will_last_updated"
          v-model="formData.will_last_updated"
          type="date"
          class="input-field"
        >
        <p class="mt-1 text-body-sm text-neutral-500">
          It's recommended to review your will every 5 years or after major life events
        </p>
      </div>

      <div v-if="formData.has_will">
        <label for="executor_name" class="label">
          Who is your executor?
        </label>
        <input
          id="executor_name"
          v-model="formData.executor_name"
          type="text"
          class="input-field"
          placeholder="Executor name"
        >
      </div>

      <div v-if="!formData.has_will" class="bg-spring-50 p-4 rounded-lg border border-spring-200">
        <p class="text-body-sm text-spring-800">
          <strong>Important:</strong> Without a will, your estate will be distributed according to intestacy rules, which may not reflect your wishes.
        </p>
      </div>
    </div>
  </OnboardingStep>
</template>

<script>
import { ref, onMounted } from 'vue';
import { useStore } from 'vuex';
import OnboardingStep from '../OnboardingStep.vue';

export default {
  name: 'WillInfoStep',

  components: {
    OnboardingStep,
  },

  emits: ['next', 'back', 'skip'],

  setup(props, { emit }) {
    const store = useStore();

    const formData = ref({
      has_will: null,
      will_last_updated: null,
      executor_name: '',
    });

    const loading = ref(false);
    const error = ref(null);

    const handleNext = async () => {
      loading.value = true;
      error.value = null;

      try {
        await store.dispatch('onboarding/saveStepData', {
          stepName: 'will_info',
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
      emit('skip', 'will_info');
    };

    const formatDate = (dateString) => {
      if (!dateString) return '';
      try {
        const date = new Date(dateString);
        if (isNaN(date.getTime())) return '';
        const year = date.getFullYear();
        const month = String(date.getMonth() + 1).padStart(2, '0');
        const day = String(date.getDate()).padStart(2, '0');
        return `${year}-${month}-${day}`;
      } catch (e) {
        return '';
      }
    };

    onMounted(async () => {
      const existingData = await store.dispatch('onboarding/fetchStepData', 'will_info');
      if (existingData) {
        // Format date field if exists
        if (existingData.will_last_updated) {
          existingData.will_last_updated = formatDate(existingData.will_last_updated);
        }
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
    };
  },
};
</script>
