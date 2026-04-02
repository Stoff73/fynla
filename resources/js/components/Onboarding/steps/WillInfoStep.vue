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
          Do you currently have a valid will? <span class="q-icon" @click="emitWhyField($event,'has_will')" title="Why we ask this">?</span>
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
          When was your will last updated? <span class="q-icon" @click="emitWhyField($event,'will_last_updated')" title="Why we ask this">?</span>
        </label>
        <input
          id="will_last_updated"
          v-model="formData.will_last_updated"
          type="date"
          class="input-field"
          @focus="emitWhyField($event,'will_last_updated')"
        >
        <p class="mt-1 text-body-sm text-neutral-500">
          It's recommended to review your will every 5 years or after major life events
        </p>
      </div>

      <div v-if="formData.has_will">
        <label class="label">
          {{ formData.executors.length > 1 ? 'Who are your executors?' : 'Who is your executor?' }} <span class="q-icon" @click="emitWhyField($event,'executors')" title="Why we ask this">?</span>
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

      <div v-if="formData.has_will === false">
        <div class="bg-violet-50 p-4 rounded-lg border border-violet-200">
          <p class="text-body-sm text-violet-800">
            <strong>Important:</strong> Without a will, your estate will be distributed according to intestacy rules, which may not reflect your wishes.
          </p>
        </div>
      </div>

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

  emits: ['next', 'back', 'skip', 'sidebar-update'],

  setup(props, { emit }) {
    const store = useStore();
    const router = useRouter();

    const WHY_FIELD_DATA = {
      has_will: { whyWeAsk: 'Knowing whether you have a will helps us assess your estate planning position and identify if intestacy rules would apply.' },
      will_last_updated: { whyWeAsk: 'An outdated will may not reflect your current wishes or financial situation. We recommend reviewing every 5 years or after major life events.' },
      executors: { whyWeAsk: 'Your executor is responsible for administering your estate. Recording this helps ensure your estate plan is complete.' },
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
      emitWhyField,
      STEP_RESOURCES,
    };
  },
};
</script>
