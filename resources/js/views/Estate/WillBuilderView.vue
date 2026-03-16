<template>
  <AppLayout>
    <div class="max-w-5xl mx-auto py-8 px-4 sm:px-6 lg:px-8">
      <WillBuilderWizard
        v-if="!loading"
        :initial-data="initialData"
        :pre-populated="prePopulated"
        :document-id="documentId"
        @document-created="handleDocumentCreated"
      />
      <div v-else class="text-center py-16">
        <div class="w-10 h-10 border-4 border-horizon-200 border-t-raspberry-500 rounded-full animate-spin mx-auto"></div>
        <p class="mt-4 text-neutral-500">Loading Will Builder...</p>
      </div>
    </div>
  </AppLayout>
</template>

<script>
import AppLayout from '@/layouts/AppLayout.vue';
import WillBuilderWizard from '@/components/Estate/WillBuilder/WillBuilderWizard.vue';
import estateService from '@/services/estateService';

export default {
  name: 'WillBuilderView',

  components: {
    AppLayout,
    WillBuilderWizard,
  },

  data() {
    return {
      loading: true,
      initialData: null,
      prePopulated: null,
      documentId: null,
    };
  },

  async mounted() {
    try {
      // Load pre-populated data and check for existing draft in parallel
      const [prePopRes, draftRes] = await Promise.all([
        estateService.getWillBuilderPrePopulate(),
        estateService.getWillBuilderDraft(),
      ]);

      this.prePopulated = prePopRes.data;

      if (draftRes.data) {
        this.initialData = draftRes.data;
        this.documentId = draftRes.data.id;
      }
    } catch (error) {
      console.error('Failed to load Will Builder data:', error);
    } finally {
      this.loading = false;
    }
  },

  methods: {
    handleDocumentCreated(doc) {
      this.documentId = doc.id;
      this.initialData = doc;
    },
  },
};
</script>
