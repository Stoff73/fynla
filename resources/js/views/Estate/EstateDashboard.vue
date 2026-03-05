<template>
  <AppLayout>
    <div class="estate-dashboard py-2 sm:py-6">
      <div class="max-w-7xl mx-auto">
      <!-- Header -->
      <div class="mb-8">
        <h1 class="text-xl sm:text-2xl lg:text-3xl font-bold text-horizon-500 mb-2">Estate Planning</h1>
        <p class="text-neutral-500">
          Plan your estate with Inheritance Tax calculations, gifting strategies, and trust planning
        </p>
      </div>

      <!-- Loading State -->
      <div v-if="initialLoading" class="flex justify-center items-center py-12">
        <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-violet-600"></div>
      </div>

      <!-- Error State -->
      <div
        v-else-if="error"
        class="bg-raspberry-50 border-l-4 border-raspberry-500 p-4 mb-6"
      >
        <div class="flex">
          <div class="flex-shrink-0">
            <svg
              class="h-5 w-5 text-raspberry-400"
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
            <p class="text-sm text-raspberry-700">{{ error }}</p>
          </div>
        </div>
      </div>

      <!-- Main Content -->
      <div v-else>
        <!-- Life Events relevant to estate planning -->
        <ModuleLifeEvents
          class="mb-6"
          module="estate"
          :events="lifeEvents"
          :impact-summary="lifeEventImpact"
        />

        <!-- Tab Content -->
        <div>
          <!-- Inheritance Tax Planning Tab -->
          <IHTPlanning v-if="activeTab === 'iht'" @will-updated="reloadIHTCalculation" @switch-tab="switchTab" />

          <!-- Gifting Strategy Tab -->
          <GiftingStrategy v-else-if="activeTab === 'gifting'" @switch-tab="switchTab" />

          <!-- Life Policy Strategy Tab -->
          <LifePolicyStrategy v-else-if="activeTab === 'life-policy'" @switch-tab="switchTab" />

          <!-- Trust Planning Tab -->
          <TrustPlanning v-else-if="activeTab === 'trusts'" @switch-tab="switchTab" />
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
import ModuleLifeEvents from '@/components/Shared/ModuleLifeEvents.vue';

export default {
  name: 'EstateDashboard',

  components: {
    AppLayout,
    IHTPlanning,
    GiftingStrategy,
    LifePolicyStrategy,
    TrustPlanning,
    ModuleLifeEvents,
  },

  data() {
    return {
      activeTab: 'iht',
      initialLoading: true,
      tabs: [
        { id: 'iht', label: 'Inheritance Tax Planning' },
        { id: 'gifting', label: 'Gifting Strategy' },
        { id: 'life-policy', label: 'Life Policy Strategy' },
        { id: 'trusts', label: 'Trust Strategy' },
      ],
    };
  },

  computed: {
    ...mapState('estate', ['error', 'lifeEvents', 'lifeEventImpact']),

    isPreviewMode() {
      return this.$store.getters['preview/isPreviewMode'];
    },
  },

  mounted() {
    this.loadEstateData();
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

    reloadIHTCalculation() {
      // Force reload Inheritance Tax calculation when will is updated
      if (this.activeTab === 'iht') {
        // IHTPlanning component will reload automatically
        this.$forceUpdate();
      }
    },

    switchTab(tabId) {
      // Switch to a specific tab (e.g., from Inheritance Tax Planning to Gifting)
      this.activeTab = tabId;
    },
  },
};
</script>

