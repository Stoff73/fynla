<template>
  <AppLayout>
    <div class="estate-dashboard py-2 sm:py-6">
      <div class="max-w-7xl mx-auto">
      <!-- Header -->
      <div class="mb-8">
        <h1 class="text-3xl font-bold text-gray-900 mb-2">Estate Planning</h1>
        <p class="text-gray-600">
          Plan your estate with IHT calculations, gifting strategies, and trust planning
        </p>
      </div>

      <!-- Profile Completeness Alert -->
      <ProfileCompletenessAlert
        v-if="profileCompleteness && !loadingCompleteness"
        :completenessData="profileCompleteness"
        :dismissible="true"
      />

      <!-- Loading State -->
      <div v-if="initialLoading" class="flex justify-center items-center py-12">
        <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-600"></div>
      </div>

      <!-- Error State -->
      <div
        v-else-if="error"
        class="bg-red-50 border-l-4 border-red-500 p-4 mb-6"
      >
        <div class="flex">
          <div class="flex-shrink-0">
            <svg
              class="h-5 w-5 text-red-400"
              xmlns="http://www.w3.org/2000/svg"
              viewBox="0 0 20 20"
              fill="currentColor"
            >
              <path
                fill-rule="evenodd"
                d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z"
                clip-rule="evenodd"
              />
            </svg>
          </div>
          <div class="ml-3">
            <p class="text-sm text-red-700">{{ error }}</p>
          </div>
        </div>
      </div>

      <!-- Main Content -->
      <div v-else class="bg-white rounded-lg shadow">
        <!-- Tab Navigation -->
        <div class="border-b border-gray-200">
          <nav class="-mb-px flex overflow-x-auto" aria-label="Tabs">
            <button
              v-for="tab in tabs"
              :key="tab.id"
              @click="activeTab = tab.id"
              :class="[
                activeTab === tab.id
                  ? 'border-blue-500 text-blue-600'
                  : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300',
                'whitespace-nowrap py-3 sm:py-4 px-3 sm:px-6 border-b-2 font-medium text-xs sm:text-sm transition-colors duration-200 flex-shrink-0',
              ]"
            >
              {{ tab.label }}
            </button>
          </nav>
        </div>

        <!-- Tab Content -->
        <div class="p-6">
          <!-- IHT Planning Tab -->
          <IHTPlanning v-if="activeTab === 'iht'" @will-updated="reloadIHTCalculation" @switch-tab="switchTab" />

          <!-- Gifting Strategy Tab -->
          <GiftingStrategy v-else-if="activeTab === 'gifting'" />

          <!-- Life Policy Strategy Tab -->
          <LifePolicyStrategy v-else-if="activeTab === 'life-policy'" />

          <!-- Trust Planning Tab -->
          <TrustPlanning v-else-if="activeTab === 'trusts'" />
        </div>
      </div>
      </div>
    </div>
  </AppLayout>
</template>

<script>
import { mapState, mapActions } from 'vuex';
import AppLayout from '@/layouts/AppLayout.vue';
import IHTPlanning from '@/components/Estate/IHTPlanning.vue';
import GiftingStrategy from '@/components/Estate/GiftingStrategy.vue';
import LifePolicyStrategy from '@/components/Estate/LifePolicyStrategy.vue';
import TrustPlanning from '@/components/Estate/TrustPlanning.vue';
import ProfileCompletenessAlert from '@/components/Shared/ProfileCompletenessAlert.vue';
import api from '@/services/api';

export default {
  name: 'EstateDashboard',

  components: {
    AppLayout,
    IHTPlanning,
    GiftingStrategy,
    LifePolicyStrategy,
    TrustPlanning,
    ProfileCompletenessAlert,
  },

  data() {
    return {
      activeTab: 'iht',
      initialLoading: true,
      tabs: [
        { id: 'iht', label: 'IHT Planning' },
        { id: 'gifting', label: 'Gifting Strategy' },
        { id: 'life-policy', label: 'Life Policy Strategy' },
        { id: 'trusts', label: 'Trust Strategy' },
      ],
      profileCompleteness: null,
      loadingCompleteness: false,
    };
  },

  computed: {
    ...mapState('estate', ['error']),

    isPreviewMode() {
      return this.$store.getters['preview/isPreviewMode'];
    },
  },

  mounted() {
    this.loadEstateData();
    // Skip profile completeness in preview mode
    if (!this.isPreviewMode) {
      this.loadProfileCompleteness();
    }
  },

  methods: {
    ...mapActions('estate', ['fetchEstateData']),

    async loadEstateData() {
      try {
        await this.fetchEstateData();
      } catch (error) {
        console.error('Failed to load estate data:', error);
      } finally {
        this.initialLoading = false;
      }
    },

    async loadProfileCompleteness() {
      this.loadingCompleteness = true;
      try {
        const response = await api.get('/user/profile/completeness');
        const completenessData = response.data.data;

        // Filter out protection and dependants fields for Estate module
        const filteredMissingFields = {};
        Object.keys(completenessData.missing_fields || {}).forEach(key => {
          const field = completenessData.missing_fields[key];
          // Exclude protection policies and dependants
          if (!field.message.toLowerCase().includes('protection') &&
              !field.message.toLowerCase().includes('dependant')) {
            filteredMissingFields[key] = field;
          }
        });

        // Recalculate completeness score based on remaining fields
        const totalFields = Object.keys(completenessData.missing_fields || {}).length;
        const remainingFields = Object.keys(filteredMissingFields).length;
        const fieldsCompleted = totalFields - remainingFields;

        this.profileCompleteness = {
          ...completenessData,
          missing_fields: filteredMissingFields,
          completeness_score: remainingFields === 0 ? 100 : Math.round((fieldsCompleted / totalFields) * 100),
          is_complete: remainingFields === 0,
        };
      } catch (error) {
        console.error('Failed to load profile completeness:', error);
      } finally {
        this.loadingCompleteness = false;
      }
    },

    reloadIHTCalculation() {
      // Force reload IHT calculation when will is updated
      if (this.activeTab === 'iht') {
        // IHTPlanning component will reload automatically
        this.$forceUpdate();
      }
    },

    switchTab(tabId) {
      // Switch to a specific tab (e.g., from IHT Planning to Gifting)
      this.activeTab = tabId;
    },
  },
};
</script>

<style scoped>
/* Mobile optimization for tab navigation */
@media (max-width: 640px) {
  .estate-dashboard nav[aria-label="Tabs"] button {
    font-size: 0.875rem;
    padding-left: 1rem;
    padding-right: 1rem;
  }
}

/* Smooth scroll for tab navigation on mobile */
nav[aria-label="Tabs"] {
  -webkit-overflow-scrolling: touch;
  scrollbar-width: none;
}

nav[aria-label="Tabs"]::-webkit-scrollbar {
  display: none;
}
</style>
