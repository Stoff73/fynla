<template>
  <AppLayout>
    <div class="plan-page py-6">
      <div class="">
        <!-- Print Header (hidden on screen) -->
        <PrintHeader :title="title" />

        <!-- Print CTA -->
        <div v-if="!loading && !error" class="mb-6">
          <button
            class="inline-flex items-center px-4 py-2 text-sm font-medium text-white bg-raspberry-500 rounded-lg hover:bg-raspberry-600 transition-colors"
            @click="handlePrint"
          >
            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
            </svg>
            Print / Save PDF
          </button>
        </div>

        <!-- Loading State -->
        <PlanLoadingState v-if="loading" :message="loadingMessage" />

        <!-- Error State -->
        <PlanErrorState v-else-if="error" :message="error" @retry="$emit('retry')" />

        <!-- Content -->
        <div v-else>
          <slot />
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script>
import AppLayout from '@/layouts/AppLayout.vue';
import PrintHeader from '@/components/Common/PrintHeader.vue';
import PlanLoadingState from './PlanLoadingState.vue';
import PlanErrorState from './PlanErrorState.vue';

export default {
  name: 'PlanPageLayout',

  components: {
    AppLayout,
    PrintHeader,
    PlanLoadingState,
    PlanErrorState,
  },

  props: {
    title: {
      type: String,
      required: true,
    },
    subtitle: {
      type: String,
      default: null,
    },
    loading: {
      type: Boolean,
      default: false,
    },
    error: {
      type: String,
      default: null,
    },
    loadingMessage: {
      type: String,
      default: 'Generating your plan...',
    },
    printTitle: {
      type: String,
      default: null,
    },
    planData: {
      type: Object,
      default: null,
    },
  },

  emits: ['retry', 'print'],

  methods: {
    handlePrint() {
      this.$emit('print');
    },
  },
};
</script>
