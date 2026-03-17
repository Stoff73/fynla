<template>
  <OnboardingStep
    title="Personal Information"
    description="Tell us about yourself to help us tailor your estate plan"
    :can-go-back="false"
    :can-skip="false"
    :loading="loading"
    :error="error"
    @next="handleNext"
  >
    <div class="space-y-6">
      <div class="bg-violet-50 border border-violet-200 rounded-lg p-4">
        <p class="text-body-sm text-violet-800">
          <strong>Why this matters:</strong> Personal information helps us calculate your estate value, available tax reliefs, and provide personalized estate planning advice.
        </p>
      </div>

      <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <!-- Date of Birth -->
        <div>
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
            :min="minDob"
          >
          <p v-if="fieldErrors.date_of_birth" class="mt-1 text-body-sm text-raspberry-500">{{ fieldErrors.date_of_birth }}</p>
          <p v-else class="mt-1 text-body-sm text-neutral-500">
            Used for age-based calculations and projections
          </p>
        </div>

        <!-- Gender -->
        <div>
          <label for="gender" class="label">
            Gender <span class="text-raspberry-500">*</span>
          </label>
          <select
            id="gender"
            v-model="formData.gender"
            class="input-field"
            :class="{ 'border-raspberry-300': fieldErrors.gender }"
          >
            <option value="">Select gender</option>
            <option value="male">Male</option>
            <option value="female">Female</option>
            <option value="other">Other</option>
          </select>
          <p v-if="fieldErrors.gender" class="mt-1 text-body-sm text-raspberry-500">{{ fieldErrors.gender }}</p>
        </div>

        <!-- Marital Status -->
        <div>
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
            Affects spouse exemption and transferable nil rate band
          </p>
        </div>

      </div>

      <!-- Address Section -->
      <div class="border-t pt-6">
        <h4 class="text-body font-medium text-horizon-500 mb-4">
          Address
        </h4>

        <div class="grid grid-cols-1 gap-4">
          <div>
            <label for="address_line_1" class="label">
              Address Line 1 <span class="text-raspberry-500">*</span>
            </label>
            <input
              id="address_line_1"
              v-model="formData.address_line_1"
              type="text"
              class="input-field"
              :class="{ 'border-raspberry-300': fieldErrors.address_line_1 }"
              placeholder="123 Test Street"
            >
            <p v-if="fieldErrors.address_line_1" class="mt-1 text-body-sm text-raspberry-500">{{ fieldErrors.address_line_1 }}</p>
          </div>

          <div>
            <label for="address_line_2" class="label">
              Address Line 2
            </label>
            <input
              id="address_line_2"
              v-model="formData.address_line_2"
              type="text"
              class="input-field"
              placeholder="Apartment, suite, etc. (optional)"
            >
          </div>

          <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div>
              <label for="city" class="label">
                City <span class="text-raspberry-500">*</span>
              </label>
              <input
                id="city"
                v-model="formData.city"
                type="text"
                class="input-field"
                :class="{ 'border-raspberry-300': fieldErrors.city }"
                placeholder="London"
              >
              <p v-if="fieldErrors.city" class="mt-1 text-body-sm text-raspberry-500">{{ fieldErrors.city }}</p>
            </div>

            <div>
              <label for="county" class="label">
                County
              </label>
              <input
                id="county"
                v-model="formData.county"
                type="text"
                class="input-field"
                placeholder="Greater London"
              >
            </div>

            <div>
              <label for="postcode" class="label">
                Postcode <span class="text-raspberry-500">*</span>
              </label>
              <input
                id="postcode"
                v-model="formData.postcode"
                type="text"
                class="input-field"
                :class="{ 'border-raspberry-300': fieldErrors.postcode }"
                placeholder="SW1A 1AA"
                maxlength="8"
                @input="formatPostcode"
              >
              <p v-if="fieldErrors.postcode" class="mt-1 text-body-sm text-raspberry-500">{{ fieldErrors.postcode }}</p>
            </div>
          </div>

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
          </div>
        </div>
      </div>

      <!-- Health & Lifestyle Section -->
      <div class="border-t pt-6">
        <h4 class="text-body font-medium text-horizon-500 mb-4">
          Health & Lifestyle Information
        </h4>
        <div class="bg-violet-50 border border-violet-200 rounded-lg p-4 mb-6">
          <p class="text-body-sm text-violet-800">
            <strong>Why this matters:</strong> Health and lifestyle information helps us provide accurate protection strategies and estimate insurance premium costs.
          </p>
        </div>

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

          <!-- Education Level -->
          <div>
            <label for="education_level" class="label">
              Highest Education Level
            </label>
            <select
              id="education_level"
              v-model="formData.education_level"
              class="input-field"
            >
              <option value="">Select...</option>
              <option value="secondary">Secondary (GCSE/O-Levels)</option>
              <option value="a_level">A-Levels/Vocational</option>
              <option value="undergraduate">Undergraduate Degree</option>
              <option value="postgraduate">Postgraduate Degree</option>
              <option value="professional">Professional Qualification</option>
              <option value="other">Other</option>
            </select>
            <p class="mt-1 text-body-sm text-neutral-500">
              Optional - helps with occupation profiling
            </p>
          </div>
        </div>
      </div>
    </div>
  </OnboardingStep>
</template>

<script>
// DEPRECATED: Will be replaced by unified form with context="onboarding". See life-stage-journey-design.md §11.7
import { ref, computed, onMounted, watch } from 'vue';
import { useStore } from 'vuex';
import OnboardingStep from '../OnboardingStep.vue';

export default {
  name: 'PersonalInfoStep',

  components: {
    OnboardingStep,
  },

  emits: ['next', 'back', 'skip'],

  setup(props, { emit }) {
    const store = useStore();

    const formData = ref({
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
    const fieldErrors = ref({});

    // Clear individual field errors as the user provides input
    watch(formData, (newVal) => {
      const requiredFields = ['date_of_birth', 'gender', 'marital_status', 'address_line_1', 'city', 'postcode'];
      for (const field of requiredFields) {
        if (fieldErrors.value[field] && newVal[field] && String(newVal[field]).trim()) {
          delete fieldErrors.value[field];
        }
      }
      // Clear the general error message when all field errors are resolved
      if (Object.keys(fieldErrors.value).length === 0 && error.value === 'Please complete all required fields marked with *') {
        error.value = null;
      }
    }, { deep: true });

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

    const validateForm = () => {
      fieldErrors.value = {};

      if (!formData.value.date_of_birth) {
        fieldErrors.value.date_of_birth = 'Date of Birth is required';
      }
      if (!formData.value.gender) {
        fieldErrors.value.gender = 'Gender is required';
      }
      if (!formData.value.marital_status) {
        fieldErrors.value.marital_status = 'Marital Status is required';
      }
      if (!formData.value.address_line_1 || !formData.value.address_line_1.trim()) {
        fieldErrors.value.address_line_1 = 'Address Line 1 is required';
      }
      if (!formData.value.city || !formData.value.city.trim()) {
        fieldErrors.value.city = 'City is required';
      }
      if (!formData.value.postcode || !formData.value.postcode.trim()) {
        fieldErrors.value.postcode = 'Postcode is required';
      }

      if (Object.keys(fieldErrors.value).length > 0) {
        error.value = 'Please complete all required fields marked with *';
        return false;
      }

      return true;
    };

    const handleNext = async () => {
      if (!validateForm()) {
        return;
      }

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
          console.error('Failed to fetch current user:', err);
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
    });

    return {
      formData,
      loading,
      error,
      fieldErrors,
      maxDob,
      minDob,
      formatPostcode,
      handleNext,
      handleAddressSelected,
    };
  },
};
</script>
