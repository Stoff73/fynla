<template>
  <div>
    <div class="mb-6">
      <h2 class="text-h4 font-semibold text-gray-900">Household Expenditure</h2>
      <p class="mt-1 text-body-sm text-gray-600">
        Manage your spending patterns for accurate financial planning
      </p>
    </div>

    <!-- Error Message -->
    <div v-if="error" class="rounded-md bg-error-50 p-4 mb-6">
      <p class="text-body-sm text-error-800">{{ error }}</p>
    </div>

    <!-- Success Message -->
    <div v-if="successMessage" class="rounded-md bg-success-50 p-4 mb-6">
      <p class="text-body-sm text-success-800">{{ successMessage }}</p>
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
</template>

<script>
import { ref, computed, onMounted, watch } from 'vue';
import { useStore } from 'vuex';
import ExpenditureForm from './ExpenditureForm.vue';

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
      // Skip in preview mode
      const isPreviewMode = store.getters['preview/isPreviewMode'];
      if (isPreviewMode) {
        console.log('[ExpenditureOverview] Preview mode - skipping spouse fetch');
        return;
      }

      if (!user.value?.spouse_id) return;

      try {
        // Fetch spouse user data via API
        const response = await store.dispatch('auth/fetchUserById', user.value.spouse_id);
        spouse.value = response || {};
      } catch (err) {
        console.error('Failed to fetch spouse data:', err);
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

        successMessage.value = 'Expenditure updated successfully';

        setTimeout(() => {
          successMessage.value = null;
        }, 3000);
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

    onMounted(() => {
      // Skip API calls in preview mode - data already loaded
      const isPreviewMode = store.getters['preview/isPreviewMode'];
      if (isPreviewMode) {
        console.log('[ExpenditureOverview] Preview mode - skipping onMounted API calls');
        return;
      }

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
