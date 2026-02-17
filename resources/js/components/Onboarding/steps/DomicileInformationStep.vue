<template>
  <OnboardingStep
    title="Domicile Information"
    description="Your domicile status affects your UK tax liability and Inheritance Tax calculations"
    :can-go-back="true"
    :can-skip="false"
    :loading="loading"
    :error="error"
    @next="handleNext"
    @back="handleBack"
  >
    <div class="space-y-6">
      <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
        <p class="text-body-sm text-blue-800">
          <strong>Why this matters:</strong> UK domicile status determines which assets are subject to UK Inheritance Tax. Non-UK domiciled individuals only pay Inheritance Tax on UK assets, while UK domiciled individuals pay Inheritance Tax on worldwide assets.
        </p>
      </div>

      <div class="grid grid-cols-1 gap-6">
        <!-- Country of Birth -->
        <div>
          <label for="country_of_birth" class="label">
            Where were you born?
          </label>
          <CountrySelector
            v-model="formData.country_of_birth"
            :required="false"
            :disabled="false"
            placeholder="Search for your country of birth..."
            @update:model-value="handleCountryChange"
          />
          <p class="mt-1 text-body-sm text-gray-500">
            Your country of birth helps us determine your domicile status for tax purposes.
          </p>
        </div>

        <!-- UK Arrival Date (shown only for non-UK born) -->
        <div v-if="shouldShowUKArrivalDate" class="space-y-4 border-t pt-4">
          <h4 class="text-body font-medium text-gray-900">
            UK Residency Information
          </h4>

          <div>
            <label for="uk_arrival_date" class="label">
              Date Moved to UK
            </label>
            <input
              id="uk_arrival_date"
              v-model="formData.uk_arrival_date"
              type="date"
              class="input-field"
              :max="today"
              @change="calculateYearsResident"
            >
            <p class="mt-1 text-body-sm text-gray-500">
              When did you first move to the UK?
            </p>
          </div>

          <div v-if="yearsResident !== null" class="bg-gray-50 rounded-lg p-4">
            <p class="text-body-sm text-gray-700">
              <strong>Years UK Resident:</strong> {{ yearsResident }} years
            </p>
            <p class="mt-2 text-body-sm font-medium text-gray-900">
              <strong>Domicile Status:</strong> {{ domicileStatusLabel }}
            </p>
            <p v-if="isDeemedDomiciled" class="mt-2 text-body-sm text-blue-700">
              You are considered deemed domiciled in the UK because you have been resident for at least 15 of the last 20 tax years. This means you are subject to UK Inheritance Tax on your worldwide assets.
            </p>
            <p v-else class="mt-2 text-body-sm text-blue-700">
              You are not yet deemed domiciled. You only pay UK Inheritance Tax on UK assets. You will need {{ 15 - yearsResident }} more year(s) of UK residence to become deemed domiciled.
            </p>
          </div>
        </div>
      </div>
    </div>
  </OnboardingStep>
</template>

<script>
import { ref, computed, onMounted } from 'vue';
import { useStore } from 'vuex';
import OnboardingStep from '../OnboardingStep.vue';
import CountrySelector from '@/components/Shared/CountrySelector.vue';

export default {
  name: 'DomicileInformationStep',

  components: {
    OnboardingStep,
    CountrySelector,
  },

  emits: ['next', 'back', 'skip'],

  setup(props, { emit }) {
    const store = useStore();

    const formData = ref({
      domicile_status: 'uk_domiciled', // Will be auto-determined
      country_of_birth: 'England', // Default to England
      uk_arrival_date: null,
      years_uk_resident: null,
      deemed_domicile_date: null,
    });

    // UK constituent countries
    const ukCountries = ['England', 'Scotland', 'Wales', 'Northern Ireland'];

    const isUKCountry = (country) => {
      return ukCountries.includes(country);
    };

    const loading = ref(false);
    const error = ref(null);
    const yearsResident = ref(null);

    const today = computed(() => {
      const date = new Date();
      return date.toISOString().split('T')[0];
    });

    const isDeemedDomiciled = computed(() => {
      return yearsResident.value !== null && yearsResident.value >= 15;
    });

    const shouldShowUKArrivalDate = computed(() => {
      // Show UK arrival date field only if born outside UK
      return formData.value.country_of_birth &&
             !isUKCountry(formData.value.country_of_birth);
    });

    const domicileStatusLabel = computed(() => {
      if (isUKCountry(formData.value.country_of_birth)) {
        return 'UK Domiciled';
      }

      if (isDeemedDomiciled.value) {
        return 'Deemed UK Domiciled';
      }

      return 'Non-UK Domiciled';
    });

    const calculateYearsResident = () => {
      if (!formData.value.uk_arrival_date) {
        yearsResident.value = null;
        return;
      }

      const arrival = new Date(formData.value.uk_arrival_date);
      const now = new Date();
      const years = Math.floor((now - arrival) / (365.25 * 24 * 60 * 60 * 1000));

      yearsResident.value = Math.max(0, years);
      formData.value.years_uk_resident = yearsResident.value;

      // Calculate deemed domicile date if applicable
      if (yearsResident.value >= 15) {
        const deemedDate = new Date(arrival);
        deemedDate.setFullYear(deemedDate.getFullYear() + 15);
        formData.value.deemed_domicile_date = deemedDate.toISOString().split('T')[0];
      } else {
        formData.value.deemed_domicile_date = null;
      }

      // Auto-determine domicile status
      updateDomicileStatus();
    };

    const handleCountryChange = () => {
      // If UK born, clear UK arrival fields and set as UK domiciled
      if (isUKCountry(formData.value.country_of_birth)) {
        formData.value.uk_arrival_date = null;
        formData.value.years_uk_resident = null;
        formData.value.deemed_domicile_date = null;
        yearsResident.value = null;
        formData.value.domicile_status = 'uk_domiciled';
      } else {
        // Non-UK born - status will be determined by years resident
        updateDomicileStatus();
      }
    };

    const updateDomicileStatus = () => {
      // Auto-determine domicile status based on country of birth and years resident
      if (isUKCountry(formData.value.country_of_birth)) {
        formData.value.domicile_status = 'uk_domiciled';
      } else if (yearsResident.value !== null && yearsResident.value >= 15) {
        // Deemed domiciled if 15+ years resident
        formData.value.domicile_status = 'uk_domiciled';
      } else {
        formData.value.domicile_status = 'non_uk_domiciled';
      }
    };

    const handleNext = async () => {
      // All fields are optional - no validation required

      // Auto-determine domicile status before saving
      updateDomicileStatus();

      loading.value = true;
      error.value = null;

      try {
        await store.dispatch('onboarding/saveStepData', {
          stepName: 'domicile_info',
          data: formData.value,
        });

        emit('next');
      } catch (err) {
        error.value = err.message || 'Failed to save domicile information. Please try again.';
      } finally {
        loading.value = false;
      }
    };

    const handleBack = () => {
      emit('back');
    };

    onMounted(async () => {
      // Load existing step data if available
      try {
        const stepData = await store.dispatch('onboarding/fetchStepData', 'domicile_info');
        if (stepData && Object.keys(stepData).length > 0) {
          formData.value = { ...formData.value, ...stepData };

          // Recalculate years resident if uk_arrival_date exists
          if (formData.value.uk_arrival_date) {
            calculateYearsResident();
          }
        }
      } catch (err) {
        // No existing data, start fresh
      }
    });

    return {
      formData,
      loading,
      error,
      today,
      yearsResident,
      isDeemedDomiciled,
      domicileStatusLabel,
      shouldShowUKArrivalDate,
      calculateYearsResident,
      handleCountryChange,
      handleNext,
      handleBack,
    };
  },
};
</script>
