<template>
  <div class="lpa-wizard">
    <!-- Header -->
    <div class="mb-6">
      <button
        class="text-sm text-horizon-400 hover:text-horizon-500 flex items-center mb-2"
        @click="$emit('cancel')"
      >
        <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
        </svg>
        Back to Estate Planning
      </button>
      <h1 class="text-xl font-bold text-horizon-500">
        {{ editId ? 'Edit' : 'Create' }} {{ typeLabel }}
      </h1>
      <p class="text-sm text-neutral-500 mt-1">
        {{ typeDescription }}
      </p>
    </div>

    <!-- Loading -->
    <div v-if="initialLoading" class="text-center py-12">
      <div class="w-10 h-10 border-4 border-horizon-200 border-t-raspberry-500 rounded-full animate-spin mx-auto"></div>
      <p class="mt-2 text-sm text-neutral-500">Loading...</p>
    </div>

    <div v-else>
      <!-- Step Indicator -->
      <div class="flex items-center mb-8 overflow-x-auto">
        <div
          v-for="(step, index) in visibleSteps"
          :key="step.id"
          class="flex items-center"
        >
          <div
            :class="[
              'flex items-center justify-center w-8 h-8 rounded-full text-xs font-bold flex-shrink-0',
              index < currentStepIndex ? 'bg-spring-500 text-white' :
              index === currentStepIndex ? 'bg-raspberry-500 text-white' :
              'bg-neutral-100 text-neutral-500',
            ]"
          >
            <svg v-if="index < currentStepIndex" class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
              <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
            </svg>
            <span v-else>{{ index + 1 }}</span>
          </div>
          <span
            :class="[
              'ml-2 text-xs font-medium whitespace-nowrap hidden sm:inline',
              index === currentStepIndex ? 'text-horizon-500' : 'text-neutral-400',
            ]"
          >
            {{ step.label }}
          </span>
          <div
            v-if="index < visibleSteps.length - 1"
            class="w-8 sm:w-12 h-px mx-2 flex-shrink-0"
            :class="index < currentStepIndex ? 'bg-spring-500' : 'bg-neutral-200'"
          ></div>
        </div>
      </div>

      <!-- Step Content -->
      <div class="bg-white rounded-lg border border-light-gray p-6 mb-6">
        <DonorDetailsStep
          v-if="currentStep === 'donor'"
          v-model="formData"
          :errors="errors"
        />
        <AttorneysStep
          v-else-if="currentStep === 'attorneys'"
          v-model="formData.attorneys"
          :errors="errors"
          attorney-type="primary"
          title="Primary Attorneys"
          description="Appoint one or more people to make decisions on your behalf."
        />
        <ReplacementAttorneysStep
          v-else-if="currentStep === 'replacement'"
          v-model="formData.attorneys"
          :errors="errors"
        />
        <DecisionTypeStep
          v-else-if="currentStep === 'decision'"
          v-model="formData"
          :primary-attorney-count="primaryAttorneyCount"
          :errors="errors"
        />
        <WhenCanActStep
          v-else-if="currentStep === 'when_can_act'"
          v-model="formData"
          :errors="errors"
        />
        <PreferencesStep
          v-else-if="currentStep === 'preferences'"
          v-model="formData"
          :lpa-type="lpaType"
          :errors="errors"
        />
        <CertificateProviderStep
          v-else-if="currentStep === 'certificate'"
          v-model="formData"
          :errors="errors"
        />
        <NotificationPersonsStep
          v-else-if="currentStep === 'notifications'"
          v-model="formData.notification_persons"
          :errors="errors"
        />
        <ReviewStep
          v-else-if="currentStep === 'review'"
          :form-data="formData"
          :lpa-type="lpaType"
        />
      </div>

      <!-- Navigation -->
      <div class="flex items-center justify-between">
        <button
          v-if="currentStepIndex > 0"
          class="px-4 py-2 text-sm font-medium text-horizon-500 border border-light-gray rounded-lg hover:bg-savannah-100"
          @click="previousStep"
        >
          Back
        </button>
        <div v-else></div>

        <div class="flex items-center space-x-3">
          <button
            v-if="canSkipStep"
            class="px-4 py-2 text-sm font-medium text-neutral-500 hover:text-horizon-500"
            @click="nextStep"
          >
            Skip
          </button>
          <button
            v-if="currentStep === 'review'"
            class="px-4 py-2 text-sm font-medium text-horizon-500 border border-light-gray rounded-lg hover:bg-savannah-100"
            :disabled="saving"
            @click="saveDraft"
          >
            Save as Draft
          </button>
          <button
            class="px-4 py-2 text-sm font-medium text-white bg-raspberry-500 rounded-lg hover:bg-raspberry-600 disabled:opacity-50"
            :disabled="saving"
            @click="currentStep === 'review' ? saveComplete() : nextStep()"
          >
            {{ saving ? 'Saving...' : currentStep === 'review' ? 'Complete' : 'Next' }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import { mapActions } from 'vuex';
import estateService from '@/services/estateService';
import DonorDetailsStep from './LpaWizardSteps/DonorDetailsStep.vue';
import AttorneysStep from './LpaWizardSteps/AttorneysStep.vue';
import ReplacementAttorneysStep from './LpaWizardSteps/ReplacementAttorneysStep.vue';
import DecisionTypeStep from './LpaWizardSteps/DecisionTypeStep.vue';
import WhenCanActStep from './LpaWizardSteps/WhenCanActStep.vue';
import PreferencesStep from './LpaWizardSteps/PreferencesStep.vue';
import CertificateProviderStep from './LpaWizardSteps/CertificateProviderStep.vue';
import NotificationPersonsStep from './LpaWizardSteps/NotificationPersonsStep.vue';
import ReviewStep from './LpaWizardSteps/ReviewStep.vue';

export default {
  name: 'LpaWizard',

  components: {
    DonorDetailsStep,
    AttorneysStep,
    ReplacementAttorneysStep,
    DecisionTypeStep,
    WhenCanActStep,
    PreferencesStep,
    CertificateProviderStep,
    NotificationPersonsStep,
    ReviewStep,
  },

  props: {
    lpaType: {
      type: String,
      required: true,
      validator: (v) => ['property_financial', 'health_welfare'].includes(v),
    },
    editId: {
      type: Number,
      default: null,
    },
  },

  emits: ['complete', 'cancel'],

  data() {
    return {
      currentStepIndex: 0,
      initialLoading: false,
      saving: false,
      errors: {},
      formData: {
        lpa_type: this.lpaType,
        donor_full_name: '',
        donor_date_of_birth: '',
        donor_address_line_1: '',
        donor_address_line_2: '',
        donor_address_city: '',
        donor_address_county: '',
        donor_address_postcode: '',
        attorney_decision_type: 'jointly_and_severally',
        jointly_for_some_details: '',
        when_attorneys_can_act: 'only_when_lost_capacity',
        preferences: '',
        instructions: '',
        life_sustaining_treatment: null,
        certificate_provider_name: '',
        certificate_provider_address: '',
        certificate_provider_relationship: '',
        certificate_provider_known_years: null,
        certificate_provider_professional_details: '',
        notes: '',
        attorneys: [],
        notification_persons: [],
      },
    };
  },

  computed: {
    typeLabel() {
      return this.lpaType === 'property_financial'
        ? 'Property & Financial Affairs Lasting Power of Attorney'
        : 'Health & Welfare Lasting Power of Attorney';
    },
    typeDescription() {
      return this.lpaType === 'property_financial'
        ? 'Covers decisions about your bank accounts, investments, property, bills, and tax affairs.'
        : 'Covers decisions about your medical treatment, care home, daily routine, and life-sustaining treatment.';
    },
    allSteps() {
      const steps = [
        { id: 'donor', label: 'Donor Details' },
        { id: 'attorneys', label: 'Attorneys' },
        { id: 'replacement', label: 'Replacements', skippable: true },
        { id: 'decision', label: 'Decision Type', condition: this.primaryAttorneyCount > 1 },
        { id: 'when_can_act', label: 'When Can Act', condition: this.lpaType === 'property_financial' },
        { id: 'preferences', label: 'Preferences' },
        { id: 'certificate', label: 'Certificate Provider' },
        { id: 'notifications', label: 'Notify', skippable: true },
        { id: 'review', label: 'Review' },
      ];
      return steps;
    },
    visibleSteps() {
      return this.allSteps.filter(s => s.condition === undefined || s.condition);
    },
    currentStep() {
      return this.visibleSteps[this.currentStepIndex]?.id || 'donor';
    },
    canSkipStep() {
      const step = this.visibleSteps[this.currentStepIndex];
      return step?.skippable && this.currentStepIndex < this.visibleSteps.length - 1;
    },
    primaryAttorneyCount() {
      return (this.formData.attorneys || []).filter(a => a.attorney_type === 'primary').length;
    },
  },

  async mounted() {
    if (this.editId) {
      await this.loadExistingLpa();
    } else {
      await this.loadDonorDefaults();
    }
  },

  methods: {
    ...mapActions('estate', ['createLpa', 'updateLpa', 'fetchLpas']),

    async loadDonorDefaults() {
      try {
        const response = await estateService.getLpaDonorDefaults();
        const defaults = response.data;
        if (defaults.donor_full_name) {
          this.formData.donor_full_name = defaults.donor_full_name;
          this.formData.donor_date_of_birth = defaults.donor_date_of_birth || '';
          this.formData.donor_address_line_1 = defaults.donor_address_line_1 || '';
          this.formData.donor_address_line_2 = defaults.donor_address_line_2 || '';
          this.formData.donor_address_city = defaults.donor_address_city || '';
          this.formData.donor_address_county = defaults.donor_address_county || '';
          this.formData.donor_address_postcode = defaults.donor_address_postcode || '';
        }
      } catch {
        // Defaults are optional
      }
    },

    async loadExistingLpa() {
      this.initialLoading = true;
      try {
        const response = await estateService.getLpa(this.editId);
        const lpa = response.data;
        // Populate form data from existing LPA
        Object.keys(this.formData).forEach(key => {
          if (key === 'attorneys') {
            this.formData.attorneys = (lpa.attorneys || []).map(a => ({ ...a }));
          } else if (key === 'notification_persons') {
            this.formData.notification_persons = (lpa.notification_persons || []).map(p => ({ ...p }));
          } else if (lpa[key] !== undefined && lpa[key] !== null) {
            this.formData[key] = lpa[key];
          }
        });
      } catch {
        // If we can't load, start fresh
      } finally {
        this.initialLoading = false;
      }
    },

    nextStep() {
      if (this.currentStepIndex < this.visibleSteps.length - 1) {
        this.currentStepIndex++;
        this.errors = {};
        window.scrollTo(0, 0);
      }
    },

    previousStep() {
      if (this.currentStepIndex > 0) {
        this.currentStepIndex--;
        this.errors = {};
        window.scrollTo(0, 0);
      }
    },

    async saveDraft() {
      await this.save('draft');
    },

    async saveComplete() {
      await this.save('completed');
    },

    async save(status) {
      this.saving = true;
      this.errors = {};

      try {
        const payload = { ...this.formData, status };

        if (this.editId) {
          await this.updateLpa({ id: this.editId, data: payload });
        } else {
          await this.createLpa(payload);
        }

        await this.fetchLpas();
        this.$emit('complete');
      } catch (error) {
        if (error.response?.data?.errors) {
          this.errors = error.response.data.errors;
        }
      } finally {
        this.saving = false;
      }
    },
  },
};
</script>
