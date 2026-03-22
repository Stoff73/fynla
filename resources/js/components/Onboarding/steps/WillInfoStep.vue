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
        <label class="label">
          {{ formData.executors.length > 1 ? 'Who are your executors?' : 'Who is your executor?' }}
        </label>
        <div v-for="(executor, index) in formData.executors" :key="index" class="flex items-center gap-2 mb-2">
          <input
            v-model="formData.executors[index]"
            type="text"
            class="input-field flex-1"
            :placeholder="index === 0 ? 'Primary executor name' : 'Additional executor name'"
          >
          <button
            v-if="formData.executors.length > 1"
            type="button"
            @click="formData.executors.splice(index, 1)"
            class="p-2 text-neutral-500 hover:text-raspberry-500 transition-colors"
          >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>
        </div>
        <button
          type="button"
          @click="formData.executors.push('')"
          class="text-sm text-raspberry-500 hover:text-raspberry-600 font-medium transition-colors"
        >
          + Add executor
        </button>
      </div>

      <div v-if="formData.has_will === false" class="space-y-4">
        <div class="bg-violet-50 p-4 rounded-lg border border-violet-200">
          <p class="text-body-sm text-violet-800">
            <strong>Important:</strong> Without a will, your estate will be distributed according to intestacy rules, which may not reflect your wishes.
          </p>
        </div>
        <div class="bg-white p-4 rounded-lg border border-light-gray">
          <p class="text-body font-medium text-horizon-500 mb-2">Create your will now</p>
          <p class="text-body-sm text-neutral-500 mb-4">
            Use our guided Will Builder to create a will that reflects your wishes. It takes about 15 minutes and you can save your progress at any time.
          </p>
          <button
            type="button"
            @click="openWillBuilder"
            class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-button text-white bg-raspberry-500 hover:bg-raspberry-600 transition-colors"
          >
            Start Will Builder
            <svg class="w-4 h-4 ml-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
            </svg>
          </button>
        </div>
      </div>

      <UsefulResources :links="STEP_RESOURCES.will" />
    </div>
  </OnboardingStep>
</template>

<script>
import { ref, onMounted } from 'vue';
import { useStore } from 'vuex';
import { useRouter } from 'vue-router';
import OnboardingStep from '../OnboardingStep.vue';
import UsefulResources from '@/components/Onboarding/UsefulResources.vue';
import { STEP_RESOURCES } from '@/constants/onboardingLinks';

export default {
  name: 'WillInfoStep',

  components: {
    OnboardingStep,
    UsefulResources,
  },

  emits: ['next', 'back', 'skip'],

  setup(props, { emit }) {
    const store = useStore();
    const router = useRouter();

    const formData = ref({
      has_will: null,
      will_last_updated: null,
      executors: [''],
    });

    const loading = ref(false);
    const error = ref(null);

    const handleNext = async () => {
      loading.value = true;
      error.value = null;

      try {
        // Convert executors array to comma-separated string for backend
        const payload = {
          ...formData.value,
          executor_name: formData.value.executors.filter(e => e.trim()).join(', '),
        };
        delete payload.executors;

        await store.dispatch('onboarding/saveStepData', {
          stepName: 'will_info',
          data: payload,
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
        // Convert executor_name string to executors array
        if (existingData.executor_name) {
          existingData.executors = existingData.executor_name.split(',').map(e => e.trim()).filter(Boolean);
          if (existingData.executors.length === 0) existingData.executors = [''];
          delete existingData.executor_name;
        }
        Object.assign(formData.value, existingData);
      }
    });

    const openWillBuilder = () => {
      window.open('/estate/will-builder', '_blank');
    };

    return {
      formData,
      loading,
      error,
      handleNext,
      handleBack,
      handleSkip,
      openWillBuilder,
      STEP_RESOURCES,
    };
  },
};
</script>
