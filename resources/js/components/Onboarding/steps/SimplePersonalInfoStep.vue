<template>
  <OnboardingStep
    title="About You"
    description="Let's confirm your details before we get started"
    :can-go-back="false"
    :can-skip="false"
    :loading="loading"
    :error="error"
    @next="handleNext"
  >
    <div class="space-y-6">
      <!-- Name fields (read-only from registration) -->
      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
          <label class="label">First Name</label>
          <input
            :value="formData.first_name"
            type="text"
            class="input-field bg-eggshell-500 cursor-not-allowed"
            disabled
          >
          <p class="mt-1 text-body-sm text-neutral-500">
            From your registration
          </p>
        </div>

        <div>
          <label class="label">Surname</label>
          <input
            :value="formData.surname"
            type="text"
            class="input-field bg-eggshell-500 cursor-not-allowed"
            disabled
          >
          <p class="mt-1 text-body-sm text-neutral-500">
            From your registration
          </p>
        </div>
      </div>

      <!-- Phone Number -->
      <div>
        <label for="phone" class="label">
          Phone Number
        </label>
        <input
          id="phone"
          v-model="formData.phone"
          type="tel"
          class="input-field"
          placeholder="07700 900000"
        >
        <p class="mt-1 text-body-sm text-neutral-500">
          Used for two-factor authentication to keep your account secure
        </p>
      </div>

      <!-- Conditional Date of Birth (shown for non-budgeting journeys) -->
      <div v-if="showDateOfBirth">
        <label for="date_of_birth" class="label">
          Date of Birth <span class="text-raspberry-500">*</span>
        </label>
        <input
          id="date_of_birth"
          v-model="formData.date_of_birth"
          type="date"
          class="input-field"
          :class="{ 'border-raspberry-300': fieldErrors.date_of_birth }"
          :max="maxDob"
        >
        <p v-if="fieldErrors.date_of_birth" class="mt-1 text-body-sm text-raspberry-500">{{ fieldErrors.date_of_birth }}</p>
        <p v-else class="mt-1 text-body-sm text-neutral-500">
          Used for age-based calculations and projections. Check your <a :href="LINKS.GOV_STATE_PENSION_AGE" target="_blank" rel="noopener noreferrer" class="underline font-medium text-violet-500 hover:text-violet-700">State Pension age</a>
        </p>
      </div>

      <!-- Conditional Marital Status (shown for protection/estate journeys) -->
      <div v-if="showMaritalStatus">
        <label for="marital_status" class="label">
          Marital Status <span class="text-raspberry-500">*</span>
        </label>
        <select
          id="marital_status"
          v-model="formData.marital_status"
          class="input-field"
          :class="{ 'border-raspberry-300': fieldErrors.marital_status }"
        >
          <option value="">Select marital status</option>
          <option value="single">Single</option>
          <option value="married">Married</option>
          <option value="divorced">Divorced</option>
          <option value="widowed">Widowed</option>
        </select>
        <p v-if="fieldErrors.marital_status" class="mt-1 text-body-sm text-raspberry-500">{{ fieldErrors.marital_status }}</p>
        <p v-else class="mt-1 text-body-sm text-neutral-500">
          Affects protection needs and tax allowances
        </p>
      </div>

      <!-- Conditional Health & Lifestyle (shown for protection journey) -->
      <div v-if="showHealthFields" class="space-y-6">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
          <!-- Health Status -->
          <div>
            <label for="health_status" class="label">
              Are you in good health?
            </label>
            <select
              id="health_status"
              v-model="formData.health_status"
              class="input-field"
            >
              <option value="">Select...</option>
              <option value="yes">Yes</option>
              <option value="yes_previous">Yes, previous health conditions</option>
              <option value="no_previous">No, previous health conditions</option>
              <option value="no_existing">No, existing health conditions</option>
              <option value="no_both">No, previous and existing health conditions</option>
            </select>
            <p class="mt-1 text-body-sm text-neutral-500">
              Affects protection insurance premiums
            </p>
          </div>

          <!-- Smoking Status -->
          <div>
            <label for="smoking_status" class="label">
              Do you smoke?
            </label>
            <select
              id="smoking_status"
              v-model="formData.smoking_status"
              class="input-field"
            >
              <option value="">Select...</option>
              <option value="never">Never smoked</option>
              <option value="quit_recent">No, gave up 12 months or sooner</option>
              <option value="quit_long_ago">No, gave up more than 12 months ago</option>
              <option value="yes">Yes</option>
            </select>
            <p class="mt-1 text-body-sm text-neutral-500">
              Significantly impacts insurance premiums
            </p>
          </div>
        </div>
      </div>

      <UsefulResources :links="STEP_RESOURCES.simplePersonalInfo" />
    </div>
  </OnboardingStep>
</template>

<script>
// DEPRECATED: Will be replaced by unified form with context="onboarding". See life-stage-journey-design.md §11.7
import { ref, computed, onMounted } from 'vue';
import { useStore } from 'vuex';
import OnboardingStep from '../OnboardingStep.vue';
import UsefulResources from '../UsefulResources.vue';
import { LINKS, STEP_RESOURCES } from '@/constants/onboardingLinks';

export default {
  name: 'SimplePersonalInfoStep',

  components: {
    OnboardingStep,
    UsefulResources,
  },

  emits: ['next', 'back', 'skip'],

  setup(props, { emit }) {
    const store = useStore();

    const formData = ref({
      first_name: '',
      surname: '',
      phone: '',
      date_of_birth: '',
      marital_status: '',
      health_status: '',
      smoking_status: '',
    });

    const loading = ref(false);
    const error = ref(null);
    const fieldErrors = ref({});

    const maxDob = computed(() => {
      const today = new Date();
      return today.toISOString().split('T')[0];
    });

    // Journey selections helper
    const journeySelections = computed(() => {
      return store.state.journeys?.selections || [];
    });

    // Show DOB for any journey except budgeting-only
    const showDateOfBirth = computed(() => {
      const selections = journeySelections.value;
      // If budgeting is the only selection, hide DOB
      if (selections.length === 1 && selections[0] === 'budgeting') {
        return false;
      }
      // If no selections but current journey is budgeting, hide DOB
      const currentJourney = store.state.journeys?.currentJourney;
      if (selections.length === 0 && currentJourney === 'budgeting') {
        return false;
      }
      return true;
    });

    // Show marital status for protection or estate journeys
    const showMaritalStatus = computed(() => {
      const selections = journeySelections.value;
      return selections.includes('protection') || selections.includes('estate');
    });

    // Show health & smoking for protection journey
    const showHealthFields = computed(() => {
      const selections = journeySelections.value;
      return selections.includes('protection');
    });

    const validate = () => {
      fieldErrors.value = {};

      if (showDateOfBirth.value && !formData.value.date_of_birth) {
        fieldErrors.value.date_of_birth = 'Date of birth is required';
      }

      if (showMaritalStatus.value && !formData.value.marital_status) {
        fieldErrors.value.marital_status = 'Marital status is required';
      }

      return Object.keys(fieldErrors.value).length === 0;
    };

    const handleNext = async () => {
      if (!validate()) return;

      loading.value = true;
      error.value = null;

      try {
        const dataToSave = { ...formData.value };
        if (!showDateOfBirth.value) {
          delete dataToSave.date_of_birth;
        }
        if (!showMaritalStatus.value) {
          delete dataToSave.marital_status;
        }
        if (!showHealthFields.value) {
          delete dataToSave.health_status;
          delete dataToSave.smoking_status;
        }

        await store.dispatch('onboarding/saveStepData', {
          stepName: 'simple_personal_info',
          data: dataToSave,
        });
        emit('next');
      } catch (err) {
        error.value = err.message || 'Failed to save. Please try again.';
      } finally {
        loading.value = false;
      }
    };

    onMounted(async () => {
      // Pre-populate from user data
      const currentUser = store.getters['auth/currentUser'];
      if (currentUser) {
        formData.value.first_name = currentUser.first_name || '';
        formData.value.surname = currentUser.surname || '';
        formData.value.phone = currentUser.phone || '';
        formData.value.date_of_birth = currentUser.date_of_birth || '';
        formData.value.marital_status = currentUser.marital_status || '';
        formData.value.health_status = currentUser.health_status || '';
        formData.value.smoking_status = currentUser.smoking_status || '';
      }

      // Load any previously saved step data
      try {
        const stepData = await store.dispatch('onboarding/fetchStepData', 'simple_personal_info');
        if (stepData && Object.keys(stepData).length > 0) {
          formData.value = { ...formData.value, ...stepData };
        }
      } catch {
        // No existing data
      }
    });

    return {
      formData,
      loading,
      error,
      fieldErrors,
      maxDob,
      showDateOfBirth,
      showMaritalStatus,
      showHealthFields,
      handleNext,
      LINKS,
      STEP_RESOURCES,
    };
  },
};
</script>
