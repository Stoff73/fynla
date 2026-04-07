<template>
  <AppLayout>
    <div class="estate-dashboard module-gradient py-2 sm:py-6">
      <ModuleStatusBar />
      <div class="">
      <!-- Loading State -->
      <div v-if="initialLoading" class="flex justify-center items-center py-12">
        <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-violet-600"></div>
      </div>

      <!-- Error State -->
      <div
        v-else-if="error"
        class="bg-raspberry-50 border border-raspberry-200 p-4 mb-6"
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
        <!-- Will Builder Banner (only show when no will exists) -->
        <div v-if="!hasWillDocument" class="mb-6">
          <router-link
            to="/estate/will-builder"
            class="block bg-white border border-light-gray rounded-lg p-5 hover:bg-light-gray hover:shadow-sm transition-all group"
          >
            <div class="flex items-center justify-between">
              <div class="flex items-center gap-4">
                <div class="w-10 h-10 bg-raspberry-100 rounded-lg flex items-center justify-center flex-shrink-0">
                  <svg class="w-5 h-5 text-raspberry-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                  </svg>
                </div>
                <div>
                  <h3 class="text-sm font-semibold text-horizon-500 group-hover:text-raspberry-600 transition-colors">Build Your Will</h3>
                  <p class="text-xs text-neutral-500">Create a legally-structured will for England and Wales with our guided builder</p>
                </div>
              </div>
              <svg class="w-5 h-5 text-neutral-500 group-hover:text-raspberry-500 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
              </svg>
            </div>
          </router-link>
        </div>

        <!-- Estate Planning Cards -->
        <IHTPlanning @will-updated="reloadIHTCalculation" />
      </div>
      </div>
    </div>
  </AppLayout>
</template>

<script>
import { mapState, mapActions } from 'vuex';
import AppLayout from '@/layouts/AppLayout.vue';
import IHTPlanning from '@/components/Estate/IHTPlanning.vue';
import estateService from '@/services/estateService';
import ModuleStatusBar from '@/components/Shared/ModuleStatusBar.vue';

import logger from '@/utils/logger';
export default {
  name: 'EstateDashboard',

  components: {
    AppLayout,
    IHTPlanning,
    ModuleStatusBar,
  },

  data() {
    return {
      initialLoading: true,
      hasWillDocument: false,
    };
  },

  computed: {
    ...mapState('estate', ['error', 'willInfo']),

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
        // Check if user has a will (traditional Will record OR WillDocument from builder)
        if (this.willInfo?.has_will) {
          this.hasWillDocument = true;
        } else {
          try {
            const willResponse = await estateService.getWillBuilderDraft();
            if (willResponse && willResponse.data) {
              this.hasWillDocument = true;
            }
          } catch {
            // No will document found — banner will show
          }
        }
      } catch (error) {
        logger.error('Failed to load estate data:', error);
      } finally {
        this.initialLoading = false;
      }
    },

    reloadIHTCalculation() {
      this.$forceUpdate();
    },
  },
};
</script>

