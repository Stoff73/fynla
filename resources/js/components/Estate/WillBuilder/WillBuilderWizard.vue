<template>
  <div>
    <!-- Progress Indicator (hidden when viewing complete will) -->
    <div v-if="!isViewingComplete" class="bg-white rounded-lg shadow-sm border border-light-gray p-4 mb-8">
      <div class="overflow-x-auto">
        <div class="flex items-start justify-between min-w-max px-2">
          <div
            v-for="(step, index) in visibleSteps"
            :key="step.name"
            class="flex-1 flex flex-col items-center relative min-w-[80px]"
          >
            <!-- Step Circle -->
            <div
              class="w-9 h-9 rounded-full flex items-center justify-center border-2 transition-all cursor-pointer"
              :class="getStepCircleClass(index)"
              @click="jumpToStep(index)"
            >
              <svg v-if="index < currentStepIndex" class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
              </svg>
              <span v-else class="text-sm font-semibold">{{ index + 1 }}</span>
            </div>
            <!-- Step Label -->
            <span
              class="text-xs mt-1.5 text-center leading-tight max-w-[70px]"
              :class="index === currentStepIndex ? 'text-raspberry-600 font-semibold' : index < currentStepIndex ? 'text-spring-600' : 'text-neutral-500'"
            >
              {{ step.shortLabel || step.title }}
            </span>
            <!-- Connecting Line -->
            <div
              v-if="index < visibleSteps.length - 1"
              class="absolute h-0.5 top-[18px] left-1/2 -z-10"
              :style="{ width: 'calc(100% - 20px)' }"
              :class="index < currentStepIndex ? 'bg-spring-500' : 'bg-savannah-300'"
            ></div>
          </div>
        </div>
      </div>
    </div>

    <!-- Step Content -->
    <Transition name="fade" mode="out-in">
      <component
        :is="currentStepComponent"
        :key="currentStep.name"
        :form-data="formData"
        :pre-populated="prePopulated"
        :document-id="internalDocumentId"
        :saving="saving"
        @next="handleNext"
        @back="handleBack"
        @update="handleStepUpdate"
        @jump="jumpToStep"
      />
    </Transition>

    <!-- Success Modal (shown after will completion) -->
    <Teleport to="body">
      <div v-if="showSuccessModal" class="fixed inset-0 bg-horizon-600/50 flex items-center justify-center z-50">
        <div class="bg-white rounded-lg shadow-xl max-w-md w-full mx-4 p-8 text-center">
          <div class="w-16 h-16 bg-spring-100 rounded-full flex items-center justify-center mx-auto mb-4">
            <svg class="w-8 h-8 text-spring-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
          </div>
          <h3 class="text-xl font-bold text-horizon-500 mb-2">Your Will Has Been Created</h3>
          <p class="text-sm text-neutral-500 mb-6">
            Your will has been saved securely. You can print it, review it at any time, or return to your financial planning.
          </p>
          <div class="flex flex-col gap-3">
            <button
              @click="showSuccessModal = false"
              class="px-6 py-2.5 bg-raspberry-500 text-white rounded-button font-medium hover:bg-raspberry-600 transition-colors"
            >
              View Signing Instructions
            </button>
            <router-link
              to="/estate"
              class="px-4 py-2 text-neutral-500 hover:text-horizon-500 transition-colors text-sm"
            >
              Return to Estate Planning
            </router-link>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<script>
import estateService from '@/services/estateService';
import WillBuilderIntroStep from './steps/WillBuilderIntroStep.vue';
import WillBuilderPersonalStep from './steps/WillBuilderPersonalStep.vue';
import WillBuilderExecutorsStep from './steps/WillBuilderExecutorsStep.vue';
import WillBuilderGuardiansStep from './steps/WillBuilderGuardiansStep.vue';
import WillBuilderGiftsStep from './steps/WillBuilderGiftsStep.vue';
import WillBuilderResiduaryStep from './steps/WillBuilderResiduaryStep.vue';
import WillBuilderFuneralStep from './steps/WillBuilderFuneralStep.vue';
import WillBuilderDigitalStep from './steps/WillBuilderDigitalStep.vue';
import WillBuilderReviewStep from './steps/WillBuilderReviewStep.vue';
import WillBuilderSigningStep from './steps/WillBuilderSigningStep.vue';

import logger from '@/utils/logger';
export default {
  name: 'WillBuilderWizard',

  components: {
    WillBuilderIntroStep,
    WillBuilderPersonalStep,
    WillBuilderExecutorsStep,
    WillBuilderGuardiansStep,
    WillBuilderGiftsStep,
    WillBuilderResiduaryStep,
    WillBuilderFuneralStep,
    WillBuilderDigitalStep,
    WillBuilderReviewStep,
    WillBuilderSigningStep,
  },

  props: {
    initialData: { type: Object, default: null },
    prePopulated: { type: Object, default: null },
    documentId: { type: Number, default: null },
    startAtReview: { type: Boolean, default: false },
  },

  emits: ['document-created'],

  data() {
    return {
      currentStepIndex: 0,
      saving: false,
      showSuccessModal: false,
      internalDocumentId: this.documentId,
      formData: this.buildInitialFormData(),
    };
  },

  computed: {
    allSteps() {
      return [
        { name: 'intro', title: 'Introduction', shortLabel: 'Intro', component: 'WillBuilderIntroStep' },
        { name: 'personal', title: 'Personal Details', shortLabel: 'Personal', component: 'WillBuilderPersonalStep' },
        { name: 'executors', title: 'Executors', shortLabel: 'Executors', component: 'WillBuilderExecutorsStep' },
        { name: 'guardians', title: 'Guardians', shortLabel: 'Guardians', component: 'WillBuilderGuardiansStep', conditional: true },
        { name: 'gifts', title: 'Specific Gifts', shortLabel: 'Gifts', component: 'WillBuilderGiftsStep' },
        { name: 'residuary', title: 'Residuary Estate', shortLabel: 'Residuary', component: 'WillBuilderResiduaryStep' },
        { name: 'funeral', title: 'Funeral Wishes', shortLabel: 'Funeral', component: 'WillBuilderFuneralStep' },
        { name: 'digital', title: 'Digital Assets', shortLabel: 'Digital', component: 'WillBuilderDigitalStep' },
        { name: 'review', title: 'Review', shortLabel: 'Review', component: 'WillBuilderReviewStep' },
        { name: 'signing', title: 'Signing Guide', shortLabel: 'Signing', component: 'WillBuilderSigningStep' },
      ];
    },

    isViewingComplete() {
      return this.startAtReview && this.currentStep?.name === 'review';
    },

    visibleSteps() {
      return this.allSteps.filter(step => {
        if (step.conditional && step.name === 'guardians') {
          return this.prePopulated?.has_minor_children === true;
        }
        return true;
      });
    },

    currentStep() {
      return this.visibleSteps[this.currentStepIndex] || this.visibleSteps[0];
    },

    currentStepComponent() {
      const componentMap = {
        WillBuilderIntroStep,
        WillBuilderPersonalStep,
        WillBuilderExecutorsStep,
        WillBuilderGuardiansStep,
        WillBuilderGiftsStep,
        WillBuilderResiduaryStep,
        WillBuilderFuneralStep,
        WillBuilderDigitalStep,
        WillBuilderReviewStep,
        WillBuilderSigningStep,
      };
      return componentMap[this.currentStep.component];
    },
  },

  mounted() {
    if (this.startAtReview) {
      const reviewIndex = this.visibleSteps.findIndex(s => s.name === 'review');
      if (reviewIndex >= 0) {
        this.currentStepIndex = reviewIndex;
      }
    } else if (this.initialData && this.initialData.status === 'draft') {
      // Resume from the first step that needs attention
      // Steps with data: intro (domicile_confirmed), personal (testator_full_name),
      // executors (executors array), gifts, residuary, funeral, digital
      const resumeIndex = this.findResumeStep();
      if (resumeIndex > 0) {
        this.currentStepIndex = resumeIndex;
      }
    }
  },

  watch: {
    documentId(val) {
      this.internalDocumentId = val;
    },
  },

  methods: {
    findResumeStep() {
      const d = this.initialData;
      const steps = this.visibleSteps;

      // Check each step's key data fields to find the first incomplete one
      for (let i = 0; i < steps.length; i++) {
        const step = steps[i];
        switch (step.name) {
          case 'intro':
            if (!d.domicile_confirmed) return i;
            break;
          case 'personal':
            if (!d.testator_full_name) return i;
            break;
          case 'executors':
            if (!d.executors || d.executors.length === 0 || !d.executors[0]?.name) return i;
            break;
          case 'gifts':
            // Gifts are optional — skip past if executors are done
            break;
          case 'residuary':
            if (!d.residuary_estate || d.residuary_estate.length === 0) return i;
            break;
          case 'funeral':
            if (!d.funeral_preference) return i;
            break;
          case 'digital':
            // Digital is optional
            break;
          case 'review':
            return i;
          case 'signing':
            return i;
        }
      }
      // Default: gifts step (first optional step after required ones)
      const giftsIndex = steps.findIndex(s => s.name === 'gifts');
      return giftsIndex > 0 ? giftsIndex : 0;
    },

    buildInitialFormData() {
      if (this.initialData) {
        return { ...this.initialData };
      }

      const pre = this.prePopulated;
      return {
        will_type: 'simple',
        domicile_confirmed: null,
        testator_full_name: pre?.testator?.full_name || '',
        testator_address: pre?.testator?.address || '',
        testator_date_of_birth: pre?.testator?.date_of_birth || '',
        testator_occupation: pre?.testator?.occupation || '',
        executors: pre?.existing_executor_name
          ? [{ name: pre.existing_executor_name, address: '', relationship: '', phone: '' }]
          : [],
        guardians: [],
        specific_gifts: [],
        residuary_estate: [],
        funeral_preference: null,
        funeral_wishes_notes: '',
        digital_executor_name: '',
        digital_assets_instructions: '',
        survivorship_days: 28,
      };
    },

    getStepCircleClass(index) {
      if (index < this.currentStepIndex) {
        return 'border-spring-500 bg-spring-500 text-white';
      }
      if (index === this.currentStepIndex) {
        return 'border-raspberry-500 bg-raspberry-500 text-white';
      }
      return 'border-savannah-300 bg-white text-neutral-500';
    },

    async handleNext(stepData) {
      if (stepData) {
        Object.assign(this.formData, stepData);
      }

      // Save step to backend
      await this.saveCurrentStep(stepData);

      // Show success modal when completing the Review step (advancing to Signing)
      const currentStepName = this.currentStep?.name;
      if (currentStepName === 'review') {
        this.showSuccessModal = true;
      }

      if (this.currentStepIndex < this.visibleSteps.length - 1) {
        this.currentStepIndex++;
        window.scrollTo({ top: 0, behavior: 'smooth' });
      }
    },

    handleBack() {
      if (this.currentStepIndex > 0) {
        this.currentStepIndex--;
        window.scrollTo({ top: 0, behavior: 'smooth' });
      }
    },

    handleStepUpdate(stepData) {
      Object.assign(this.formData, stepData);
    },

    jumpToStep(index) {
      if (index < this.currentStepIndex) {
        this.currentStepIndex = index;
        window.scrollTo({ top: 0, behavior: 'smooth' });
      }
    },

    async saveCurrentStep(stepData) {
      const step = this.currentStep;
      if (step.name === 'signing') return;

      this.saving = true;
      try {
        // If no document yet, create one on the intro step
        if (!this.internalDocumentId && step.name === 'intro') {
          const res = await estateService.createWillDocument({
            will_type: this.formData.will_type,
            testator_full_name: this.formData.testator_full_name || 'Draft',
            testator_address: this.formData.testator_address,
            testator_date_of_birth: this.formData.testator_date_of_birth,
            testator_occupation: this.formData.testator_occupation,
            domicile_confirmed: this.formData.domicile_confirmed,
          });
          this.internalDocumentId = res.data.id;
          this.formData.id = res.data.id;
          this.$emit('document-created', res.data);
          return;
        }

        if (this.internalDocumentId && step.name !== 'review') {
          const payload = { step: step.name, ...stepData };
          const res = await estateService.updateWillDocument(this.internalDocumentId, payload);
          if (res.data) {
            Object.assign(this.formData, res.data);
          }
        }
      } catch (error) {
        logger.error('Failed to save step:', error);
      } finally {
        this.saving = false;
      }
    },
  },
};
</script>

<style scoped>
.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.2s ease;
}
.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}
</style>
