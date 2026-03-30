<template>
  <div class="space-y-6">
    <!-- Error Message -->
    <div v-if="error" class="rounded-md bg-raspberry-50 p-4">
      <p class="text-body-sm text-raspberry-800">{{ error }}</p>
    </div>

    <!-- Success Message -->
    <div v-if="successMessage" class="rounded-md bg-success-50 p-4">
      <p class="text-body-sm text-success-800">{{ successMessage }}</p>
    </div>

    <!-- Expenditure Card -->
    <div class="bg-white rounded-lg border border-light-gray p-6">
      <div class="mb-6">
        <h3 class="text-h4 font-semibold text-horizon-500">Household Expenditure</h3>
        <p class="mt-1 text-body-sm text-neutral-500">
          Manage your spending patterns for accurate financial planning
        </p>
      </div>

      <!-- Shared Expenditure Form -->
      <ExpenditureForm
      :initial-data="user"
      :spouse-data="spouse"
      :spouse-name="spouseName"
      :is-married="isMarried"
      :always-show-tabs="true"
      :show-cancel="true"
      cancel-text="Reset"
      save-text="Save Changes"
        @save="handleSave"
        @cancel="handleReset"
      />
    </div>
  </div>
</template>

<script>
import { ref, computed, onMounted, onBeforeUnmount, watch } from 'vue';
import { useStore } from 'vuex';
import ExpenditureForm from './ExpenditureForm.vue';

import logger from '@/utils/logger';
export default {
  name: 'ExpenditureOverview',

  components: {
    ExpenditureForm,
  },

  setup() {
    const store = useStore();
    const error = ref(null);
    const successMessage = ref(null);
    const spouse = ref({});
    let messageTimeout = null;

    const user = computed(() => store.getters['auth/currentUser']);
    const profile = computed(() => store.getters['userProfile/profile']);

    const isMarried = computed(() => {
      return user.value?.marital_status === 'married' && !!user.value?.spouse_id;
    });

    const spouseName = computed(() => {
      if (!spouse.value || !spouse.value.name) return 'Spouse';
      return spouse.value.name.split(' ')[0]; // Get first name only
    });

    const fetchSpouseData = async () => {
      // Fetch spouse data for all users (including preview mode)
      // Preview users are real database users and use the same code paths
      if (!user.value?.spouse_id) return;

      try {
        // Fetch spouse user data via API
        const response = await store.dispatch('auth/fetchUserById', user.value.spouse_id);
        spouse.value = response || {};
      } catch (err) {
        logger.error('Failed to fetch spouse data:', err);
        spouse.value = {};
      }
    };

    const handleSave = async (formData) => {
      error.value = null;
      successMessage.value = null;

      try {
        // Check if formData contains both userData and spouseData (separate mode)
        if (formData.userData && formData.spouseData) {
          // Save user data
          await store.dispatch('userProfile/updateExpenditure', formData.userData);

          // Save spouse data
          if (user.value?.spouse_id) {
            await store.dispatch('userProfile/updateSpouseExpenditure', {
              spouseId: user.value.spouse_id,
              expenditureData: formData.spouseData,
            });
          }
        } else {
          // Joint mode or single user - save just user data
          await store.dispatch('userProfile/updateExpenditure', formData);
        }

        // Refresh user and spouse data
        await store.dispatch('auth/fetchUser');
        await store.dispatch('userProfile/fetchProfile');
        await fetchSpouseData();

        // Check if we're in preview mode
        const isPreviewMode = store.getters['preview/isPreviewMode'];
        successMessage.value = isPreviewMode
          ? 'Expenditure saved for this session only (preview mode).'
          : 'Expenditure updated successfully';

        if (messageTimeout) clearTimeout(messageTimeout);
        messageTimeout = setTimeout(() => {
          successMessage.value = null;
        }, isPreviewMode ? 5000 : 3000);
      } catch (err) {
        error.value = err.response?.data?.message || 'Failed to update expenditure. Please try again.';
      }
    };

    const handleReset = () => {
      // Trigger a re-fetch to reset the form
      store.dispatch('auth/fetchUser');
      fetchSpouseData();
      error.value = null;
      successMessage.value = null;
    };

    onBeforeUnmount(() => {
      if (messageTimeout) clearTimeout(messageTimeout);
    });

    onMounted(() => {
      // Load data for all users (including preview mode)
      // Preview users are real database users and use the same code paths
      if (!profile.value) {
        store.dispatch('userProfile/fetchProfile');
      }
      if (isMarried.value) {
        fetchSpouseData();
      }
    });

    return {
      user,
      spouse,
      spouseName,
      isMarried,
      error,
      successMessage,
      handleSave,
      handleReset,
    };
  },
};
</script>
