<template>
  <div class="lpa-detail-view">
    <!-- Back button -->
    <button @click="$emit('back')" class="detail-inline-back mb-4 print:hidden">
      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
      </svg>
      Back to Lasting Powers of Attorney
    </button>

    <!-- Screen header -->
    <div class="flex items-center justify-between mb-6 print:hidden">
      <div class="flex items-center space-x-3">
        <h2 class="text-lg font-bold text-horizon-500">{{ typeLabel }}</h2>
        <span :class="['inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium', statusClass]">
          {{ statusLabel }}
        </span>
      </div>
      <div class="flex items-center space-x-2">
        <button
          class="px-3 py-1.5 text-sm font-medium text-horizon-500 border border-light-gray rounded-lg hover:bg-savannah-100 flex items-center gap-2"
          @click="handlePrint"
        >
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/>
          </svg>
          Print / Save PDF
        </button>
        <button
          v-preview-disabled
          class="px-3 py-1.5 text-sm font-medium text-white bg-raspberry-500 rounded-lg hover:bg-raspberry-600"
          @click="$emit('edit', lpa)"
        >
          Edit
        </button>
      </div>
    </div>

    <!-- Legal Document View -->
    <div class="bg-white rounded-lg shadow-sm border border-light-gray p-8 mb-4">
      <div
        class="lpa-document prose prose-sm max-w-none"
        v-html="sanitizedHtml"
      ></div>
    </div>

    <!-- Compliance Checklist (draft/incomplete only) -->
    <div v-if="lpa.status === 'draft'" class="bg-white rounded-lg border border-light-gray p-5 mb-4 print:hidden">
      <h3 class="text-sm font-bold text-horizon-500 uppercase tracking-wider mb-3 border-b border-light-gray pb-2">
        Compliance Checks
      </h3>
      <LpaComplianceChecklist
        :compliance="compliance"
        :loading="complianceLoading"
      />
    </div>

    <!-- Legal Disclaimer -->
    <div class="bg-savannah-100 rounded-lg p-4 text-xs text-neutral-500">
      <p class="font-medium text-horizon-500 mb-1">Important</p>
      <p>
        This document is a record of your Lasting Power of Attorney details. It is not a legally binding document.
        To make your Lasting Power of Attorney legally valid, you must print and sign the official forms and register
        them with the Office of the Public Guardian. Physical (wet ink) signatures are required from the donor,
        attorneys, certificate provider, and any witnesses. Visit
        <span class="font-medium">gov.uk/lasting-power-of-attorney</span> for more information.
      </p>
    </div>

  </div>
</template>

<script>
import LpaComplianceChecklist from './LpaComplianceChecklist.vue';
import { renderLpaDocument, printLpaDocument } from '@/utils/lpaDocumentRenderer';
import { sanitizeHtml } from '@/utils/sanitizeHtml';
import estateService from '@/services/estateService';

export default {
  name: 'LpaDetailView',

  components: {
    LpaComplianceChecklist,
  },

  props: {
    lpa: {
      type: Object,
      required: true,
    },
  },

  emits: ['back', 'edit'],

  data() {
    return {
      compliance: null,
      complianceLoading: false,
    };
  },

  computed: {
    typeLabel() {
      return this.lpa.lpa_type === 'property_financial'
        ? 'Property & Financial Affairs'
        : 'Health & Welfare';
    },
    statusLabel() {
      const labels = {
        draft: 'Draft',
        completed: 'Completed',
        registered: 'Registered',
        uploaded: 'Uploaded',
      };
      return labels[this.lpa.status] || this.lpa.status;
    },
    statusClass() {
      const classes = {
        draft: 'bg-neutral-100 text-neutral-600',
        completed: 'bg-violet-100 text-violet-800',
        registered: 'bg-spring-100 text-spring-800',
        uploaded: 'bg-light-blue-100 text-light-blue-800',
      };
      return classes[this.lpa.status] || 'bg-neutral-100 text-neutral-600';
    },
    renderedHtml() {
      return renderLpaDocument(this.lpa);
    },
    sanitizedHtml() {
      return sanitizeHtml(this.renderedHtml);
    },
  },

  mounted() {
    if (this.lpa.status === 'draft') {
      this.loadCompliance();
    }
  },

  methods: {
    async loadCompliance() {
      if (!this.lpa.id) return;
      this.complianceLoading = true;
      try {
        const response = await estateService.getLpaCompliance(this.lpa.id);
        this.compliance = response.data;
      } catch {
        // Compliance check is optional — fail silently
      } finally {
        this.complianceLoading = false;
      }
    },

    handlePrint() {
      printLpaDocument(this.lpa);
    },
  },
};
</script>

<style scoped>
.lpa-document {
  font-family: 'Times New Roman', Georgia, serif;
  font-size: 11px;
  line-height: 1.5;
  max-height: 700px;
  overflow-y: auto;
  padding: 20px;
  @apply border border-light-gray;
  border-radius: 8px;
  @apply bg-eggshell-50;
}

/* Print document — exact hex values required for print/document fidelity.
   #1F2A44 = horizon-500 (brand border), #ddd = rule divider, #000 = signature line.
   These are inside :deep() document renderers and must not use Tailwind utilities. */
.lpa-document :deep(h1) {
  text-align: center;
  font-size: 16px;
  letter-spacing: 2px;
  margin-bottom: 4px;
}

.lpa-document :deep(h2) {
  text-align: center;
  font-size: 13px;
  font-weight: 400;
  margin-bottom: 8px;
}

.lpa-document :deep(h3) {
  font-size: 11px;
  text-transform: uppercase;
  letter-spacing: 1px;
  margin-top: 16px;
  margin-bottom: 6px;
  border-bottom: 1px solid #ddd;
  padding-bottom: 3px;
}

.lpa-document :deep(.title-rule) {
  border: none;
  border-top: 2px solid #1F2A44;
  margin: 10px 0 16px;
}

.lpa-document :deep(.opg-ref) {
  text-align: center;
  font-size: 10px;
  margin-bottom: 4px;
}

.lpa-document :deep(.clause) {
  margin-bottom: 8px;
  text-align: justify;
}

.lpa-document :deep(.clause-indent) {
  margin-left: 16px;
  font-style: italic;
}

.lpa-document :deep(.sub-heading) {
  font-weight: 700;
  font-size: 10px;
  margin-top: 10px;
  margin-bottom: 3px;
  text-decoration: underline;
}

.lpa-document :deep(.sub-clauses) {
  margin-left: 20px;
}

.lpa-document :deep(.sub-clauses p) {
  margin-bottom: 6px;
}

.lpa-document :deep(.signature-block) {
  margin: 16px 0;
}

.lpa-document :deep(.sig-label) {
  font-weight: 700;
  font-size: 9px;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  margin-bottom: 3px;
}

.lpa-document :deep(.sig-line .line) {
  border-bottom: 1px solid #000;
  width: 200px;
  height: 30px;
}

.lpa-document :deep(.sig-meta) {
  font-size: 9px;
  @apply text-neutral-600;
}

.lpa-document :deep(.signed-name) {
  font-family: 'Brush Script MT', 'Segoe Script', cursive;
  font-size: 16px;
  padding-left: 4px;
}

.lpa-document :deep(.registration-stamp) {
  margin-top: 20px;
  padding: 12px;
  border: 2px solid #1F2A44;
  border-radius: 4px;
}

.lpa-document :deep(.registration-stamp h3) {
  border-bottom: none;
  margin-top: 0;
}
</style>
