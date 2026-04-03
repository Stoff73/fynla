<template>
  <OnboardingStep
    title="Personal Information"
    description="Tell us about yourself to help us tailor your financial plan"
    :can-go-back="false"
    :can-skip="false"
    :hide-nav="true"
    :loading="loading"
    :error="error"
    @next="handleNext"
  >
    <div class="space-y-4">
      <!-- First Name (pre-populated) -->
      <div class="prepop-field">
        <label class="onb-label">
          First Name
          <span class="relative inline-block ml-1 group cursor-help">
            <svg class="w-4 h-4 inline text-violet-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
            <span class="absolute bottom-full left-1/2 -translate-x-1/2 mb-2 w-48 p-2 bg-horizon-500 text-white text-xs rounded-lg opacity-0 group-hover:opacity-100 transition-opacity pointer-events-none z-10">From your registration</span>
          </span>
        </label>
        <input :value="formData.first_name" type="text" class="onb-input prepop-input" disabled>
      </div>

      <!-- Middle Name (editable — first empty field) -->
      <div>
        <label class="onb-label">
          Middle Name
          <span class="q-icon" @click="emitWhyField($event,'middle_name')" title="Why we ask this">?</span>
        </label>
        <input
          id="middle_name_input"
          v-model="formData.middle_name"
          type="text"
          class="onb-input"
          placeholder="Enter your middle name (optional)"
          @focus="emitWhyField($event,'middle_name')"
        >
      </div>

      <!-- Surname (pre-populated) -->
      <div class="prepop-field">
        <label class="onb-label">
          Surname
          <span class="relative inline-block ml-1 group cursor-help">
            <svg class="w-4 h-4 inline text-violet-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
            <span class="absolute bottom-full left-1/2 -translate-x-1/2 mb-2 w-48 p-2 bg-horizon-500 text-white text-xs rounded-lg opacity-0 group-hover:opacity-100 transition-opacity pointer-events-none z-10">From your registration</span>
          </span>
        </label>
        <input :value="formData.surname" type="text" class="onb-input prepop-input" disabled>
      </div>

      <!-- Email (pre-populated) -->
      <div class="prepop-field">
        <label class="onb-label">
          Email Address
          <span class="relative inline-block ml-1 group cursor-help">
            <svg class="w-4 h-4 inline text-violet-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
            <span class="absolute bottom-full left-1/2 -translate-x-1/2 mb-2 w-48 p-2 bg-horizon-500 text-white text-xs rounded-lg opacity-0 group-hover:opacity-100 transition-opacity pointer-events-none z-10">From your registration</span>
          </span>
        </label>
        <input :value="formData.email" type="text" class="onb-input prepop-input" disabled>
      </div>

      <!-- Date of Birth -->
      <div>
        <label for="date_of_birth" class="onb-label">
          Date of Birth
          <span class="q-icon" @click="emitWhyField($event,'date_of_birth')" title="Why we ask this">?</span>
        </label>
        <input
          id="date_of_birth"
          v-model="formData.date_of_birth"
          type="date"
          class="onb-input"
          :max="maxDob"
          :min="minDob"
          @focus="emitWhyField($event,'date_of_birth')"
        >
      </div>

      <!-- Gender -->
      <div>
        <label for="gender" class="onb-label">
          Gender
          <span class="q-icon" @click="emitWhyField($event,'gender')" title="Why we ask this">?</span>
        </label>
        <select id="gender" v-model="formData.gender" class="onb-input" @focus="emitWhyField($event,'gender')">
          <option value="">Select gender</option>
          <option value="male">Male</option>
          <option value="female">Female</option>
          <option value="other">Other</option>
          <option value="prefer_not_to_say">Prefer not to say</option>
        </select>
      </div>

      <!-- Marital Status -->
      <div v-if="isFieldVisible('marital_status')">
        <label for="marital_status" class="onb-label">
          Marital Status
          <span class="q-icon" @click="emitWhyField($event,'marital_status')" title="Why we ask this">?</span>
        </label>
        <select id="marital_status" v-model="formData.marital_status" class="onb-input" @focus="emitWhyField($event,'marital_status')">
          <option value="">Select marital status</option>
          <option value="single">Single</option>
          <option value="married">Married</option>
          <option value="divorced">Divorced</option>
          <option value="widowed">Widowed</option>
        </select>
      </div>

      <!-- Address Section -->
      <div v-if="isFieldVisible('address_line_1')" class="border-t pt-6">
        <h4 class="text-body font-medium text-horizon-500 mb-4">Address</h4>

        <div class="space-y-4">
          <div>
            <label for="address_line_1" class="onb-label">
              Address Line 1 <span class="q-icon" @click="emitWhyField($event,'address_line_1')" title="Why we ask this">?</span>
            </label>
            <input id="address_line_1" v-model="formData.address_line_1" type="text" class="onb-input" placeholder="123 Test Street" @focus="emitWhyField($event,'address_line_1')">
          </div>

          <div>
            <label for="address_line_2" class="onb-label">
              Address Line 2 <span class="q-icon" @click="emitWhyField($event,'address_line_2')" title="Why we ask this">?</span>
            </label>
            <input id="address_line_2" v-model="formData.address_line_2" type="text" class="onb-input" placeholder="Apartment, suite, etc. (optional)" @focus="emitWhyField($event,'address_line_2')">
          </div>

          <div>
            <label for="city" class="onb-label">
              City <span class="q-icon" @click="emitWhyField($event,'city')" title="Why we ask this">?</span>
            </label>
            <input id="city" v-model="formData.city" type="text" class="onb-input" placeholder="London" @focus="emitWhyField($event,'city')">
          </div>

          <div>
            <label for="county" class="onb-label">
              County <span class="q-icon" @click="emitWhyField($event,'county')" title="Why we ask this">?</span>
            </label>
            <input id="county" v-model="formData.county" type="text" class="onb-input" placeholder="Greater London" @focus="emitWhyField($event,'county')">
          </div>

          <div>
            <label for="postcode" class="onb-label">
              Postcode <span class="q-icon" @click="emitWhyField($event,'postcode')" title="Why we ask this">?</span>
            </label>
            <input id="postcode" v-model="formData.postcode" type="text" class="onb-input" placeholder="SW1A 1AA" maxlength="8" @input="formatPostcode" @focus="emitWhyField($event,'postcode')">
          </div>

          <div>
            <label for="phone" class="onb-label">
              Phone Number <span class="q-icon" @click="emitWhyField($event,'phone')" title="Why we ask this">?</span>
            </label>
            <input id="phone" v-model="formData.phone" type="tel" class="onb-input" placeholder="07700 900000" @focus="emitWhyField($event,'phone')">
          </div>
        </div>
      </div>

      <!-- Health & Lifestyle Section -->
      <div v-if="isFieldVisible('health_status')" class="border-t pt-6">
        <h4 class="text-body font-medium text-horizon-500 mb-4">
          Health & Lifestyle Information
        </h4>
        <div class="space-y-4">
          <!-- Health Status -->
          <div>
            <label for="health_status" class="onb-label">
              Are you in good health? <span class="q-icon" @click="emitWhyField($event,'health_status')" title="Why we ask this">?</span>
            </label>
            <select id="health_status" v-model="formData.health_status" class="onb-input" @focus="emitWhyField($event,'health_status')">
              <option value="">Select...</option>
              <option value="yes">Yes</option>
              <option value="yes_previous">Yes, previous health conditions</option>
              <option value="no_previous">No, previous health conditions</option>
              <option value="no_existing">No, existing health conditions</option>
              <option value="no_both">No, previous and existing health conditions</option>
            </select>
          </div>

          <!-- Smoking Status -->
          <div>
            <label for="smoking_status" class="onb-label">
              Do you smoke? <span class="q-icon" @click="emitWhyField($event,'smoking_status')" title="Why we ask this">?</span>
            </label>
            <select id="smoking_status" v-model="formData.smoking_status" class="onb-input" @focus="emitWhyField($event,'smoking_status')">
              <option value="">Select...</option>
              <option value="never">Never smoked</option>
              <option value="quit_recent">No, gave up 12 months or sooner</option>
              <option value="quit_long_ago">No, gave up more than 12 months ago</option>
              <option value="yes">Yes</option>
            </select>
          </div>

          <!-- Education Level -->
          <div>
            <label for="education_level" class="onb-label">
              Highest Education Level <span class="q-icon" @click="emitWhyField($event,'education_level')" title="Why we ask this">?</span>
            </label>
            <select id="education_level" v-model="formData.education_level" class="onb-input" @focus="emitWhyField($event,'education_level')">
              <option value="">Select...</option>
              <option value="secondary">Secondary (GCSE/O-Levels)</option>
              <option value="a_level">A-Levels/Vocational</option>
              <option value="undergraduate">Undergraduate Degree</option>
              <option value="postgraduate">Postgraduate Degree</option>
              <option value="professional">Professional Qualification</option>
              <option value="other">Other</option>
            </select>
          </div>
        </div>
      </div>

    </div>
  </OnboardingStep>
</template>

<script>
// DEPRECATED: Will be replaced by unified form with context="onboarding". See life-stage-journey-design.md §11.7
import { ref, computed, onMounted, nextTick, watch } from 'vue';
import { useStore } from 'vuex';
import OnboardingStep from '../OnboardingStep.vue';
import UsefulResources from '../UsefulResources.vue';
import { LINKS, STEP_RESOURCES } from '@/constants/onboardingLinks';

import logger from '@/utils/logger';
export default {
  name: 'PersonalInfoStep',

  components: {
    OnboardingStep,
    UsefulResources,
  },

  emits: ['next', 'back', 'skip', 'sidebar-update'],

  setup(props, { emit }) {
    const store = useStore();

    const isFieldVisible = (fieldName) => {
      return store.getters['lifeStage/isFieldVisible']('personalInfo', fieldName, 'onboarding');
    };

    const currentUser = store.getters['auth/currentUser'];
    const formData = ref({
      first_name: currentUser?.first_name || '',
      middle_name: currentUser?.middle_name || '',
      surname: currentUser?.surname || currentUser?.last_name || '',
      email: currentUser?.email || '',
      date_of_birth: '',
      gender: '',
      marital_status: '',
      address_line_1: '',
      address_line_2: '',
      city: '',
      county: '',
      postcode: '',
      phone: '',
      health_status: '',
      smoking_status: '',
      education_level: '',
    });

    const loading = ref(false);
    const error = ref(null);

    const maxDob = computed(() => {
      // Max DOB is 18 years ago (minimum age)
      const date = new Date();
      date.setFullYear(date.getFullYear() - 18);
      return date.toISOString().split('T')[0];
    });

    const minDob = computed(() => {
      // Min DOB is 105 years ago (maximum age)
      const date = new Date();
      date.setFullYear(date.getFullYear() - 105);
      return date.toISOString().split('T')[0];
    });

    const formatPostcode = (event) => {
      // Simple postcode formatting - uppercase
      formData.value.postcode = event.target.value.toUpperCase();
    };

    const handleAddressSelected = (address) => {
      // Populate address fields from postcode lookup
      formData.value.address_line_1 = address.line_1 || '';
      formData.value.address_line_2 = address.line_2 || '';
      formData.value.city = address.city || '';
      formData.value.county = address.county || '';
      formData.value.postcode = address.postcode || '';
    };

    const WHY_FIELD_DATA = {
      middle_name: { whyWeAsk: 'Your middle name helps us match your identity accurately across financial accounts and official records.' },
      date_of_birth: { whyWeAsk: 'Your date of birth affects pension eligibility, life expectancy projections, and State Pension age calculations.' },
      gender: { whyWeAsk: 'Gender affects life expectancy projections, pension eligibility dates, and Lifetime ISA access rules.' },
      marital_status: { whyWeAsk: 'Marital status affects spouse exemption, transferable nil rate band, and joint asset planning.' },
      address_line_1: { whyWeAsk: 'Your address helps us identify regional factors that may affect property values and local tax considerations.' },
      address_line_2: { whyWeAsk: 'Your address helps us identify regional factors that may affect property values and local tax considerations.' },
      city: { whyWeAsk: 'Your location can affect property valuations and regional cost-of-living assumptions in your financial plan.' },
      county: { whyWeAsk: 'County information helps with regional property market data and local authority considerations.' },
      postcode: { whyWeAsk: 'Your postcode helps us estimate property values and identify location-specific financial factors.' },
      phone: { whyWeAsk: 'A contact number allows us to reach you for important account security notifications.' },
      health_status: { whyWeAsk: 'Health status affects protection insurance premiums and life expectancy projections.' },
      smoking_status: { whyWeAsk: 'Smoking status significantly impacts life insurance and income protection premiums.' },
      education_level: { whyWeAsk: 'Education level helps with occupation profiling for income projections.' },
    };

    let lastEmittedField = null;

    const emitWhyField = (event, fieldName) => {
      const data = WHY_FIELD_DATA[fieldName];
      if (!data) return;

      // Prevent double-fire: if ? click already focused the input, skip the focus event
      if (event?.type === 'focus' && lastEmittedField === fieldName) {
        lastEmittedField = null;
        return;
      }

      // Find the input/select element by looking in the parent div
      const el = event?.target;
      const fieldDiv = el?.closest?.('div') || null;
      const inputEl = fieldDiv?.querySelector('input:not(.prepop-input), select');

      // If click was on ? icon, focus the input (which will NOT re-fire due to guard above)
      if (event?.type === 'click' && inputEl && !inputEl.disabled) {
        lastEmittedField = fieldName;
        inputEl.focus();
      }

      // Always calculate offset from the input element's rect
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

    const handleNext = async () => {
      loading.value = true;
      error.value = null;

      try {
        await store.dispatch('onboarding/saveStepData', {
          stepName: 'personal_info',
          data: formData.value,
        });

        emit('next');
      } catch (err) {
        error.value = err.message || 'Failed to save personal information. Please try again.';
      } finally {
        loading.value = false;
      }
    };

    // Format date to yyyy-MM-dd for HTML5 date input
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
      // Ensure we have latest user data from backend
      if (!store.getters['auth/currentUser']) {
        try {
          await store.dispatch('auth/fetchUser');
        } catch (err) {
          logger.error('Failed to fetch current user:', err);
        }
      }

      // Get current user from store
      const currentUser = store.getters['auth/currentUser'];

      // Pre-populate from user table if data exists
      if (currentUser) {
        if (currentUser.date_of_birth) {
          formData.value.date_of_birth = formatDate(currentUser.date_of_birth);
        }
        if (currentUser.gender) {
          formData.value.gender = currentUser.gender;
        }
        if (currentUser.marital_status) {
          formData.value.marital_status = currentUser.marital_status;
        }
        if (currentUser.address_line_1) {
          formData.value.address_line_1 = currentUser.address_line_1;
        }
        if (currentUser.address_line_2) {
          formData.value.address_line_2 = currentUser.address_line_2;
        }
        if (currentUser.city) {
          formData.value.city = currentUser.city;
        }
        if (currentUser.county) {
          formData.value.county = currentUser.county;
        }
        if (currentUser.postcode) {
          formData.value.postcode = currentUser.postcode;
        }
        if (currentUser.phone) {
          formData.value.phone = currentUser.phone;
        }
      }

      // Fetch step data from backend (will override user data if exists)
      try {
        const stepData = await store.dispatch('onboarding/fetchStepData', 'personal_info');
        if (stepData && Object.keys(stepData).length > 0) {
          // Format date_of_birth if it exists in step data
          if (stepData.date_of_birth) {
            stepData.date_of_birth = formatDate(stepData.date_of_birth);
          }
          formData.value = { ...formData.value, ...stepData };
        }
      } catch (err) {
        // No existing step data, use pre-populated values from user table
      }

      // Auto-focus and highlight first empty field after DOM renders
      nextTick(() => {
        const middleNameInput = document.getElementById('middle_name_input');
        if (middleNameInput) {
          middleNameInput.focus();
        }
      });
    });

    return {
      formData,
      loading,
      error,
      maxDob,
      minDob,
      formatPostcode,
      handleNext,
      handleAddressSelected,
      isFieldVisible,
      emitWhyField,
      LINKS,
      STEP_RESOURCES,
    };
  },
};
</script>

<style scoped>
.prepop-field {
  cursor: not-allowed;
}
</style>
