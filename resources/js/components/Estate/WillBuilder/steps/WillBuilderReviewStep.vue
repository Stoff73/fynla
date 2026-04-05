<template>
  <div>
    <!-- Validation Warnings -->
    <div v-if="!isComplete && warnings.length > 0" class="bg-violet-50 border border-violet-200 rounded-lg p-4 mb-6">
      <h3 class="text-sm font-semibold text-violet-800 mb-2">Please Review</h3>
      <ul class="space-y-1">
        <li
          v-for="(warning, i) in warnings"
          :key="i"
          class="text-sm flex items-start gap-2"
          :class="warning.severity === 'error' ? 'text-raspberry-700' : warning.severity === 'warning' ? 'text-violet-700' : 'text-neutral-500'"
        >
          <span>{{ warning.severity === 'error' ? '!' : warning.severity === 'warning' ? '!' : 'i' }}</span>
          <span>{{ warning.message }}</span>
        </li>
      </ul>
    </div>

    <!-- Mirror Will Tabs -->
    <div v-if="formData.will_type === 'mirror' && mirrorData" class="flex gap-2 mb-4">
      <button
        @click="activeTab = 'primary'"
        class="px-4 py-2 rounded-lg text-sm font-medium transition-colors"
        :class="activeTab === 'primary' ? 'bg-raspberry-500 text-white' : 'bg-white border border-light-gray text-neutral-500 hover:bg-savannah-100'"
      >
        Your Will
      </button>
      <button
        @click="activeTab = 'mirror'"
        class="px-4 py-2 rounded-lg text-sm font-medium transition-colors"
        :class="activeTab === 'mirror' ? 'bg-raspberry-500 text-white' : 'bg-white border border-light-gray text-neutral-500 hover:bg-savannah-100'"
      >
        Spouse's Will
      </button>
    </div>

    <!-- Will Document Preview -->
    <div class="bg-white rounded-lg shadow-sm border border-light-gray p-8">
      <h2 class="text-h3 font-bold text-horizon-500 mb-4">{{ isComplete ? 'Your Will' : 'Will Preview' }}</h2>

      <!-- Rendered Will HTML -->
      <div
        class="will-preview prose prose-sm max-w-none"
        v-html="sanitizedHtml"
      ></div>
    </div>

    <!-- Actions -->
    <div class="bg-white rounded-lg shadow-sm border border-light-gray p-6 mt-4">
      <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
        <div class="flex gap-3">
          <template v-if="isComplete">
            <button
              v-preview-disabled
              @click="$emit('jump', 0)"
              class="px-4 py-2 bg-white border border-horizon-300 text-neutral-500 rounded-lg font-medium hover:bg-savannah-100 transition-colors"
            >
              Edit Will
            </button>
          </template>
          <template v-else>
            <button
              @click="$emit('back')"
              class="px-4 py-2 text-neutral-500 hover:text-horizon-500 transition-colors"
            >
              Back
            </button>
            <button
              v-for="(step, index) in editableSteps"
              :key="step.name"
              @click="$emit('jump', step.originalIndex)"
              class="text-xs text-violet-600 hover:text-violet-800 underline"
            >
              Edit {{ step.label }}
            </button>
          </template>
        </div>
        <div class="flex gap-3">
          <!-- Generate Mirror (if mirror will and not yet generated) -->
          <button
            v-if="!isComplete && formData.will_type === 'mirror' && !mirrorData && documentId"
            @click="generateMirror"
            :disabled="generatingMirror"
            class="px-4 py-2 bg-white border border-horizon-300 text-neutral-500 rounded-lg font-medium hover:bg-savannah-100 transition-colors disabled:opacity-50"
          >
            {{ generatingMirror ? 'Generating...' : 'Generate Spouse\'s Will' }}
          </button>

          <button
            @click="handlePrint"
            class="px-4 py-2 bg-white border border-horizon-300 text-neutral-500 rounded-lg font-medium hover:bg-savannah-100 transition-colors flex items-center gap-2"
          >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/>
            </svg>
            Print / Save PDF
          </button>

          <button
            v-if="!isComplete"
            @click="handleComplete"
            :disabled="hasErrors || completing"
            class="px-6 py-2.5 bg-raspberry-500 text-white rounded-lg font-medium hover:bg-raspberry-600 transition-colors disabled:opacity-50"
          >
            {{ completing ? 'Completing...' : 'Complete & Finalise' }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import { renderWillDocument, printWillDocument } from '@/utils/willDocumentRenderer';
import { sanitizeHtml } from '@/utils/sanitizeHtml';
import { previewModeMixin } from '@/mixins/previewModeMixin';
import estateService from '@/services/estateService';

import logger from '@/utils/logger';
export default {
  name: 'WillBuilderReviewStep',

  mixins: [previewModeMixin],

  props: {
    formData: { type: Object, required: true },
    prePopulated: { type: Object, default: null },
    documentId: { type: Number, default: null },
  },

  emits: ['next', 'back', 'jump'],

  data() {
    return {
      warnings: [],
      activeTab: 'primary',
      mirrorData: null,
      completing: false,
      generatingMirror: false,
    };
  },

  computed: {
    activeData() {
      return this.activeTab === 'mirror' && this.mirrorData ? this.mirrorData : this.formData;
    },

    renderedHtml() {
      return renderWillDocument(this.activeData);
    },

    sanitizedHtml() {
      return sanitizeHtml(this.renderedHtml);
    },

    isComplete() {
      return this.formData.status === 'complete';
    },

    hasErrors() {
      return this.warnings.some(w => w.severity === 'error');
    },

    editableSteps() {
      return [
        { name: 'personal', label: 'Personal', originalIndex: 1 },
        { name: 'executors', label: 'Executors', originalIndex: 2 },
        { name: 'gifts', label: 'Gifts', originalIndex: 4 },
        { name: 'residuary', label: 'Residuary', originalIndex: 5 },
      ];
    },
  },

  async mounted() {
    await this.loadValidation();
  },

  methods: {
    async loadValidation() {
      if (!this.documentId) return;
      try {
        const res = await estateService.validateWillDocument(this.documentId);
        this.warnings = res.data?.warnings || [];
      } catch (error) {
        logger.error('Failed to validate:', error);
      }
    },

    handlePrint() {
      printWillDocument(this.activeData);
    },

    async generateMirror() {
      if (!this.documentId) return;
      this.generatingMirror = true;
      try {
        const res = await estateService.generateMirrorWill(this.documentId);
        this.mirrorData = res.data?.mirror || null;
        this.activeTab = 'mirror';
      } catch (error) {
        logger.error('Failed to generate mirror will:', error);
      } finally {
        this.generatingMirror = false;
      }
    },

    async handleComplete() {
      if (!this.documentId || this.hasErrors) return;
      this.completing = true;
      try {
        await estateService.completeWillDocument(this.documentId);
        this.$emit('next', {});
      } catch (error) {
        logger.error('Failed to complete will:', error);
        if (error.response?.data?.message) {
          this.warnings.push({
            field: 'general',
            message: error.response.data.message,
            severity: 'error',
          });
        }
      } finally {
        this.completing = false;
      }
    },
  },
};
</script>

<style scoped>
.will-preview {
  font-family: 'Times New Roman', Georgia, serif;
  font-size: 11px;
  line-height: 1.5;
  max-height: 600px;
  overflow-y: auto;
  padding: 20px;
  @apply border border-light-gray;
  border-radius: 8px;
  @apply bg-eggshell-500;
}

/* Print document — exact hex values required for print/document fidelity.
   #1F2A44 = horizon-500 (brand border), #ddd = rule divider, #000 = signature line.
   These are inside :deep() document renderers and must not use Tailwind utilities. */
.will-preview :deep(h1) {
  text-align: center;
  font-size: 16px;
  letter-spacing: 2px;
  margin-bottom: 4px;
}

.will-preview :deep(h2) {
  text-align: center;
  font-size: 13px;
  font-weight: 400;
  margin-bottom: 8px;
}

.will-preview :deep(h3) {
  font-size: 11px;
  text-transform: uppercase;
  letter-spacing: 1px;
  margin-top: 16px;
  margin-bottom: 6px;
  border-bottom: 1px solid #ddd;
  padding-bottom: 3px;
}

.will-preview :deep(.title-rule) {
  border: none;
  border-top: 2px solid #1F2A44;
  margin: 10px 0 16px;
}

.will-preview :deep(.clause) {
  margin-bottom: 8px;
  text-align: justify;
}

.will-preview :deep(.sub-clauses) {
  margin-left: 20px;
}

.will-preview :deep(.signature-block) {
  margin: 24px 0 16px;
}

.will-preview :deep(.sig-line .line) {
  border-bottom: 1px solid #000;
  width: 200px;
  height: 30px;
}

.will-preview :deep(.witnesses) {
  display: flex;
  gap: 24px;
  margin-top: 12px;
}

.will-preview :deep(.witness) {
  flex: 1;
}

.will-preview :deep(.witness-field) {
  display: flex;
  align-items: flex-end;
  margin-bottom: 8px;
  gap: 4px;
}

.will-preview :deep(.witness-field span) {
  font-size: 9px;
  white-space: nowrap;
  min-width: 60px;
}

.will-preview :deep(.witness-field .line) {
  flex: 1;
  border-bottom: 1px solid #000;
  min-height: 14px;
}

.will-preview :deep(.signed-name) {
  font-family: 'Brush Script MT', 'Segoe Script', cursive;
  font-size: 16px;
  padding-left: 4px;
}

.will-preview :deep(.filled) {
  font-size: 10px;
  padding-left: 4px;
}
</style>
