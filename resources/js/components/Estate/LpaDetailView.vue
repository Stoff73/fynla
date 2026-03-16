<template>
  <div class="lpa-detail-view">
    <!-- Print-friendly header -->
    <div class="print-header hidden print:block mb-8 text-center">
      <h1 class="text-2xl font-black text-horizon-500">Lasting Power of Attorney</h1>
      <p class="text-lg text-neutral-500">{{ typeLabel }}</p>
    </div>

    <!-- Screen header -->
    <div class="flex items-center justify-between mb-6 print:hidden">
      <div class="flex items-center space-x-3">
        <button
          class="text-sm text-horizon-400 hover:text-horizon-500 flex items-center"
          @click="$emit('back')"
        >
          <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
          </svg>
          Back
        </button>
        <h2 class="text-lg font-bold text-horizon-500">{{ typeLabel }}</h2>
        <span :class="['inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium', statusClass]">
          {{ statusLabel }}
        </span>
      </div>
      <div class="flex items-center space-x-2">
        <button
          class="px-3 py-1.5 text-sm font-medium text-horizon-500 border border-light-gray rounded-lg hover:bg-savannah-100"
          @click="printLpa"
        >
          Print / Save as PDF
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

    <!-- Section 1: Donor -->
    <div class="bg-white rounded-lg border border-light-gray p-5 mb-4">
      <h3 class="text-sm font-bold text-horizon-500 uppercase tracking-wider mb-3 border-b border-light-gray pb-2">
        Section 1 — The Donor
      </h3>
      <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <div>
          <p class="text-xs text-neutral-500">Full Name</p>
          <p class="text-sm font-medium text-horizon-500">{{ lpa.donor_full_name || '—' }}</p>
        </div>
        <div>
          <p class="text-xs text-neutral-500">Date of Birth</p>
          <p class="text-sm font-medium text-horizon-500">{{ formatDateDisplay(lpa.donor_date_of_birth) }}</p>
        </div>
        <div class="sm:col-span-2">
          <p class="text-xs text-neutral-500">Address</p>
          <p class="text-sm font-medium text-horizon-500">{{ formattedDonorAddress }}</p>
        </div>
      </div>
    </div>

    <!-- Section 2: Primary Attorneys -->
    <div class="bg-white rounded-lg border border-light-gray p-5 mb-4">
      <h3 class="text-sm font-bold text-horizon-500 uppercase tracking-wider mb-3 border-b border-light-gray pb-2">
        Section 2 — The Attorneys
      </h3>
      <div v-if="primaryAttorneys.length === 0" class="text-sm text-neutral-500 italic">
        No attorneys appointed yet.
      </div>
      <div v-else class="space-y-4">
        <div
          v-for="(attorney, index) in primaryAttorneys"
          :key="attorney.id"
          class="border-b border-light-gray pb-3 last:border-b-0 last:pb-0"
        >
          <p class="text-xs text-neutral-500 mb-1">Attorney {{ index + 1 }}</p>
          <div class="grid grid-cols-1 sm:grid-cols-3 gap-2">
            <div>
              <p class="text-sm font-medium text-horizon-500">{{ attorney.full_name }}</p>
            </div>
            <div>
              <p class="text-xs text-neutral-500">Relationship</p>
              <p class="text-sm text-horizon-500">{{ attorney.relationship_to_donor || '—' }}</p>
            </div>
            <div>
              <p class="text-xs text-neutral-500">Date of Birth</p>
              <p class="text-sm text-horizon-500">{{ formatDateDisplay(attorney.date_of_birth) }}</p>
            </div>
          </div>
        </div>

        <!-- Decision Type -->
        <div v-if="primaryAttorneys.length > 1 && lpa.attorney_decision_type" class="mt-3 pt-3 border-t border-light-gray">
          <p class="text-xs text-neutral-500">How attorneys make decisions</p>
          <p class="text-sm font-medium text-horizon-500">{{ decisionTypeLabel }}</p>
          <p v-if="lpa.jointly_for_some_details" class="text-xs text-neutral-500 mt-1">
            {{ lpa.jointly_for_some_details }}
          </p>
        </div>
      </div>
    </div>

    <!-- Section 3: Replacement Attorneys -->
    <div class="bg-white rounded-lg border border-light-gray p-5 mb-4">
      <h3 class="text-sm font-bold text-horizon-500 uppercase tracking-wider mb-3 border-b border-light-gray pb-2">
        Section 3 — Replacement Attorneys
      </h3>
      <div v-if="replacementAttorneys.length === 0" class="text-sm text-neutral-500 italic">
        No replacement attorneys appointed.
      </div>
      <div v-else class="space-y-3">
        <div
          v-for="(attorney, index) in replacementAttorneys"
          :key="attorney.id"
          class="border-b border-light-gray pb-3 last:border-b-0 last:pb-0"
        >
          <p class="text-xs text-neutral-500 mb-1">Replacement Attorney {{ index + 1 }}</p>
          <div class="grid grid-cols-1 sm:grid-cols-3 gap-2">
            <div>
              <p class="text-sm font-medium text-horizon-500">{{ attorney.full_name }}</p>
            </div>
            <div>
              <p class="text-xs text-neutral-500">Relationship</p>
              <p class="text-sm text-horizon-500">{{ attorney.relationship_to_donor || '—' }}</p>
            </div>
            <div>
              <p class="text-xs text-neutral-500">Date of Birth</p>
              <p class="text-sm text-horizon-500">{{ formatDateDisplay(attorney.date_of_birth) }}</p>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Section 4: When can attorneys act (Property only) -->
    <div v-if="lpa.lpa_type === 'property_financial'" class="bg-white rounded-lg border border-light-gray p-5 mb-4">
      <h3 class="text-sm font-bold text-horizon-500 uppercase tracking-wider mb-3 border-b border-light-gray pb-2">
        Section 4 — When Attorneys Can Act
      </h3>
      <p class="text-sm text-horizon-500">
        {{ lpa.when_attorneys_can_act === 'while_has_capacity'
          ? 'While the donor still has mental capacity, and also when they have lost mental capacity.'
          : lpa.when_attorneys_can_act === 'only_when_lost_capacity'
            ? 'Only when the donor has lost mental capacity.'
            : 'Not specified.' }}
      </p>
    </div>

    <!-- Section 5: Preferences & Instructions -->
    <div class="bg-white rounded-lg border border-light-gray p-5 mb-4">
      <h3 class="text-sm font-bold text-horizon-500 uppercase tracking-wider mb-3 border-b border-light-gray pb-2">
        {{ lpa.lpa_type === 'property_financial' ? 'Section 5' : 'Section 4' }} — Preferences & Instructions
      </h3>
      <div class="space-y-3">
        <div>
          <p class="text-xs text-neutral-500">Preferences (advisory — your attorneys should consider these)</p>
          <p class="text-sm text-horizon-500 whitespace-pre-wrap">{{ lpa.preferences || 'None specified.' }}</p>
        </div>
        <div>
          <p class="text-xs text-neutral-500">Instructions (binding — your attorneys must follow these)</p>
          <p class="text-sm text-horizon-500 whitespace-pre-wrap">{{ lpa.instructions || 'None specified.' }}</p>
        </div>
        <!-- Life-sustaining treatment (Health only) -->
        <div v-if="lpa.lpa_type === 'health_welfare'">
          <p class="text-xs text-neutral-500">Life-sustaining treatment</p>
          <p class="text-sm font-medium text-horizon-500">
            {{ lpa.life_sustaining_treatment === 'can_consent'
              ? 'Your attorneys can give or refuse consent to life-sustaining treatment on your behalf.'
              : lpa.life_sustaining_treatment === 'cannot_consent'
                ? 'Your attorneys cannot give or refuse consent to life-sustaining treatment on your behalf.'
                : 'Not specified.' }}
          </p>
        </div>
      </div>
    </div>

    <!-- Section 6: Certificate Provider -->
    <div class="bg-white rounded-lg border border-light-gray p-5 mb-4">
      <h3 class="text-sm font-bold text-horizon-500 uppercase tracking-wider mb-3 border-b border-light-gray pb-2">
        Certificate Provider
      </h3>
      <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <div>
          <p class="text-xs text-neutral-500">Name</p>
          <p class="text-sm font-medium text-horizon-500">{{ lpa.certificate_provider_name || '—' }}</p>
        </div>
        <div>
          <p class="text-xs text-neutral-500">Relationship</p>
          <p class="text-sm text-horizon-500">{{ lpa.certificate_provider_relationship || '—' }}</p>
        </div>
        <div>
          <p class="text-xs text-neutral-500">Known for</p>
          <p class="text-sm text-horizon-500">
            {{ lpa.certificate_provider_known_years ? lpa.certificate_provider_known_years + ' years' : '—' }}
          </p>
        </div>
      </div>
    </div>

    <!-- Section 7: People to Notify -->
    <div class="bg-white rounded-lg border border-light-gray p-5 mb-4">
      <h3 class="text-sm font-bold text-horizon-500 uppercase tracking-wider mb-3 border-b border-light-gray pb-2">
        People to Notify
      </h3>
      <div v-if="notificationPersons.length === 0" class="text-sm text-neutral-500 italic">
        No people to notify listed.
      </div>
      <div v-else class="space-y-2">
        <div
          v-for="(person, index) in notificationPersons"
          :key="person.id"
          class="flex items-center justify-between py-1"
        >
          <p class="text-sm text-horizon-500">{{ index + 1 }}. {{ person.full_name }}</p>
        </div>
      </div>
    </div>

    <!-- Registration Info -->
    <div v-if="lpa.is_registered_with_opg" class="bg-spring-50 rounded-lg border border-spring-200 p-5 mb-4">
      <h3 class="text-sm font-bold text-spring-800 mb-2">Registration Details</h3>
      <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
        <div>
          <p class="text-xs text-spring-600">Registration Date</p>
          <p class="text-sm font-medium text-spring-800">{{ formatDateDisplay(lpa.registration_date) }}</p>
        </div>
        <div v-if="lpa.opg_reference">
          <p class="text-xs text-spring-600">Office of the Public Guardian Reference</p>
          <p class="text-sm font-medium text-spring-800">{{ lpa.opg_reference }}</p>
        </div>
      </div>
    </div>

    <!-- Notes -->
    <div v-if="lpa.notes" class="bg-white rounded-lg border border-light-gray p-5 mb-4">
      <h3 class="text-sm font-bold text-horizon-500 uppercase tracking-wider mb-2">Notes</h3>
      <p class="text-sm text-horizon-500 whitespace-pre-wrap">{{ lpa.notes }}</p>
    </div>

    <!-- Compliance Checklist -->
    <div class="bg-white rounded-lg border border-light-gray p-5 mb-4 print:hidden">
      <h3 class="text-sm font-bold text-horizon-500 uppercase tracking-wider mb-3 border-b border-light-gray pb-2">
        Compliance Checks
      </h3>
      <LpaComplianceChecklist
        :compliance="compliance"
        :loading="complianceLoading"
      />
    </div>

    <!-- Legal Disclaimer -->
    <div class="bg-savannah-100 rounded-lg p-4 text-xs text-neutral-500 print:block">
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
import estateService from '@/services/estateService';
import { formatDate } from '@/utils/dateFormatter';

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
        ? 'Lasting Power of Attorney — Property & Financial Affairs'
        : 'Lasting Power of Attorney — Health & Welfare';
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
    primaryAttorneys() {
      return (this.lpa.attorneys || []).filter(a => a.attorney_type === 'primary');
    },
    replacementAttorneys() {
      return (this.lpa.attorneys || []).filter(a => a.attorney_type === 'replacement');
    },
    notificationPersons() {
      return this.lpa.notification_persons || [];
    },
    decisionTypeLabel() {
      const labels = {
        jointly: 'Jointly — all attorneys must agree on every decision',
        jointly_and_severally: 'Jointly and severally — attorneys can make decisions together or independently',
        jointly_for_some: 'Jointly for some decisions, severally for others',
      };
      return labels[this.lpa.attorney_decision_type] || '';
    },
    formattedDonorAddress() {
      const parts = [
        this.lpa.donor_address_line_1,
        this.lpa.donor_address_line_2,
        this.lpa.donor_address_city,
        this.lpa.donor_address_county,
        this.lpa.donor_address_postcode,
      ].filter(Boolean);
      return parts.length > 0 ? parts.join(', ') : '—';
    },
  },

  mounted() {
    this.loadCompliance();
  },

  methods: {
    formatDateDisplay(date) {
      if (!date) return '—';
      return formatDate(date);
    },

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

    printLpa() {
      window.print();
    },
  },
};
</script>

<style scoped>
@media print {
  .print-header { display: block !important; }
  .lpa-detail-view { max-width: 100%; }
}
</style>
