<template>
  <div>
    <div class="mb-6 flex flex-col sm:flex-row sm:justify-between sm:items-start gap-4">
      <div>
        <h2 class="text-h4 font-semibold text-gray-900">Personal Information</h2>
        <p class="mt-1 text-body-sm text-gray-600">
          Update your personal details and contact information
        </p>
      </div>
      <button
        v-if="!isEditing"
        type="button"
        @click="isEditing = true"
        class="btn-primary w-full sm:w-auto"
      >
        Edit Information
      </button>
    </div>

    <!-- Success Message -->
    <div v-if="successMessage" class="rounded-md bg-success-50 p-4 mb-6">
      <div class="flex">
        <div class="ml-3">
          <p class="text-body-sm font-medium text-success-800">
            {{ successMessage }}
          </p>
        </div>
      </div>
    </div>

    <!-- Error Message -->
    <div v-if="errorMessage" class="rounded-md bg-error-50 p-4 mb-6">
      <div class="flex">
        <div class="ml-3">
          <h3 class="text-body-sm font-medium text-error-800">Error updating information</h3>
          <div class="mt-2 text-body-sm text-error-700">
            <p>{{ errorMessage }}</p>
          </div>
        </div>
      </div>
    </div>

    <form @submit.prevent="handleSubmit" class="space-y-6">
      <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 sm:gap-6">
        <!-- Name -->
        <div>
          <label for="name" class="block text-body-sm font-medium text-gray-700 mb-1">
            Full Name
          </label>
          <input
            id="name"
            v-model="form.name"
            type="text"
            class="input-field"
            :disabled="!isEditing"
          />
        </div>

        <!-- Email -->
        <div>
          <label for="email" class="block text-body-sm font-medium text-gray-700 mb-1">
            Email Address
          </label>
          <input
            id="email"
            v-model="form.email"
            type="email"
            class="input-field"
            :disabled="!isEditing"
          />
        </div>

        <!-- Date of Birth -->
        <div>
          <label for="date_of_birth" class="block text-body-sm font-medium text-gray-700 mb-1">
            Date of Birth
          </label>
          <input
            id="date_of_birth"
            v-model="form.date_of_birth"
            type="date"
            class="input-field"
            :disabled="!isEditing"
            :max="maxDob"
            :min="minDob"
          />
        </div>

        <!-- Gender -->
        <div>
          <label for="gender" class="block text-body-sm font-medium text-gray-700 mb-1">
            Gender
          </label>
          <select
            id="gender"
            v-model="form.gender"
            class="input-field"
            :disabled="!isEditing"
          >
            <option value="">Select gender</option>
            <option value="male">Male</option>
            <option value="female">Female</option>
            <option value="other">Other</option>
          </select>
        </div>

        <!-- Marital Status -->
        <div>
          <label for="marital_status" class="block text-body-sm font-medium text-gray-700 mb-1">
            Marital Status
          </label>
          <select
            id="marital_status"
            v-model="form.marital_status"
            class="input-field"
            :disabled="!isEditing"
          >
            <option value="">Select status</option>
            <option value="single">Single</option>
            <option value="married">Married</option>
            <option value="divorced">Divorced</option>
            <option value="widowed">Widowed</option>
          </select>
        </div>

        <!-- National Insurance Number -->
        <div>
          <label for="national_insurance_number" class="block text-body-sm font-medium text-gray-700 mb-1">
            National Insurance Number
          </label>
          <input
            id="national_insurance_number"
            v-model="form.national_insurance_number"
            type="text"
            placeholder="AB123456C"
            maxlength="9"
            class="input-field uppercase"
            :disabled="!isEditing"
          />
        </div>

        <!-- Phone -->
        <div>
          <label for="phone" class="block text-body-sm font-medium text-gray-700 mb-1">
            Phone Number
          </label>
          <input
            id="phone"
            v-model="form.phone"
            type="tel"
            placeholder="+44 or 0"
            class="input-field"
            :disabled="!isEditing"
          />
        </div>
      </div>

      <!-- Address Section -->
      <div class="border-t border-gray-200 pt-6">
        <h3 class="text-h5 font-semibold text-gray-900 mb-4">Address</h3>

        <!-- Postcode Lookup (only when editing) -->
        <PostcodeLookup
          v-if="isEditing"
          v-model="form.postcode"
          label="Find Address by Postcode"
          class="mb-4"
          @address-selected="handleAddressSelected"
        />

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 sm:gap-6">
          <!-- Address Line 1 -->
          <div class="sm:col-span-2">
            <label for="address_line_1" class="block text-body-sm font-medium text-gray-700 mb-1">
              Address Line 1
            </label>
            <input
              id="address_line_1"
              v-model="form.address_line_1"
              type="text"
              class="input-field"
              :disabled="!isEditing"
            />
          </div>

          <!-- Address Line 2 -->
          <div class="sm:col-span-2">
            <label for="address_line_2" class="block text-body-sm font-medium text-gray-700 mb-1">
              Address Line 2
            </label>
            <input
              id="address_line_2"
              v-model="form.address_line_2"
              type="text"
              class="input-field"
              :disabled="!isEditing"
            />
          </div>

          <!-- City -->
          <div>
            <label for="city" class="block text-body-sm font-medium text-gray-700 mb-1">
              City
            </label>
            <input
              id="city"
              v-model="form.city"
              type="text"
              class="input-field"
              :disabled="!isEditing"
            />
          </div>

          <!-- County -->
          <div>
            <label for="county" class="block text-body-sm font-medium text-gray-700 mb-1">
              County
            </label>
            <input
              id="county"
              v-model="form.county"
              type="text"
              class="input-field"
              :disabled="!isEditing"
            />
          </div>

          <!-- Postcode -->
          <div>
            <label for="postcode" class="block text-body-sm font-medium text-gray-700 mb-1">
              Postcode
            </label>
            <input
              id="postcode"
              v-model="form.postcode"
              type="text"
              placeholder="SW1A 1AA"
              class="input-field uppercase"
              :disabled="!isEditing"
            />
          </div>
        </div>
      </div>

      <!-- Action Buttons -->
      <div v-if="isEditing" class="flex justify-end space-x-4 pt-6 border-t border-gray-200">
          <button
            type="button"
            @click="handleCancel"
            class="btn-secondary"
            :disabled="submitting"
          >
            Cancel
          </button>
          <button
            type="submit"
            class="btn-primary"
            :disabled="submitting"
          >
            <span v-if="!submitting">Save Changes</span>
            <span v-else>Saving...</span>
          </button>
      </div>
    </form>
  </div>
</template>

<script>
import { ref, computed, watch } from 'vue';
import { useStore } from 'vuex';
import PostcodeLookup from '@/components/Shared/PostcodeLookup.vue';

// Preview mode message
const PREVIEW_SUCCESS_MESSAGE = 'Changes saved for this session only (preview mode).';

export default {
  name: 'PersonalInformation',

  components: {
    PostcodeLookup,
  },

  setup() {
    const store = useStore();
    const isEditing = ref(false);
    const submitting = ref(false);
    const successMessage = ref('');
    const errorMessage = ref('');

    const profile = computed(() => store.getters['userProfile/profile']);
    const personalInfo = computed(() => store.getters['userProfile/personalInfo']);

    // Date constraints: user must be 18-105 years old
    const maxDob = computed(() => {
      const date = new Date();
      date.setFullYear(date.getFullYear() - 18);
      return date.toISOString().split('T')[0];
    });

    const minDob = computed(() => {
      const date = new Date();
      date.setFullYear(date.getFullYear() - 105);
      return date.toISOString().split('T')[0];
    });

    const form = ref({
      name: '',
      email: '',
      date_of_birth: '',
      gender: '',
      marital_status: '',
      national_insurance_number: '',
      phone: '',
      address_line_1: '',
      address_line_2: '',
      city: '',
      county: '',
      postcode: '',
    });

    // Format date for HTML5 date input (yyyy-MM-dd)
    const formatDateForInput = (date) => {
      if (!date) return '';
      try {
        // If it's already in YYYY-MM-DD format, return it
        if (typeof date === 'string' && /^\d{4}-\d{2}-\d{2}$/.test(date)) {
          return date;
        }
        // Parse and format the date
        const dateObj = new Date(date);
        if (isNaN(dateObj.getTime())) return '';
        const year = dateObj.getFullYear();
        const month = String(dateObj.getMonth() + 1).padStart(2, '0');
        const day = String(dateObj.getDate()).padStart(2, '0');
        return `${year}-${month}-${day}`;
      } catch (e) {
        return '';
      }
    };

    // Initialize form from personalInfo
    const initializeForm = () => {
      if (personalInfo.value) {
        form.value = {
          name: personalInfo.value.name || '',
          email: personalInfo.value.email || '',
          date_of_birth: formatDateForInput(personalInfo.value.date_of_birth),
          gender: personalInfo.value.gender || '',
          marital_status: personalInfo.value.marital_status || '',
          national_insurance_number: personalInfo.value.national_insurance_number || '',
          phone: personalInfo.value.phone || '',
          address_line_1: personalInfo.value.address?.line_1 || '',
          address_line_2: personalInfo.value.address?.line_2 || '',
          city: personalInfo.value.address?.city || '',
          county: personalInfo.value.address?.county || '',
          postcode: personalInfo.value.address?.postcode || '',
        };
      }
    };

    // Watch for changes in personalInfo and reinitialize form
    watch(personalInfo, () => {
      initializeForm();
    }, { immediate: true });

    const handleSubmit = async () => {
      submitting.value = true;
      successMessage.value = '';
      errorMessage.value = '';

      try {
        // Clean up form data: convert empty strings to null for optional fields
        const cleanedData = Object.entries(form.value).reduce((acc, [key, value]) => {
          // Keep required fields as-is (name, email)
          if (key === 'name' || key === 'email') {
            acc[key] = value;
          } else {
            // Convert empty strings to null for optional fields
            acc[key] = value === '' ? null : value;
          }
          return acc;
        }, {});

        const response = await store.dispatch('userProfile/updatePersonalInfo', cleanedData);

        // Check if we're in preview mode
        const isPreviewMode = store.getters['preview/isPreviewMode'];
        successMessage.value = isPreviewMode
          ? PREVIEW_SUCCESS_MESSAGE
          : 'Personal information updated successfully!';
        isEditing.value = false;

        // Clear success message after 3 seconds (longer for preview)
        setTimeout(() => {
          successMessage.value = '';
        }, isPreviewMode ? 5000 : 3000);
      } catch (error) {
        console.error('Update error:', error);
        // Show validation errors if available
        if (error.errors) {
          const errors = Object.values(error.errors).flat();
          errorMessage.value = errors.join('. ');
        } else {
          errorMessage.value = error.message || 'Failed to update personal information';
        }
      } finally {
        submitting.value = false;
      }
    };

    const handleCancel = () => {
      initializeForm();
      isEditing.value = false;
      errorMessage.value = '';
    };

    const handleAddressSelected = (address) => {
      // Populate address fields from postcode lookup
      form.value.address_line_1 = address.line_1 || '';
      form.value.address_line_2 = address.line_2 || '';
      form.value.city = address.city || '';
      form.value.county = address.county || '';
      form.value.postcode = address.postcode || '';
    };

    return {
      form,
      isEditing,
      submitting,
      successMessage,
      errorMessage,
      maxDob,
      minDob,
      handleSubmit,
      handleCancel,
      handleAddressSelected,
    };
  },
};
</script>
