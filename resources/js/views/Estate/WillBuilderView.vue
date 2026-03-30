<template>
  <AppLayout>
    <div class="module-gradient py-8 px-4 sm:px-6">
      <ModuleStatusBar />
      <!-- Show will overview if user already has a will -->
      <WillPlanning
        v-if="!loading && hasExistingWill"
        :start-in-edit-mode="false"
        @will-updated="loadData"
      />
      <!-- Show will builder if no will exists -->
      <WillBuilderWizard
        v-else-if="!loading"
        :initial-data="initialData"
        :pre-populated="prePopulated"
        :document-id="documentId"
        :start-at-review="startAtReview"
        @document-created="handleDocumentCreated"
      />
      <div v-else class="text-center py-16">
        <div class="w-10 h-10 border-4 border-horizon-200 border-t-raspberry-500 rounded-full animate-spin mx-auto"></div>
        <p class="mt-4 text-neutral-500">Loading...</p>
      </div>
    </div>
  </AppLayout>
</template>

<script>
import AppLayout from '@/layouts/AppLayout.vue';
import WillBuilderWizard from '@/components/Estate/WillBuilder/WillBuilderWizard.vue';
import WillPlanning from '@/components/Estate/WillPlanning.vue';
import estateService from '@/services/estateService';
import api from '@/services/api';
import ModuleStatusBar from '@/components/Shared/ModuleStatusBar.vue';

import logger from '@/utils/logger';
export default {
  name: 'WillBuilderView',

  components: {
    AppLayout,
    WillBuilderWizard,
    WillPlanning,
    ModuleStatusBar,
  },

  data() {
    return {
      loading: true,
      hasExistingWill: false,
      initialData: null,
      prePopulated: null,
      documentId: null,
      startAtReview: false,
    };
  },

  async mounted() {
    await this.loadData();
  },

  watch: {
    '$route.query.view'() {
      this.loadData();
    },
  },

  methods: {
    async loadData() {
      this.loading = true;
      this.hasExistingWill = false;
      this.startAtReview = false;
      try {
        // Check if user already has a will record
        // Skip this check if ?view=document is set (viewing completed will in builder)
        const viewDocument = this.$route.query?.view === 'document';
        if (!viewDocument) {
          const willResponse = await api.get('/estate/will');
          if (willResponse.data?.data?.has_will) {
            this.hasExistingWill = true;
            this.loading = false;
            return;
          }
        }

        // No existing will — load builder data
        const [prePopRes, draftRes] = await Promise.all([
          estateService.getWillBuilderPrePopulate(),
          estateService.getWillBuilderDraft(),
        ]);

        this.prePopulated = prePopRes.data;

        if (draftRes.data) {
          this.initialData = draftRes.data;
          this.documentId = draftRes.data.id;
          if (draftRes.data.status === 'complete') {
            this.startAtReview = true;
          }
        }
      } catch (error) {
        logger.error('Failed to load Will data:', error);
      } finally {
        this.loading = false;
      }
    },

    handleDocumentCreated(doc) {
      this.documentId = doc.id;
      this.initialData = doc;
    },
  },
};
</script>
