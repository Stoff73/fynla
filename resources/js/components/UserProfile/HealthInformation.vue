<template>
  <div class="space-y-6">
    <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
      <p class="text-body-sm text-blue-800">
        <strong>Why this matters:</strong> Health and lifestyle information helps us provide accurate protection recommendations and estimate insurance premium costs.
      </p>
    </div>

    <!-- Display Mode -->
    <div v-if="!isEditing" class="bg-white rounded-lg border border-light-gray p-6">
      <div class="flex justify-between items-start mb-6">
        <h3 class="text-h4 font-semibold text-horizon-500">Health & Lifestyle</h3>
        <button
          @click="startEditing"
          class="btn-secondary"
        >
          Edit
        </button>
      </div>

      <!-- Clean two-column layout -->
      <div class="grid grid-cols-1 lg:grid-cols-2 gap-x-12 gap-y-6">
        <!-- Left Column -->
        <div class="space-y-3">
          <div class="flex justify-between">
            <span class="text-body-sm text-neutral-500">Health Status:</span>
            <span class="text-body-sm text-horizon-500 text-right">{{ formatHealthStatus(displayData.health_status) }}</span>
          </div>
          <div class="flex justify-between">
            <span class="text-body-sm text-neutral-500">Smoking Status:</span>
            <span class="text-body-sm text-horizon-500 text-right">{{ formatSmokingStatus(displayData.smoking_status) }}</span>
          </div>
        </div>

        <!-- Right Column -->
        <div class="space-y-3">
          <div class="flex justify-between">
            <span class="text-body-sm text-neutral-500">Education Level:</span>
            <span class="text-body-sm text-horizon-500 text-right">{{ formatEducationLevel(displayData.education_level) }}</span>
          </div>
        </div>
      </div>
    </div>

    <!-- Edit Mode -->
    <div v-else class="bg-white rounded-lg border border-light-gray p-6">
      <h3 class="text-h4 font-semibold text-horizon-500 mb-6">Edit Health & Lifestyle</h3>

      <form @submit.prevent="saveChanges" class="space-y-6">
        <!-- Error Message -->
        <div v-if="error" class="bg-red-50 border border-red-200 rounded-lg p-4">
          <p class="text-body-sm text-red-800">{{ error }}</p>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 sm:gap-6">
          <!-- Health Status -->
          <div>
            <label for="health_status" class="label">
              Are you in good health?
            </label>
            <select
              id="health_status"
              v-model="formData.health_status"
              class="input-field"
              required
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
              required
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

        <!-- Action Buttons -->
        <div class="flex justify-end space-x-3 pt-4 border-t">
          <button
            type="button"
            @click="cancelEditing"
            class="btn-secondary"
            :disabled="saving"
          >
            Cancel
          </button>
          <button
            type="submit"
            class="btn-primary"
            :disabled="saving"
          >
            <span v-if="saving">Saving...</span>
            <span v-else>Save Changes</span>
          </button>
        </div>
      </form>
    </div>
  </div>
</template>

<script>
import { ref, computed, watch } from 'vue';
import { useStore } from 'vuex';
import userProfileService from '@/services/userProfileService';

import logger from '@/utils/logger';
export default {
  name: 'HealthInformation',

  setup() {
    const store = useStore();
    const isEditing = ref(false);
    const saving = ref(false);
    const error = ref(null);

    const user = computed(() => store.getters['auth/currentUser']);

    const displayData = computed(() => ({
      health_status: user.value?.health_status || '',
      smoking_status: user.value?.smoking_status || '',
      education_level: user.value?.education_level || '',
    }));

    const formData = ref({
      health_status: '',
      smoking_status: '',
      education_level: '',
    });

    // Watch for user changes and update form data
    watch(user, (newUser) => {
      if (newUser) {
        formData.value = {
          health_status: newUser.health_status || '',
          smoking_status: newUser.smoking_status || '',
          education_level: newUser.education_level || '',
        };
      }
    }, { immediate: true });

    const formatHealthStatus = (status) => {
      const statusMap = {
        'yes': 'Yes, good health',
        'yes_previous': 'Yes, previous health conditions',
        'no_previous': 'No, previous health conditions',
        'no_existing': 'No, existing health conditions',
        'no_both': 'No, previous and existing health conditions',
      };
      return statusMap[status] || 'Not specified';
    };

    const formatSmokingStatus = (status) => {
      const statusMap = {
        'never': 'Never smoked',
        'quit_recent': 'No, gave up 12 months or sooner',
        'quit_long_ago': 'No, gave up more than 12 months ago',
        'yes': 'Yes',
      };
      return statusMap[status] || 'Not specified';
    };

    const formatEducationLevel = (level) => {
      const levelMap = {
        'secondary': 'Secondary (GCSE/O-Levels)',
        'a_level': 'A-Levels/Vocational',
        'undergraduate': 'Undergraduate Degree',
        'postgraduate': 'Postgraduate Degree',
        'professional': 'Professional Qualification',
        'other': 'Other',
      };
      return levelMap[level] || 'Not specified';
    };

    const startEditing = () => {
      formData.value = {
        health_status: displayData.value.health_status,
        smoking_status: displayData.value.smoking_status,
        education_level: displayData.value.education_level,
      };
      error.value = null;
      isEditing.value = true;
    };

    const cancelEditing = () => {
      isEditing.value = false;
      error.value = null;
    };

    const saveChanges = async () => {
      error.value = null;
      saving.value = true;

      try {
        await userProfileService.updatePersonalInfo(formData.value);

        // Update the user in the store
        await store.dispatch('auth/fetchUser');

        isEditing.value = false;
      } catch (err) {
        logger.error('Failed to save health information:', err);
        error.value = err.response?.data?.message || 'Failed to save health information. Please try again.';
      } finally {
        saving.value = false;
      }
    };

    return {
      isEditing,
      saving,
      error,
      displayData,
      formData,
      formatHealthStatus,
      formatSmokingStatus,
      formatEducationLevel,
      startEditing,
      cancelEditing,
      saveChanges,
    };
  },
};
</script>
