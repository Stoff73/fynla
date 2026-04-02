<template>
  <div
    class="fixed inset-0 z-50 overflow-y-auto"
    aria-labelledby="modal-title"
    role="dialog"
    aria-modal="true"
  >
    <!-- Background overlay -->
    <div
      class="fixed inset-0 bg-savannah-1000 bg-opacity-75 transition-opacity"
      @click="handleClose"
    />

    <!-- Modal container -->
    <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
      <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>

      <!-- Modal panel -->
      <div class="relative inline-block align-bottom bg-white rounded-lg text-left shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-2xl sm:w-full max-h-[90vh] overflow-y-auto">
        <!-- Header -->
        <div class="bg-white px-6 pt-6 border-b border-light-gray">
          <div class="flex items-center justify-between mb-4">
            <h3 class="text-xl font-semibold text-horizon-500">
              Upload {{ documentTypeLabel }}
            </h3>
            <button
              type="button"
              class="text-horizon-400 hover:text-neutral-500 transition-colors"
              @click="handleClose"
            >
              <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
              </svg>
            </button>
          </div>

          <!-- Step Indicator -->
          <div class="flex items-center justify-center space-x-4 pb-4">
            <div
              v-for="(stepItem, index) in steps"
              :key="stepItem"
              class="flex items-center"
            >
              <div
                class="w-8 h-8 rounded-full flex items-center justify-center text-sm font-medium transition-colors"
                :class="getStepClass(index)"
              >
                {{ index + 1 }}
              </div>
              <span
                class="ml-2 text-sm"
                :class="currentStepIndex >= index ? 'text-horizon-500' : 'text-horizon-400'"
              >
                {{ stepItem }}
              </span>
              <div
                v-if="index < steps.length - 1"
                class="w-8 h-0.5 mx-4"
                :class="currentStepIndex > index ? 'bg-raspberry-600' : 'bg-savannah-200'"
              />
            </div>
          </div>
        </div>

        <!-- Content Area -->
        <div class="px-6 py-6">
          <!-- Step 1: Upload -->
          <div v-if="currentStep === 'upload'">
            <div class="bg-violet-50 border border-violet-200 rounded-lg p-4 mb-4">
              <div class="flex items-start">
                <svg class="w-5 h-5 text-violet-500 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
                </svg>
                <p class="ml-3 text-sm text-violet-800">
                  Information you upload here goes through to Anthropic, we use the Haiku 3.5 model. The information is not anonymised, so please do not upload any information that you would not want to be on the internet. This feature is still being developed.
                </p>
              </div>
            </div>
            <UploadDropZone
              @file-selected="handleFileSelected"
              @file-removed="handleFileRemoved"
              @error="handleUploadError"
            />
          </div>

          <!-- Step 2: Processing -->
          <div v-else-if="currentStep === 'processing'">
            <ProcessingState
              :step="processingStep"
              :upload-progress="uploadProgress"
            />
          </div>

          <!-- Step 3: Review -->
          <div v-else-if="currentStep === 'review'" class="space-y-6">
            <!-- Success Banner -->
            <div class="bg-spring-50 border border-spring-200 rounded-lg p-4">
              <div class="flex items-start">
                <svg class="w-5 h-5 text-spring-500 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
                <div class="ml-3">
                  <p class="text-sm font-medium text-spring-800">
                    Document analysed successfully
                  </p>
                  <p class="text-sm text-spring-700 mt-1">
                    Detected: {{ detectedTypeLabel }}
                  </p>
                </div>
              </div>
            </div>

            <!-- Warnings -->
            <div
              v-if="extractionWarnings.length > 0"
              class="bg-violet-50 border border-violet-200 rounded-lg p-4"
            >
              <div class="flex items-start">
                <svg class="w-5 h-5 text-violet-500 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                </svg>
                <div class="ml-3">
                  <p class="text-sm font-medium text-violet-800">
                    Please review these fields
                  </p>
                  <ul class="text-sm text-violet-700 mt-1 list-disc list-inside">
                    <li v-for="warning in extractionWarnings" :key="warning">
                      {{ warning }}
                    </li>
                  </ul>
                </div>
              </div>
            </div>

            <!-- Extracted Fields -->
            <div class="space-y-4">
              <h4 class="text-sm font-medium text-neutral-500">
                Extracted Data
              </h4>

              <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div
                  v-for="(value, key) in extractedFields"
                  :key="key"
                  class="relative"
                >
                  <label class="block text-xs font-medium text-neutral-500 mb-1">
                    {{ formatFieldLabel(key) }}
                  </label>
                  <div class="flex items-center gap-2">
                    <input
                      v-model="editedFields[key]"
                      type="text"
                      class="block w-full rounded-md border-horizon-300 shadow-sm focus:border-violet-500 focus:ring-violet-500 sm:text-sm"
                      :class="getFieldClass(key)"
                    />
                    <ConfidenceBadge
                      v-if="fieldConfidence[key]"
                      :confidence="fieldConfidence[key]"
                    />
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Step 3b: Sheet Review (Excel files) -->
          <div v-else-if="currentStep === 'sheet-review'">
            <SheetReviewStep
              :sheets="excelSheets"
              :document-id="documentId"
              @confirm="handleExcelConfirm"
              @close="handleClose"
            />
          </div>

          <!-- Error State -->
          <div v-else-if="currentStep === 'error'" class="text-center py-8">
            <svg class="mx-auto h-12 w-12 text-raspberry-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
            </svg>
            <h3 class="mt-4 text-lg font-medium text-horizon-500">
              {{ errorTitle }}
            </h3>
            <p class="mt-2 text-sm text-neutral-500">
              {{ errorMessage }}
            </p>
            <div class="mt-6 flex justify-center gap-3">
              <button
                type="button"
                class="inline-flex items-center px-4 py-2 border border-horizon-300 text-sm font-medium rounded-md text-neutral-500 bg-white hover:bg-savannah-100"
                @click="resetToUpload"
              >
                Try Again
              </button>
              <button
                type="button"
                class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-raspberry-600 hover:bg-raspberry-700"
                @click="$emit('manual-entry')"
              >
                Enter Manually
              </button>
            </div>
          </div>
        </div>

        <!-- Footer -->
        <div class="bg-savannah-100 px-6 py-4 flex justify-end gap-3">
          <button
            type="button"
            class="inline-flex items-center px-4 py-2 border border-horizon-300 text-sm font-medium rounded-md text-neutral-500 bg-white hover:bg-savannah-100"
            @click="handleClose"
          >
            Cancel
          </button>
          <button
            v-if="currentStep === 'upload' && selectedFile"
            type="button"
            class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-raspberry-600 hover:bg-raspberry-700"
            @click="startUpload"
          >
            Upload & Analyse
          </button>
          <button
            v-if="currentStep === 'review'"
            type="button"
            class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-raspberry-600 hover:bg-raspberry-700"
            :disabled="isSaving"
            @click="handleSave"
          >
            <svg
              v-if="isSaving"
              class="animate-spin -ml-1 mr-2 h-4 w-4 text-white"
              fill="none"
              viewBox="0 0 24 24"
            >
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
            </svg>
            {{ isSaving ? 'Saving...' : 'Save ' + documentTypeLabel }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import UploadDropZone from './UploadDropZone.vue';
import ProcessingState from './ProcessingState.vue';
import ConfidenceBadge from './ConfidenceBadge.vue';
import SheetReviewStep from './SheetReviewStep.vue';
import documentService from '../../services/documentService';

export default {
  name: 'DocumentUploadModal',

  components: {
    UploadDropZone,
    ProcessingState,
    ConfidenceBadge,
    SheetReviewStep,
  },

  props: {
    /**
     * Expected document type (optional)
     */
    documentType: {
      type: String,
      default: null,
    },
  },

  emits: ['close', 'extracted', 'saved', 'manual-entry'],

  data() {
    return {
      steps: ['Upload', 'Processing', 'Review'],
      currentStep: 'upload',
      selectedFile: null,
      uploadProgress: 0,
      processingStep: 'uploading',
      documentId: null,
      extractedFields: {},
      editedFields: {},
      fieldConfidence: {},
      extractionWarnings: [],
      detectedType: null,
      detectedSubtype: null,
      targetModel: null,
      isExcel: false,
      excelSheets: [],
      isSaving: false,
      errorTitle: '',
      errorMessage: '',
      delayTimeout: null,
    };
  },

  computed: {
    currentStepIndex() {
      const stepMap = {
        upload: 0,
        processing: 1,
        review: 2,
        'sheet-review': 2,
        error: 2,
      };
      return stepMap[this.currentStep] ?? 0;
    },

    documentTypeLabel() {
      const labels = {
        pension_statement: 'Pension Statement',
        insurance_policy: 'Insurance Policy',
        investment_statement: 'Investment Statement',
        mortgage_statement: 'Mortgage Statement',
        savings_statement: 'Savings Statement',
      };
      return labels[this.documentType] || 'Document';
    },

    detectedTypeLabel() {
      if (this.detectedSubtype) {
        const subtypeLabels = {
          dc_pension: 'Defined Contribution Pension',
          db_pension: 'Defined Benefit Pension',
          state_pension: 'State Pension',
          life_insurance: 'Life Insurance',
          critical_illness: 'Critical Illness',
          income_protection: 'Income Protection',
          investment_account: 'Investment Account',
          mortgage: 'Mortgage',
          savings_account: 'Savings Account',
        };
        return subtypeLabels[this.detectedSubtype] || this.detectedSubtype;
      }
      return this.documentTypeLabel;
    },
  },

  beforeUnmount() {
    if (this.delayTimeout) clearTimeout(this.delayTimeout);
  },

  methods: {
    getStepClass(index) {
      if (this.currentStepIndex > index) {
        return 'bg-raspberry-600 text-white';
      }
      if (this.currentStepIndex === index) {
        return 'bg-violet-100 text-violet-600 border-2 border-violet-600';
      }
      return 'bg-savannah-100 text-horizon-400';
    },

    handleFileSelected(file) {
      this.selectedFile = file;
    },

    handleFileRemoved() {
      this.selectedFile = null;
    },

    handleUploadError(error) {
      this.errorMessage = error;
    },

    async startUpload() {
      if (!this.selectedFile) {
        return;
      }

      // Show processing state immediately
      this.currentStep = 'processing';
      this.processingStep = 'uploading';
      this.uploadProgress = 0;

      try {
        // Upload and process
        const result = await documentService.upload(
          this.selectedFile,
          this.documentType,
          (progress) => {
            this.uploadProgress = progress;
            if (progress >= 100) {
              this.processingStep = 'analysing';
            }
          }
        );

        if (result.success) {
          this.documentId = result.data.document_id;

          // Excel files go to sheet review step
          if (result.data.is_excel) {
            this.isExcel = true;
            this.excelSheets = result.data.sheets || [];
            this.currentStep = 'sheet-review';
            return;
          }

          this.processingStep = 'extracting';

          // Small delay to show extracting step
          await new Promise(resolve => {
            if (this.delayTimeout) clearTimeout(this.delayTimeout);
            this.delayTimeout = setTimeout(resolve, 500);
          });
          this.processingStep = 'mapping';

          // Store results
          this.extractedFields = result.data.extracted_fields || {};
          this.editedFields = { ...this.extractedFields };
          this.fieldConfidence = result.data.field_confidence || {};
          this.extractionWarnings = result.data.warnings || [];
          this.detectedType = result.data.document_type;
          this.detectedSubtype = result.data.detected_subtype;
          this.targetModel = result.data.target_model;

          // Emit extracted event
          this.$emit('extracted', {
            documentId: this.documentId,
            fields: this.extractedFields,
            confidence: this.fieldConfidence,
            targetModel: this.targetModel,
          });

          // Move to review
          this.currentStep = 'review';
        } else {
          throw new Error(result.message || 'Upload failed');
        }
      } catch (error) {
        this.errorTitle = 'Processing Failed';

        // Extract specific validation errors if available
        const responseData = error.response?.data;
        if (responseData?.errors) {
          // Get all validation error messages
          const errorMessages = Object.values(responseData.errors)
            .flat()
            .filter(msg => msg);
          this.errorMessage = errorMessages.length > 0
            ? errorMessages.join(' ')
            : responseData.message || 'Validation failed';
        } else {
          this.errorMessage = responseData?.message || error.message || 'An error occurred while processing the document';
        }

        this.currentStep = 'error';
      }
    },

    async handleSave() {
      if (!this.documentId) return;

      this.isSaving = true;

      try {
        const result = await documentService.confirm(this.documentId, this.editedFields);

        if (result.success) {
          this.$emit('saved', {
            documentId: this.documentId,
            modelType: result.data.model_type,
            modelId: result.data.model_id,
            data: this.editedFields,
          });
          this.handleClose();
        } else {
          throw new Error(result.message || 'Save failed');
        }
      } catch (error) {
        this.errorTitle = 'Save Failed';

        // Extract specific validation errors if available
        const responseData = error.response?.data;
        if (responseData?.errors) {
          const errorMessages = Object.values(responseData.errors)
            .flat()
            .filter(msg => msg);
          this.errorMessage = errorMessages.length > 0
            ? errorMessages.join(' ')
            : responseData.message || 'Validation failed';
        } else {
          this.errorMessage = responseData?.message || error.message || 'Failed to save the extracted data';
        }

        this.currentStep = 'error';
      } finally {
        this.isSaving = false;
      }
    },

    async handleExcelConfirm(confirmedSheets) {
      this.currentStep = 'processing';
      this.processingStep = 'mapping';
      try {
        const result = await documentService.confirmExcel(this.documentId, confirmedSheets);
        if (result.success) {
          this.$emit('saved', {
            documentId: this.documentId,
            isExcel: true,
            results: result.data.results,
          });
          this.handleClose();
        } else {
          throw new Error(result.message || 'Import failed');
        }
      } catch (error) {
        this.errorTitle = 'Import Failed';
        const responseData = error.response?.data;
        this.errorMessage = responseData?.message || error.message || 'Failed to import spreadsheet data';
        this.currentStep = 'error';
      }
    },

    resetToUpload() {
      this.currentStep = 'upload';
      this.selectedFile = null;
      this.uploadProgress = 0;
      this.processingStep = 'uploading';
      this.documentId = null;
      this.isExcel = false;
      this.excelSheets = [];
      this.extractedFields = {};
      this.editedFields = {};
      this.fieldConfidence = {};
      this.extractionWarnings = [];
      this.errorTitle = '';
      this.errorMessage = '';
    },

    handleClose() {
      this.$emit('close');
    },

    formatFieldLabel(key) {
      return key
        .replace(/_/g, ' ')
        .replace(/\b\w/g, char => char.toUpperCase());
    },

    getFieldClass(key) {
      const confidence = this.fieldConfidence[key];
      if (!confidence) return '';

      if (confidence < 0.6) {
        return 'border-violet-300 bg-violet-50';
      }
      return '';
    },
  },
};
</script>
